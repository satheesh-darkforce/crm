<?php

use yii\db\Migration;

/**
 * Payroll & attendance enhancements (requirement.txt, 6 features).
 *
 *  #1 developer: Salary Management fields — per-component applicable flag + amount
 *     (PF / ESI / TDS / Professional Tax / Incentive / Allowance) and a master
 *     `is_salary_applicable` switch that gates who appears on the Create Payslip screen.
 *  #2 leave_requests.permission_hours: hours (1.0 / 2.0) for an hourly Permission
 *     request (leave_type = 2). NULL for ordinary leave.
 *  #3 dailyattendance.cl_credited: idempotent guard so working a Sunday/holiday
 *     credits cl_backup exactly once (mirrors existing cl_debited).
 *  #4 dailyattendance.lop_exempt: set when an admin overrides a day to Present so
 *     that day is skipped by the late-in / early-out LOP counter.
 *
 * All adds are guarded so the migration is safe to re-run.
 */
class m260608_000001_payroll_attendance_enhancements extends Migration
{
    public function safeUp()
    {
        // ---- #1 developer salary management fields -------------------------------
        $dev = 'developer';
        $flag   = $this->tinyInteger(1)->notNull()->defaultValue(0);
        $amount = $this->decimal(10, 2)->notNull()->defaultValue(0);

        $devCols = [
            'is_salary_applicable'    => $flag,
            'pf_status'               => $flag,
            'pf_amount'               => $amount,
            'esi_status'              => $flag,
            'esi_amount'              => $amount,
            'tds_status'              => $flag,
            'tds_amount'              => $amount,
            'professional_tax_status' => $flag,
            'professional_tax_amount' => $amount,
            'incentive_status'        => $flag,
            'incentive_amount'        => $amount,
            'allowance_applicable'    => $flag,
            'allowance_amount'        => $amount,
        ];
        $devSchema = $this->db->getTableSchema($dev);
        foreach ($devCols as $name => $type) {
            if (!$devSchema->getColumn($name)) {
                $this->addColumn($dev, $name, $type);
            }
        }

        // ---- #2 leave_requests.permission_hours ----------------------------------
        $lr = 'leave_requests';
        if (!$this->db->getTableSchema($lr)->getColumn('permission_hours')) {
            $this->addColumn($lr, 'permission_hours', $this->decimal(3, 1)->null()->defaultValue(null));
        }

        // ---- #3/#4 dailyattendance flags -----------------------------------------
        $da = 'dailyattendance';
        $daSchema = $this->db->getTableSchema($da);
        if (!$daSchema->getColumn('cl_credited')) {
            $this->addColumn($da, 'cl_credited', $this->tinyInteger(1)->notNull()->defaultValue(0));
        }
        if (!$daSchema->getColumn('lop_exempt')) {
            $this->addColumn($da, 'lop_exempt', $this->tinyInteger(1)->notNull()->defaultValue(0));
        }
    }

    public function safeDown()
    {
        $drop = [
            'developer' => [
                'is_salary_applicable', 'pf_status', 'pf_amount', 'esi_status', 'esi_amount',
                'tds_status', 'tds_amount', 'professional_tax_status', 'professional_tax_amount',
                'incentive_status', 'incentive_amount', 'allowance_applicable', 'allowance_amount',
            ],
            'leave_requests'  => ['permission_hours'],
            'dailyattendance' => ['cl_credited', 'lop_exempt'],
        ];
        foreach ($drop as $table => $cols) {
            $schema = $this->db->getTableSchema($table);
            if ($schema === null) {
                continue;
            }
            foreach ($cols as $col) {
                if ($schema->getColumn($col)) {
                    $this->dropColumn($table, $col);
                }
            }
        }
    }
}
