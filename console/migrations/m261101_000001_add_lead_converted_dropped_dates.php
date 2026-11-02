<?php

use yii\db\Migration;

/**
 * Leads module: timestamps written when a lead is marked Converted (lead_status=5)
 * or Dropped (lead_status=6). The legacy Yii1 code wrote these columns but some
 * databases never received them.
 *
 * Guarded so the migration is safe to re-run.
 */
class m261101_000001_add_lead_converted_dropped_dates extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->getTableSchema('lead', true);
        if (!$schema->getColumn('converted_date_time')) {
            $this->addColumn('lead', 'converted_date_time', $this->dateTime()->null()->after('offering_service'));
        }
        if (!$schema->getColumn('dropped_date_time')) {
            $this->addColumn('lead', 'dropped_date_time', $this->dateTime()->null()->after('converted_date_time'));
        }
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema('lead', true);
        if ($schema->getColumn('dropped_date_time')) {
            $this->dropColumn('lead', 'dropped_date_time');
        }
        if ($schema->getColumn('converted_date_time')) {
            $this->dropColumn('lead', 'converted_date_time');
        }
    }
}
