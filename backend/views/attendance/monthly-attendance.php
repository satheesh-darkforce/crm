<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer[] $developers */
/** @var array $employeeOptions  id => label (ex-employees suffixed "(Ex)") */
/** @var string[] $dates */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int $year */
/** @var int $month */
/** @var array $dailyMap [dev_id][date] => DailyAttendance */
/** @var bool $isAdmin */
/** @var bool $canViewAll */
/** @var array|null $payroll */
/** @var array|null $lateExcuseSummary  employee-only: lateMonth/lateTotal/excuseMonth/excuseTotal/monthLabel */
/** @var array<string,bool> $lockedMonths  ['Y-m' => bool] — true if payslip generated */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use kartik\select2\Select2;

$this->title = 'Monthly Attendance';

// Date filter bounds — match Yii1 monthlyattendance.php:
// any past date back to 2010 is selectable; future dates after today are blocked.
$dateRangeMin = '2010-01-01';
$dateRangeMax = date('Y-m-d');

// Current filter state — carried into the change-attendance link so the page
// returns to the same filtered view after an edit (kept in sync with the
// $filterKeys list in AttendanceController::actionChangeAttendance).
$attFilters = [];
foreach (['developer_id', 'team', 'branch', 'ex_employee', 'year', 'month'] as $fk) {
    $fv = Yii::$app->request->get($fk);
    if ($fv !== null && $fv !== '') {
        $attFilters[$fk] = $fv;
    }
}

// Per-cell leave-type → label/colour, mirrors Yii1 Utility::get_leave_status mapping
$leaveLabel = [
    1 => ['P',  '#047716'],  // Present
    2 => ['OD', '#ec971f'],  // On Duty
    3 => ['AB', '#c3325f'],  // Absent
    4 => ['H',  '#5897fb'],  // Holiday
    5 => ['LA', '#0055ff'],  // Late
    6 => ['PE', '#ff5500'],  // Permission
    7 => ['L',  '#9400D3'],  // Leave
    8 => ['HD', '#C46ED3'],  // Half Day
];
?>
<style>
/* monthly-attendance.css — inline so the browser picks it up directly with
   the page response (no CSS-asset cache to invalidate). */
/* Sticky-table wrapper — mirrors Yii1 #employees-grid scroll behavior.
   max-width pinned to viewport minus sidebar+padding so a wide table actually
   triggers .att-grid's own overflow scrollbar instead of the page's. */
#main-content .attendance-monthly { max-width: 100%; min-width: 0; }
#main-content .attendance-monthly .att-grid {
    display: block;
    max-width: calc(100vw - 308px);   /* sidebar 252 + #main-content padding 56 */
    max-height: 583px;
    overflow-x: auto;
    overflow-y: auto;
    scrollbar-width: thin;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 4px;
}
/* sm/xs only: grid wrapper width override (no sidebar on the left). */
@media (max-width: 767.98px) {
    #main-content .attendance-monthly .att-grid {
        max-width: calc(100vw - 2rem);
        max-height: 70vh;
    }
}
/* ≤ 1350px: drop the 882px sticky-column stack.
   - 360px phone: viewport is far smaller than the 882px sticky stack.
   - md (768–991): sidebar steals 252px, content box ~460–700px — still
     smaller than the 882px sticky stack.
   - lg (992–1349): viewport - 252 sidebar - 56 padding leaves 684–1042px;
     the 882px sticky stack still covers most/all date columns.
   Only Name stays sticky at left so each row is identifiable while the user
   scrolls horizontally. ≥ 1350px keeps the original sticky behavior.
   Subtle: the base rule `.att-grid thead th { position: sticky !important;
   top: 0 !important }` has higher specificity than `.sticky-X`, so we
   CANNOT cancel the header's `position: sticky` from a `.sticky-X` selector.
   Instead we KEEP `position: sticky; top: 0` on header cells (so the header
   row still pins to the top during vertical scroll) and only nullify the
   `left` / `right` offsets that were causing horizontal pinning. Body cells
   become fully static. Column widths from desktop rules are preserved so
   `table-layout: fixed` keeps header and body aligned column-for-column. */
@media (max-width: 1349.98px) {
    /* HEADER cells (higher specificity needed to beat `.att-grid thead th`):
       cancel horizontal sticky offsets only. Vertical sticky-top is kept. */
    #main-content .attendance-monthly .att-grid thead .sticky-sno,
    #main-content .attendance-monthly .att-grid thead .sticky-code,
    #main-content .attendance-monthly .att-grid thead .sticky-clbackup,
    #main-content .attendance-monthly .att-grid thead .sticky-clapplied,
    #main-content .attendance-monthly .att-grid thead .sticky-tlop,
    #main-content .attendance-monthly .att-grid thead .sticky-leavelop,
    #main-content .attendance-monthly .att-grid thead .sticky-latelop,
    #main-content .attendance-monthly .att-grid thead .sticky-totallate,
    #main-content .attendance-monthly .att-grid thead .sticky-totalabs,
    #main-content .attendance-monthly .att-grid thead .sticky-totalleave {
        left: auto !important;
        right: auto !important;
    }
    /* BODY cells: fully static (drop horizontal sticky). */
    #main-content .attendance-monthly .att-grid tbody .sticky-sno,
    #main-content .attendance-monthly .att-grid tbody .sticky-code,
    #main-content .attendance-monthly .att-grid tbody .sticky-clbackup,
    #main-content .attendance-monthly .att-grid tbody .sticky-clapplied,
    #main-content .attendance-monthly .att-grid tbody .sticky-tlop,
    #main-content .attendance-monthly .att-grid tbody .sticky-leavelop,
    #main-content .attendance-monthly .att-grid tbody .sticky-latelop,
    #main-content .attendance-monthly .att-grid tbody .sticky-totallate,
    #main-content .attendance-monthly .att-grid tbody .sticky-totalabs,
    #main-content .attendance-monthly .att-grid tbody .sticky-totalleave {
        position: static !important;
    }
    /* Name column: keep sticky-left in both head and body, anchored at 0
       (# column is no longer sticky on its left, so 36px offset is gone). */
    #main-content .attendance-monthly .sticky-name {
        left: 0 !important;
    }
}
/* table-layout:fixed locks column widths from the first row (the header) so
   body cells can never drift out of alignment with their headers.
   width:max-content sizes the table naturally; min-width:100% fills the box. */
