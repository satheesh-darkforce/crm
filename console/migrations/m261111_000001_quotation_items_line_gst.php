<?php

use yii\db\Migration;

/**
 * Product ↔ Quotation ↔ Invoice (PROJECT_ANALYSIS.md §6):
 *  - client_quotation_items: quotation lines picked from Products, with a snapshot
 *    of name, HSN/SAC, unit, unit price and GST so later product edits never change a quote;
 *  - client_quotation: status, validity, tax mode, totals, terms, notes, invoice link;
 *  - invoice_details: product link, unit, discount, per-line GST;
 *  - invoice: quotation link.
 * Additive only; old quotations / invoices keep working. Guarded so it is safe to re-run.
 */
class m261111_000001_quotation_items_line_gst extends Migration
{
    public function safeUp()
    {
        if ($this->db->getTableSchema('client_quotation_items', true) === null) {
            $this->createTable('client_quotation_items', [
                'id'               => $this->bigPrimaryKey(),
                'quote_id'         => $this->bigInteger()->notNull(),
                'product_id'       => $this->bigInteger()->null(),
                'sort_order'       => $this->integer()->notNull()->defaultValue(0),
                'description'      => $this->string(500)->notNull(),
                'hsn_sac'          => $this->string(20)->null(),
                'unit'             => $this->string(20)->null(),
                'quantity'         => $this->decimal(12, 3)->notNull(),
                'unit_price'       => $this->decimal(12, 2)->notNull(),
                'discount_percent' => $this->decimal(5, 2)->notNull()->defaultValue(0),
                'gst_rate'         => $this->decimal(5, 2)->notNull()->defaultValue(0),
                'taxable_amount'   => $this->decimal(14, 2)->notNull(),
                'gst_amount'       => $this->decimal(14, 2)->notNull(),
                'line_total'       => $this->decimal(14, 2)->notNull(),
                'created_at'       => $this->dateTime()->null(),
            ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $this->createIndex('idx_cqi_quote', 'client_quotation_items', ['quote_id', 'sort_order']);
            $this->createIndex('idx_cqi_product', 'client_quotation_items', 'product_id');
            $this->addForeignKey('fk_cqi_quote', 'client_quotation_items', 'quote_id', 'client_quotation', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey('fk_cqi_product', 'client_quotation_items', 'product_id', 'delivery_product_details', 'id', 'SET NULL', 'CASCADE');
        }

        $this->addColumns('client_quotation', [
            'quote_status'   => $this->tinyInteger()->notNull()->defaultValue(1)->comment('1 Draft, 2 Sent, 3 Accepted, 4 Rejected, 5 Invoiced'),
            'valid_until'    => $this->date()->null(),
            'tax_mode'       => $this->tinyInteger()->null()->comment('1 CGST+SGST, 2 IGST, 3 No GST'),
            'sub_total'      => $this->decimal(14, 2)->null(),
            'discount_total' => $this->decimal(14, 2)->null(),
            'taxable_total'  => $this->decimal(14, 2)->null(),
            'gst_total'      => $this->decimal(14, 2)->null(),
            'grand_total'    => $this->decimal(14, 2)->null(),
            'terms'          => $this->text()->null(),
            'notes'          => $this->text()->null(),
            'invoice_id'     => $this->bigInteger()->null(),
        ]);
        $this->addIndexes('client_quotation', ['idx_cq_quote_status' => 'quote_status', 'idx_cq_invoice' => 'invoice_id']);

        $this->addColumns('invoice_details', [
            'product_id'       => $this->bigInteger()->null(),
            'unit'             => $this->string(20)->null(),
            'discount_percent' => $this->decimal(5, 2)->null(),
            'gst_rate'         => $this->decimal(5, 2)->null()->comment('NULL = old invoice: GST taken from the invoice header'),
            'gst_amount'       => $this->decimal(14, 2)->null(),
        ]);
        $this->addIndexes('invoice_details', ['idx_invd_product' => 'product_id']);

        $this->addColumns('invoice', [
            'quotation_id' => $this->bigInteger()->null(),
        ]);
        $this->addIndexes('invoice', ['idx_invoice_quotation' => 'quotation_id']);
    }

    public function safeDown()
    {
        $this->dropIndexes('invoice', ['idx_invoice_quotation']);
        $this->dropColumns('invoice', ['quotation_id']);
        $this->dropIndexes('invoice_details', ['idx_invd_product']);
        $this->dropColumns('invoice_details', ['product_id', 'unit', 'discount_percent', 'gst_rate', 'gst_amount']);
        $this->dropIndexes('client_quotation', ['idx_cq_quote_status', 'idx_cq_invoice']);
        $this->dropColumns('client_quotation', ['quote_status', 'valid_until', 'tax_mode', 'sub_total', 'discount_total', 'taxable_total',
            'gst_total', 'grand_total', 'terms', 'notes', 'invoice_id']);
        if ($this->db->getTableSchema('client_quotation_items', true) !== null) {
            $this->dropTable('client_quotation_items');
        }
    }

    private function addColumns(string $table, array $columns): void
    {
        $schema = $this->db->getTableSchema($table, true);
        foreach ($columns as $name => $type) {
            if (!$schema->getColumn($name)) {
                $this->addColumn($table, $name, $type);
            }
        }
    }

    private function dropColumns(string $table, array $columns): void
    {
        $schema = $this->db->getTableSchema($table, true);
        foreach ($columns as $name) {
            if ($schema->getColumn($name)) {
                $this->dropColumn($table, $name);
            }
        }
    }

    private function addIndexes(string $table, array $indexes): void
    {
        $have = array_column($this->db->createCommand("SHOW INDEX FROM `$table`")->queryAll(), 'Key_name');
        foreach ($indexes as $name => $column) {
            if (!in_array($name, $have, true)) {
                $this->createIndex($name, $table, $column);
            }
        }
    }

    private function dropIndexes(string $table, array $indexes): void
    {
        $have = array_column($this->db->createCommand("SHOW INDEX FROM `$table`")->queryAll(), 'Key_name');
        foreach ($indexes as $name) {
            if (in_array($name, $have, true)) {
                $this->dropIndex($name, $table);
            }
        }
    }
}
