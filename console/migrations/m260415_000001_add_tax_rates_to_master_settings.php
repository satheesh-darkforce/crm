<?php

use yii\db\Migration;

class m260415_000001_add_tax_rates_to_master_settings extends Migration
{
    public function safeUp()
    {
        $table = 'master_settings';

        if (!$this->db->getTableSchema($table)->getColumn('sgst_percent')) {
            $this->addColumn($table, 'sgst_percent', $this->decimal(5, 2)->defaultValue(9.00)->after('invoice_lut_arn'));
        }
        if (!$this->db->getTableSchema($table)->getColumn('cgst_percent')) {
            $this->addColumn($table, 'cgst_percent', $this->decimal(5, 2)->defaultValue(9.00)->after('sgst_percent'));
        }
        if (!$this->db->getTableSchema($table)->getColumn('igst_percent')) {
            $this->addColumn($table, 'igst_percent', $this->decimal(5, 2)->defaultValue(18.00)->after('cgst_percent'));
        }
    }

    public function safeDown()
    {
        $this->dropColumn('master_settings', 'igst_percent');
        $this->dropColumn('master_settings', 'cgst_percent');
        $this->dropColumn('master_settings', 'sgst_percent');
    }
}
