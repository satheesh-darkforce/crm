<?php

namespace backend\controllers;

use common\components\UserAccess;
use backend\components\LopCalculator;
use backend\models\Attendance;
use backend\models\AttendanceVerification;
use backend\models\DailyAttendance;
use backend\models\Developer;
use backend\models\DeveloperAllowanceDeduction;
use backend\models\LeaveSettings;
use backend\models\Payslip;
use backend\models\SalaryAllowance;
use backend\models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * AttendanceController — daily attendance list, monthly grid, check-in/out,
 * leave settings and attendance verification (migrated from Yii1).
 */
class AttendanceController extends Controller
{
    /**
     * Developer IDs hidden from the Live Attendance board by request — they are
     * not tracked there. (5 = Senthilkumar, 35 = GOWRI PRIYA D.)
     */
    private const LIVE_BOARD_HIDDEN_DEV_IDS = [5, 35];

    // -----------------------------------------------------------------------
    // Daily attendance list (admin view)
    // -----------------------------------------------------------------------

    public function actionIndex(): string
    {
        // Yii1 parity: filters mirror protected/views/attendance/_search.php —
        // developer_id, date range, team, branch, in_ip, out_ip. Joins developer
        // for the team/branch filters and the listing's developer_name display.
        $query = Attendance::find()
            ->alias('a')
            ->joinWith('developer d')
            ->where(['a.status' => 1])
            ->orderBy(['a.id' => SORT_DESC]);

        $req = Yii::$app->request;
        if ($devId = $req->get('developer_id')) {
            $query->andWhere(['a.developer_id' => (int)$devId]);
        }
        if ($date = $req->get('date')) {
            $ts = strtotime($date);
            if ($ts !== false) { $query->andWhere(['DATE(a.in_time)' => date('Y-m-d', $ts)]); }
        }
        if ($fromDate = $req->get('from_date')) {
            $ts = strtotime($fromDate);
            if ($ts !== false) { $query->andWhere(['>=', 'DATE(a.in_time)', date('Y-m-d', $ts)]); }
        }
        if ($toDate = $req->get('to_date')) {
            $ts = strtotime($toDate);
            if ($ts !== false) { $query->andWhere(['<=', 'DATE(a.in_time)', date('Y-m-d', $ts)]); }
        }
        if (($team = $req->get('team', '')) !== '' && in_array((string)$team, ['1', '2'], true)) {
            $query->andWhere(['d.team' => (int)$team]);
        }
        if (($branch = $req->get('branch', '')) !== '' && in_array((string)$branch, ['1', '2'], true)) {
            $query->andWhere(['d.branch' => (int)$branch]);
        }
        if ($inIp = trim((string)$req->get('in_ip', ''))) {
            $query->andWhere(['like', 'a.in_ip', $inIp]);
        }
        if ($outIp = trim((string)$req->get('out_ip', ''))) {
            $query->andWhere(['like', 'a.out_ip', $outIp]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 25],
        ]);

        // Employee dropdown includes ex-employees (status=0), labelled "(Ex)".
        $devRows = Developer::find()
            ->where(['status' => [0, 1]])
            ->orderBy(['status' => SORT_DESC, 'developer_name' => SORT_ASC])
            ->all();
        $developerOptions = ['' => 'All Employees'];
        foreach ($devRows as $d) {
            $developerOptions[$d->id] = $d->developer_name . ((int) $d->status === 0 ? ' (Ex)' : '');
        }

        return $this->render('index', compact('dataProvider', 'developerOptions'));
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    // -----------------------------------------------------------------------
    // Live dashboard — real-time view of who is checked in, what project/task
    // they are working on right now, current task duration and break time.
    // -----------------------------------------------------------------------

