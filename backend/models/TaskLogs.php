<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Task time-tracking logs.
 *
 * Table: task_logs
 *
 * @property int    $id
 * @property int    $task_id
 * @property int    $user_id
 * @property string $start_time
 * @property string $end_time
 * @property int    $total_time   (seconds)
 * @property string $comments
 * @property int    $is_converted  0=not converted, 1=converted to work report
 * @property int    $status
 */
class TaskLogs extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'task_logs';
    }

    public function rules(): array
    {
        return [
            [['task_id', 'user_id', 'start_time'], 'required'],
            [['task_id', 'user_id', 'is_converted', 'status'], 'integer'],
            [['total_time'], 'integer', 'min' => 0],
            [['start_time', 'end_time'], 'safe'],
            [['comments'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'task_id'    => 'Task',
            'user_id'    => 'User',
            'start_time' => 'Start Time',
            'end_time'   => 'End Time',
            'total_time' => 'Total Time',
            'comments'     => 'Comments',
            'is_converted' => 'Converted',
        ];
    }

    public function getTask(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Task::class, ['id' => 'task_id']);
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'user_id']);
    }

    public function getTotalTimeFormatted(): string
    {
        $secs = (int)$this->total_time;
        if ($secs <= 0) return '—';
        return sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
    }
}
