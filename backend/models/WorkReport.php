<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: WorkReport model (tableName: work_report, PK: work_id)
 *
 * Table: work_report
 *
 * data_source: 1=Web, 2=Mobile, 3=API
 *
 * @property int    $work_id
 * @property int    $developer_id
 * @property int    $project_id
 * @property int    $client_id
 * @property int    $task_id
 * @property string $task
 * @property string $description
 * @property string $date
 * @property string $from_time
 * @property string $to_time
 * @property int    $total_time      (minutes)
 * @property string $created_time
 * @property int    $created_by
 * @property int    $updated_by
 * @property string $last_updated_time
 * @property int    $data_source
 * @property string $raw_input_data
 * @property float  $from_latitude
 * @property float  $from_longitude
 * @property float  $to_latitude
 * @property float  $to_longitude
 * @property int    $status
 */
class WorkReport extends ActiveRecord
{
    const SOURCE_WEB    = 1;
    const SOURCE_MOBILE = 2;
    const SOURCE_API    = 3;

    public static function tableName(): string
    {
        return 'work_report';
    }

    public function rules(): array
    {
        // Applied to new reports and edited values only: legacy rows include
        // 2,600+ zero-minute entries that must stay editable.
        $changed = fn(string $attr) => fn($m) => $m->isNewRecord || (string)$m->getOldAttribute($attr) !== (string)$m->$attr;

        return [
            [['date'], 'date', 'format' => 'php:Y-m-d', 'message' => 'Enter the date as YYYY-MM-DD.', 'when' => $changed('date')],
            [['date'], 'compare', 'compareValue' => date('Y-m-d'), 'operator' => '<=', 'type' => 'string',
                'message' => 'Work reports cannot be dated in the future.', 'when' => $changed('date')],
            [['total_time'], 'integer', 'min' => 60, 'max' => 86400,
                'tooSmall' => 'Total time must be at least 1 minute.', 'tooBig' => 'Total time cannot exceed 24 hours.',
                'when' => $changed('total_time')],
            [['project_id'], 'exist', 'targetClass' => Project::class, 'targetAttribute' => 'id', 'filter' => ['status' => 1],
                'message' => 'Select an active project.', 'when' => $changed('project_id')],
            [['developer_id'], 'exist', 'targetClass' => Developer::class, 'targetAttribute' => 'id', 'filter' => ['status' => 1],
                'message' => 'Select an active employee.', 'when' => $changed('developer_id')],
            [['task', 'description', 'from_time', 'to_time'], 'trim'],
            // BUG-1 fix: removed client_id from required (projects may have no client)
            // BUG-4 fix: added task to required (matches Yii1 behaviour)
            [['developer_id', 'project_id', 'date', 'task', 'status'], 'required'],
            [['developer_id', 'project_id', 'client_id', 'task_id', 'created_by', 'updated_by', 'data_source', 'status'], 'integer'],
            [['total_time'], 'integer', 'min' => 0],
            [['from_latitude', 'from_longitude', 'to_latitude', 'to_longitude'], 'number'],
            [['description', 'raw_input_data'], 'string'],
            [['date', 'created_time', 'last_updated_time'], 'safe'],
            // Strip optional seconds (:SS) before matching HH:MM
            [['from_time', 'to_time'], 'filter', 'filter' => fn($v) => strlen((string)$v) === 8 && preg_match('/^\d{2}:\d{2}:\d{2}$/', (string)$v) ? substr($v, 0, 5) : $v, 'skipOnEmpty' => true],
            // Accepts "HH:MM AM/PM" (Yii1 legacy format) or 24-hour "HH:MM".
            [['from_time', 'to_time'], 'match', 'pattern' => '/^(?:(0?[1-9]|1[0-2]):[0-5]\d\s*(?:AM|PM)|([01]?\d|2[0-3]):[0-5]\d)$/i', 'message' => 'Time must be in HH:MM AM/PM format.', 'skipOnEmpty' => true],
            [['from_time', 'to_time'], 'string', 'max' => 12],
            ['to_time', 'validateTimeRange'],
            [['task'], 'string', 'max' => 5000],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'work_id'     => 'Work Report ID',
            'developer_id'=> 'Employee',
            'project_id'  => 'Project',
            'date'        => 'Date',
            'task'        => 'Task',
            'total_time'  => 'Total time',
            'from_time'   => 'From',
            'to_time'     => 'To',
        ];
    }

