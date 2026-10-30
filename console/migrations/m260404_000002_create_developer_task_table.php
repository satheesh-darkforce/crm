<?php

use yii\db\Migration;

/**
 * Creates the `developer_task` junction table.
 * Links developers (developer.id) to tasks (tasks.id) for multi-developer assignment.
 */
class m260404_000002_create_developer_task_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('developer_task', [
            'id'           => $this->primaryKey(),
            'task_id'      => $this->integer()->notNull(),
            'developer_id' => $this->integer()->notNull(),
        ]);

        $this->createIndex('idx_developer_task_task_id', 'developer_task', 'task_id');
        $this->createIndex('idx_developer_task_developer_id', 'developer_task', 'developer_id');
    }

    public function safeDown()
    {
        $this->dropTable('developer_task');
    }
}
