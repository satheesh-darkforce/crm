<?php

namespace common\components;

use backend\models\Attendance;
use backend\models\DailyAttendance;
use backend\models\Developer;
use backend\models\Holiday;
use backend\models\LeaveRequests;
use backend\models\LeaveSettings;
use backend\models\Shifttime;

/**
 * Daily attendance processor — port of Yii1 Utility::dailyattendancecall().
 *
 * Reads raw RFID/punch rows from `attendance` for a given date and writes
 * one processed row per active developer into `dailyattendance`, which is
 * the table the Monthly Attendance view reads from.
 *
 * Leave type codes (Yii1 parity):
 *   1 = Present, 2 = On duty, 3 = Absent, 4 = Holiday,
 *   5 = Late, 6 = Permission, 7 = Leave (CL), 8 = Half day
 *
 * Idempotent: re-running for the same date updates the existing row
 * (matched on developer_id + attendance_date + status=1).
 */
class AttendanceProcessor
{
    /**
     * Process attendance for a single date.
     *
     * @param string   $date         Y-m-d
     * @param int|null $developerId  Restrict to one developer when non-null
     * @return string                Yii1-compatible "<date> Success/Failed<br>" lines
     */
    public static function processDate(string $date, ?int $developerId = null): string
    {
        $reply = '';

        $devQ = Developer::find()->where(['status' => 1]);
        if ($developerId !== null && $developerId > 0) {
            $devQ->andWhere(['id' => $developerId]);
        }
        $developers = $devQ->all();
        if (empty($developers)) {
            return $reply;
        }

        $todayTs    = strtotime($date);
        $todayDay   = strtolower(date('D', $todayTs));

        $previousDate = date('Y-m-d', strtotime('-1 days', $todayTs));
        $nextDate     = date('Y-m-d', strtotime('+1 days', $todayTs));

        $isScheduledHoliday = 0;
        if (Holiday::find()->where(['status' => 1, 'date' => $date])->exists()) {
            $isScheduledHoliday = 1;
        }

        $afternoonCheckinTime  = strtotime($date . ' 14:00:00');
        $afternoonCheckoutTime = strtotime($date . ' 13:00:00');

        // On-time window = shift intime + grace minutes, PLUS a base 59-second
        // tolerance so the whole shift-start minute counts as on time:
        //   grace 0 → on time through 09:00:59 (late from 09:01:00)
        //   grace 2 → on time through 09:02:59 (late from 09:03:00)
        // Lateness beyond this is excused ONLY by an APPROVED permission request
        // (see hasApprovedPermission); the monthly cap is enforced at request
        // creation (PermissionController), so no count-based auto-excusal here.
        $graceMinutes  = 0;
        $leaveSettings = LeaveSettings::findOne(1);
        if ($leaveSettings !== null) {
            $graceMinutes = max(0, (int)($leaveSettings->check_in_grace_time ?? 0));
        }
        $checkinGraceTime = ($graceMinutes * 60) + 59;

        foreach ($developers as $developer) {
            // Yii1 calls set_cl_backup(1, ...) on day-1 of month; the Yii1
            // method body is commented out in production (no-op), so we mirror
            // that behavior intentionally and skip the call.

            $inTime  = null;
            $outTime = null;
            $leaveType    = 1;
            $isHoliday    = 0;
            $present      = 0;
            $leaveLop     = 0.0;
            $clDebited    = 0;
            $isPermission = 0;
            $halfSession  = null;
            $approvedLeave = false;

            $lateMorning    = 0;
            $lateEvening    = 0;
            $latelopMorning = 0.0;
            $latelopEvening = 0.0;

            $morningLeave    = 0;
            $eveningLeave    = 0;
            $morningLeaveLop = 0.0;
            $eveningLeaveLop = 0.0;

            // Yii1 takes the first attendance row for the day. Mirror that:
            // the schema typically holds one row per dev/day with both
            // in_time/out_time updated by the punch flow.
            // A developer can have several punch rows for one day (RFID re-taps,
            // device re-syncs). Check-in is the EARLIEST in_time and check-out the
            // LATEST out_time across all of the day's rows — not an arbitrary row
            // by id, which could surface a later tap as the "check-in".
            $att = Attendance::find()
                ->select(['min_in' => 'MIN(in_time)', 'max_out' => 'MAX(out_time)'])
                ->where(['developer_id' => $developer->id])
                ->andWhere('DATE(in_time) = :d', [':d' => $date])
                ->asArray()
                ->one();

            if ($att !== null && !empty($att['min_in'])) {
                $present = 1;
                $inTime  = date('H:i:s', strtotime($att['min_in']));
                $outTime = !empty($att['max_out']) ? date('H:i:s', strtotime($att['max_out'])) : null;

                if ($isScheduledHoliday === 1 || $todayDay === 'sun') {
                    $leaveType = 4;
                    $isHoliday = 1;
                } else {
                    $attendanceInTime  = $inTime  ? strtotime($date . ' ' . $inTime)  : 0;
                    $attendanceOutTime = $outTime ? strtotime($date . ' ' . $outTime) : 0;

                    if (!empty($developer->branch)) {
                        $shift = Shifttime::find()->where(['branch' => (int)$developer->branch])->one();
                        if ($shift !== null) {
                            $shiftIn  = !empty($shift->intime)  ? strtotime($date . ' ' . $shift->intime)  : 0;
                            $shiftOut = !empty($shift->outtime) ? strtotime($date . ' ' . $shift->outtime) : 0;

                            $checkShiftInTime = $shiftIn;
                            if ($checkinGraceTime > 0 && $shiftIn > 0 && $attendanceInTime > 0) {
                                $checkShiftInTime = $shiftIn + $checkinGraceTime;
                            }

                            // Morning checkin — late once past shift intime + grace.
                            if ($attendanceInTime > $checkShiftInTime) {
                                $leaveType      = 5;
                                $lateMorning    = 1;
                                $latelopMorning = 0.5;

                                // Excused ONLY by an APPROVED hourly permission for
                                // this date. (A half-day leave is handled separately
                                // below via approvedHalfDayLeave.)
                                if (self::hasApprovedPermission($date, (int)$developer->id, 'morning')) {
                                    $leaveType      = 6;
                                    $lateMorning    = 0;
                                    $latelopMorning = 0.0;
                                    $isPermission   = 1;
                                } elseif ($attendanceInTime <= $afternoonCheckinTime) {
                                    $latelopMorning = 0.5;
                                } else {
                                    $latelopMorning = 1.0;
                                }
                            }

                            // Evening checkout — early-out (or missing checkout) is late.
                            // Early-out boundary is shift outtime minus 1s, so checking
                            // out at 18:29:59 (or later) is NOT early; before 18:29:59 is.
                            $earlyOutCutoff = $shiftOut > 0 ? $shiftOut - 1 : 0;
                            if ($attendanceOutTime <= 0) {
                                $lateEvening    = 1;
                                $latelopEvening = 0.5;
                            } elseif ($attendanceOutTime < $earlyOutCutoff) {
                                $leaveType      = 5;
                                $lateEvening    = 1;
                                $latelopEvening = 0.5;

                                // Excused ONLY by an APPROVED hourly permission for
                                // this date. (A half-day leave is handled separately
                                // below via approvedHalfDayLeave.)
                                if (self::hasApprovedPermission($date, (int)$developer->id, 'evening')) {
                                    $leaveType      = 6;
                                    $lateEvening    = 0;
                                    $latelopEvening = 0.0;
                                    $isPermission   = 1;
                                } elseif ($attendanceOutTime >= $afternoonCheckoutTime) {
                                    $latelopEvening = 0.5;
                                } else {
                                    $latelopEvening = 1.0;
                                }
                            }
                        }
                    }

                    // An APPROVED half-day leave classifies the day as a half-day
                    // regardless of punches; the worked half's late check-in / early
                    // checkout is excused only because the leave is approved. CL/LOP
                    // for the leave half was settled at approval
                    // (LeaveController::applyHalfDayLeave), so the existing cl_debited
                    // is preserved on the upsert — never re-debited here.
                    $halfSession = self::approvedHalfDayLeave($date, (int)$developer->id);
                    if ($halfSession !== null) {
                        $leaveType      = 8;
                        $isPermission   = 1;
                        $morningLeave   = $halfSession === LeaveRequests::SESSION_MORNING ? 1 : 0;
                        $eveningLeave   = $halfSession === LeaveRequests::SESSION_EVENING ? 1 : 0;
                        $lateMorning    = 0;
                        $lateEvening    = 0;
                        $latelopMorning = 0.0;
                        $latelopEvening = 0.0;
                    }
                }
            } else {
                // Absent path
                $leave = LeaveRequests::find()
                    ->where(['status' => 1, 'request_status' => 2, 'developer_id' => $developer->id])
                    // NULL to_date = single-day leave (from_date only).
                    ->andWhere('from_date <= :d AND COALESCE(to_date, from_date) >= :d', [':d' => $date])
                    ->one();
                if ($leave !== null) {
                    if ($isScheduledHoliday === 1 || $todayDay === 'sun') {
                        $leaveType = 4;
                        $isHoliday = 1;
                    } else {
                        // Approved leave. CL is debited at the upsert below (where the
                        // existing row's cl_debited guards against re-debiting on
                        // reprocess): covered by CL ⇒ no LOP; CL exhausted ⇒ full LOP.
                        $leaveType     = 7;
                        $approvedLeave = true;
                    }
                } else {
                    if ($isScheduledHoliday === 1 || $todayDay === 'sun') {
                        // Sandwich rule: a holiday/Sunday is counted as Absent (full-day
                        // LOP) when the developer has an UNAPPROVED absence on the
                        // previous OR the next calendar day. An approved leave — or a
                        // punch — on the flanking day does NOT bridge, so the holiday
                        // stays a Holiday. Checked against source data (punches, holidays,
                        // approved leaves) so it is order-independent: the next day need
                        // not be processed yet, and reprocessing self-corrects.
                        $bridged = self::wasUnapprovedAbsence($previousDate, (int)$developer->id)
                            || self::wasUnapprovedAbsence($nextDate, (int)$developer->id);
                        if ($bridged) {
                            $leaveType = 3;
                            $isHoliday = 0;
                            $leaveLop  = 1.0;
                        } else {
                            $leaveType = 4;
                            $isHoliday = 1;
                        }
                    } else {
                        // Absent working day → full-day LOP.
                        $leaveType = 3;
                        $leaveLop  = 1.0;
                    }
                }
            }

            // Upsert dailyattendance row
            $row = DailyAttendance::find()
                ->where(['status' => 1, 'developer_id' => $developer->id, 'attendance_date' => $date])
                ->one();
            if ($row === null) {
                $row = new DailyAttendance();
                $row->status = 1;
            }

            // Comp-off: working a Sunday or scheduled holiday earns +1 CL backup,
            // credited once and guarded by the cl_credited flag so reprocessing the
            // same date never double-credits. Mirrors LopCalculator::creditHolidayWork
            // (the publish-time path) but applied here so the daily cron credits it.
            $alreadyCredited = (int)($row->cl_credited ?? 0) === 1;
            $creditHoliday   = ($isHoliday === 1 && $present === 1 && !$alreadyCredited);

            // Settle an approved full-day leave against CL backup. Debit 1 CL when a
            // balance exists (leave covered ⇒ no LOP); if already debited on this row,
            // preserve it without re-debiting; if no CL, the day is a full-day LOP.
            $debitLeaveCl = false;
            if ($approvedLeave) {
                if ((int)$row->cl_debited === 1) {
                    $clDebited = 1;
                    $leaveLop  = 0.0;
                } elseif ((float)$developer->cl_backup >= 1) {
                    $clDebited    = 1;
                    $leaveLop     = 0.0;
                    $debitLeaveCl = true;
                } else {
                    $leaveLop = 1.0;
                }
            }

            $totalLop = $leaveLop + $morningLeaveLop + $eveningLeaveLop + $latelopMorning + $latelopEvening;

            $row->developer_id      = (int)$developer->id;
            $row->attendance_date   = $date;
            $row->in_time           = $inTime;
            $row->out_time          = $outTime;
            $row->is_present        = $present;
            $row->morning_late      = $lateMorning;
            $row->evening_late      = $lateEvening;
            $row->leave_type        = $leaveType;
            $row->leave_lop         = (string)$leaveLop;
            $row->morning_late_lop  = (string)$latelopMorning;
            $row->evening_late_lop  = (string)$latelopEvening;
            $row->total_lop         = (string)($totalLop > 1 ? 1 : $totalLop);
            $row->is_holiday        = $isHoliday;
            // Keep a half-day's CL debit (settled at approval); the processor's own
            // value is always 0 here and would otherwise wipe it on reprocessing.
            $row->cl_debited        = $halfSession !== null ? (float)$row->cl_debited : $clDebited;
            $row->is_permission     = $isPermission;
            $row->morning_leave     = $morningLeave;
            $row->evening_leave     = $eveningLeave;
            $row->morning_leave_lop = (string)$morningLeaveLop;
            $row->evening_leave_lop = (string)$eveningLeaveLop;
            // Stamp the comp-off credit on the row; preserve a prior credit untouched.
            $row->cl_credited       = ($creditHoliday || $alreadyCredited) ? 1 : (int)($row->cl_credited ?? 0);
            if ($row->isNewRecord) {
                $row->created_date = date('Y-m-d H:i:s');
            }

            $saved = $row->save(false);
            if ($saved && $creditHoliday) {
                // Credit the earned CL only after the guard is durably persisted.
                $developer->cl_backup = (float)$developer->cl_backup + 1;
                $developer->save(false);
            }
            if ($saved && $debitLeaveCl) {
                // Debit the leave's CL only after cl_debited is durably persisted.
                $developer->cl_backup = max(0, (float)$developer->cl_backup - 1);
                $developer->save(false);
            }
            $reply .= $saved ? $date . ' Success<br>' : $date . ' Failed<br>';
        }

        return $reply;
    }

