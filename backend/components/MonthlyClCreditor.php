<?php

namespace backend\components;

use backend\models\Developer;
use backend\models\MonthlyClCredit;
use Yii;

/**
 * Monthly +1 CL accrual.
 *
 * Credits exactly ONE casual-leave day (developer.cl_backup += 1) to every
 * ACTIVE developer (status = 1) for a given month — and never more than once.
 *
 * Duplicate protection: a guard row is written to `monthly_cl_credit` for the
 * (developer, month) pair BEFORE cl_backup is bumped, inside a transaction. The
 * UNIQUE KEY on (developer_id, credit_month) means a re-run — or two crons
 * firing at once — can only insert the guard once, so the +1 happens once.
 * The whole operation is therefore idempotent: safe to run repeatedly and safe
 * to back-fill an earlier month without disturbing months already credited.
 *
 * This intentionally does NOTHING else — no LOP, no reset, no proration. Just
 * +1 CL per active developer per month.
 */
class MonthlyClCreditor
{
    /**
     * @param string $month any date string; only the YYYY-MM part is used.
     * @return string human-readable summary (also stored in the cron log).
     */
    public static function creditForMonth(string $month): string
    {
        $month = substr($month, 0, 7); // normalize to 'YYYY-MM'
        $now   = date('Y-m-d H:i:s');

        $developers = Developer::find()->where(['status' => 1])->all();

        $credited = 0;
        $skipped  = 0; // already credited for this month
        $failed   = 0;

        foreach ($developers as $dev) {
            // Cheap pre-check; the UNIQUE KEY is the real guarantee.
            if (MonthlyClCredit::find()
                ->where(['developer_id' => $dev->id, 'credit_month' => $month])
                ->exists()) {
                $skipped++;
                continue;
            }

            $txn = Yii::$app->db->beginTransaction();
            try {
                $before = (float) $dev->cl_backup;

                $guard = new MonthlyClCredit();
                $guard->developer_id = (int) $dev->id;
                $guard->credit_month = $month;
                $guard->cl_before    = $before;
                $guard->cl_after     = $before + 1;
                $guard->credited_at  = $now;

                // Insert the guard FIRST — the unique key rejects a duplicate
                // here, so the increment below can never run twice.
                if (!$guard->save(false)) {
                    $txn->rollBack();
                    $failed++;
                    continue;
                }

                $dev->cl_backup = $before + 1;
                if (!$dev->save(false, ['cl_backup'])) {
                    $txn->rollBack();
                    $failed++;
                    continue;
                }

                $txn->commit();
                $credited++;
            } catch (\Throwable $e) {
                $txn->rollBack();
                // A unique-violation here means another run beat us to it —
                // treat as already-credited, not a failure.
                $skipped++;
            }
        }

        return sprintf(
            'Monthly CL credit for %s: credited=%d, skipped(existing)=%d, failed=%d, active_developers=%d',
            $month,
            $credited,
            $skipped,
            $failed,
            count($developers)
        );
    }
}
