<?php

namespace backend\components;

use backend\models\Attendance;
use backend\models\DailyAttendance;
use backend\models\Developer;
use backend\models\Holiday;

/**
 * Centralised payroll attendance rules (requirement #3, #4, #5).
 *
 * Used by both the attendance publish step and the payslip generation so the
 * numbers always agree:
 *
 *  #3 creditHolidayWork() — working a Sunday or public holiday credits one day
 *     to the employee's CL backup (idempotent via dailyattendance.cl_credited).
 *  #4 lateLop()           — late check-in / early check-out (before EARLY_OUT_CUTOFF,
 *     18:30) counts: the first 2 in a month are excused, then every 2 counts = a
 *     half-day (0.5) LOP. Days an admin overrode to Present (lop_exempt=1) are
 *     skipped. markEarlyOut() auto-flags evening_late from the punch checkout time.
 *  #5 sandwichLop()       — a holiday/Sunday bridged by full-day leave on the
 *     working day before AND after counts the bridged day(s) as LOP.
 */
class LopCalculator
{
    /** Fixed early check-out cutoff (HH:MM, 24h). Checking out before this is an early-out. */
    public const EARLY_OUT_CUTOFF = '18:30';

    /** Full-day leave/absent leave_type codes that trigger a sandwich bridge. */
    private const FULL_LEAVE_TYPES = [3, 7]; // 3 = Absent, 7 = Leave (CL)

    /** Cache of holiday date strings (Y-m-d) per query window. */
    private static array $holidayCache = [];

    // ── #3: CL backup credit for Sunday / holiday work ───────────────────────

    /**
     * Credit one CL backup day for each Sunday/holiday the employee actually
     * worked (has a check-in punch) and that hasn't been credited yet. Returns
     * the number of days credited. Idempotent: sets dailyattendance.cl_credited.
     */
    public static function creditHolidayWork(int $devId, string $from, string $to): int
    {
        $holidays = self::holidaySet($from, $to);

        // Distinct punch dates in range (the days the employee actually worked).
        $punchDates = Attendance::find()
            ->select(['d' => 'DATE(in_time)'])
            ->where(['status' => 1, 'developer_id' => $devId])
            ->andWhere(['>=', 'DATE(in_time)', $from])
            ->andWhere(['<=', 'DATE(in_time)', $to])
            ->andWhere(['is not', 'in_time', null])
            ->distinct()
            ->column();

        $credited = 0;
        foreach ($punchDates as $date) {
            $isSunday  = (int)date('w', strtotime($date)) === 0;
            $isHoliday = isset($holidays[$date]);
            if (!$isSunday && !$isHoliday) {
                continue;
            }

            // Find-or-create the daily row so the cl_credited guard is durable.
            $row = DailyAttendance::find()
                ->where(['developer_id' => $devId, 'attendance_date' => $date, 'status' => 1])
                ->one();
            if (!$row) {
                $row = new DailyAttendance();
                $row->developer_id    = $devId;
                $row->attendance_date = $date;
                $row->is_present      = 1;
                $row->is_holiday      = $isHoliday ? 1 : 0;
                $row->leave_type      = 4; // Holiday
                $row->created_date    = date('Y-m-d H:i:s');
                $row->status          = 1;
            }
            if ((int)$row->cl_credited === 1) {
                continue; // already credited
            }
            $row->cl_credited = 1;
            $row->save(false);

            $dev = Developer::findOne($devId);
            if ($dev) {
                $dev->cl_backup = (float)$dev->cl_backup + 1;
                $dev->save(false);
            }
            $credited++;
        }

        return $credited;
    }

    // ── #4: early-out auto-flagging + late LOP conversion ────────────────────

    /**
     * Auto-flag evening_late = 1 on days where the punch check-out time is before
     * the fixed 18:30 cutoff (skips rows already exempt or already flagged).
     */
    public static function markEarlyOut(int $devId, string $from, string $to): void
    {
        $rows = DailyAttendance::find()
            ->where(['status' => 1, 'developer_id' => $devId])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->all();

        foreach ($rows as $row) {
            if ((int)$row->lop_exempt === 1 || (int)$row->evening_lop_exempt === 1
                || (int)$row->evening_office_work === 1 || (int)$row->evening_late === 1) {
                continue; // whole-day/evening admin excusal, office work, or already flagged
            }
            // Prefer the daily row out_time; fall back to the punch out_time.
            $out = $row->out_time;
            if (!$out) {
                $punch = Attendance::find()
                    ->where(['status' => 1, 'developer_id' => $devId])
                    ->andWhere(['=', 'DATE(in_time)', date('Y-m-d', strtotime($row->attendance_date))])
                    ->andWhere(['is not', 'out_time', null])
                    ->orderBy(['out_time' => SORT_DESC])
                    ->one();
                $out = $punch ? date('H:i:s', strtotime($punch->out_time)) : null;
            }
            if (!$out) {
                continue; // no checkout recorded — nothing to flag
            }
            if (strtotime($out) < strtotime(self::EARLY_OUT_CUTOFF)) {
                $row->evening_late = 1;
                $row->save(false);
            }
        }
    }