    public function validateTimeRange(): void
    {
        if ($this->from_time && $this->to_time) {
            $fMins = self::timeStrToMinutes((string)$this->from_time);
            $tMins = self::timeStrToMinutes((string)$this->to_time);
            if ($fMins !== null && $tMins !== null && $fMins >= $tMins) {
                $this->addError('to_time', 'To time must be after from time.');
                return;
            }
        }
        // Total time (stored in seconds) must fit inside the From→To window when both are set.
        if ($this->from_time && $this->to_time && $this->total_time) {
            $f = strtotime('1970-01-01 ' . $this->from_time);
            $t = strtotime('1970-01-01 ' . $this->to_time);
            if ($f !== false && $t !== false && $t > $f) {
                $windowSeconds = $t - $f;
                if ((int)$this->total_time > $windowSeconds) {
                    $hh = intdiv($windowSeconds, 3600);
                    $mm = intdiv($windowSeconds % 3600, 60);
                    $this->addError('total_time', sprintf(
                        'Total time cannot be more than the From–To window (%02d:%02d).',
                        $hh, $mm
                    ));
                }
            }
        }
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }

    public function getProject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getTotalTimeFormatted(): string
    {
        // total_time is stored in SECONDS (Yii1 parity). Format as HH:MM, the
        // same way every work-report listing view renders it.
        $secs = (int)$this->total_time;
        if ($secs <= 0) return '00:00';
        return sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
    }

    /**
     * Returns work-report hours grouped by project for a developer.
     * Mirrors Yii1 WorkReport::view_employee().
     *
     * @param int      $developerId
     * @param int|null $type  1=current month, 2=last month, null=all-time
     * @return array   Each row: ['project_id', 'project_title', 'total_secs']
     */
    public static function getByEmployee(int $developerId, ?int $type = null): array
    {
        $query = static::find()
            ->select(['work_report.project_id', 'SUM(work_report.total_time) AS total_secs'])
            ->joinWith('project', false)
            ->addSelect(['project.title AS project_title'])
            ->where(['work_report.status' => 1, 'work_report.developer_id' => $developerId])
            ->groupBy('work_report.project_id')
            ->orderBy(['MAX(work_report.work_id)' => SORT_DESC])
            ->asArray();

        if ($type === 1) {
            $query->andWhere('DATE_FORMAT(work_report.date, "%Y-%m") = :ym', [':ym' => date('Y-m')]);
        } elseif ($type === 2) {
            $query->andWhere('DATE_FORMAT(work_report.date, "%Y-%m") = :ym', [':ym' => date('Y-m', strtotime('-1 month'))]);
        }

        return $query->all();
    }

    /** Convert seconds to "Xh Ym" string. */
    public static function secondsToHours(int $secs): string
    {
        return intdiv($secs, 3600) . 'h ' . intdiv($secs % 3600, 60) . 'm';
    }

    /** Convert "HH:MM AM/PM" or 24h "HH:MM" string to minutes-since-midnight. */
    public static function timeStrToMinutes(string $val): ?int
    {
        $val = trim($val);
        if ($val === '') return null;
        if (preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $val, $m)) {
            $h = (int)$m[1] % 12;
            if (strtoupper($m[3]) === 'PM') $h += 12;
            return $h * 60 + (int)$m[2];
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $val, $m)) {
            return (int)$m[1] * 60 + (int)$m[2];
        }
        return null;
    }
}
