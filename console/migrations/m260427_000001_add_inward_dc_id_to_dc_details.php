<?php

use yii\db\Migration;

/**
 * Adds dc_details.inward_dc_id so a return DC (type=1, dc_type=3) can point
 * back to its source inward DC by primary key, instead of relying on the
 * client-supplied inward_dc_no string (which can collide across clients).
 */
class m260427_000001_add_inward_dc_id_to_dc_details extends Migration
{
    public function safeUp()
    {
        $table = 'dc_details';

        if (!$this->db->getTableSchema($table)->getColumn('inward_dc_id')) {
            $this->addColumn($table, 'inward_dc_id', $this->bigInteger()->null()->after('inward_dc_no'));
            $this->createIndex('idx-dc_details-inward_dc_id', $table, 'inward_dc_id');
        }

        // Backfill existing return DCs (type=1 with inward_dc_no set) by matching
        // on (client_id, inward_dc_no) — the most reliable mapping available.
        $this->execute("
            UPDATE dc_details r
            JOIN dc_details i
              ON i.type = 2
             AND i.client_id = r.client_id
             AND i.inward_dc_no = r.inward_dc_no
             AND i.status <> 0
            SET r.inward_dc_id = i.id
            WHERE r.type = 1
              AND r.inward_dc_no IS NOT NULL
              AND r.inward_dc_id IS NULL
        ");
    }

    public function safeDown()
    {
        $table = 'dc_details';
        if ($this->db->getTableSchema($table)->getColumn('inward_dc_id')) {
            try { $this->dropIndex('idx-dc_details-inward_dc_id', $table); } catch (\Throwable $e) {}
            $this->dropColumn($table, 'inward_dc_id');
        }
    }
}
