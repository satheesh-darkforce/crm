<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Developer task model for the `tasks` table.
 * This is the developer-oriented task system (separate from the PM `task` table).
 *
 * @property int    $id
 * @property int    $project_id
 * @property string $task
 * @property string $description
 * @property int    $hours_of_work        stored in seconds
 * @property string $assigned_date        Y-m-d
 * @property string $expected_completion_date  Y-m-d H:i:s
 * @property int    $task_status          1=Pending, 2=Inprogress, 3=Completed
 * @property string $created_at
 * @property int    $created_by
 * @property int    $status               1=active, 0=deleted
 *
 * @property Project         $project
 * @property User            $createdBy
 * @property DeveloperTask[] $developerTasks
 * @property WorkReport[]    $workReports
 */
class Tasks extends ActiveRecord
{
    const STATUS_PENDING    = 1;
    const STATUS_INPROGRESS = 2;
    const STATUS_COMPLETED  = 3;

    public static function tableName(): string
    {
        return 'tasks';
    }

    public function rules(): array
    {
        return [
            [['project_id', 'task', 'assigned_date'], 'required'],
            [['project_id', 'task_status', 'created_by', 'status', 'hours_of_work'], 'integer'],
            [['task'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['assigned_date', 'expected_completion_date', 'created_at'], 'safe'],
            ['expected_completion_date', 'validateDateRange'],
        ];
    }

    public function validateDateRange(string $attribute): void
    {
        if (!empty($this->assigned_date) && !empty($this->expected_completion_date)) {
            if (strtotime($this->expected_completion_date) < strtotime($this->assigned_date)) {
                $this->addError($attribute, 'Expected completion date cannot be before assigned date.');
            }
        }
    }

    public function attributeLabels(): array
    {
        return [
            'id'                       => 'ID',
            'project_id'               => 'Project',
            'task'                     => 'Task',
            'description'              => 'Description',
            'hours_of_work'            => 'Hours Of Work',
            'assigned_date'            => 'Assigned Date',
            'expected_completion_date' => 'Expected Completion Date',
            'task_status'              => 'Status',
            'created_at'               => 'Created At',
            'created_by'               => 'Created By',
        ];
    }

    public function getProject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getCreatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getDeveloperTasks(): \yii\db\ActiveQuery
    {
        return $this->hasMany(DeveloperTask::class, ['task_id' => 'id']);
    }

    public function getWorkReports(): \yii\db\ActiveQuery
    {
        return $this->hasMany(WorkReport::class, ['task_id' => 'id']);
    }

    public function getStatusLabel(): string
    {
        return [
            self::STATUS_PENDING    => 'Pending',
            self::STATUS_INPROGRESS => 'Inprogress',
            self::STATUS_COMPLETED  => 'Completed',
        ][$this->task_status] ?? 'Unknown';
    }

    public function getStatusBadge(): string
    {
        return [
            self::STATUS_PENDING    => 'secondary',
            self::STATUS_INPROGRESS => 'primary',
            self::STATUS_COMPLETED  => 'success',
        ][$this->task_status] ?? 'secondary';
    }

    public function getHoursFormatted(): string
    {
        if (!$this->hours_of_work) {
            return '—';
        }
        return sprintf('%02d:%02d', intdiv((int)$this->hours_of_work, 3600), intdiv((int)$this->hours_of_work % 3600, 60));
    }

    /** Developer names as a comma-separated string (for display). */
    public function getDeveloperNames(): string
    {
        $names = [];
        foreach ($this->developerTasks as $dt) {
            if ($dt->developer) {
                $names[] = $dt->developer->developer_name;
            }
        }
        return $names ? implode(', ', $names) : '—';
    }
}
