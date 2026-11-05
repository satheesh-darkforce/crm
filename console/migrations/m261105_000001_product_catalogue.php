<?php

use yii\db\Migration;

/**
 * Product Management: turns the existing item master (delivery_product_details,
 * already used by delivery challans) into the sales catalogue instead of adding
 * a second product table, and adds product categories.
 *
 * Additive only — existing rows and DC links are untouched. Guarded so the
 * migration is safe to re-run.
 */
class m261105_000001_product_catalogue extends Migration
{
    private const ITEMS = 'delivery_product_details';

    public function safeUp()
    {
        if ($this->db->getTableSchema('product_category', true) === null) {
            $this->createTable('product_category', [
                'id'          => $this->primaryKey(),
                'name'        => $this->string(80)->notNull(),
                'slug'        => $this->string(80)->notNull(),
                'description' => $this->string(255)->null(),
                'sort_order'  => $this->integer()->notNull()->defaultValue(0),
                'status'      => $this->tinyInteger()->notNull()->defaultValue(1),
                'created_at'  => $this->dateTime()->null(),
                'created_by'  => $this->bigInteger()->null(),
                'updated_at'  => $this->dateTime()->null(),
                'updated_by'  => $this->bigInteger()->null(),
            ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $this->createIndex('idx_product_category_status', 'product_category', ['status', 'sort_order']);
            $this->createIndex('idx_product_category_slug', 'product_category', 'slug');
        }

        $schema  = $this->db->getTableSchema(self::ITEMS, true);
        $columns = [
            'product_code'      => $this->string(50)->null()->after('serial_no'),
            'category_id'       => $this->integer()->null()->after('product_code'),
            'item_type'         => $this->tinyInteger()->notNull()->defaultValue(1)->comment('1=Product, 2=Service')->after('category_id'),
            'short_description' => $this->string(255)->null()->after('units'),
            'description'       => $this->text()->null()->after('short_description'),
            'sale_price'        => $this->decimal(12, 2)->null()->after('description'),
            'mrp'               => $this->decimal(12, 2)->null()->after('sale_price'),
            'price_on_request'  => $this->tinyInteger()->notNull()->defaultValue(0)->after('mrp'),
            'gst_rate'          => $this->decimal(5, 2)->null()->after('price_on_request'),
            'features'          => $this->text()->null()->after('gst_rate'),
            'specs'             => $this->text()->null()->after('features'),
            'image'             => $this->string(255)->null()->after('specs'),
            'is_active'         => $this->tinyInteger()->notNull()->defaultValue(1)->after('image'),
            'created_by'        => $this->bigInteger()->null()->after('created_at'),
            'updated_at'        => $this->dateTime()->null()->after('created_by'),
            'updated_by'        => $this->bigInteger()->null()->after('updated_at'),
        ];
        foreach ($columns as $name => $type) {
            if (!$schema->getColumn($name)) {
                $this->addColumn(self::ITEMS, $name, $type);
            }
        }

        $indexes = array_column($this->db->createCommand('SHOW INDEX FROM ' . self::ITEMS)->queryAll(), 'Key_name');
        if (!in_array('idx_items_category', $indexes, true)) {
            $this->createIndex('idx_items_category', self::ITEMS, 'category_id');
        }
        if (!in_array('idx_items_code', $indexes, true)) {
            $this->createIndex('idx_items_code', self::ITEMS, 'product_code');
        }
        if (!in_array('idx_items_active', $indexes, true)) {
            $this->createIndex('idx_items_active', self::ITEMS, ['status', 'is_active', 'item_type']);
        }
        $fks = $this->db->getTableSchema(self::ITEMS, true)->foreignKeys;
        if (!isset($fks['fk_items_category'])) {
            $this->addForeignKey('fk_items_category', self::ITEMS, 'category_id', 'product_category', 'id', 'SET NULL', 'CASCADE');
        }
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema(self::ITEMS, true);
        if (isset($schema->foreignKeys['fk_items_category'])) {
            $this->dropForeignKey('fk_items_category', self::ITEMS);
        }
        $indexes = array_column($this->db->createCommand('SHOW INDEX FROM ' . self::ITEMS)->queryAll(), 'Key_name');
        foreach (['idx_items_category', 'idx_items_code', 'idx_items_active'] as $idx) {
            if (in_array($idx, $indexes, true)) {
                $this->dropIndex($idx, self::ITEMS);
            }
        }
        foreach (['product_code', 'category_id', 'item_type', 'short_description', 'description', 'sale_price', 'mrp',
                  'price_on_request', 'gst_rate', 'features', 'specs', 'image', 'is_active', 'created_by', 'updated_at', 'updated_by'] as $col) {
            if ($schema->getColumn($col)) {
                $this->dropColumn(self::ITEMS, $col);
            }
        }
        if ($this->db->getTableSchema('product_category', true) !== null) {
            $this->dropTable('product_category');
        }
    }
}