    public function actionLive(): string
    {
        $today = date('Y-m-d');
        $now   = time();

        // Late / excuse visibility gate. Two-tier by design:
        //   * `attendance/live`          — can open the board, but sees only
        //                                  their OWN late & excuse figures.
        //   * `attendance/live-all-data` — additionally sees EVERY employee's
        //                                  late & excuse figures.
        // Admins and sub-admins always see everyone's. (`attendance/live-all-data`
        // implies `attendance/live` via UserAccess::$implicitChildren, so granting
        // "show all data" alone still unlocks the menu/page.)
        /** @var \backend\models\User $viewer */
        $viewer         = Yii::$app->user->identity;
        $canViewAllLate = $viewer->isAdmin() || $viewer->isSubAdmin()
            || UserAccess::checkSpecificMenuAccess('attendance/live-all-data');
        $currentDevId   = $viewer->getDeveloperId();

        // All active employees, excluding admin / sub-admin accounts (they are not
        // tracked on the live attendance board).
        $adminUserIds = User::find()
            ->select('user_id')
            ->where(['usertype' => [User::TYPE_ADMIN, User::TYPE_SUBADMIN]])
            ->column();
        $developers = Developer::find()
            ->where(['status' => 1])
            ->andFilterWhere(['not in', 'user_id', $adminUserIds])
            ->andWhere(['not in', 'id', self::LIVE_BOARD_HIDDEN_DEV_IDS])
            ->orderBy(['developer_name' => SORT_ASC])
            ->all();

        $devUserIds = array_values(array_filter(array_map(static fn($d) => (int) $d->user_id, $developers)));

        // Today's attendance keyed by developer_id.
        $attByDev = [];
        foreach (
            Attendance::find()
                ->where(['status' => 1])
                ->andWhere('DATE(in_time) = :d', [':d' => $today])
                ->all() as $a
        ) {
            // First check-in of the day wins as the reference record.
            if (!isset($attByDev[(int) $a->developer_id])) {
                $attByDev[(int) $a->developer_id] = $a;
            }
        }

        // Task logs for TODAY only. The DB carries many stale orphaned timers
        // (end_time NULL from past days), so we scope to today's start_time —
        // a running log started today is the genuine "currently working" task.
        $runningByUser = [];   // user_id => currently running TaskLogs (today)
        $workedByUser  = [];   // user_id => total seconds actually spent on tasks today
        $hasLogByUser  = [];   // user_id => true if any task was logged today
        $lastLogByUser = [];   // user_id => most recent TaskLogs (today) by start_time
        $lastStartByUser = []; // user_id => start_time ts of that most recent log
        $taskSecsByUser  = []; // user_id => [task_id => total seconds on that task today]
        if ($devUserIds) {
            $logs = \backend\models\TaskLogs::find()
                ->with(['task.project'])
                ->where(['status' => 1, 'user_id' => $devUserIds])
                ->andWhere('DATE(start_time) = :d', [':d' => $today])
                ->all();

            foreach ($logs as $log) {
                $uid     = (int) $log->user_id;
                $hasLogByUser[$uid] = true;
                $startTs = (int) strtotime((string) $log->start_time);
                // Track the latest-started task so we can show it during breaks.
                if (!isset($lastStartByUser[$uid]) || $startTs >= $lastStartByUser[$uid]) {
                    $lastStartByUser[$uid] = $startTs;
                    $lastLogByUser[$uid]   = $log;
                }
                $isRunning = $log->end_time === null || $log->end_time === ''
                    || strncmp((string) $log->end_time, '0000', 4) === 0;
                if ($isRunning) {
                    $runningByUser[$uid] = $log;
                    // Time so far on the in-progress task counts as worked.
                    $elapsed = max(0, $now - $startTs);
                } else {
                    // Finished task: accumulate its elapsed time as worked.
                    $endTs   = (int) strtotime((string) $log->end_time);
                    $elapsed = max(0, $endTs - $startTs);
                }
                $workedByUser[$uid] = ($workedByUser[$uid] ?? 0) + $elapsed;
                // Today's total time on each individual task (across its logs).
                $tid = (int) $log->task_id;
                $taskSecsByUser[$uid][$tid] = ($taskSecsByUser[$uid][$tid] ?? 0) + $elapsed;
            }
        }

        // Late-session and excuse counts per developer. Both are counted per
        // session, so a day affected on BOTH the morning and evening side counts
        // as 2. An "excuse" is a late the admin marked "Present — excuse late"
        // (morning_lop_exempt / evening_lop_exempt). The month figure is scoped
        // to the current calendar month; the total is all-time. Only active rows
        // (status=1) are considered.
        $monthStart       = date('Y-m-01');
        $lateMonthByDev   = [];
        $lateTotalByDev   = [];
        $excuseMonthByDev = [];
        $excuseTotalByDev = [];
        foreach (
            DailyAttendance::find()
                ->select([
                    'developer_id',
                    // Office-work sessions are never late: exclude them from the
                    // late counts even if their late bit is set.
                    'total_late'   => 'COALESCE(SUM(CASE WHEN morning_office_work = 1 THEN 0 ELSE morning_late END),0)'
                                    . ' + COALESCE(SUM(CASE WHEN evening_office_work = 1 THEN 0 ELSE evening_late END),0)',
                    'month_late'   => 'COALESCE(SUM(CASE WHEN attendance_date >= :ms AND morning_office_work = 0 THEN morning_late ELSE 0 END),0)'
                                    . ' + COALESCE(SUM(CASE WHEN attendance_date >= :ms AND evening_office_work = 0 THEN evening_late ELSE 0 END),0)',
                    'total_excuse' => 'COALESCE(SUM(morning_lop_exempt),0) + COALESCE(SUM(evening_lop_exempt),0)',
                    'month_excuse' => 'COALESCE(SUM(CASE WHEN attendance_date >= :ms THEN morning_lop_exempt ELSE 0 END),0)'
                                    . ' + COALESCE(SUM(CASE WHEN attendance_date >= :ms THEN evening_lop_exempt ELSE 0 END),0)',
                ])
                ->where(['status' => 1])
                ->params([':ms' => $monthStart])
                ->groupBy('developer_id')
                ->asArray()
                ->all() as $lr
        ) {
            $did = (int) $lr['developer_id'];
            $lateTotalByDev[$did]   = (int) $lr['total_late'];
            $lateMonthByDev[$did]   = (int) $lr['month_late'];
            $excuseTotalByDev[$did] = (int) $lr['total_excuse'];
            $excuseMonthByDev[$did] = (int) $lr['month_excuse'];
        }

        // Build one row per employee.
        $rows = [];
        foreach ($developers as $dev) {
            $uid     = (int) $dev->user_id;
            $att     = $attByDev[(int) $dev->id] ?? null;
            $present = $att && $att->in_time;
            $running = $runningByUser[$uid] ?? null;
            $hasLog  = $hasLogByUser[$uid] ?? false;
            $worked  = $workedByUser[$uid] ?? 0;

            $checkIn  = $present ? $att->in_time : null;
            // Treat NULL / empty / legacy zero-dates as "not checked out yet".
            $checkOut = null;
            if ($present && $att->out_time && strncmp((string) $att->out_time, '0000', 4) !== 0) {
                $checkOut = $att->out_time;
            }

            if (!$present) {
                $status = 'absent';
            } elseif ($checkOut) {
                $status = 'checked_out';
            } elseif ($running) {
                $status = 'working';
            } elseif ($hasLog) {
                $status = 'break';
            } else {
                $status = 'no_task';
            }

            // Project / task columns. When actively working, show the running
            // task and its live duration. When on break (a task was logged
            // earlier but none is running now), still show the LAST task so the
            // row keeps context — only the live duration is dropped. Truly
            // "no task added" rows fall through to a dash in the view.
            $projectId = null;
            $project   = null;
            $task      = null;
            $taskSecs  = null;
            $refLog    = $running ?: ($lastLogByUser[$uid] ?? null);
            if ($refLog && $refLog->task) {
                $task      = $refLog->task->task;
                $project   = $refLog->task->project->title ?? null;
                $projectId = (int) $refLog->task->project_id;
                if ($running) {
                    // Currently working: show the live elapsed time on this task.
                    $taskSecs = max(0, $now - (int) strtotime((string) $running->start_time));
                } else {
                    // On break: show today's TOTAL time on the last worked task
                    // (summed across all of its logs), so the column keeps a
                    // meaningful figure even when no timer is running.
                    $taskSecs = $taskSecsByUser[$uid][(int) $refLog->task_id] ?? null;
                }
            }

            // Break duration = the day's TOTAL non-working time within the
            // present window: from check-in to check-out (or now if still on
            // site), minus the time actually spent on tasks. This rolls up
            // every period that counts as a break —
            //   • the wait before the first task was started
            //   • every pause / gap between tasks
            //   • "no task added" periods
            //   • the tail after the last task until check-out / now
            // Only the currently-running task's elapsed time is excluded (it is
            // working time), so an employee actively on a task still accrues
            // break for all the idle stretches earlier in the day.
            $breakSecs = null;
            if ($present) {
                $endRef    = $checkOut ? (int) strtotime($checkOut) : $now;
                $breakSecs = max(0, ($endRef - (int) strtotime($checkIn)) - $worked);
            }

            $rows[] = [
                'developerId' => (int) $dev->id,
                'name'        => $dev->developer_name,
                'designation' => $dev->designation,
                'checkIn'     => $checkIn,
                'checkOut'    => $checkOut,
                'project'     => $project,
                'projectId'   => $projectId,
                'task'        => $task,
                'taskSecs'    => $taskSecs,
                'breakSecs'   => $breakSecs,
                'status'      => $status,
                'lateMonth'   => $lateMonthByDev[(int) $dev->id] ?? 0,
                'lateTotal'   => $lateTotalByDev[(int) $dev->id] ?? 0,
                'excuseMonth' => $excuseMonthByDev[(int) $dev->id] ?? 0,
                'excuseTotal' => $excuseTotalByDev[(int) $dev->id] ?? 0,
            ];
        }

        // Headline counts — over ALL active employees, independent of filters.
        $totalEmp   = count($rows);
        $presentEmp = count(array_filter($rows, static fn($r) => $r['status'] !== 'absent'));
        $absentEmp  = $totalEmp - $presentEmp;
        $workingEmp = count(array_filter($rows, static fn($r) => $r['status'] === 'working'));
        // "No task added after check-in" is idle time too — count it as break.
        $breakEmp   = count(array_filter($rows, static fn($r) => in_array($r['status'], ['break', 'no_task'], true)));

        // Display filters (do not affect the headline counts).
        $nameFilter    = trim((string) Yii::$app->request->get('q', ''));
        $projectFilter = (int) Yii::$app->request->get('project_id', 0);
        if ($nameFilter !== '') {
            $rows = array_filter($rows, static fn($r) => stripos($r['name'], $nameFilter) !== false);
        }
        if ($projectFilter) {
            $rows = array_filter($rows, static fn($r) => $r['projectId'] === $projectFilter);
        }

        // Sort: present first (earliest check-in on top), absentees last by name.
        usort($rows, static function ($a, $b) {
            $aAbsent = $a['status'] === 'absent' ? 1 : 0;
            $bAbsent = $b['status'] === 'absent' ? 1 : 0;
            if ($aAbsent !== $bAbsent) {
                return $aAbsent <=> $bAbsent;
            }
            if ($aAbsent) {
                return strcasecmp($a['name'], $b['name']);
            }
            return strcmp((string) $a['checkIn'], (string) $b['checkIn']);
        });

        // Projects for the filter dropdown.
        $projects = \backend\models\Project::find()
            ->where(['status' => 1])
            ->orderBy(['title' => SORT_ASC])
            ->all();

        return $this->render('live', [
            'rows'          => $rows,
            'projects'      => $projects,
            'totalEmp'      => $totalEmp,
            'presentEmp'    => $presentEmp,
            'absentEmp'     => $absentEmp,
            'workingEmp'    => $workingEmp,
            'breakEmp'      => $breakEmp,
            'nameFilter'    => $nameFilter,
            'projectFilter' => $projectFilter,
            'canViewAllLate' => $canViewAllLate,
            'currentDevId'   => $currentDevId,
        ]);
    }