    /**
     * True when the developer had an UNAPPROVED absence on $date — used by the
     * sandwich rule. A day counts as an unapproved absence when it is a normal
     * working day (not Sunday, not a scheduled holiday) with NO punch and NO
     * approved leave covering it. A holiday/Sunday, a punched day, or an
     * approved-leave day all return false (they do not bridge a holiday).
     */
    private static function wasUnapprovedAbsence(string $date, int $devId): bool
    {
        // A day that has not finished yet (today or later) cannot be a confirmed
        // absence — its punches may still arrive. Treat it as "not absent" so an
        // incomplete flanking day never bridges a holiday/Sunday into Absent. The
        // sandwich is re-evaluated once that day has fully ended (see the cron in
        // SiteController::actionDailyattendancecall, which re-processes D-2).
        if ($date >= date('Y-m-d')) {
            return false;
        }
        // A Sunday or scheduled holiday is itself not an absence.
        if (strtolower(date('D', strtotime($date))) === 'sun') {
            return false;
        }
        if (Holiday::find()->where(['status' => 1, 'date' => $date])->exists()) {
            return false;
        }
        // Any punch that day → present → not absent.
        $punched = Attendance::find()
            ->where(['developer_id' => $devId])
            ->andWhere('DATE(in_time) = :d', [':d' => $date])
            ->exists();
        if ($punched) {
            return false;
        }
        // An APPROVED leave covering the date is an absence, but an approved one —
        // it does NOT bridge a holiday.
        $approvedLeave = LeaveRequests::find()
            ->where(['status' => 1, 'request_status' => LeaveRequests::REQ_STATUS_APPROVED, 'developer_id' => $devId])
            ->andWhere('from_date <= :d AND COALESCE(to_date, from_date) >= :d', [':d' => $date])
            ->exists();

        return !$approvedLeave;
    }

