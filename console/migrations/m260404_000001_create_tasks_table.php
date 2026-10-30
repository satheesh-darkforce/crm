<?php

use yii\db\Migration;

/**
 * Creates the `tasks` table used by the developer task system.
 * Mirrors the Yii1 Tasks model / tasks table.
 */
class m260404_000001_create_tasks_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('tasks', [
            'id'                       => $this->primaryKey(),
            'project_id'               => $this->integer()->null(),
            'task'                     => $this->string(255)->null(),
            'description'              => $this->text()->null(),
            'hours_of_work'            => $this->integer()->null()->comment('in seconds'),
            'assigned_date'            => $this->date()->null(),
            'expected_completion_date' => $this->dateTime()->null(),
            'task_status'              => $this->tinyInteger()->defaultValue(1)->comment('1=Pending,2=Inprogress,3=Completed'),
            'created_at'               => $this->dateTime()->null(),
            'created_by'               => $this->integer()->null(),
            'status'                   => $this->tinyInteger()->defaultValue(1)->comment('1=active,0=deleted'),
        ]);

        $this->createIndex('idx_tasks_project_id', 'tasks', 'project_id');
        $this->createIndex('idx_tasks_status', 'tasks', 'status');
        $this->createIndex('idx_tasks_task_status', 'tasks', 'task_status');
    }

    public function safeDown()
    {
        $this->dropTable('tasks');
    }
}
