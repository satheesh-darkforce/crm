<?php

use yii\db\Migration;

class m260324_000001_create_iot_tags_table extends Migration
{
    public function safeUp()
    {
        // Table may already exist if imported from production DB
        $tableExists = $this->db->schema->getTableSchema('iot_tags') !== null;
        if (!$tableExists) {
            $this->createTable('iot_tags', [
                'id'         => $this->primaryKey(),
                'name'       => $this->string(255)->notNull(),
                'created_at' => $this->string(50)->null(),
                'created_by' => $this->integer()->null(),
                'updated_at' => $this->string(50)->null(),
                'updated_by' => $this->integer()->null(),
                'status'     => $this->tinyInteger()->defaultValue(1),
            ]);
        }
    }

    public function safeDown()
    {
        $this->dropTable('iot_tags');
    }
}