    /**
     * Raw late count (morning_late + evening_late) in range, excluding exempt
     * sessions. Admin overrides on the Change Attendance screen:
     *   lop_exempt=1          → whole day excused (counts nothing)
     *   morning_lop_exempt=1  → morning late recorded but not counted
     *   evening_lop_exempt=1  → evening late recorded but not counted
     *
     * Days with an APPROVED permission (is_permission=1 — hourly or half-day) or a
     * half-day leave (leave_type=8) are also fully excused from late-LOP
     * (leaverequest.txt #3). Unapproved requests are never stamped, so their
     * lateness still counts.
     */
    public static function lateCount(int $devId, string $from, string $to): int
    {
        $rows = DailyAttendance::find()
            ->where(['status' => 1, 'developer_id' => $devId])
            ->andWhere(['or', ['morning_late' => 1], ['evening_late' => 1]])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->all();
        $count = 0;
        foreach ($rows as $r) {
            if ((int)$r->lop_exempt === 1) {
                continue; // whole day excused by admin
            }
            if ((int)$r->is_permission === 1 || (int)$r->leave_type === 8) {
                continue; // approved permission / half-day-leave day — late not counted
            }
            // Office-work sessions are never counted as late.
            if ((int)$r->morning_late === 1 && (int)$r->morning_lop_exempt !== 1 && (int)$r->morning_office_work !== 1) { $count++; }
            if ((int)$r->evening_late === 1 && (int)$r->evening_lop_exempt !== 1 && (int)$r->evening_office_work !== 1) { $count++; }
        }
        return $count;
    }

    /**
     * Late LOP: first 2 counts in the month are excused, then every 2 further
     * counts = 0.5 day LOP.  floor(max(0, count-2) / 2) * 0.5
     */
    public static function lateLop(int $devId, string $from, string $to): float
    {
        $billable = max(0, self::lateCount($devId, $from, $to) - 2);
        return floor($billable / 2) * 0.5;
    }

    // ── #5: sandwich leave ───────────────────────────────────────────────────

    /**
     * DISABLED — always returns 0.
     *
     * The sandwich policy is handled entirely by AttendanceProcessor: a
     * holiday/Sunday is converted to Absent (leave_type=3, is_holiday=0) when an
     * unapproved absence falls on its previous OR next calendar day. That marked
     * row is already counted by absentLop, so a separate sandwich computation
     * here would DOUBLE-COUNT it.
     *
     * Kept as a no-op (rather than deleted) so the several payroll callers
     * (PayslipController, AttendanceController publish, summary) stay valid and
     * consistently add 0. The bridged-holiday LOP lives entirely in absentLop.
     */
    public static function sandwichLop(int $devId, string $from, string $to): float
    {
        return 0.0;
    }

    /** @deprecated retained for reference; no longer reached. */
    private static function sandwichLopLegacy(int $devId, string $from, string $to): float
    {
        $holidays = self::holidaySet($from, $to);

        // Map each date to a state: 'holiday' | 'leave' (unapproved absence,
        // bridges) | 'approved_leave' (does NOT bridge) | 'work'.
        $leaveDates = self::fullLeaveDates($devId, $from, $to); // date => isApproved
        $state = [];
        for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
            $d = date('Y-m-d', $t);
            if ((int)date('w', $t) === 0 || isset($holidays[$d])) {
                $state[$d] = 'holiday';
            } elseif (isset($leaveDates[$d])) {
                $state[$d] = $leaveDates[$d] ? 'approved_leave' : 'leave';
            } else {
                $state[$d] = 'work';
            }
        }

        $dates = array_keys($state);
        $sandwich = 0.0;
        $i = 0;
        $n = count($dates);
        while ($i < $n) {
            if ($state[$dates[$i]] !== 'holiday') {
                $i++;
                continue;
            }
            // Span the contiguous holiday block [i .. j-1].
            $j = $i;
            while ($j < $n && $state[$dates[$j]] === 'holiday') {
                $j++;
            }
            $before = $i - 1 >= 0 ? $state[$dates[$i - 1]] : null;
            $after  = $j < $n ? $state[$dates[$j]] : null;
            if ($before === 'leave' && $after === 'leave') {
                $sandwich += ($j - $i); // bridged holiday day(s) become LOP
            }
            $i = $j;
        }

