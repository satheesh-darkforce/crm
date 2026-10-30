<?php

use yii\db\Migration;

class m260324_000002_create_iot_uploads_table extends Migration
{
    public function safeUp()
    {
        if ($this->db->schema->getTableSchema('iot_uploads') !== null) {
            return; // already exists (imported from production)
        }
        $this->createTable('iot_uploads', [
            'id'          => $this->primaryKey(),
            'product_id'  => $this->integer()->notNull(),
            'type'        => $this->string(50)->null(),
            'upload_type' => $this->integer()->defaultValue(5),
            'path'        => $this->string(500)->null(),
            'created_at'  => $this->string(50)->null(),
            'created_by'  => $this->integer()->null(),
            'updated_at'  => $this->string(50)->null(),
            'updated_by'  => $this->integer()->null(),
            'status'      => $this->tinyInteger()->defaultValue(1),
        ]);
        $this->createIndex('idx_iot_uploads_product', 'iot_uploads', 'product_id');
    }

    public function safeDown()
    {
        $this->dropTable('iot_uploads');
    }
}
