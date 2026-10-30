<?php
/** @var \yii\web\View $this */
/** @var array[] $reportData   [{project_id, project_name, client_name, total_secs, developers:[{name,secs,pct}]}] */
/** @var int   $grandTotal */
/** @var string|null $fromDate */
/** @var string|null $toDate */
/** @var array $devIds */
/** @var array $projIds */
/** @var array $cliIds */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[]   $projects */
/** @var \backend\models\ClientModel[] $clients */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Overall report';

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.project-header td{background:#f0f4ff;font-weight:600}
.dev-row td{background:#fff;font-size:.84rem;padding-left:2.5rem!important}
.progress{height:6px;border-radius:3px}
</style>

<div class="work-report-overall">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Overall Report</h5>
            <?php if ($fromDate && $toDate): ?>
            <small class="text-muted"><?= date('d M Y', strtotime($fromDate)) ?> – <?= date('d M Y', strtotime($toDate)) ?></small>
            <?php else: ?>
            <small class="text-muted">Select a date range to generate the report</small>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($grandTotal > 0): ?>
            <span class="badge bg-primary-subtle text-primary border" style="font-size:.85rem">
                Grand Total: <?= $fmt($grandTotal) ?> hrs
            </span>
            <?php endif; ?>
            <?php if ($fromDate && $toDate && !empty($reportData) && UserAccess::checkAccess('work-report/export-overall-report')): ?>
            <?= Html::a('Export to Excel', \yii\helpers\Url::to(array_filter([
                '/work-report/export-overall-report',
                'from_date'    => $fromDate,
                'to_date'      => $toDate,
                'developer_id' => $devIds,
                'project_id'   => $projIds,
            ])), ['class' => 'btn btn-success btn-sm']) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">From Date <span class="text-danger">*</span></label>
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $fromDate ?? '',
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-from-date', 'placeholder' => 'From', 'class' => 'form-control form-control-sm', 'required' => true],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">To Date <span class="text-danger">*</span></label>
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $toDate ?? '',
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-to-date', 'placeholder' => 'To', 'class' => 'form-control form-control-sm', 'required' => true],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                    <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Employees</label>
                    <?= Select2::widget([
                        'name'          => 'developer_id[]',
                        'value'         => (array)$devIds,
                        'data'          => ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Employees', 'multiple' => true],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Projects</label>
                    <?= Select2::widget([
                        'name'          => 'project_id[]',
                        'value'         => (array)$projIds,
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'All Projects', 'multiple' => true],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Clients</label>
                    <?= Select2::widget([
                        'name'          => 'client_id[]',
                        'value'         => (array)($cliIds ?? []),
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'All Clients', 'multiple' => true],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm">Generate</button>
                    <?php if ($fromDate): ?>
                    <?= Html::a('Clear', ['overall'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
<script>
(function(){var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}$f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});$t.on('changeDate change',v);$f.closest('form').on('submit',function(){return v();});}());
</script>
        </div>
    </div>

    <?php if ($fromDate && $toDate): ?>

    <?php if (empty($reportData)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">No records found for the selected filters.</div>
    </div>
    <?php else: ?>

    <!-- Chart: per-project hours bar chart (inline SVG, no external lib) -->
    <?php if (!empty($reportData) && $grandTotal > 0):
        $chartRows = $reportData;
        usort($chartRows, fn($a, $b) => $b['total_secs'] <=> $a['total_secs']);
        $chartRows = array_slice($chartRows, 0, 12);
        $maxSecs   = max(array_column($chartRows, 'total_secs')) ?: 1;
        $palette   = ['#4f46e5','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16','#0ea5e9'];
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><span class="fw-semibold">Top Projects by Hours</span></div>
        <div class="card-body py-3">
            <div style="display:grid;gap:.5rem">
                <?php foreach ($chartRows as $i => $row):
                    $width = max(2, round($row['total_secs'] / $maxSecs * 100, 2));
                    $color = $palette[$i % count($palette)];
                    $hrs   = round($row['total_secs'] / 3600, 1);
                ?>
                <div class="d-flex align-items-center gap-2">
                    <div class="text-truncate small text-muted" style="width:200px;flex-shrink:0" title="<?= Html::encode($row['project_name']) ?>">
                        <?= Html::encode($row['project_name']) ?>
                    </div>
                    <div style="flex:1;background:#f1f5f9;border-radius:4px;height:18px;position:relative">
                        <div style="width:<?= $width ?>%;background:<?= $color ?>;height:100%;border-radius:4px;transition:width .3s"></div>
                        <span style="position:absolute;right:6px;top:1px;font-size:.72rem;font-weight:600;color:#1e293b"><?= $hrs ?>h</span>
                    </div>
                    <div class="small fw-semibold text-end" style="width:80px;flex-shrink:0"><?= $fmt((int)$row['total_secs']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($reportData) > 12): ?>
            <small class="text-muted d-block mt-2">Top 12 of <?= count($reportData) ?> projects shown.</small>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Project cards -->
    <?php foreach ($reportData as $idx => $proj): ?>
    <div class="card border-0 shadow-sm mb-3">
        <!-- Project header -->
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
            <div>
                <span class="badge bg-secondary-subtle text-secondary me-2" style="font-size:.72rem"><?= $idx + 1 ?></span>
                <span class="fw-semibold"><?= Html::encode($proj['project_name']) ?></span>
                <span class="text-muted small ms-2">— <?= Html::encode($proj['client_name']) ?></span>
            </div>
            <span class="badge bg-primary-subtle text-primary border" style="font-size:.82rem">
                <?= $fmt($proj['total_secs']) ?> hrs
            </span>
        </div>
        <!-- Developer breakdown -->
        <div class="card-body p-0">
            <table class="table mb-0" style="font-size:.84rem">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="font-size:.72rem;color:#6c757d">Employee</th>
                        <th style="font-size:.72rem;color:#6c757d;width:200px">Contribution</th>
                        <th class="text-center" style="font-size:.72rem;color:#6c757d;width:90px">Hours</th>
                        <th class="text-center" style="font-size:.72rem;color:#6c757d;width:60px">%</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($proj['developers'] as $dev): ?>
                <tr>
                    <td class="ps-3"><?= Html::encode($dev['name']) ?></td>
                    <td>
                        <div class="progress">
                            <div class="progress-bar bg-primary" style="width:<?= min(100, $dev['pct']) ?>%"></div>
                        </div>
                    </td>
                    <td class="text-center fw-semibold text-primary"><?= $fmt($dev['secs']) ?></td>
                    <td class="text-center text-muted"><?= number_format($dev['pct'], 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Grand total -->
    <div class="d-flex justify-content-end mb-3">
        <div class="card border-0 shadow-sm px-4 py-2">
            <span class="text-muted small">Grand Total</span>
            <span class="fw-bold text-primary fs-5"><?= $fmt($grandTotal) ?> hrs</span>
        </div>
    </div>

    <?php endif; ?>
    <?php endif; ?>
</div>
