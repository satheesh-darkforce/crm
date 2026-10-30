<?php

use yii\db\Migration;

/**
 * Per-session "Office Work" flag (Change Attendance — admin override).
 *
 * A 4th per-session option alongside On time / Late / Excuse: when a check-in or
 * check-out session is marked "Office Work" the lateness is NOT counted anywhere
 * (live dashboard, monthly attendance, payslip). Unlike "excuse" (which keeps
 * morning_late=1 and exempts only the LOP), office work sets morning_late=0 and
 * records the reason in these flags so the form round-trips and reports can show
 * a distinct "(OW)" marker. The flags also let the count functions stay correct
 * even if the nightly AttendanceProcessor re-flags the session's late bit from
 * the punch times.
 *
 *   morning_office_work = 1 → morning session was office work (not late)
 *   evening_office_work = 1 → evening session was office work (not late)
 *
 * Guarded so the migration is safe to re-run.
 */
class m260612_000002_add_office_work_to_dailyattendance extends Migration
{
    public function safeUp()
    {
        $da     = 'dailyattendance';
        $flag   = $this->tinyInteger(1)->notNull()->defaultValue(0);
        $schema = $this->db->getTableSchema($da);

        if (!$schema->getColumn('morning_office_work')) {
            $this->addColumn($da, 'morning_office_work', $flag);
        }
        if (!$schema->getColumn('evening_office_work')) {
            $this->addColumn($da, 'evening_office_work', $flag);
        }
    }

    public function safeDown()
    {
        $da     = 'dailyattendance';
        $schema = $this->db->getTableSchema($da);
        if ($schema === null) {
            return;
        }
        foreach (['morning_office_work', 'evening_office_work'] as $col) {
            if ($schema->getColumn($col)) {
                $this->dropColumn($da, $col);
            }
        }
    }
}