#main-content .attendance-monthly .att-grid table {
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    font-size: .78rem;
    width: max-content;
    min-width: 100%;
    margin: 0;
}
#main-content .attendance-monthly .att-grid th,
#main-content .attendance-monthly .att-grid td {
    border: 1px solid #ddd;
    padding: 4px 6px;
    text-align: center;
    vertical-align: middle;
    background: #fff;
    box-sizing: border-box;
    word-wrap: break-word;
    overflow-wrap: break-word;
}
#main-content .attendance-monthly .att-grid tbody td { white-space: nowrap }
/* Forced with !important to defeat the global `.table thead th { text-transform:
   uppercase; white-space: nowrap; padding:.65rem .9rem }` rule in site.css. */
#main-content .attendance-monthly .att-grid thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    background: #f4f6f9 !important;
    font-weight: 600 !important;
    font-size: .72rem !important;
    color: #495057 !important;
    line-height: 1.15 !important;
    padding: 4px 6px !important;
    letter-spacing: 0 !important;
    text-align: center !important;
    text-transform: none !important;
    white-space: normal !important;
    word-break: break-word !important;
    overflow-wrap: break-word !important;
}
/* Left sticky columns — # / Name / Emp Code (Team non-sticky, matches Yii1) */
#main-content .attendance-monthly .sticky-sno  { position: sticky; left: 0;    z-index: 2; width: 36px;  min-width: 36px;  max-width: 36px }
#main-content .attendance-monthly .sticky-name { position: sticky; left: 36px; z-index: 2; width: 150px; min-width: 150px; max-width: 150px; text-align: left !important }
#main-content .attendance-monthly .sticky-code { position: sticky; left: 186px;z-index: 2; width: 90px;  min-width: 90px;  max-width: 90px }
/* Right sticky summary columns (offsets cascade right→left).
   Widths bumped so headers ("Total Leave", "Leave/Abs LOP") fit without spillover. */
#main-content .attendance-monthly .sticky-clbackup  { position: sticky; right: 0;    z-index: 2; width: 78px; min-width: 78px; max-width: 78px }
#main-content .attendance-monthly .sticky-clapplied { position: sticky; right: 78px; z-index: 2; width: 78px; min-width: 78px; max-width: 78px }
#main-content .attendance-monthly .sticky-tlop      { position: sticky; right: 156px;z-index: 2; width: 70px; min-width: 70px; max-width: 70px }
#main-content .attendance-monthly .sticky-leavelop  { position: sticky; right: 226px;z-index: 2; width: 90px; min-width: 90px; max-width: 90px }
#main-content .attendance-monthly .sticky-latelop   { position: sticky; right: 316px;z-index: 2; width: 70px; min-width: 70px; max-width: 70px }
#main-content .attendance-monthly .sticky-totallate { position: sticky; right: 386px;z-index: 2; width: 70px; min-width: 70px; max-width: 70px }
#main-content .attendance-monthly .sticky-totalabs  { position: sticky; right: 456px;z-index: 2; width: 70px; min-width: 70px; max-width: 70px }
#main-content .attendance-monthly .sticky-totalleave{ position: sticky; right: 526px;z-index: 2; width: 80px; min-width: 80px; max-width: 80px }
/* Header cells layered above body sticky cells */
#main-content .attendance-monthly .att-grid thead .sticky-sno,
#main-content .attendance-monthly .att-grid thead .sticky-name,
#main-content .attendance-monthly .att-grid thead .sticky-code,
#main-content .attendance-monthly .att-grid thead .sticky-clbackup,
#main-content .attendance-monthly .att-grid thead .sticky-clapplied,
#main-content .attendance-monthly .att-grid thead .sticky-tlop,
#main-content .attendance-monthly .att-grid thead .sticky-leavelop,
#main-content .attendance-monthly .att-grid thead .sticky-latelop,
#main-content .attendance-monthly .att-grid thead .sticky-totallate,
#main-content .attendance-monthly .att-grid thead .sticky-totalabs,
#main-content .attendance-monthly .att-grid thead .sticky-totalleave { z-index: 12; background: #f4f6f9 }
/* Team + date columns — explicit widths so table-layout:fixed has known widths. */
#main-content .attendance-monthly .att-team { width: 70px; min-width: 70px; max-width: 70px }
#main-content .attendance-monthly .att-day  { width: 90px; min-width: 90px; max-width: 90px; font-size: .68rem; line-height: 1.25 }
#main-content .attendance-monthly .att-badge{ display: inline-block; padding: 2px 6px; border-radius: 3px; color: #fff; font-size: .7rem; font-weight: 600 }
/* Leave Policy modal renders OUTSIDE .attendance-monthly, so re-declare the badge style there (white text on its bg colour). */
#leavePolicyModal .att-badge{ display: inline-block; padding: 2px 6px; border-radius: 3px; color: #fff !important; font-size: .7rem; font-weight: 600; line-height: 1.4 }
#main-content .attendance-monthly .att-time{ display: block }
#main-content .attendance-monthly .att-time.late{ color: #c0392b }
#main-content .attendance-monthly .att-time.ok  { color: #16794f }
#main-content .attendance-monthly .att-tag{ font-size: .6rem; font-weight: 700; margin-left: 2px }
/* Hover keeps sticky cells colour-synced with the row */
#main-content .attendance-monthly .att-grid tbody tr:hover td { background: #f8f9ff }
/* Cursor signals click-to-open on the cell */
#main-content .attendance-monthly .att-grid td a { cursor: pointer }
</style>

