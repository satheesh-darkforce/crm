<?php

use yii\db\Migration;

/**
 * Half-Day Permission support (leaverequest.txt #2).
 *
 * A permission row (leave_requests.leave_type = 2) is a HALF-DAY permission when
 * permission_session > 0 (1 = morning half, 2 = evening half). Hourly permissions
 * keep permission_session = 0 and use permission_hours (1 or 2).
 *
 * Guarded so the migration is safe to re-run.
 */
class m260610_000003_add_permission_session extends Migration
{
    public function safeUp()
    {
        $tbl = 'leave_requests';
        if (!$this->db->getTableSchema($tbl)->getColumn('permission_session')) {
            $this->addColumn(
                $tbl,
                'permission_session',
                $this->tinyInteger(1)->notNull()->defaultValue(0)->after('permission_hours')
            );
        }
    }

    public function safeDown()
    {
        $tbl    = 'leave_requests';
        $schema = $this->db->getTableSchema($tbl);
        if ($schema !== null && $schema->getColumn('permission_session')) {
            $this->dropColumn($tbl, 'permission_session');
        }
    }
}
