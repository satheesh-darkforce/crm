<?php

use yii\db\Migration;

/**
 * Per-session late LOP exemption (requirement.txt #4 — admin override).
 *
 * The whole-day `lop_exempt` flag (added in m260608) can only excuse an entire
 * day from late-LOP counting. Admins also need to excuse a SINGLE session: mark
 * the morning late as "Present" while still counting the evening late, or vice
 * versa. These two flags let LopCalculator::lateCount()/markEarlyOut() honour a
 * per-session excusal that survives the publish step (markEarlyOut would
 * otherwise re-flag evening_late from the punch checkout time).
 *
 *   morning_lop_exempt = 1 → morning_late is recorded but NOT counted for LOP
 *   evening_lop_exempt = 1 → evening_late is recorded but NOT counted for LOP
 *
 * Guarded so the migration is safe to re-run.
 */
class m260610_000001_add_per_session_lop_exempt extends Migration
{
    public function safeUp()
    {
        $da     = 'dailyattendance';
        $flag   = $this->tinyInteger(1)->notNull()->defaultValue(0);
        $schema = $this->db->getTableSchema($da);

        if (!$schema->getColumn('morning_lop_exempt')) {
            $this->addColumn($da, 'morning_lop_exempt', $flag);
        }
        if (!$schema->getColumn('evening_lop_exempt')) {
            $this->addColumn($da, 'evening_lop_exempt', $flag);
        }
    }

    public function safeDown()
    {
        $da     = 'dailyattendance';
        $schema = $this->db->getTableSchema($da);
        if ($schema === null) {
            return;
        }
        foreach (['morning_lop_exempt', 'evening_lop_exempt'] as $col) {
            if ($schema->getColumn($col)) {
                $this->dropColumn($da, $col);
            }
        }
    }
}