    // -----------------------------------------------------------------------
    // Monthly attendance grid (employee × date matrix)
    // -----------------------------------------------------------------------

    public function actionMonthlyAttendance(): string
    {
        /** @var \backend\models\User $user */
        $user    = Yii::$app->user->identity;
        $isAdmin = $user->isAdmin() || $user->isSubAdmin();
        // Yii1 parity: a developer granted the explicit `attendance/monthly-attendance`
        // permission via developer.access_permissions can view all employees, just
        // like an admin (Yii1 AttendanceController::actionMonthlyattendance line 201).
        $canViewAll = $isAdmin || UserAccess::checkSpecificMenuAccess('attendance/monthly-attendance');

        // Filter by Year + Month (defaults to current month). The full month
        // range is derived from the selected year/month — no from/to date pickers.
        $year  = (int)Yii::$app->request->get('year', date('Y'));
        $month = (int)Yii::$app->request->get('month', date('n'));
        if ($month < 1 || $month > 12) { $month = (int)date('n'); }
        if ($year < 2010 || $year > (int)date('Y')) { $year = (int)date('Y'); }

        $fromDate = sprintf('%04d-%02d-01', $year, $month);
        $toDate   = date('Y-m-t', strtotime($fromDate));

        // Collect dates for the column headers
        $dates = [];
        $cur   = $fromDate;
        while (strtotime($cur) <= strtotime($toDate)) {
            $dates[] = $cur;
            $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
        }

        // Build query for developers who have attendance records in range
        // Matches Yii1: INNER JOIN attendance (check-in/out table, not dailyattendance)
        $query = Developer::find()
            ->alias('d')
            ->innerJoin('attendance att', 'd.id = att.developer_id')
            ->andWhere(['>=', 'DATE(att.in_time)', $fromDate])
            ->andWhere(['<=', 'DATE(att.in_time)', $toDate])
            ->orderBy(['d.developer_name' => SORT_ASC])
            ->distinct();

        if ($canViewAll) {
            // Admin or developer with explicit attendance/monthly-attendance permission:
            // optional developer / ex-employee / team / branch filters from request.
            if ($devId = Yii::$app->request->get('developer_id')) {
                $query->andWhere(['d.id' => (int)$devId]);
            }
            if ($devName = Yii::$app->request->get('developer_name')) {
                $query->andWhere(['like', 'd.developer_name', $devName]);
            }
            // Yii1 parity: separate ex-employee filter for inactive developers (status=0).
            if ($exId = Yii::$app->request->get('ex_employee')) {
                $query->andWhere(['d.id' => (int)$exId]);
            }
            if ($team = Yii::$app->request->get('team')) {
                $query->andWhere(['d.team' => (int)$team]);
            }
            if ($branch = Yii::$app->request->get('branch')) {
                $query->andWhere(['d.branch' => (int)$branch]);
            }
        } else {
            // Developer without elevated permission: restrict to own records only.
            $selfDev = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if ($selfDev) {
                $query->andWhere(['d.id' => $selfDev->id]);
            }
        }

        $developers = $query->all();

        // Batch-load daily attendance for all developers in range to avoid N+1
        $devIds   = array_map(fn($d) => $d->id, $developers);
        $dailyMap = []; // [developer_id][date] => DailyAttendance
        if (!empty($devIds)) {
            $records = DailyAttendance::find()
                ->where(['status' => 1, 'developer_id' => $devIds])
                ->andWhere(['>=', 'DATE(attendance_date)', $fromDate])
                ->andWhere(['<=', 'DATE(attendance_date)', $toDate])
                ->all();
            foreach ($records as $r) {
                $d = date('Y-m-d', strtotime($r->attendance_date));
                $dailyMap[$r->developer_id][$d] = $r;
            }
        }

        // Per-employee LOP summary (requirement #4/#5) for display in the grid.
        $lopSummary = [];
        foreach ($devIds as $did) {
            $lopSummary[$did] = LopCalculator::summary($did, $fromDate, $toDate);
        }

        // Employee dropdown options — active employees plus ex-employees (status=0)
        // merged into a single list, with ex-employees labelled "(Ex)".
        $employeeOptions = ['' => 'All Employees'];
        if ($canViewAll) {
            $empRows = Developer::find()
                ->where(['status' => [0, 1]])
                ->orderBy(['status' => SORT_DESC, 'developer_name' => SORT_ASC])
                ->all();
            foreach ($empRows as $d) {
                $employeeOptions[$d->id] = $d->developer_name . ((int) $d->status === 0 ? ' (Ex)' : '');
            }
        }

        // ── Own late / excuse summary (employees only — never shown to admin) ─────
        // Current calendar-month + all-time late and excuse (admin-marked LOP-exempt)
        // counts for the logged-in employee, counted per session with office-work
        // sessions excluded — same logic as the Live Attendance board. Only the
        // developer's own figures are computed here.
        $lateExcuseSummary = null;
        if (!$isAdmin) {
            $summaryDev = isset($selfDev) && $selfDev
                ? $selfDev
                : Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if ($summaryDev) {
                $summaryMonthStart = date('Y-m-01');
                $leRow = DailyAttendance::find()
                    ->select([
                        'total_late'   => 'COALESCE(SUM(CASE WHEN morning_office_work = 1 THEN 0 ELSE morning_late END),0)'
                                        . ' + COALESCE(SUM(CASE WHEN evening_office_work = 1 THEN 0 ELSE evening_late END),0)',
                        'month_late'   => 'COALESCE(SUM(CASE WHEN attendance_date >= :ms AND morning_office_work = 0 THEN morning_late ELSE 0 END),0)'
                                        . ' + COALESCE(SUM(CASE WHEN attendance_date >= :ms AND evening_office_work = 0 THEN evening_late ELSE 0 END),0)',
                        'total_excuse' => 'COALESCE(SUM(morning_lop_exempt),0) + COALESCE(SUM(evening_lop_exempt),0)',
                        'month_excuse' => 'COALESCE(SUM(CASE WHEN attendance_date >= :ms THEN morning_lop_exempt ELSE 0 END),0)'
                                        . ' + COALESCE(SUM(CASE WHEN attendance_date >= :ms THEN evening_lop_exempt ELSE 0 END),0)',
                    ])
                    ->where(['status' => 1, 'developer_id' => $summaryDev->id])
                    ->params([':ms' => $summaryMonthStart])
                    ->asArray()
                    ->one();
                $lateExcuseSummary = [
                    'lateMonth'   => (int)($leRow['month_late']   ?? 0),
                    'lateTotal'   => (int)($leRow['total_late']   ?? 0),
                    'excuseMonth' => (int)($leRow['month_excuse'] ?? 0),
                    'excuseTotal' => (int)($leRow['total_excuse'] ?? 0),
                    'monthLabel'  => date('M Y'),
                ];
            }
        }

        // ── Payroll section (non-admin only — matches Yii1 monthlyattendance.php) ─────────
        // Always shows the *previous* calendar month, independent of the filter dates.
        $payroll = null;
        if (!$isAdmin) {
            $payrollMonthStart = date('Y-m-01', strtotime('first day of previous month'));
            $payrollMonthEnd   = date('Y-m-t',  strtotime($payrollMonthStart));

            $selfDev = isset($selfDev) ? $selfDev
                : Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);

            if ($selfDev) {
                // Did the developer attend at least once in the payroll month?
                $hasPresence = (int) DailyAttendance::find()
                    ->where(['status' => 1, 'developer_id' => $selfDev->id, 'is_present' => 1])
                    ->andWhere(['between', 'attendance_date', $payrollMonthStart, $payrollMonthEnd])
                    ->count();

                if ($hasPresence > 0) {
                    // Load all daily-attendance rows for that month for tally
                    $payrollDaily = DailyAttendance::find()
                        ->where(['status' => 1, 'developer_id' => $selfDev->id])
                        ->andWhere(['between', 'attendance_date', $payrollMonthStart, $payrollMonthEnd])
                        ->all();

                    $payrollAllowances = SalaryAllowance::find()->where(['type' => 1, 'status' => 1])->all();
                    $payrollDetections = SalaryAllowance::find()->where(['type' => 2, 'status' => 1])->all();

                    $empAllowances = DeveloperAllowanceDeduction::find()
                        ->alias('t')
                        ->innerJoin('salary_allowance_deduction sad', 'sad.id = t.allowance_deduction_id')
                        ->where([
                            't.status' => 1, 't.developer_id' => $selfDev->id,
                            'sad.status' => 1, 'sad.type' => 1,
                        ])
                        ->all();

                    $empDetections = DeveloperAllowanceDeduction::find()
                        ->alias('t')
                        ->innerJoin('salary_allowance_deduction sad', 'sad.id = t.allowance_deduction_id')
                        ->where([
                            't.status' => 1, 't.developer_id' => $selfDev->id,
                            'sad.status' => 1, 'sad.type' => 2,
                        ])
                        ->all();

                    $payroll = [
                        'monthStart'    => $payrollMonthStart,
                        'monthEnd'      => $payrollMonthEnd,
                        'developer'     => $selfDev,
                        'daily'         => $payrollDaily,
                        'allowances'    => $payrollAllowances,
                        'detections'    => $payrollDetections,
                        'empAllowances' => $empAllowances,
                        'empDetections' => $empDetections,
                    ];
                }
            }
        }