        return $sandwich;
    }

    // ── Leave / absence LOP ──────────────────────────────────────────────────

    /**
     * Leave / absence LOP days in range. Mirrors the payslip rules exactly so the
     * Create Payslip page and PayslipController agree:
     *   leave_type 7 (CL) full day, not present, CL not debited        → 1
     *   leave_type 8 (half-day) morning leave while present, no CL     → 0.5
     *   leave_type 8 (half-day) evening leave while present, no CL     → 0.5
     *   leave_type 3 (Absent)                                          → 1
     * Holiday rows (is_holiday=1) are excluded. A half-day covered by CL
     * (cl_debited >= 0.5 — e.g. an approved half-day leave) is NOT LOP.
     */
    public static function absentLop(int $devId, string $from, string $to): float
    {
        $rows = DailyAttendance::find()
            ->where(['status' => 1, 'is_holiday' => 0, 'developer_id' => $devId])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->all();
        $leave = 0.0;
        foreach ($rows as $r) {
            if ($r->leave_type == 7 && $r->total_lop == 1 && $r->is_present == 0 && !$r->cl_debited) { $leave += 1; }
            if ($r->leave_type == 8 && $r->morning_leave == 1 && $r->is_present == 1 && (float)$r->cl_debited < 0.5) { $leave += 0.5; }
            if ($r->leave_type == 8 && $r->evening_leave == 1 && $r->is_present == 1 && (float)$r->cl_debited < 0.5) { $leave += 0.5; }
            if ($r->leave_type == 3)                                                                  { $leave += 1; }
        }
        return $leave;
    }

    // ── Shared totals ────────────────────────────────────────────────────────

    /** Total payroll LOP days for the range = leave/absence + late + sandwich. */
    public static function totalLop(int $devId, string $from, string $to): float
    {
        return self::absentLop($devId, $from, $to)
             + self::lateLop($devId, $from, $to)
             + self::sandwichLop($devId, $from, $to);
    }

    /** Per-employee LOP summary for display (monthly attendance) and payroll. */
    public static function summary(int $devId, string $from, string $to): array
    {
        $lateCount   = self::lateCount($devId, $from, $to);
        $lateLop     = self::lateLop($devId, $from, $to);
        $sandwichLop = self::sandwichLop($devId, $from, $to);
        $absentLop   = self::absentLop($devId, $from, $to);
        return [
            'lateCount'   => $lateCount,
            'lateLop'     => $lateLop,
            'sandwichLop' => $sandwichLop,
            'absentLop'   => $absentLop,
            'totalLop'    => $absentLop + $lateLop + $sandwichLop,
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Holiday dates (Y-m-d => true) within the window, cached per window. */
    private static function holidaySet(string $from, string $to): array
    {
        $key = $from . '|' . $to;
        if (isset(self::$holidayCache[$key])) {
            return self::$holidayCache[$key];
        }
        $dates = Holiday::find()
            ->select(['date'])
            ->where(['status' => 1])
            ->andWhere(['>=', 'date', $from])
            ->andWhere(['<=', 'date', $to])
            ->column();
        $set = [];
        foreach ($dates as $d) {
            $set[date('Y-m-d', strtotime($d))] = true;
        }
        return self::$holidayCache[$key] = $set;
    }

    /**
     * Dates the employee was on a full-day leave/absent, mapped to whether that
     * leave is APPROVED (Y-m-d => bool isApproved).
     *
     *   leave_type 7 (CL)     → approved leave (synced from an approved leave
     *                           request, or admin-set CL) → isApproved = true
     *   leave_type 3 (Absent) → unapproved absence        → isApproved = false
     *
     * An approved leave on either side of a holiday does NOT create a sandwich.
     */
    private static function fullLeaveDates(int $devId, string $from, string $to): array
    {
        $rows = DailyAttendance::find()
            ->where(['status' => 1, 'developer_id' => $devId, 'is_present' => 0])
            ->andWhere(['leave_type' => self::FULL_LEAVE_TYPES])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->all();
        $set = [];
        foreach ($rows as $r) {
            $set[date('Y-m-d', strtotime($r->attendance_date))] = ((int)$r->leave_type === 7);
        }
        return $set;
    }
}