<div class="ui-page attendance-monthly">
    <div class="ui-head">
        <div>
            <h1>Monthly Attendance</h1>
            <div class="ui-sub"><?= Html::encode(date('d M Y', strtotime($fromDate))) ?> &ndash; <?= Html::encode(date('d M Y', strtotime($toDate))) ?></div>
        </div>
        <div class="ui-actions">
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#leavePolicyModal">
            <i class="bi bi-info-circle"></i> Click to view Leave Policy
        </button>
        </div>
    </div>

    <!-- Legend (matches Yii1) -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3 d-flex flex-wrap gap-3" style="font-size:.8rem">
            <span><span class="att-badge" style="background:#c3325f">AB</span> Absent</span>
            <span><span class="att-badge" style="background:#5897fb">H</span> Holiday</span>
            <span><span class="att-badge" style="background:#047716">P</span> Present</span>
            <span><span class="att-badge" style="background:#ec971f">OD</span> On Duty</span>
            <span><span class="att-badge" style="background:#0055ff">LA</span> Late</span>
            <span><span class="att-badge" style="background:#ff5500">PE</span> Permission</span>
            <span><span class="att-badge" style="background:#C46ED3">HD</span> Half Day</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" id="ma-filter-form" class="row g-2 align-items-center">
                <?php if ($canViewAll): ?>
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => Yii::$app->request->get('developer_id'),
                        'data'          => $employeeOptions,
                        'options'       => ['placeholder' => 'All Employees (incl. ex-employees)'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <select name="team" class="form-select form-select-sm">
                        <option value="">All Teams</option>
                        <?php foreach (\backend\models\Developer::$teams as $tv => $tl): ?>
                            <option value="<?= $tv ?>" <?= Yii::$app->request->get('team') == $tv ? 'selected' : '' ?>><?= $tl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="branch" class="form-select form-select-sm">
                        <option value="">All Branches</option>
                        <?php foreach (\backend\models\Developer::$branches as $bv => $bl): ?>
                            <option value="<?= $bv ?>" <?= Yii::$app->request->get('branch') == $bv ? 'selected' : '' ?>><?= $bl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <select name="year" class="form-select form-select-sm">
                        <?php for ($y = (int)date('Y'); $y >= 2010; $y--): ?>
                            <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="month" class="form-select form-select-sm">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Search</button>
                    <?= Html::a('Clear', ['monthly-attendance'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?= Html::a(
                        '<i class="bi bi-file-earmark-excel"></i> Export',
                        array_merge(['monthly-attendance-export'], Yii::$app->request->queryParams),
                        [
                            'class' => 'btn btn-success btn-sm ms-1',
                            'title' => 'Export summary (Name, Total Leave, Total Abs, Total Late, Late LOP, Leave/Abs LOP, Total LOP, CL Applied, CL Backup)',
                        ]
                    ) ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$isAdmin && $lateExcuseSummary !== null): ?>
    <!-- Employee's own late / excuse summary (never shown to admin) -->
    <div class="row g-2 mb-3 late-excuse-summary">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="le-label">Late (<?= Html::encode($lateExcuseSummary['monthLabel']) ?>)</div>
                    <div class="le-value le-late"><?= (int)$lateExcuseSummary['lateMonth'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="le-label">Late (Total)</div>
                    <div class="le-value le-late"><?= (int)$lateExcuseSummary['lateTotal'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="le-label">Excuses (<?= Html::encode($lateExcuseSummary['monthLabel']) ?>)</div>
                    <div class="le-value le-excuse"><?= (int)$lateExcuseSummary['excuseMonth'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="le-label">Excuses (Total)</div>
                    <div class="le-value le-excuse"><?= (int)$lateExcuseSummary['excuseTotal'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <style>
    .late-excuse-summary .le-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600}
    .late-excuse-summary .le-value{font-size:1.4rem;font-weight:700;line-height:1.2}
    .late-excuse-summary .le-late{color:#c0392b}
    .late-excuse-summary .le-excuse{color:#0f7b53}
</style>
    <?php endif; ?>

    <!-- Attendance Grid (no flex card wrapper — keeps overflow scroll inside .att-grid) -->
    <div class="att-grid shadow-sm">
        <table>
                <thead>
                    <tr>
                        <th class="sticky-sno">#</th>
                        <th class="sticky-name">Name</th>
                        <th class="sticky-code">Emp Code</th>
                        <th class="att-team">Team</th>
                        <?php foreach ($dates as $date): ?>
                            <th class="att-day">
                                <?= date('d', strtotime($date)) ?>
                                <span style="font-size:.65rem;color:#475569"><?= date('M', strtotime($date)) ?></span>
                                <br>
                                <span style="font-size:.6rem;color:#999"><?= date('D', strtotime($date)) ?></span>
                                <span style="font-size:.6rem;color:#999"><?= date('y', strtotime($date)) ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th class="sticky-totalleave">Total Leave</th>
                        <th class="sticky-totalabs">Total Abs</th>
                        <th class="sticky-totallate">Total Late</th>
                        <th class="sticky-latelop">Late LOP</th>
                        <th class="sticky-leavelop">Leave/Abs LOP</th>
                        <th class="sticky-tlop">Total LOP</th>
                        <th class="sticky-clapplied">CL Applied</th>
                        <th class="sticky-clbackup">CL Backup</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($developers)): ?>
                    <tr><td colspan="<?= 12 + count($dates) ?>" class="text-center text-muted" style="padding:40px 0">No attendance records found for the selected range.</td></tr>
                <?php else: ?>
                    <?php foreach ($developers as $i => $dev):
                        $devDailyMap = $dailyMap[$dev->id] ?? [];

                        // Per-developer totals — replicating Yii1 Utility helper logic exactly:
                        //   get_leave_late(2, ..)  → totalLeave
                        //   get_leave_late(1, ..)  → totalLate (sum of morning_late + evening_late)
                        //   get_late_lop(1, ..)    → totalLate * 0.5
                        //   get_absent(..)         → totalAbs
                        //   get_cl_taken(..)       → clApplied
                        $totalLeave = 0.0;
                        $totalAbs   = 0.0;
                        $totalLate  = 0;
                        $clApplied  = 0.0;

                        foreach ($devDailyMap as $da) {
                            if ($da->is_holiday) {
                                continue;
                            }
                            $lt = (int)$da->leave_type;
                            $ml = (int)$da->morning_leave;
                            $el = (int)$da->evening_leave;
                            // Office-work sessions are never late.
                            $mLate = ((int)$da->morning_late === 1 && (int)$da->morning_office_work !== 1) ? 1 : 0;
                            $eLate = ((int)$da->evening_late === 1 && (int)$da->evening_office_work !== 1) ? 1 : 0;
                            $isPresent = (int)$da->is_present;
                            $totalLop  = (float)$da->total_lop;
                            $clDeb     = (float)$da->cl_debited;

                            // Total Leave (flag=2 in Yii1). Count an approved leave day
                            // whether it was covered by CL (total_lop=0, cl_debited=1) or
                            // fell to LOP (total_lop=1). Yii1's `total_lop==1` gate only
                            // worked because its set_cl_backup was a no-op (leave was always
                            // LOP); now that CL actually covers leave, that gate must drop.
                            if ($lt === 7 && $isPresent === 0)              { $totalLeave += 1; }
                            if ($lt === 8 && $ml === 1 && $isPresent === 1) { $totalLeave += 0.5; }
                            if ($lt === 8 && $el === 1 && $isPresent === 1) { $totalLeave += 0.5; }

                            // Total Abs
                            if ($lt === 7 && $totalLop == 1 && $isPresent === 0 && $clDeb == 0) { $totalAbs += 1; }
                            if ($lt === 8 && $ml === 1 && $isPresent === 1)                     { $totalAbs += 0.5; }
                            if ($lt === 8 && $el === 1 && $isPresent === 1)                     { $totalAbs += 0.5; }
                            if ($lt === 3)                                                       { $totalAbs += 1; }

                            // Total Late
                            if ($mLate === 1 || $eLate === 1) {
                                $totalLate += $mLate + $eLate;
                            }

                            // CL Applied
                            if ($clDeb > 0) {
                                if ($lt === 7 && $clDeb == 1   && $isPresent === 0) { $clApplied += 1; }
                                if ($lt === 8 && $ml === 1     && $isPresent === 1 && $clDeb == 0.5) { $clApplied += 0.5; }
                                if ($lt === 8 && $el === 1     && $isPresent === 1 && $clDeb == 0.5) { $clApplied += 0.5; }
                            }
                        }

                        // Late LOP follows the new rule (first 2 excused, then every 2 → 0.5 day,
                        // admin-Present days exempt) + sandwich-leave LOP — sourced from the
                        // shared calculator so the grid agrees with payroll (requirement #4/#5).
                        $lateLop     = (float)($lopSummary[$dev->id]['lateLop'] ?? 0);
                        $sandwichLop = (float)($lopSummary[$dev->id]['sandwichLop'] ?? 0);
                        $leaveAbsLop = $totalAbs;          // Yii1 reuses get_absent() for this column
                        $totalLopAll = $totalAbs + $lateLop + $sandwichLop;
                        $clBackup    = (float)$dev->cl_backup;

                        $teamLabel = \backend\models\Developer::$teams[$dev->team] ?? '—';
                    ?>
                    <tr>
                        <td class="sticky-sno text-muted"><?= $i + 1 ?></td>
                        <td class="sticky-name fw-semibold"><?= Html::encode($dev->developer_name) ?></td>
                        <td class="sticky-code text-muted" style="font-size:.72rem"><?= Html::encode($dev->emp_code ?: '—') ?></td>
                        <td class="att-team"><?= Html::encode($teamLabel) ?></td>
                        <?php foreach ($dates as $date):
                            $da = $devDailyMap[$date] ?? null;
                            $lbl = null; $bgColor = null;
                            if ($da) {
                                [$lbl, $bgColor] = $leaveLabel[$da->leave_type] ?? [null, null];
                                if ($da->is_holiday) { $lbl = 'H'; $bgColor = '#5897fb'; }
                            }
                            // Yii1 behavior:
                            //   admin clicks cell      → "change attendance" link
                            //   developer clicks cell  → opens "update remarks" modal
                            // Yii1 parity: lock cells once the month's payslip has been generated.
                            $cellMonthKey = date('Y-m', strtotime($date));
                            $monthLocked  = !empty($lockedMonths[$cellMonthKey]);
                            $cellHref = ($isAdmin && $da && !$monthLocked)
                                ? Url::to(array_merge(['change-attendance', 'id' => $da->id], $attFilters))
                                : null;
                            $cellOpensRemarks = (!$isAdmin && $da && !$monthLocked);
                        ?>
                        <td class="att-day">
                            <?php if ($da): ?>
                                <?php
                                // Cell content matching Yii1 Utility::get_leave_status —
                                // show in/out times when present, else just the leave-type badge.
                                $hasIn  = !empty($da->in_time);
                                $hasOut = !empty($da->out_time);
                                ob_start();
                                $owTag = ' <span class="att-tag" style="color:#1565c0">(OW)</span>';
                                if ($hasIn || $hasOut) {
                                    if ($hasIn) {
                                        $mOffice = $da->morning_office_work == 1;
                                        $mLate   = !$mOffice && ($da->morning_late == 1 || $da->morning_leave == 1);
                                        $cls     = $mLate ? 'late' : 'ok';
                                        echo '<span class="att-time ' . $cls . '">'
                                            . date('h:i A', strtotime($da->in_time))
                                            . ($mOffice ? $owTag : ($mLate && $lbl ? ' <span class="att-tag">(' . $lbl . ')</span>' : ''))
                                            . '</span>';
                                    } else {
                                        echo '<span class="att-time late">checkin' . ($lbl ? ' <span class="att-tag">(' . $lbl . ')</span>' : '') . '</span>';
                                    }
                                    if ($hasOut) {
                                        $eOffice = $da->evening_office_work == 1;
                                        $eLate   = !$eOffice && ($da->evening_late == 1 || $da->evening_leave == 1);
                                        $cls     = $eLate ? 'late' : 'ok';
                                        echo '<span class="att-time ' . $cls . '">'
                                            . date('h:i A', strtotime($da->out_time))
                                            . ($eOffice ? $owTag : ($eLate && $lbl ? ' <span class="att-tag">(' . $lbl . ')</span>' : ''))
                                            . '</span>';
                                    } else {
                                        echo '<span class="att-time late">checkout' . ($lbl ? ' <span class="att-tag">(' . $lbl . ')</span>' : '') . '</span>';
                                    }
                                } elseif ($lbl !== null) {
                                    echo '<span class="att-badge" style="background:' . $bgColor . '">' . $lbl . '</span>';
                                } else {
                                    echo '<span style="color:#ccc">—</span>';
                                }
                                $cellInner = ob_get_clean();
                                ?>
                                <?php if ($cellHref): ?>
                                    <a href="<?= $cellHref ?>" style="text-decoration:none;color:inherit" title="Change attendance"><?= $cellInner ?></a>
                                <?php elseif ($cellOpensRemarks): ?>
                                    <a href="javascript:void(0)" class="remarks-btn" data-id="<?= $da->id ?>" style="text-decoration:none;color:inherit" title="Click to add/view remarks"><?= $cellInner ?></a>
                                <?php else: ?>
                                    <?= $cellInner ?>
                                <?php endif; ?>
                                <?php if ($da->remarks !== null && $da->remarks !== ''): ?>
                                    <a href="javascript:void(0)" class="remarks-btn d-block" data-id="<?= $da->id ?>" style="font-size:.55rem;color:#17a2b8;text-decoration:none" title="Click to view remark"><i style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#17a2b8"></i> remarks</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#ccc;font-size:.65rem">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="sticky-totalleave fw-semibold"><?= $totalLeave > 0 ? rtrim(rtrim(number_format($totalLeave, 1), '0'), '.') : '0' ?></td>
                        <td class="sticky-totalabs fw-semibold <?= $totalAbs > 0 ? 'text-danger' : '' ?>"><?= $totalAbs > 0 ? rtrim(rtrim(number_format($totalAbs, 1), '0'), '.') : '0' ?></td>
                        <td class="sticky-totallate fw-semibold"><?= $totalLate ?></td>
                        <td class="sticky-latelop fw-semibold"><?= $lateLop > 0 ? rtrim(rtrim(number_format($lateLop, 1), '0'), '.') : '0' ?></td>
                        <td class="sticky-leavelop fw-semibold"><?= $leaveAbsLop > 0 ? rtrim(rtrim(number_format($leaveAbsLop, 1), '0'), '.') : '0' ?></td>
                        <td class="sticky-tlop fw-semibold <?= $totalLopAll > 0 ? 'text-danger' : '' ?>"><?= $totalLopAll > 0 ? rtrim(rtrim(number_format($totalLopAll, 1), '0'), '.') : '0' ?></td>
                        <td class="sticky-clapplied fw-semibold"><?= $clApplied > 0 ? rtrim(rtrim(number_format($clApplied, 1), '0'), '.') : '0' ?></td>
                        <td class="sticky-clbackup fw-semibold"><?= $clBackup > 0 ? rtrim(rtrim(number_format($clBackup, 1), '0'), '.') : '0' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
    </div>

<?php
// ── Payroll section (non-admin only — matches Yii1 monthlyattendance.php) ──────
if ($payroll):
    $monthStart = $payroll['monthStart'];
    $monthEnd   = $payroll['monthEnd'];
    $dev        = $payroll['developer'];

    // Constants from Yii1 Utility::*
    $noOfWorkingDays   = 30;
    $leaveAllocated    = 1;
    $additionalWorking = 0;
    $monthWorkingDay   = (int)date('t', strtotime($monthStart)) ?: 30; // real calendar days in the month
    $currencySymbol    = '₹';
    $remarksDefault    = 'N/A';

    // Re-tally totals over the *previous month* using same logic as the grid summary
    $pTotalLeave = 0.0;
    $pTotalAbs   = 0.0;
    $pTotalLate  = 0;
    $pPermissions = 0;

    foreach ($payroll['daily'] as $da) {
        if ($da->is_permission == 1) { $pPermissions++; }
        if ($da->is_holiday) { continue; }
        $lt = (int)$da->leave_type;
        $ml = (int)$da->morning_leave;
        $el = (int)$da->evening_leave;
        // Office-work sessions are never late.
        $mLate = ((int)$da->morning_late === 1 && (int)$da->morning_office_work !== 1) ? 1 : 0;
        $eLate = ((int)$da->evening_late === 1 && (int)$da->evening_office_work !== 1) ? 1 : 0;
        $isPresent = (int)$da->is_present;
        $tlop = (float)$da->total_lop;
        $clDeb = (float)$da->cl_debited;

        // Count approved leave whether CL-covered (total_lop=0) or LOP (total_lop=1) —
        // see grid summary note; the old total_lop==1 gate predates real CL debiting.
        if ($lt === 7 && $isPresent === 0)               { $pTotalLeave += 1; }
        if ($lt === 8 && $ml === 1 && $isPresent === 1)  { $pTotalLeave += 0.5; }
        if ($lt === 8 && $el === 1 && $isPresent === 1)  { $pTotalLeave += 0.5; }

        if ($lt === 7 && $tlop == 1 && $isPresent === 0 && $clDeb == 0) { $pTotalAbs += 1; }
        if ($lt === 8 && $ml === 1 && $isPresent === 1)                 { $pTotalAbs += 0.5; }
        if ($lt === 8 && $el === 1 && $isPresent === 1)                 { $pTotalAbs += 0.5; }
        if ($lt === 3) { $pTotalAbs += 1; }

        if ($mLate === 1 || $eLate === 1) {
            $pTotalLate += $mLate + $eLate;
        }
    }
    $pLateLop = $pTotalLate * 0.5;
    $pTotalLop = $pTotalAbs + $pLateLop;
    $latePermission = $pTotalLate + $pPermissions;

    // Map per-employee allowance/deduction values by allowance_deduction_id
    $empAllowMap = [];
    foreach ($payroll['empAllowances'] as $r) { $empAllowMap[$r->allowance_deduction_id] = (float)$r->value; }
    $empDetMap = [];
    foreach ($payroll['empDetections'] as $r) { $empDetMap[$r->allowance_deduction_id] = (float)$r->value; }

    // Compute final salary (matching Yii1 logic)
    $totalTax = 0.0;
    $deductionAmount = 0.0;
    foreach ($payroll['detections'] as $d) {
        $val = $empDetMap[$d->id] ?? 0;
        if ((int)$d->type2 === 1) { $totalTax += $val; }
        else                       { $deductionAmount += $val; }
    }
    $grossSalary = (float)($dev->gross_salary ?: 0);
    $finalSalary = 0.0;
    if ($grossSalary > 0) {
        $perDaySalary = $grossSalary / $monthWorkingDay;
        $deductLop    = $pTotalLop * $perDaySalary;
        $finalSalary  = $grossSalary - $deductLop - $deductionAmount;
        if ($totalTax > 0) {
            $finalSalary -= ($finalSalary / 100) * $totalTax;
        }
    }
?>
<div class="mt-4">
    <h5 class="fw-semibold mb-2">Payroll <?= Html::encode(date('M-Y', strtotime($monthStart))) ?></h5>
    <div class="att-grid" style="max-height:none">
        <table class="table table-sm table-bordered mb-0" style="font-size:.78rem;width:max-content;min-width:100%">
            <thead style="background:#f4f6f9">
                <tr>
                    <th>#</th>
                    <th>Employee Name</th>
                    <th>No Of Working Days</th>
                    <th>Leave Balance</th>
                    <th>Leave Allocated</th>
                    <th>Leave Taken</th>
                    <th>Leave Lop</th>
                    <th>Late / Permission</th>
                    <th>Late Lop</th>
                    <th>Additional Working</th>
                    <th>Total Lop</th>
                    <?php foreach ($payroll['allowances'] as $a): ?>
                        <th><?= Html::encode($a->title) ?></th>
                    <?php endforeach; ?>
                    <?php foreach ($payroll['detections'] as $d): ?>
                        <th><?= Html::encode($d->title) ?><?= ((int)$d->type2 === 1) ? '(%)' : '' ?></th>
                    <?php endforeach; ?>
                    <th>Gross Salary</th>
                    <th>Actual Salary</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><?= Html::encode($dev->developer_name) ?></td>
                    <td><?= $noOfWorkingDays ?></td>
                    <td><?= rtrim(rtrim(number_format((float)$dev->cl_backup, 2), '0'), '.') ?: '0' ?></td>
                    <td><?= $leaveAllocated ?></td>
                    <td><?= $pTotalLeave > 0 ? rtrim(rtrim(number_format($pTotalLeave, 2), '0'), '.') : '0' ?></td>
                    <td><?= $pTotalAbs   > 0 ? rtrim(rtrim(number_format($pTotalAbs,   2), '0'), '.') : '0' ?></td>
                    <td><?= $latePermission ?></td>
                    <td><?= $pLateLop > 0 ? rtrim(rtrim(number_format($pLateLop, 2), '0'), '.') : '0' ?></td>
                    <td><?= $additionalWorking ?></td>
                    <td><?= $pTotalLop > 0 ? rtrim(rtrim(number_format($pTotalLop, 2), '0'), '.') : '0' ?></td>
                    <?php foreach ($payroll['allowances'] as $a): ?>
                        <td><?= isset($empAllowMap[$a->id]) ? rtrim(rtrim(number_format($empAllowMap[$a->id], 2), '0'), '.') : '0' ?></td>
                    <?php endforeach; ?>
                    <?php foreach ($payroll['detections'] as $d): ?>
                        <td><?= isset($empDetMap[$d->id]) ? rtrim(rtrim(number_format($empDetMap[$d->id], 2), '0'), '.') : '0' ?></td>
                    <?php endforeach; ?>
                    <td><?= $currencySymbol ?><?= $grossSalary > 0 ? number_format($grossSalary, 2) : '0' ?></td>
                    <td><?= $currencySymbol ?><?= $grossSalary > 0 ? number_format(round($finalSalary), 0) : '0' ?></td>
                    <td><?= Html::encode($remarksDefault) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
</div><!-- /#main-content .attendance-monthly -->

<?php
// ── Leave Policy popup ────────────────────────────────────────────────────
// Pull the live policy numbers so the document always matches current settings.
$ls        = \backend\models\LeaveSettings::findOne(1);
$graceMin  = $ls ? (int)$ls->check_in_grace_time : 0;
$permCount = $ls && (int)$ls->no_of_permissions > 0 ? (int)$ls->no_of_permissions : 2;
$permDur   = $ls && $ls->duration ? $ls->duration : '02:00:00';
$permHrs   = (int)explode(':', $permDur)[0];
// Representative shift (branch 1) for the example; actual timing is per branch.
$shift     = \backend\models\Shifttime::find()->where(['branch' => 1])->one();
$shiftIn   = $shift && $shift->intime  ? date('g:i A', strtotime($shift->intime))  : '9:00 AM';
$shiftOut  = $shift && $shift->outtime ? date('g:i A', strtotime($shift->outtime)) : '6:30 PM';
$graceEx   = $shift && $shift->intime ? date('g:i A', strtotime($shift->intime) + ($graceMin * 60)) : '9:02 AM';
?>
<!-- Leave Policy Modal -->
<div class="modal fade" id="leavePolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-journal-text"></i> Leave &amp; Attendance Policy</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="font-size:.85rem; line-height:1.55">

                <p class="text-muted">All values below reflect your current configured settings. Examples assume a shift of
                    <strong><?= Html::encode($shiftIn) ?> &ndash; <?= Html::encode($shiftOut) ?></strong> (actual timing is per your branch).</p>

                <!-- Legend -->
                <div class="border rounded p-2 mb-3 d-flex flex-wrap gap-2" style="font-size:.78rem">
                    <span><span class="att-badge" style="background:#047716">P</span> Present</span>
                    <span><span class="att-badge" style="background:#ec971f">OD</span> On Duty</span>
                    <span><span class="att-badge" style="background:#0055ff">LA</span> Late</span>
                    <span><span class="att-badge" style="background:#ff5500">PE</span> Permission</span>
                    <span><span class="att-badge" style="background:#C46ED3">HD</span> Half Day</span>
                    <span><span class="att-badge" style="background:#9400D3">L</span> Leave</span>
                    <span><span class="att-badge" style="background:#5897fb">H</span> Holiday</span>
                    <span><span class="att-badge" style="background:#c3325f">AB</span> Absent</span>
                </div>

                <h6 class="fw-bold text-primary">1. Work Timing &amp; Grace</h6>
                <ul>
                    <li>Standard shift: <strong><?= Html::encode($shiftIn) ?> &ndash; <?= Html::encode($shiftOut) ?></strong>.</li>
                    <?php if ($graceMin > 0): ?>
                    <li>A grace of <strong><?= $graceMin ?> minute(s)</strong> is allowed on check-in. Checking in after
                        <em>shift start + grace</em> is marked <strong>Late (LA)</strong>.</li>
                    <?php else: ?>
                    <li><strong>No grace time</strong> is configured &mdash; any check-in <em>after the shift start time</em>
                        is marked <strong>Late (LA)</strong>.</li>
                    <?php endif; ?>
                    <li>Leaving before shift end is treated as an <strong>early check-out</strong> (evening late).</li>
                    <li><strong>Checking out is compulsory.</strong> If you forget to check out, it is treated as a
                        <strong>late</strong> for that day.</li>
                </ul>
                <?php if ($graceMin > 0): ?>
                <div class="alert alert-light border py-2"><strong>Example:</strong> Shift starts <?= Html::encode($shiftIn) ?>,
                    grace <?= $graceMin ?> min &rarr; check-in up to <strong><?= Html::encode($graceEx) ?></strong> is on time;
                    later is Late.</div>
                <?php else: ?>
                <div class="alert alert-light border py-2"><strong>Example:</strong> Shift starts <?= Html::encode($shiftIn) ?>,
                    no grace time &rarr; any check-in after <strong><?= Html::encode($shiftIn) ?></strong> is marked Late.</div>
                <?php endif; ?>

                <h6 class="fw-bold text-primary">2. Late Arrival &amp; Late LOP</h6>
                <ul>
                    <li>The first <strong>2 lates</strong> in a month are excused (no deduction).</li>
                    <li>After that, <strong>every 2 lates = 0.5 day LOP</strong>
                        &nbsp;(<code>Late LOP = floor((total lates &minus; 2) &divide; 2) &times; 0.5</code>).</li>
                    <li>A single left-over late (that doesn't form a pair) is not charged.</li>
                    <li>A late is excused only by an <strong>approved Permission</strong> for that day (see point 3).</li>
                </ul>
                <div class="alert alert-light border py-2"><strong>Example:</strong> 6 lates in a month &rarr;
                    first 2 excused, 4 remain &rarr; 4 &divide; 2 = 2 pairs &rarr; 2 &times; 0.5 =
                    <strong>1 day LOP</strong>.&nbsp; (5 lates &rarr; 3 remain &rarr; 1 pair = <strong>0.5 day</strong>.)</div>

                <h6 class="fw-bold text-primary">3. Permission (hourly)</h6>
                <ul>
                    <li>Up to <strong><?= $permCount ?> hour(s) of permission per month</strong> &mdash; e.g. one <?= $permHrs ?>-hour
                        permission <em>or</em> two 1-hour permissions.</li>
                    <li>Must be <strong>requested and approved in advance</strong>. Once the monthly limit is used, no further
                        permission can be requested.</li>
                    <li>An approved permission excuses that day's late &rarr; shown as <strong>PE</strong>, no LOP.</li>
                </ul>
                <div class="alert alert-light border py-2"><strong>Example:</strong> Approved 1-hour permission &rarr; a late
                    arrival within the window that day is excused (PE), not counted toward Late LOP.</div>

                <h6 class="fw-bold text-primary">4. Half-Day Leave</h6>
                <ul>
                    <li>Requested through the <strong>Leave</strong> module (Half Day duration + Morning/Evening session) &mdash;
                        no longer a permission type.</li>
                    <li>Costs <strong>half a day</strong> &mdash; debited from your <strong>CL backup</strong> if available,
                        otherwise it is a <strong>0.5 day LOP</strong>. Marked <strong>HD</strong>.</li>
                    <li>A late check-in or early checkout on the <strong>worked half</strong> is excused
                        <strong>only when the half-day leave is approved</strong>; while it is still pending, the usual Late LOP applies.</li>
                </ul>

                <h6 class="fw-bold text-primary">5. Leave (CL) &amp; Absence</h6>
                <ul>
                    <li>Only an <strong>approved leave</strong> counts as Leave (<strong>L</strong>) &mdash; it debits
                        <strong>1 CL</strong> from your backup.</li>
                    <li>If your <strong>CL backup is 0</strong>, the approved leave becomes a <strong>full-day LOP</strong>.</li>
                    <li>An unapproved / un-applied absence is marked <strong>Absent (AB)</strong> = full-day LOP.</li>
                </ul>
                <div class="alert alert-light border py-2"><strong>Example:</strong> Approved leave + CL available &rarr; L, 1 CL
                    debited, no LOP. &nbsp; Approved leave + CL = 0 &rarr; 1 day LOP. &nbsp; No leave applied &rarr; AB, 1 day LOP.</div>

                <h6 class="fw-bold text-primary">6. Comp-Off (working a holiday / Sunday)</h6>
                <ul>
                    <li>If you <strong>punch in on a Sunday or a scheduled holiday</strong>, you earn <strong>+1 CL</strong> in your
                        backup (credited once for that day).</li>
                </ul>
                <div class="alert alert-light border py-2"><strong>Example:</strong> You work on a Sunday &rarr; CL backup
                    increases by 1.</div>

                <h6 class="fw-bold text-primary">7. Sandwich Leave (Holiday / Sunday)</h6>
                <ul>
                    <li>Sundays and scheduled holidays are normally free <strong>Holidays (H)</strong>.</li>
                    <li><strong>Sandwich rule:</strong> a holiday/Sunday is "sandwiched" and counted as
                        <strong>Absent (AB)</strong> when the <strong>day immediately before OR the day immediately after it
                        is an unapproved absence</strong>.</li>
                    <li>If the flanking day is an <strong>approved leave</strong> or you were <strong>present</strong>, it does
                        not bridge &mdash; the holiday stays a <strong>Holiday</strong>.</li>
                    <li>For a run of holidays (e.g. Sat + Sun together), each holiday looks at its own immediate neighbours.</li>
                </ul>
                <div class="alert alert-light border py-2"><strong>Examples:</strong><br>
                    &bull; Fri <em>Absent</em>, Sat Holiday &rarr; Sun counted as <strong>AB</strong>.<br>
                    &bull; Sat Holiday, Mon <em>Absent</em> &rarr; Sun counted as <strong>AB</strong> (next day absent).<br>
                    &bull; Fri <em>Approved Leave</em>, Sat Holiday, Mon <em>Present</em> &rarr; Sun stays <strong>Holiday</strong>.<br>
                    &bull; Fri <em>Present</em>, Sat Holiday, Mon <em>Present</em> &rarr; Sun stays <strong>Holiday</strong>.</div>

            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Remarks Modal -->
<div class="modal fade" id="remarksModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remarks</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="remarksModalBody">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>
            </div>
        </div>
    </div>
</div>

<?php
$updateRemarksUrl = Url::to(['update-remarks']);
$this->registerJs(<<<JS
jQuery('.remarks-btn').on('click', function () {
    var id = jQuery(this).data('id');
    window.__remarksCellId = id;   // remembered so the AJAX submit can flag this cell
    var modal = new bootstrap.Modal(document.getElementById('remarksModal'));
    jQuery('#remarksModalBody').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading...</div>');
    modal.show();
    jQuery.ajax({
        url: '$updateRemarksUrl',
        data: { id: id },
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function (html) { jQuery('#remarksModalBody').html(html); },
        error: function () {
            jQuery('#remarksModalBody').html('<div class="alert alert-danger">Failed to load remarks.</div>');
        }
    });
});
JS, \yii\web\View::POS_READY);

// Auto-submit the filter form on change (debounced) — search-first behaviour.
$this->registerJs(<<<'JS'
(function () {
    var form = document.getElementById('ma-filter-form');
    if (!form) { return; }
    var $form = jQuery(form);
    var timer = null;
    function go() { form.submit(); }
    function debounced() { clearTimeout(timer); timer = setTimeout(go, 500); }

    // Select2 employee dropdown — submit only on a real user pick/clear.
    $form.find('select[name="developer_id"]').on('select2:select select2:unselect select2:clear', go);
    // Native dropdowns (team, branch, year, month) submit immediately on change.
    $form.find('select[name="team"], select[name="branch"], select[name="year"], select[name="month"]').on('change', go);
})();
JS, \yii\web\View::POS_READY);
