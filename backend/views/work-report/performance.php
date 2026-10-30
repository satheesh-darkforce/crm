<?php
/** @var \yii\web\View $this */
/** @var array[] $rows          [{developer_id, total_secs, days_worked, entry_count, project_count}] */
/** @var array $devMap          [id => Developer] */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int|null $devFilter */
/** @var \backend\models\Developer[] $developers */
/** @var int $workingDays */
/** @var int $expectedSecsPerDay  (8 * 60) */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$this->title = 'Performance report';

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '0:00';

$expectedTotal = $workingDays * $expectedSecsPerDay;

// Colour thresholds: ≥80% green, 50-79% yellow, <50% red
$perfBadge = function(float $pct): string {
    if ($pct >= 80) return 'bg-success-subtle text-success';
    if ($pct >= 50) return 'bg-warning-subtle text-warning';
    return 'bg-danger-subtle text-danger';
};

// Build export URL preserving the current filter set (Yii1 parity — uses daily export).
$exportUrl = Url::to(array_filter([
    '/work-report/export-daily-work-report',
    'from_date'    => $fromDate,
    'to_date'      => $toDate,
    'developer_id' => $devFilter,
], fn($v) => $v !== null && $v !== ''));
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.perf-bar{height:8px;border-radius:4px;background:#e9ecef}
.perf-bar .fill{height:100%;border-radius:4px}
</style>

<div class="work-report-performance">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold">Performance Report</h5>
            <small class="text-muted">
                <?= date('d M Y', strtotime($fromDate)) ?> – <?= date('d M Y', strtotime($toDate)) ?>
                &nbsp;&bull;&nbsp; <?= $workingDays ?> working days &nbsp;&bull;&nbsp; Expected: <?= $fmt($expectedTotal) ?> hrs / person
            </small>
        </div>
        <?php if (UserAccess::checkAccess('work-report/export-daily-work-report')): ?>
        <?= Html::a('<i class="fas fa-file-excel me-1"></i> Export to Excel', $exportUrl, ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => $devFilter,
                        'data'          => ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $fromDate,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-from-date', 'placeholder' => 'From', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $toDate,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-to-date', 'placeholder' => 'To', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                    <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" id="filter-btn">Filter</button>
                    <?= Html::a('Clear', ['performance'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
            </form>
<script>
(function(){var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}$f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});$t.on('changeDate change',v);$f.closest('form').on('submit',function(){return v();});}());
</script>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Employee</th>
                        <th class="text-center">Days Logged</th>
                        <th class="text-center">Projects</th>
                        <th class="text-center">Entries</th>
                        <th class="text-center">Total Hours</th>
                        <th class="text-center">Avg / Day</th>
                        <th style="min-width:160px">Performance</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No records found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $row): ?>
                    <?php
                        $dev     = $devMap[$row['developer_id']] ?? null;
                        $secs    = (int)$row['total_secs'];
                        $daysWkd = (int)$row['days_worked'];
                        $avgDay  = $daysWkd > 0 ? intdiv($secs, $daysWkd) : 0;
                        $pct     = $expectedTotal > 0 ? min(100, round($secs / $expectedTotal * 100, 1)) : 0;
                        $barColor = $pct >= 80 ? '#198754' : ($pct >= 50 ? '#ffc107' : '#dc3545');
                    ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= Html::encode($dev ? $dev->developer_name : '—') ?></td>
                        <td class="text-center">
                            <span class="badge bg-secondary-subtle text-secondary"><?= $daysWkd ?> / <?= $workingDays ?></span>
                        </td>
                        <td class="text-center text-muted"><?= (int)$row['project_count'] ?></td>
                        <td class="text-center text-muted"><?= (int)$row['entry_count'] ?></td>
                        <td class="text-center fw-semibold text-primary"><?= $fmt($secs) ?></td>
                        <td class="text-center text-muted small"><?= $fmt($avgDay) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="perf-bar flex-grow-1">
                                    <div class="fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
                                </div>
                                <span class="badge <?= $perfBadge($pct) ?>" style="font-size:.75rem;min-width:48px">
                                    <?= number_format($pct, 1) ?>%
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($rows)): ?>
        <div class="card-footer bg-white border-top py-2 px-3" style="font-size:.78rem;color:#6c757d">
            Performance % = (Total hours logged &divide; <?= $workingDays ?> working days &times; 8 hrs). Thresholds: <span class="text-success">≥80% Good</span>, <span class="text-warning">50–79% Average</span>, <span class="text-danger">&lt;50% Low</span>.
        </div>
        <?php endif; ?>
    </div>
</div>