        // Payslip lock map (Yii1 parity: Utility::checkPayslipGeneratedStatus).
        // Build [Y-m => bool] for every month touched by the date range so the
        // view can suppress click handlers (and we can refuse POSTs) for
        // months where payroll has already been finalized.
        $lockedMonths = [];
        foreach ($dates as $d) {
            $ym = date('Y-m', strtotime($d));
            if (!array_key_exists($ym, $lockedMonths)) {
                $lockedMonths[$ym] = Payslip::isGeneratedForMonth($d);
            }
        }

        return $this->render('monthly-attendance', compact(
            'developers', 'employeeOptions', 'dates', 'fromDate', 'toDate',
            'year', 'month',
            'dailyMap', 'isAdmin', 'canViewAll', 'payroll', 'lockedMonths', 'lopSummary',
            'lateExcuseSummary'
        ));
    }

    // -----------------------------------------------------------------------
    // Monthly attendance — summary export (XLSX)
    // -----------------------------------------------------------------------
    // Exports only the per-employee totals shown on the right side of the
    // monthly grid (no daily detail). Filters mirror actionMonthlyAttendance.
    public function actionMonthlyAttendanceExport()
    {
        /** @var \backend\models\User $user */
        $user       = Yii::$app->user->identity;
        $isAdmin    = $user->isAdmin() || $user->isSubAdmin();
        $canViewAll = $isAdmin || UserAccess::checkSpecificMenuAccess('attendance/monthly-attendance');

        // Year + Month filter (same semantics as the listing screen).
        $year  = (int)Yii::$app->request->get('year', date('Y'));
        $month = (int)Yii::$app->request->get('month', date('n'));
        if ($month < 1 || $month > 12) { $month = (int)date('n'); }
        if ($year < 2010 || $year > (int)date('Y')) { $year = (int)date('Y'); }
        $fromDate = sprintf('%04d-%02d-01', $year, $month);
        $toDate   = date('Y-m-t', strtotime($fromDate));

        $query = Developer::find()
            ->alias('d')
            ->innerJoin('attendance att', 'd.id = att.developer_id')
            ->andWhere(['>=', 'DATE(att.in_time)', $fromDate])
            ->andWhere(['<=', 'DATE(att.in_time)', $toDate])
            ->orderBy(['d.developer_name' => SORT_ASC])
            ->distinct();

        if ($canViewAll) {
            if ($devId = Yii::$app->request->get('developer_id')) {
                $query->andWhere(['d.id' => (int)$devId]);
            }
            if ($devName = Yii::$app->request->get('developer_name')) {
                $query->andWhere(['like', 'd.developer_name', $devName]);
            }
            if ($exId = Yii::$app->request->get('ex_employee')) {
                $query->andWhere(['d.id' => (int)$exId]);
            }
            if ($team = Yii::$app->request->get('team')) {
                $query->andWhere(['d.team' => (int)$team]);
            }
            if ($branch = Yii::$app->request->get('branch')) {
                $query->andWhere(['d.branch' => (int)$branch]);
            }
        } else {
            $selfDev = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if ($selfDev) {
                $query->andWhere(['d.id' => $selfDev->id]);
            }
        }

        $developers = $query->all();

        $devIds   = array_map(fn($d) => $d->id, $developers);
        $dailyMap = [];
        if (!empty($devIds)) {
            $records = DailyAttendance::find()
                ->where(['status' => 1, 'developer_id' => $devIds])
                ->andWhere(['>=', 'DATE(attendance_date)', $fromDate])
                ->andWhere(['<=', 'DATE(attendance_date)', $toDate])
                ->all();
            foreach ($records as $r) {
                $dailyMap[$r->developer_id][] = $r;
            }
        }

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monthly Summary');

        $title = 'Monthly Attendance Summary — '
            . date('d M Y', strtotime($fromDate)) . ' to ' . date('d M Y', strtotime($toDate));
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $headers = [
            '#', 'Employee Name', 'Total Leave', 'Total Abs', 'Total Late',
            'Late LOP', 'Leave/Abs LOP', 'Total LOP', 'CL Applied', 'CL Backup',
        ];
        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle('A3:J3')->getFont()->setBold(true);
        $sheet->getStyle('A3:J3')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E9ECEF');
        $sheet->getStyle('A3:J3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(3)->setRowHeight(20);

        $row = 4;
        foreach ($developers as $i => $dev) {
            $rows = $dailyMap[$dev->id] ?? [];

            $totalLeave = 0.0;
            $totalAbs   = 0.0;
            $totalLate  = 0;
            $clApplied  = 0.0;

            foreach ($rows as $da) {
                if ($da->is_holiday) {
                    continue;
                }
                $lt        = (int)$da->leave_type;
                $ml        = (int)$da->morning_leave;
                $el        = (int)$da->evening_leave;
                // Office-work sessions are never late.
                $mLate     = ((int)$da->morning_late === 1 && (int)$da->morning_office_work !== 1) ? 1 : 0;
                $eLate     = ((int)$da->evening_late === 1 && (int)$da->evening_office_work !== 1) ? 1 : 0;
                $isPresent = (int)$da->is_present;
                $totalLop  = (float)$da->total_lop;
                $clDeb     = (float)$da->cl_debited;

                if ($lt === 7 && $totalLop == 1 && $isPresent === 0) { $totalLeave += 1; }
                if ($lt === 8 && $ml === 1 && $isPresent === 1)      { $totalLeave += 0.5; }
                if ($lt === 8 && $el === 1 && $isPresent === 1)      { $totalLeave += 0.5; }

                if ($lt === 7 && $totalLop == 1 && $isPresent === 0 && $clDeb == 0) { $totalAbs += 1; }
                if ($lt === 8 && $ml === 1 && $isPresent === 1)                     { $totalAbs += 0.5; }
                if ($lt === 8 && $el === 1 && $isPresent === 1)                     { $totalAbs += 0.5; }
                if ($lt === 3)                                                       { $totalAbs += 1; }

                if ($mLate === 1 || $eLate === 1) {
                    $totalLate += $mLate + $eLate;
                }

                if ($clDeb > 0) {
                    if ($lt === 7 && $clDeb == 1   && $isPresent === 0)                       { $clApplied += 1; }
                    if ($lt === 8 && $ml === 1     && $isPresent === 1 && $clDeb == 0.5)      { $clApplied += 0.5; }
                    if ($lt === 8 && $el === 1     && $isPresent === 1 && $clDeb == 0.5)      { $clApplied += 0.5; }
                }
            }

            $lateLop     = $totalLate * 0.5;
            $leaveAbsLop = $totalAbs;
            $totalLopAll = $totalAbs + $lateLop;
            $clBackup    = (float)$dev->cl_backup;

            $sheet->fromArray([
                $i + 1,
                $dev->developer_name,
                $totalLeave,
                $totalAbs,
                $totalLate,
                $lateLop,
                $leaveAbsLop,
                $totalLopAll,
                $clApplied,
                $clBackup,
            ], null, 'A' . $row);

            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        if (empty($developers)) {
            $sheet->setCellValue('A4', 'No attendance records found for the selected range.');
            $sheet->mergeCells('A4:J4');
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A4')->getFont()->setItalic(true);
        }

        $lastRow = max($row - 1, 3);
        $sheet->getStyle('A3:J' . $lastRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(32);
        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }

        $sheet->freezePane('A4');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        $filename = 'Monthly_Attendance_Summary_'
            . date('Y-m-d', strtotime($fromDate)) . '_to_'
            . date('Y-m-d', strtotime($toDate)) . '.xlsx';

        return Yii::$app->response->sendContentAsFile(
            $content,
            $filename,
            ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    // -----------------------------------------------------------------------
    // Leave Settings (admin only)
    // -----------------------------------------------------------------------

    public function actionLeaveSettings()
    {
        $model = LeaveSettings::findOne(1);
        if (!$model) {
            // Create a default record with id=1 if it doesn't exist yet
            $model = new LeaveSettings();
            $model->id                  = 1;
            $model->no_of_permissions   = 2;
            $model->duration            = '01:00:00';
            $model->check_in_grace_time = 10;
            $model->monthly_allowed_cl  = 1;
            $model->status              = 1;
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post('LeaveSettings', []);
            $model->load(['LeaveSettings' => $post]);
            $model->created_by   = Yii::$app->user->id;
            $model->created_date = date('Y-m-d H:i:s');
            $model->status       = 1;

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Leave settings updated successfully.');
                return $this->redirect(['leave-settings']);
            }
        }

        return $this->render('leave-settings', ['model' => $model]);
    }

    // -----------------------------------------------------------------------
    // My Attendance (developer view of own records)
    // -----------------------------------------------------------------------

    public function actionMyAttendance(): string
    {
        $user  = Yii::$app->user->identity;
        $devId = $user->getDeveloperId();

        $query = Attendance::find()
            ->where(['status' => 1])
            ->orderBy(['in_time' => SORT_DESC]);

        if ($devId) {
            $query->andWhere(['developer_id' => $devId]);
        }

        if ($fromDate = Yii::$app->request->get('from_date')) {
            $query->andWhere(['>=', 'DATE(in_time)', date('Y-m-d', strtotime($fromDate))]);
        }
        if ($toDate = Yii::$app->request->get('to_date')) {
            $query->andWhere(['<=', 'DATE(in_time)', date('Y-m-d', strtotime($toDate))]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 30],
        ]);

        // Current check-in status for today
        $todayRecord    = null;
        $checkedInToday = false;
        if ($devId) {
            $todayRecord = Attendance::find()
                ->where(['status' => 1, 'developer_id' => $devId])
                ->andWhere('DATE(in_time) = :today', [':today' => date('Y-m-d')])
                ->orderBy(['id' => SORT_DESC])
                ->one();
            if ($todayRecord) {
                $checkedInToday = true;
            }
        }

        return $this->render('my-attendance', compact('dataProvider', 'checkedInToday', 'todayRecord', 'devId'));
    }

    // -----------------------------------------------------------------------
    // Attendance Verification list (admin)
    // -----------------------------------------------------------------------

    public function actionAttendanceVerification(): string
    {
        $query = AttendanceVerification::find()
            ->with('developer')
            ->where(['status' => 1])
            ->orderBy(['id' => SORT_DESC]);

        if ($devId = Yii::$app->request->get('developer_id')) {
            $query->andWhere(['developer_id' => (int)$devId]);
        }
        if ($month = Yii::$app->request->get('month')) {
            $query->andWhere(['like', 'attendance_date', $month]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 30],
        ]);

        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('attendance-verification', compact('dataProvider', 'developers'));
    }

    // -----------------------------------------------------------------------
    // Publish Attendance (admin: generate AttendanceVerification rows)
    // -----------------------------------------------------------------------

    public function actionPublishAttendance()
    {
        if (Yii::$app->request->isPost) {
            $monthYear = Yii::$app->request->post('month_year');
            if ($monthYear) {
                $fromDate = date('Y-m-01', strtotime($monthYear));
                $toDate   = date('Y-m-t', strtotime($monthYear));
                $ymKey    = date('Y-m', strtotime($monthYear));

                // Check if already published
                $existing = AttendanceVerification::find()
                    ->where(['status' => 1])
                    ->andWhere(['like', 'attendance_date', $ymKey])
                    ->one();

                if ($existing) {
                    Yii::$app->session->setFlash('error', 'Attendance already published for this month.');
                } else {
                    $developers = Developer::find()->where(['status' => 1])->all();
                    $failed     = 0;
                    $published  = 0;

                    foreach ($developers as $developer) {
                        $hasDailyRecords = DailyAttendance::find()
                            ->where(['status' => 1, 'developer_id' => $developer->id])
                            ->andWhere(['>=', 'DATE(attendance_date)', $fromDate])
                            ->andWhere(['<=', 'DATE(attendance_date)', $toDate])
                            ->exists();

                        if (!$hasDailyRecords) {
                            continue;
                        }

                        // #3 Credit CL backup for Sunday/holiday work, then refresh the
                        // in-memory developer so the credited CL offsets absences below.
                        LopCalculator::creditHolidayWork($developer->id, $fromDate, $toDate);
                        $developer->refresh();
                        // #4 Auto-flag early check-out (before 18:30) before counting late LOP.
                        LopCalculator::markEarlyOut($developer->id, $fromDate, $toDate);

                        // Calculate totals from dailyattendance
                        $db = Yii::$app->db;

                        $totalLate = (int)$db->createCommand(
                            'SELECT COUNT(*) FROM dailyattendance WHERE status=1 AND developer_id=:dev
                             AND DATE(attendance_date)>=:from AND DATE(attendance_date)<=:to
                             AND ((morning_late=1 AND morning_office_work=0)
                                  OR (evening_late=1 AND evening_office_work=0))',
                            [':dev' => $developer->id, ':from' => $fromDate, ':to' => $toDate]
                        )->queryScalar();

                        $totalLeave = (int)$db->createCommand(
                            'SELECT COUNT(*) FROM dailyattendance WHERE status=1 AND developer_id=:dev
                             AND DATE(attendance_date)>=:from AND DATE(attendance_date)<=:to
                             AND leave_type IN (7,8)',
                            [':dev' => $developer->id, ':from' => $fromDate, ':to' => $toDate]
                        )->queryScalar();

                        $clTaken = (int)$db->createCommand(
                            'SELECT COUNT(*) FROM dailyattendance WHERE status=1 AND developer_id=:dev
                             AND DATE(attendance_date)>=:from AND DATE(attendance_date)<=:to
                             AND leave_type=7 AND cl_debited=1',
                            [':dev' => $developer->id, ':from' => $fromDate, ':to' => $toDate]
                        )->queryScalar();

                        $totalAbsent = (int)$db->createCommand(
                            'SELECT COUNT(*) FROM dailyattendance WHERE status=1 AND developer_id=:dev
                             AND DATE(attendance_date)>=:from AND DATE(attendance_date)<=:to
                             AND is_present=0 AND is_holiday=0',
                            [':dev' => $developer->id, ':from' => $fromDate, ':to' => $toDate]
                        )->queryScalar();

                        // #4 Late LOP: first 2 late-in/early-out excused, then every 2 = 0.5 day
                        // (admin-overridden Present days are skipped). #5 Sandwich-leave LOP.
                        $lateLopSum  = LopCalculator::lateLop($developer->id, $fromDate, $toDate);
                        $sandwichLop = LopCalculator::sandwichLop($developer->id, $fromDate, $toDate);

                        $absnetAfterCl = max(0, $totalAbsent - $developer->cl_backup);
                        $totalLop      = round($absnetAfterCl + $lateLopSum + $sandwichLop, 2);

                        $record                  = new AttendanceVerification();
                        $record->developer_id    = $developer->id;
                        $record->attendance_date = $fromDate;
                        $record->total_late      = $totalLate;
                        $record->late_lop        = (string)$lateLopSum;
                        $record->total_leave     = $totalLeave;
                        $record->cl_taken        = $clTaken;
                        $record->cl_backup       = (int)$developer->cl_backup;
                        $record->total_absent    = $totalAbsent;
                        $record->leave_lop       = (string)$absnetAfterCl;
                        $record->total_lop       = $totalLop;
                        $record->verified        = 0;
                        $record->created_date    = date('Y-m-d H:i:s');
                        $record->status          = 1;

                        if (!$record->save()) {
                            $failed++;
                        } else {
                            $published++;
                        }
                    }

                    if ($published === 0 && $failed === 0) {
                        Yii::$app->session->setFlash('warning', 'No attendance data found for this month. Nothing was published.');
                    } elseif ($failed === 0) {
                        Yii::$app->session->setFlash('success', "Attendance published successfully for {$published} employee(s).");
                    } else {
                        Yii::$app->session->setFlash('error', "Failed to publish attendance for {$failed} employee(s). Published: {$published}.");
                    }
                }
            }
            return $this->redirect(['attendance-verification']);
        }

        return $this->render('publish-attendance');
    }

    // -----------------------------------------------------------------------
    // Change Attendance (admin edits a single day's record)
    // -----------------------------------------------------------------------

    public function actionChangeAttendance(int $id)
    {
        $record = DailyAttendance::findOne(['id' => $id, 'status' => 1]);
        if (!$record) {
            throw new NotFoundHttpException('Daily attendance record not found.');
        }

        // Preserve the Monthly Attendance filters so the page returns to the same
        // view after an edit. Carried in via the cell link (GET) and re-posted as
        // hidden fields by the form, so read from POST first then GET.
        $filterKeys = ['developer_id', 'team', 'branch', 'ex_employee', 'year', 'month'];
        $filters    = [];
        foreach ($filterKeys as $k) {
            $v = Yii::$app->request->post($k, Yii::$app->request->get($k));
            if ($v !== null && $v !== '') {
                $filters[$k] = $v;
            }
        }
        $backUrl = array_merge(['monthly-attendance'], $filters);

        // Yii1 parity: refuse edits for months whose payslip has been generated.
        if (Payslip::isGeneratedForMonth($record->attendance_date)) {
            Yii::$app->session->setFlash('error',
                'Payslip already generated for this month — attendance cannot be modified.');
            return $this->redirect($backUrl);
        }

        if (Yii::$app->request->isPost) {
            $attendanceType   = (int)Yii::$app->request->post('attendance', 0);
            $isHoliday        = 0;
            $isPermission     = 0;
            $present          = 0;
            $lateMorning      = 0;
            $lateEvening      = 0;
            $leaveMorning     = 0;
            $leaveEvening     = 0;
            $leaveLop         = 0;
            $lateLopMorning   = 0;
            $lateLopEvening   = 0;
            $leaveLopMorning  = 0;
            $leaveLopEvening  = 0;
            // Per-session admin excusal of a late (requirement.txt #4): "Present —
            // excuse late" records the late but stops it counting toward LOP.
            $morningExempt    = 0;
            $eveningExempt    = 0;
            // Per-session "Office Work": the session was official work outside, so
            // it is NOT late at all (morning_late stays 0) and is never counted as
            // late anywhere. The flag records the reason for round-trip + display.
            $morningOffice    = 0;
            $eveningOffice    = 0;

            switch ($attendanceType) {
                case 1: // Present
                case 2: // Present (half day)
                    $present = 1;
                    break;
                case 3: // Absent LOP
                    $leaveLop = 1;
                    break;
                case 4: // Holiday
                    $isHoliday = 1;
                    break;
                case 5: // Late
                    $present = 1;
                    // 4-way per session: 'late' = late & counted, 'excuse' = late but
                    // admin marks present (not counted), 'office' = office work (not
                    // late at all), 'ontime' = not late at all.
                    $m = Yii::$app->request->post('late_morning', 'ontime');
                    $e = Yii::$app->request->post('late_evening', 'ontime');
                    $lateMorning    = in_array($m, ['late', 'excuse'], true) ? 1 : 0;
                    $lateEvening    = in_array($e, ['late', 'excuse'], true) ? 1 : 0;
                    $morningExempt  = ($m === 'excuse') ? 1 : 0;
                    $eveningExempt  = ($e === 'excuse') ? 1 : 0;
                    $morningOffice  = ($m === 'office') ? 1 : 0;
                    $eveningOffice  = ($e === 'office') ? 1 : 0;
                    // Recorded LOP marker reflects only the counted (non-excused) lates.
                    $lateLopMorning = ($lateMorning && !$morningExempt) ? 1 : 0;
                    $lateLopEvening = ($lateEvening && !$eveningExempt) ? 1 : 0;
                    break;
                case 6: // Permission
                    $isPermission = 1;
                    break;
                case 7: // Leave (CL)
                    $leaveLop = 1;
                    break;
                case 8: // Half Day Leave
                    $present = 1;
                    $leaveMorning    = (int)Yii::$app->request->post('leave_morning', 0);
                    $leaveEvening    = (int)Yii::$app->request->post('leave_evening', 0);
                    $leaveLopMorning = $leaveMorning;
                    $leaveLopEvening = $leaveEvening;
                    break;
            }

            $record->is_present         = $present;
            $record->is_permission      = $isPermission;
            $record->morning_late       = $lateMorning;
            $record->evening_late       = $lateEvening;
            $record->morning_leave      = $leaveMorning;
            $record->evening_leave      = $leaveEvening;
            $record->leave_type         = $attendanceType;
            $record->leave_lop          = (string)$leaveLop;
            $record->morning_late_lop   = (string)$lateLopMorning;
            $record->evening_late_lop   = (string)$lateLopEvening;
            $record->morning_leave_lop  = (string)$leaveLopMorning;
            $record->evening_leave_lop  = (string)$leaveLopEvening;
            $record->total_lop          = (string)($leaveLop + $lateLopMorning + $lateLopEvening);
            $record->is_holiday         = $isHoliday;
            // #4 Admin override to plain Present exempts the WHOLE day from late/early
            // LOP counting; per-session excusals are tracked separately below.
            $record->lop_exempt         = ($attendanceType === 1) ? 1 : 0;
            $record->morning_lop_exempt = $morningExempt;
            $record->evening_lop_exempt = $eveningExempt;
            $record->morning_office_work = $morningOffice;
            $record->evening_office_work = $eveningOffice;

            // Handle CL debit/credit
            $wasCLDebited = (int)$record->cl_debited;
            if ($attendanceType === 7) {
                // Applying CL: debit from developer's cl_backup if not already debited
                if (!$wasCLDebited) {
                    $developer = Developer::findOne($record->developer_id);
                    if ($developer && $developer->cl_backup > 0) {
                        $developer->cl_backup = max(0, $developer->cl_backup - 1);
                        $developer->save(false);
                        $record->cl_debited = 1;
                    }
                }
            } else {
                // Changing away from CL: credit back if it was previously debited
                if ($wasCLDebited) {
                    $developer = Developer::findOne($record->developer_id);
                    if ($developer) {
                        $developer->cl_backup = $developer->cl_backup + 1;
                        $developer->save(false);
                    }
                    $record->cl_debited = 0;
                }
            }

            if ($record->save(false)) {
                Yii::$app->session->setFlash('success', 'Attendance updated.');
                return $this->redirect($backUrl);
            }

            Yii::$app->session->setFlash('error', 'Failed to update attendance.');
            return $this->redirect($backUrl);
        }

        return $this->render('change-attendance', ['model' => $record, 'filters' => $filters]);
    }

    // -----------------------------------------------------------------------
    // Verify My Attendance (developer verifies their own published attendance)
    // -----------------------------------------------------------------------

    public function actionVerifyMyAttendance()
    {
        $user  = Yii::$app->user->identity;
        $devId = $user->getDeveloperId();

        $verificationRecord = AttendanceVerification::find()
            ->where(['status' => 1, 'developer_id' => $devId, 'verified' => 0])
            ->one();

        if (!$verificationRecord) {
            Yii::$app->session->setFlash('error', 'You have already verified your attendance or no pending verification.');
            return $this->redirect(['my-attendance']);
        }

        $fromDate = date('Y-m-01', strtotime($verificationRecord->attendance_date));
        $toDate   = date('Y-m-t', strtotime($verificationRecord->attendance_date));

        $dates = [];
        $cur   = $fromDate;
        while (strtotime($cur) <= strtotime($toDate)) {
            $dates[] = $cur;
            $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
        }

        $dailyRecords = DailyAttendance::find()
            ->where(['status' => 1, 'developer_id' => $devId])
            ->andWhere(['>=', 'DATE(attendance_date)', $fromDate])
            ->andWhere(['<=', 'DATE(attendance_date)', $toDate])
            ->indexBy(fn($m) => date('Y-m-d', strtotime($m->attendance_date)))
            ->all();

        return $this->render('verify-my-attendance', compact(
            'verificationRecord', 'dates', 'dailyRecords', 'fromDate', 'toDate'
        ));
    }

    // -----------------------------------------------------------------------
    // Verify Attendance (developer submits accept/reject)
    // -----------------------------------------------------------------------

    public function actionVerifyAttendance(int $id)
    {
        $user   = Yii::$app->user->identity;
        $devId  = $user->getDeveloperId();
        $status = (int)(Yii::$app->request->post('status') ?? Yii::$app->request->get('status', 1));
        $reason = Yii::$app->request->post('reason') ?? Yii::$app->request->get('reason', '');

        $record = AttendanceVerification::findOne($id);

        if (!$record) {
            Yii::$app->session->setFlash('error', 'Invalid attendance record.');
            return $this->redirect(['verify-my-attendance']);
        }

        if ((int)$record->developer_id !== (int)$devId) {
            Yii::$app->session->setFlash('error', 'You are not authorised to verify this attendance.');
            return $this->redirect(['verify-my-attendance']);
        }

        $record->verified = $status;
        $record->reason   = $reason;

        if ($record->save(false)) {
            Yii::$app->session->setFlash('success', 'Attendance verified successfully.');
            return $this->redirect(['my-attendance']);
        }

        Yii::$app->session->setFlash('error', 'Something went wrong. Please try again.');
        return $this->redirect(['verify-my-attendance']);
    }

    // -----------------------------------------------------------------------
    // Update Remarks on a daily attendance record
    // -----------------------------------------------------------------------

    public function actionUpdateRemarks(int $id)
    {
        $model = DailyAttendance::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Record not found.');
        }

        if (Yii::$app->request->isPost) {
            /** @var \backend\models\User $user */
            $user   = Yii::$app->user->identity;
            $isAjax = Yii::$app->request->isAjax;

            // Helper: respond as JSON for AJAX submits (no page reload), else
            // fall back to the legacy flash + redirect behaviour.
            $respond = function (bool $ok, string $msg) use ($isAjax) {
                if ($isAjax) {
                    return $this->asJson(['success' => $ok, 'msg' => $msg]);
                }
                Yii::$app->session->setFlash($ok ? 'success' : 'error', $msg);
                return $this->redirect(['monthly-attendance']);
            };

            // Yii1 parity: payslip-generated months are read-only.
            if (Payslip::isGeneratedForMonth($model->attendance_date)) {
                return $respond(false, 'Payslip already generated for this month — remarks cannot be modified.');
            }

            // Ownership + once-only check (Yii1 parity: only the owning
            // developer can write a remark, and only while the field is empty).
            // Admin / sub-admin retain unrestricted access.
            if ($user->isDeveloper()) {
                $selfDev = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
                if (!$selfDev || (int)$model->developer_id !== (int)$selfDev->id) {
                    throw new \yii\web\ForbiddenHttpException('You can only add remarks on your own attendance.');
                }
                if ($model->remarks !== null && $model->remarks !== '') {
                    throw new \yii\web\ForbiddenHttpException('Remark already submitted — it cannot be edited.');
                }
            }

            $model->remarks = Yii::$app->request->post('remarks', '');

            if (!$model->save(false)) {
                return $respond(false, 'Failed to update remarks. Please try again.');
            }

            // Handle optional file upload; a save failure here is non-fatal (the
            // remark text is already stored) but is surfaced to the user.
            $uploadError = null;
            $file = UploadedFile::getInstanceByName('remarks_attachment');
            if ($file) {
                $uploadsDir = Yii::getAlias('@webroot') . '/uploads/remarks';
                if (!is_dir($uploadsDir)) {
                    @\yii\helpers\FileHelper::createDirectory($uploadsDir, 0777);
                }
                if (is_dir($uploadsDir) && is_writable($uploadsDir)) {
                    $fileName = 'Remarks_' . uniqid() . '_' . $model->id . '.' . $file->extension;
                    $filePath = '/uploads/remarks/' . $fileName;
                    if ($file->saveAs($uploadsDir . '/' . $fileName)) {
                        $model->remarks_attachment = $filePath;
                        $model->save(false);
                    } else {
                        $uploadError = 'Failed to save the attachment. Please try again.';
                    }
                } else {
                    Yii::error('Remarks upload dir is not writable: ' . $uploadsDir, __METHOD__);
                    $uploadError = 'Attachment could not be saved (server upload directory is not writable). Remark text was saved.';
                }
            }

            return $uploadError !== null
                ? $respond(false, $uploadError)
                : $respond(true, 'Remarks updated successfully.');
        }

        return $this->renderAjax('update-remarks', ['model' => $model]);
    }

    // -----------------------------------------------------------------------
    // Check In
    // -----------------------------------------------------------------------

    public function actionCheckIn()
    {
        $user  = Yii::$app->user->identity;
        $devId = $user->getDeveloperId();

        if (!$devId) {
            Yii::$app->session->setFlash('error', 'Invalid user. No developer profile linked.');
            return $this->redirect(['my-attendance']);
        }

        $today = date('Y-m-d');

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Lock the developer row to prevent concurrent check-ins
            Yii::$app->db->createCommand(
                'SELECT id FROM developer WHERE id = :id FOR UPDATE'
            )->bindValue(':id', $devId)->queryScalar();

            $existing = Attendance::find()
                ->where(['status' => 1, 'developer_id' => $devId])
                ->andWhere('DATE(in_time) = :today', [':today' => $today])
                ->one();

            if ($existing) {
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', 'You have already checked in today.');
                return $this->redirect(['my-attendance']);
            }

            $model               = new Attendance();
            $model->developer_id = $devId;
            $model->in_time      = date('Y-m-d H:i:s');
            $model->in_ip        = Yii::$app->request->userIP;
            $model->out_time     = null;
            $model->out_ip       = null;
            $model->status       = 1;

            if ($model->save()) {
                $transaction->commit();
                Yii::$app->session->setFlash('success', 'Successfully checked in for today.');
            } else {
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', 'Something went wrong. Please try again.');
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Something went wrong. Please try again.');
        }

        return $this->redirect(['my-attendance']);
    }

    // -----------------------------------------------------------------------
    // Check Out
    // -----------------------------------------------------------------------

    public function actionCheckOut()
    {
        $user  = Yii::$app->user->identity;
        $devId = $user->getDeveloperId();

        if (!$devId) {
            Yii::$app->session->setFlash('error', 'Invalid user. No developer profile linked.');
            return $this->redirect(['my-attendance']);
        }

        $today = date('Y-m-d');

        $record = Attendance::find()
            ->where(['status' => 1, 'developer_id' => $devId])
            ->andWhere('DATE(in_time) = :today', [':today' => $today])
            ->one();

        if (!$record) {
            Yii::$app->session->setFlash('error', 'No check-in record found for today.');
            return $this->redirect(['my-attendance']);
        }

        if ($record->out_time) {
            Yii::$app->session->setFlash('error', 'You have already checked out today.');
            return $this->redirect(['my-attendance']);
        }

        $record->out_time = date('Y-m-d H:i:s');
        $record->out_ip   = Yii::$app->request->userIP;

        if ($record->save(false)) {
            Yii::$app->session->setFlash('success', 'Successfully checked out for today.');
        } else {
            Yii::$app->session->setFlash('error', 'Failed to check out. Please try again.');
        }

        return $this->redirect(['my-attendance']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function findModel(int $id): Attendance
    {
        $model = Attendance::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Attendance record not found.');
        }
        return $model;
    }
}
