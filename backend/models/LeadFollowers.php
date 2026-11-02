<?php

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Lead follow-up entries
 *
 * Table: lead_followers
 *
 * followup_status: 1=Open, 2=Closed
 * read_status:     1=Unread, 2=Read (by the assignee)
 * status:          1=Active, 0=Deleted
 *
 * @property int    $id
 * @property int    $lead_id
 * @property string $follow_up_date
 * @property string $follow_up_time
 * @property string $subject
 * @property string $description
 * @property int    $assigned_to
 * @property int    $mode
 * @property string $other_mode
 * @property int    $created_by
 * @property string $created_date
 * @property int    $read_status
 * @property int    $followup_status
 * @property int    $status
 */
class LeadFollowers extends ActiveRecord
{
    const FOLLOWUP_OPEN   = 1;
    const FOLLOWUP_CLOSED = 2;

    const READ_UNREAD = 1;
    const READ_READ   = 2;

    public static function tableName(): string
    {
        return 'lead_followers';
    }

    public function rules(): array
    {
        return [
            [['subject', 'other_mode', 'description'], 'trim'],
            [['lead_id', 'follow_up_date', 'follow_up_time', 'subject', 'mode', 'assigned_to'], 'required'],
            [['follow_up_date'], 'date', 'format' => 'php:Y-m-d', 'message' => 'Enter a valid date (YYYY-MM-DD).'],
            [['follow_up_time'], 'match', 'pattern' => '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', 'message' => 'Enter a valid time.'],
            [['follow_up_date'], 'validateNotInPast'],
            [['assigned_to'], 'validateAssignee'],
            [['other_mode'], 'required', 'when' => fn($m) => (int)$m->mode === Lead::MODE_OTHER,
                'whenClient' => "function(){ return $('#leadfollowers-mode').val() == '5'; }"],
            [['mode'], 'in', 'range' => array_keys(Lead::modeOptions())],
            [['lead_id', 'assigned_to', 'created_by', 'read_status', 'followup_status', 'status'], 'integer'],
            [['followup_status'], 'default', 'value' => self::FOLLOWUP_OPEN],
            [['read_status'], 'default', 'value' => self::READ_UNREAD],
            [['description'], 'string'],
            [['subject', 'other_mode'], 'string', 'max' => 255],
            [['created_date'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'lead_id'         => 'Lead',
            'follow_up_date'  => 'Follow Up Date',
            'follow_up_time'  => 'Follow Up Time',
            'subject'         => 'Subject',
            'description'     => 'Description',
            'assigned_to'     => 'Assigned To',
            'mode'            => 'Mode',
            'other_mode'      => 'If Other Mode',
            'followup_status' => 'FollowUp Status',
            'created_by'      => 'Created By',
            'created_date'    => 'Created Date',
        ];
    }

    /**
     * A follow-up is scheduled ahead: it cannot be set in the past. Existing
     * follow-ups are only checked when their date/time is changed.
     */
    public function validateNotInPast(string $attribute): void
    {
        if ($this->hasErrors('follow_up_date') || $this->hasErrors('follow_up_time') || !$this->follow_up_time) {
            return;
        }
        $changed = $this->isNewRecord
            || $this->isAttributeChanged('follow_up_date', false)
            || substr((string)$this->follow_up_time, 0, 5) !== substr((string)$this->getOldAttribute('follow_up_time'), 0, 5);
        if (!$changed) {
            return;
        }
        $at = strtotime($this->follow_up_date . ' ' . $this->follow_up_time);
        if ($at !== false && $at < strtotime(date('Y-m-d H:i'))) {
            $this->addError($attribute, 'Follow-up date & time cannot be in the past.');
        }
    }

    public function validateAssignee(string $attribute): void
    {
        if (!$this->isNewRecord && !$this->isAttributeChanged($attribute, false)) {
            return;
        }
        if (!Lead::isAssignableUser($this->$attribute)) {
            $this->addError($attribute, 'Select an active employee.');
        }
    }

    public function beforeSave($insert): bool
    {
        if ($this->follow_up_time && strlen($this->follow_up_time) === 5) {
            $this->follow_up_time .= ':00';
        }
        return parent::beforeSave($insert);
    }

    public function getLead(): ActiveQuery
    {
        return $this->hasOne(Lead::class, ['id' => 'lead_id']);
    }

    public function getAssignedTo(): ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'assigned_to']);
    }

    public function getCreatedBy(): ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getAttachments(): ActiveQuery
    {
        return $this->hasMany(LeadFollowerAttachments::class, ['lead_follower_id' => 'id'])->andWhere(['status' => 1]);
    }

    public function getFollowupStatusLabel(): string
    {
        return (int)$this->followup_status === self::FOLLOWUP_OPEN ? 'Open' : 'Closed';
    }

    /** "dd-mm-yyyy hh:mm am" for the scheduled follow-up. */
    public function getScheduledLabel(): string
    {
        return $this->follow_up_date
            ? date('d-m-Y', strtotime($this->follow_up_date)) . ' ' . ($this->follow_up_time ? date('h:i a', strtotime($this->follow_up_time)) : '')
            : '-';
    }
}
