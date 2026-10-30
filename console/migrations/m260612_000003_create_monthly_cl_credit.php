<?php

use yii\db\Migration;

/**
 * Guard / audit table for the monthly +1 CL accrual.
 *
 * Each month every active developer earns one casual-leave day
 * (developer.cl_backup += 1). This table holds one row per (developer_id,
 * credit_month); the UNIQUE KEY on that pair is what makes the accrual
 * duplicate-proof — backend\components\MonthlyClCreditor inserts the guard row
 * before bumping cl_backup, so a re-run can credit a given month only once.
 *
 * Hit via the cron endpoint /site/monthlyclcall (1st of each month).
 *
 * NOTE: `php yii migrate` is broken in this project (replays unrecorded
 * migrations and fails); the table was created on the live DB via raw SQL. This
 * file exists for schema parity and is guarded so it is safe to (re-)run.
 */
class m260612_000003_create_monthly_cl_credit extends Migration
{
    public function safeUp()
    {
        $table = 'monthly_cl_credit';

        if ($this->db->getTableSchema($table, true) !== null) {
            return; // already created on the live DB
        }

        $this->createTable($table, [
            'id'           => $this->primaryKey()->unsigned(),
            'developer_id' => $this->bigInteger()->notNull(),
            'credit_month' => $this->char(7)->notNull()->comment('YYYY-MM the CL was granted for'),
            'cl_before'    => $this->decimal(5, 2)->null(),
            'cl_after'     => $this->decimal(5, 2)->null(),
            'credited_at'  => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'uniq_dev_month',
            $table,
            ['developer_id', 'credit_month'],
            true
        );
    }

    public function safeDown()
    {
        if ($this->db->getTableSchema('monthly_cl_credit', true) !== null) {
            $this->dropTable('monthly_cl_credit');
        }
    }
}
