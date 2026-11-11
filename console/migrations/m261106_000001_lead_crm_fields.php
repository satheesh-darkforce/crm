<?php

use yii\db\Migration;

/**
 * Leads for Green Divine (PROJECT_ANALYSIS.md §2.1): lead source, site area,
 * expected installation date, site visit (date + person), quotation owner,
 * and the products a lead is interested in (linked to product categories).
 *
 * Additive only; guarded so it is safe to re-run.
 */
class m261106_000001_lead_crm_fields extends Migration
{
    public function safeUp()
    {
        $schema  = $this->db->getTableSchema('lead', true);
        $columns = [
            'lead_source'           => $this->tinyInteger()->null()->after('mode'),
            'source_detail'         => $this->string(100)->null()->after('lead_source'),
            'site_area'             => $this->decimal(10, 2)->null()->after('source_detail'),
            'area_unit'             => $this->string(10)->null()->after('site_area'),
            'expected_install_date' => $this->date()->null()->after('area_unit'),
            'site_visit_date'       => $this->dateTime()->null()->after('expected_install_date'),
            'site_visit_by'         => $this->bigInteger()->null()->after('site_visit_date'),
            'quotation_by'          => $this->bigInteger()->null()->after('site_visit_by'),
        ];
        foreach ($columns as $name => $type) {
            if (!$schema->getColumn($name)) {
                $this->addColumn('lead', $name, $type);
            }
        }
        $indexes = array_column($this->db->createCommand('SHOW INDEX FROM lead')->queryAll(), 'Key_name');
        foreach (['idx_lead_source' => 'lead_source', 'idx_lead_site_visit' => 'site_visit_date', 'idx_lead_site_visit_by' => 'site_visit_by', 'idx_lead_quotation_by' => 'quotation_by'] as $idx => $col) {
            if (!in_array($idx, $indexes, true)) {
                $this->createIndex($idx, 'lead', $col);
            }
        }

        if ($this->db->getTableSchema('lead_product_interest', true) === null) {
            $this->createTable('lead_product_interest', [
                'id'          => $this->primaryKey(),
                'lead_id'     => $this->bigInteger()->notNull(),
                'category_id' => $this->integer()->notNull(),
                'created_at'  => $this->dateTime()->null(),
            ], 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $this->createIndex('ux_lead_interest', 'lead_product_interest', ['lead_id', 'category_id'], true);
            $this->createIndex('idx_lead_interest_category', 'lead_product_interest', 'category_id');
            $this->addForeignKey('fk_lead_interest_lead', 'lead_product_interest', 'lead_id', 'lead', 'id', 'CASCADE', 'CASCADE');
            $this->addForeignKey('fk_lead_interest_category', 'lead_product_interest', 'category_id', 'product_category', 'id', 'CASCADE', 'CASCADE');
        }
    }

    public function safeDown()
    {
        if ($this->db->getTableSchema('lead_product_interest', true) !== null) {
            $this->dropTable('lead_product_interest');
        }
        $indexes = array_column($this->db->createCommand('SHOW INDEX FROM lead')->queryAll(), 'Key_name');
        foreach (['idx_lead_source', 'idx_lead_site_visit', 'idx_lead_site_visit_by', 'idx_lead_quotation_by'] as $idx) {
            if (in_array($idx, $indexes, true)) {
                $this->dropIndex($idx, 'lead');
            }
        }
        $schema = $this->db->getTableSchema('lead', true);
        foreach (['quotation_by', 'site_visit_by', 'site_visit_date', 'expected_install_date', 'area_unit', 'site_area', 'source_detail', 'lead_source'] as $col) {
            if ($schema->getColumn($col)) {
                $this->dropColumn('lead', $col);
            }
        }
    }
}
