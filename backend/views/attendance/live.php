<?php
/** @var \yii\web\View $this */
/** @var array  $rows */
/** @var \backend\models\Project[] $projects */
/** @var int    $totalEmp */
/** @var int    $presentEmp */
/** @var int    $absentEmp */
/** @var int    $workingEmp */
/** @var int    $breakEmp */
/** @var string $nameFilter */
/** @var int    $projectFilter */
/** @var bool   $canViewAllLate */
/** @var int|null $currentDevId */

use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;

$this->title = 'Live Attendance';

// Format a stored datetime to a short time with seconds (e.g. 09:42:05 AM).
$fmtTime = static function (?string $dt): string {
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $ts ? date('h:i:s A', $ts) : '—';
};

// Format a duration in seconds to HH:MM:SS (hours, minutes and seconds).
$fmtDur = static function ($secs): string {
    if ($secs === null) return '—';
    $secs = (int) $secs;
    if ($secs <= 0) return '00:00:00';
    return sprintf('%02d:%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60), $secs % 60);
};

// Status → label + badge colour.
$statusMeta = [
    'working'     => ['label' => 'Working',     'class' => 'st-working', 'row' => ''],
    'break'       => ['label' => 'On Break',    'class' => 'st-break',   'row' => 'row-break'],
    'no_task'     => ['label' => 'No Task',      'class' => 'st-notask',  'row' => 'row-notask'],
    'checked_out' => ['label' => 'Checked Out',  'class' => 'st-out',     'row' => 'row-out'],
    'absent'      => ['label' => 'Absent',       'class' => 'st-absent',  'row' => 'row-absent'],
];
?>
<style>
.live-card{border:0;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
.live-stat{border-radius:12px;padding:.85rem 1.1rem;color:#fff;display:flex;flex-direction:column;gap:.1rem}
.live-stat .num{font-size:1.6rem;font-weight:700;line-height:1}
.live-stat .lbl{font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;opacity:.92}
.live-stat.total{background:linear-gradient(135deg,#4b6cb7,#182848)}
.live-stat.present{background:linear-gradient(135deg,#11998e,#38ef7d)}
.live-stat.working{background:linear-gradient(135deg,#2193b0,#6dd5ed)}
.live-stat.brk{background:linear-gradient(135deg,#f7971e,#ffd200);color:#5b4500}
.live-stat.absent{background:linear-gradient(135deg,#cb2d3e,#ef473a)}
.live-table{width:100%}
.live-table th{font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.live-table td{vertical-align:middle;font-size:.875rem}
/* Full-row background tint per status.
   Bootstrap's .table paints each <td> with its own --bs-table-bg, which sits
   on top of any <tr> background — so the tint MUST be applied to the cells. */
.live-table tbody tr > td{background-color:transparent;transition:background-color .15s}
.live-table tbody tr:hover > td{background-color:#f8f9ff}
.live-table tbody tr.row-break > td{background-color:#fff8ec}
.live-table tbody tr.row-break:hover > td{background-color:#fff0d8}
/* "Checked in but no task added" — flagged with a full red border + red tint
   so it stands out as idle/unaccounted time on the board. */
.live-table tbody tr.row-notask > td{background-color:#fff5f5;border-top:2px solid #e74c3c;border-bottom:2px solid #e74c3c}
.live-table tbody tr.row-notask > td:first-child{border-left:2px solid #e74c3c}
.live-table tbody tr.row-notask > td:last-child{border-right:2px solid #e74c3c}
.live-table tbody tr.row-notask:hover > td{background-color:#ffeaea}
.live-table tbody tr.row-out > td{background-color:#eef3fd}
.live-table tbody tr.row-out:hover > td{background-color:#e0eafc}
.live-table tbody tr.row-absent > td{background-color:#fff5f5}
.live-table tbody tr.row-absent:hover > td{background-color:#ffeaea}
.st-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .55rem;border-radius:50rem;font-size:.72rem;font-weight:600;white-space:nowrap}
.st-badge .dot{width:.55rem;height:.55rem;border-radius:50%;display:inline-block}
.st-working{background:#e3f7ee;color:#0f7b53}.st-working .dot{background:#16b673;animation:pulse 1.4s infinite}
.st-break{background:#fff4e0;color:#9a6700}.st-break .dot{background:#f5a623}
.st-notask{background:#fdecec;color:#c0392b}.st-notask .dot{background:#e74c3c}
.st-out{background:#e7eefc;color:#2a52be}.st-out .dot{background:#3b6fe0}
.st-absent{background:#fdecec;color:#c0392b}.st-absent .dot{background:#e74c3c}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(22,182,115,.5)}70%{box-shadow:0 0 0 6px rgba(22,182,115,0)}100%{box-shadow:0 0 0 0 rgba(22,182,115,0)}}
/* Filter bar — labelled fields with the input row bottom-aligned to the buttons */
#live-filter-form .filter-field{flex:1 1 240px;min-width:200px;max-width:360px}
#live-filter-form .form-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;margin-bottom:.25rem}
#live-filter-form .form-control-sm,
#live-filter-form .btn-sm,
#live-filter-form .select2-selection--single{height:32px !important;min-height:32px !important}
#live-filter-form .select2-selection--single{display:flex;align-items:center}
#live-filter-form .btn-sm{display:inline-flex;align-items:center}
#live-filter-form .select2-selection--single .select2-selection__rendered{line-height:30px !important;padding-top:0;padding-bottom:0}
#live-filter-form .select2-selection--single .select2-selection__arrow,
#live-filter-form .select2-selection--single .select2-selection__clear{height:30px !important;top:1px}
#live-filter-form .select2-container{display:block;margin:0}
.late-pill{display:inline-block;min-width:1.6rem;padding:.1rem .45rem;border-radius:50rem;font-size:.78rem;font-weight:700;background:#fdecec;color:#c0392b}
.late-pill--total{background:#eef0f3;color:#5a6472}
.excuse-pill{display:inline-block;min-width:1.6rem;padding:.1rem .45rem;border-radius:50rem;font-size:.78rem;font-weight:700;background:#e3f7ee;color:#0f7b53}
.excuse-pill--total{background:#eef0f3;color:#5a6472}
.live-dot{width:.6rem;height:.6rem;border-radius:50%;background:#e74c3c;display:inline-block;animation:pulse-red 1.3s infinite}
@keyframes pulse-red{0%{box-shadow:0 0 0 0 rgba(231,76,60,.6)}70%{box-shadow:0 0 0 7px rgba(231,76,60,0)}100%{box-shadow:0 0 0 0 rgba(231,76,60,0)}}
</style>

<div class="ui-page attendance-live">

    <div class="ui-head">
        <div>
            <h1>Live Attendance</h1>
            <div class="ui-sub">as of <span id="live-clock"><?= date('h:i:s A') ?></span></div>
        </div>
        <div class="ui-actions">
            <span class="live-dot"></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="refresh-now">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Headline counts -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md">
            <div class="live-stat total"><span class="num"><?= $totalEmp ?></span><span class="lbl">Total Employees</span></div>
        </div>
        <div class="col-6 col-md">
            <div class="live-stat present"><span class="num"><?= $presentEmp ?></span><span class="lbl">Present</span></div>
        </div>
        <div class="col-6 col-md">
            <div class="live-stat working"><span class="num"><?= $workingEmp ?></span><span class="lbl">Working Now</span></div>
        </div>
        <div class="col-6 col-md">
            <div class="live-stat brk"><span class="num"><?= $breakEmp ?></span><span class="lbl">On Break</span></div>
        </div>
        <div class="col-6 col-md">
            <div class="live-stat absent"><span class="num"><?= $absentEmp ?></span><span class="lbl">Absent</span></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card live-card mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="d-flex flex-wrap align-items-end gap-3" id="live-filter-form">
                <div class="filter-field">
                    <label class="form-label" for="filter-name">Employee</label>
                    <?= Html::textInput('q', $nameFilter, [
                        'class'       => 'form-control form-control-sm',
                        'placeholder' => 'Search employee name…',
                        'id'          => 'filter-name',
                    ]) ?>
                </div>
                <div class="filter-field">
                    <label class="form-label" for="filter-project">Project</label>
                    <?= Select2::widget([
                        'name'          => 'project_id',
                        'value'         => $projectFilter ?: '',
                        'data'          => ['' => 'All Projects'] + ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'All Projects', 'id' => 'filter-project'],
                        'size'          => Select2::SMALL,
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i> Filter</button>
                    <?php if ($nameFilter !== '' || $projectFilter): ?>
                    <?= Html::a('<i class="fas fa-times me-1"></i> Clear', ['live'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Listing -->
    <div class="card live-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table live-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:42px">#</th>
                            <th>Employee</th>
                            <th class="text-nowrap">Check In</th>
                            <th class="text-nowrap">Check Out</th>
                            <th>Current Project</th>
                            <th>Current Task</th>
                            <th class="text-center">Task Duration</th>
                            <th class="text-center">Break Duration</th>
                            <th class="text-center">Late<br><small class="text-muted">(Month)</small></th>
                            <th class="text-center">Late<br><small class="text-muted">(Total)</small></th>
                            <th class="text-center">Excuses<br><small class="text-muted">(Month)</small></th>
                            <th class="text-center">Excuses<br><small class="text-muted">(Total)</small></th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="13" class="text-center text-muted py-4">No employees match the current filters.</td></tr>
                    <?php else: $i = 1; foreach ($rows as $r):
                        $meta = $statusMeta[$r['status']] ?? $statusMeta['no_task'];
                        // Show late/excuse figures only when the viewer may see all,
                        // or when this row is the viewer's own. Others stay hidden.
                        $showLate = $canViewAllLate || ($currentDevId !== null && (int) $r['developerId'] === (int) $currentDevId);
                    ?>
                        <tr class="<?= $meta['row'] ?? '' ?>">
                            <td class="text-muted"><?= $i++ ?></td>
                            <td>
                                <div class="fw-semibold"><?= Html::encode($r['name']) ?></div>
                                <?php if (!empty($r['designation'])): ?>
                                <div class="small text-muted"><?= Html::encode($r['designation']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= $fmtTime($r['checkIn']) ?></td>
                            <td class="text-nowrap"><?= $fmtTime($r['checkOut']) ?></td>
                            <td><?= $r['project'] ? Html::encode($r['project']) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= $r['task'] ? Html::encode($r['task']) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-center"><?= $fmtDur($r['taskSecs']) ?></td>
                            <td class="text-center"><?= $fmtDur($r['breakSecs']) ?></td>
                            <td class="text-center">
                                <?php if (!$showLate): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($r['lateMonth'] > 0): ?>
                                    <span class="late-pill"><?= (int) $r['lateMonth'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!$showLate): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($r['lateTotal'] > 0): ?>
                                    <span class="late-pill late-pill--total"><?= (int) $r['lateTotal'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!$showLate): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($r['excuseMonth'] > 0): ?>
                                    <span class="excuse-pill"><?= (int) $r['excuseMonth'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (!$showLate): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($r['excuseTotal'] > 0): ?>
                                    <span class="excuse-pill excuse-pill--total"><?= (int) $r['excuseTotal'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="st-badge <?= $meta['class'] ?>"><span class="dot"></span><?= $meta['label'] ?></span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
(function () {
    // "as of" time is the page-load time (static) — refresh to update.
    jQuery('#refresh-now').on('click', function(){ window.location.reload(); });
})();
JS, \yii\web\View::POS_READY);
?>