    /**
     * True when an APPROVED hourly permission covers this date. An hourly
     * permission excuses either side's lateness. The monthly 2-hour cap is
     * enforced at request creation (PermissionController), so an approved
     * request is sufficient here — no count is re-checked.
     *
     * @param string $side 'morning' | 'evening' (kept for call-site clarity)
     */
    private static function hasApprovedPermission(string $date, int $devId, string $side): bool
    {
        return LeaveRequests::find()
            ->where([
                'leave_type'         => LeaveRequests::TYPE_PERMISSION,
                'request_status'     => LeaveRequests::REQ_STATUS_APPROVED,
                'status'             => 1,
                'developer_id'       => $devId,
                'permission_session' => LeaveRequests::SESSION_NONE, // hourly only
            ])
            // A NULL to_date means a single-day permission (from_date only).
            ->andWhere('from_date <= :d AND COALESCE(to_date, from_date) >= :d', [':d' => $date])
            ->exists();
    }

    /**
     * Returns the session (SESSION_MORNING | SESSION_EVENING) of an APPROVED
     * half-day LEAVE covering this date, or null when there is none. Only an
     * approved half-day leave classifies the day as a half-day and excuses the
     * worked half's late check-in / early checkout.
     */
    private static function approvedHalfDayLeave(string $date, int $devId): ?int
    {
        $row = LeaveRequests::find()
            ->select('permission_session')
            ->where([
                'leave_type'         => LeaveRequests::TYPE_LEAVE,
                'request_status'     => LeaveRequests::REQ_STATUS_APPROVED,
                'status'             => 1,
                'developer_id'       => $devId,
                'permission_session' => [LeaveRequests::SESSION_MORNING, LeaveRequests::SESSION_EVENING],
            ])
            // A NULL to_date means a single-day leave (from_date only).
            ->andWhere('from_date <= :d AND COALESCE(to_date, from_date) >= :d', [':d' => $date])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row !== null ? (int)$row->permission_session : null;
    }
}
