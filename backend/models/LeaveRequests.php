<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: LeaveRequests model
 *
 * Table: leave_requests
 *
 * leave_type:
 *   1 = Leave        (full day, or half day when permission_session > 0)
 *   2 = Permission   (hourly only — half-day was moved to Leave)
 *   3 = Absent
 *
 * request_status:
 *   0 = Requested
 *   1 = 1st Level Approved
 *   2 = Approved
 *   3 = Rejected
 *   4 = Canceled
 *
 * @property int    $id
 * @property int    $developer_id
 * @property string $from_date
 * @property string $to_date
 * @property float  $no_of_days
 * @property int    $leave_type
 * @property string $reason
 * @property string $description
 * @property int    $request_status
 * @property string $requested_date
 * @property string $approved_date
 * @property int    $status
 * @property float  $permission_hours    Hours for an hourly Permission (leave_type=2); NULL otherwise
 * @property int    $permission_session  Half-day session: 0=full day/hourly, 1=morning, 2=evening.
 *                                        Used by half-day Leave (leave_type=1) to mark the leave half.
 */
class LeaveRequests extends ActiveRecord
{
    const TYPE_LEAVE      = 1;
    const TYPE_PERMISSION = 2;
    const TYPE_ABSENT     = 3;

    /** Half-day session (permission_session column). */
    const SESSION_NONE    = 0; // full-day leave / hourly permission (uses permission_hours)
    const SESSION_MORNING = 1;
    const SESSION_EVENING = 2;

    const REQ_STATUS_REQUESTED = 0;
    const REQ_STATUS_L1        = 1;
    const REQ_STATUS_APPROVED  = 2;
    const REQ_STATUS_REJECTED  = 3;
    const REQ_STATUS_CANCELED  = 4;

    public static function tableName(): string
    {
        return 'leave_requests';
    }

    public function rules(): array
    {
        return [
            [['developer_id', 'leave_type', 'from_date', 'to_date', 'no_of_days', 'reason', 'requested_date', 'status'], 'required'],
            [['developer_id', 'leave_type', 'request_status', 'status', 'permission_session'], 'integer'],
            [['permission_session'], 'default', 'value' => 0],
            [['no_of_days', 'permission_hours'], 'number'],
            [['from_date', 'to_date', 'requested_date', 'approved_date', 'permission_hours'], 'safe'],
            [['reason', 'description'], 'trim'],
            [['reason'], 'string', 'max' => 2000],
            [['description'], 'string', 'max' => 5000],
            // Date order: To Date must be on or after From Date. Dates are stored as
            // 'yyyy-mm-dd' strings, so lexical comparison gives the correct result.
            ['to_date', 'compare', 'compareAttribute' => 'from_date', 'operator' => '>=',
                'type' => 'string', 'message' => 'To Date must be on or after From Date.'],
            ['from_date', 'validateNoOverlap'],
            [['from_date', 'to_date'], 'date', 'format' => 'php:Y-m-d', 'message' => '{attribute} must be a date (YYYY-MM-DD).'],
            // Leave length must match the dates (legacy rows are only checked once edited).
            ['no_of_days', 'validateDayCount', 'when' => fn($m) => (int)$m->leave_type === self::TYPE_LEAVE
                && ($m->isNewRecord || $m->isAttributeChanged('no_of_days', false) || $m->isAttributeChanged('from_date', false) || $m->isAttributeChanged('to_date', false))],
        ];
    }

    /** Leave days: more than 0, in half-day steps, and no more than the calendar days selected. */
    public function validateDayCount(string $attr): void
    {
        $days = (float)$this->$attr;
        $span = (strtotime((string)$this->to_date) - strtotime((string)$this->from_date)) / 86400 + 1;
        if ($days <= 0) {
            $this->addError($attr, 'Number of days must be more than 0.');
        } elseif (fmod($days * 2, 1.0) !== 0.0) {
            $this->addError($attr, 'Number of days must be in half-day steps (e.g. 1, 1.5, 2).');
        } elseif ($span >= 1 && $days > $span) {
            $this->addError($attr, 'Number of days (' . $days . ') cannot be more than the ' . (int)$span . ' day(s) between the dates.');
        }
    }

    /**
     * Validates that the leave request does not overlap with an existing
     * approved or pending leave for the same developer.
     */
    public function validateNoOverlap($attribute, $params): void
    {
        if ($this->hasErrors()) {
            return;
        }
        $query = self::find()
            ->where(['developer_id' => $this->developer_id, 'status' => 1])
            ->andWhere(['NOT IN', 'request_status', [self::REQ_STATUS_REJECTED, self::REQ_STATUS_CANCELED]])
            ->andWhere(['<=', 'from_date', $this->to_date ?: $this->from_date])
            ->andWhere(['>=', 'to_date', $this->from_date]);
        if (!$this->isNewRecord) {
            $query->andWhere(['!=', 'id', $this->id]);
        }
        if ($query->exists()) {
            $this->addError($attribute, 'A leave request already exists for the selected dates.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'developer_id'   => 'Developer',
            'leave_type'     => 'Leave Type',
            'from_date'      => 'From Date',
            'to_date'        => 'To Date',
            'no_of_days'     => 'No. of Days',
            'request_status' => 'Status',
            'approved_date'  => 'Approved Date',
            'reason'         => 'Reason',
            'permission_hours'   => 'Permission Hours',
            'permission_session' => 'Half-Day Session',
        ];
    }

    /** True when this is a half-day leave (leave_type=1 with a morning/evening session). */
    public function isHalfDayLeave(): bool
    {
        return (int)$this->leave_type === self::TYPE_LEAVE
            && (int)$this->permission_session > 0;
    }

    /**
     * No. of days as a clean number: "0.5", "1", "1.5", "2" — drops the trailing
     * ".0" that the DECIMAL(4,1) column would otherwise render for whole days.
     */
    public function getDaysLabel(): string
    {
        return rtrim(rtrim(number_format((float)$this->no_of_days, 1, '.', ''), '0'), '.');
    }

    /** Morning / Evening label for a half-day leave (empty otherwise). */
    public function getSessionName(): string
    {
        return match ((int)$this->permission_session) {
            self::SESSION_MORNING => 'Morning',
            self::SESSION_EVENING => 'Evening',
            default               => '',
        };
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }

    public function getLeaveTypeName(): string
    {
        if ($this->isHalfDayLeave()) {
            return 'Half Day Leave (' . $this->getSessionName() . ')';
        }
        return match ((int)$this->leave_type) {
            self::TYPE_LEAVE      => 'Leave',
            self::TYPE_PERMISSION => 'Permission',
            self::TYPE_ABSENT     => 'Absent',
            default               => 'Unknown',
        };
    }

    public function getRequestStatusName(): string
    {
        return match ((int)$this->request_status) {
            self::REQ_STATUS_REQUESTED => 'Requested',
            self::REQ_STATUS_L1        => 'Pending (L1)',
            self::REQ_STATUS_APPROVED  => 'Approved',
            self::REQ_STATUS_REJECTED  => 'Rejected',
            self::REQ_STATUS_CANCELED  => 'Canceled',
            default                    => 'Unknown',
        };
    }
}
