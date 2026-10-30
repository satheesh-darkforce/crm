<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Junction model for the `developer_task` table.
 * Links a developer to a Tasks (tasks table) record.
 *
 * @property int $id
 * @property int $task_id
 * @property int $developer_id
 *
 * @property Tasks     $task
 * @property Developer $developer
 */
class DeveloperTask extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'developer_task';
    }

    public function rules(): array
    {
        return [
            [['task_id', 'developer_id'], 'required'],
            [['task_id', 'developer_id'], 'integer'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'           => 'ID',
            'task_id'      => 'Task',
            'developer_id' => 'Developer',
        ];
    }

    public function getTask(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Tasks::class, ['id' => 'task_id']);
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }
}
