<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[]   $projects */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $yesterday */
/** @var ?string $fromDate */
/** @var ?string $toDate */
/** @var ?string $devFilter */
/** @var ?string $projFilter */
/** @var ?string $cliFilter */
/** @var int $grandTotalSecs */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = "Employee yesterday's work report";

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$totalSecs = 0;
foreach ($models as $m) { $totalSecs += (int)$m->total_time; }

// Group by developer for summary
$devSummary = [];
foreach ($models as $m) {
    $devName = $m->developer->developer_name ?? '—';
    $devSummary[$devName] = ($devSummary[$devName] ?? 0) + (int)$m->total_time;
}

// Build export URL preserving the current filter set (Yii1 parity).
$exportUrl = Url::to(array_filter([
    '/work-report/export-developer-cumulative-report',
    'from_date'    => $fromDate,
    'to_date'      => $toDate,
    'developer_id' => $devFilter,
    'project_id'   => $projFilter,
    'client_id'    => $cliFilter,
], fn($v) => $v !== null && $v !== ''));
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="work-report-yesterday">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Yesterday's Reports</h5>
            <small class="text-muted"><?= date('d M Y', strtotime($yesterday)) ?> &mdash; <?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border" style="font-size:.82rem">
                Total Time: <?= $fmt((int) $grandTotalSecs) ?> hrs
            </span>
            <span class="badge bg-primary-subtle text-primary border" style="font-size:.82rem">
                Current Page Timing: <?= $fmt($totalSecs) ?> hrs
            </span>
            <?php if (UserAccess::checkAccess('work-report/export-developer-cumulative-report')): ?>
            <?= Html::a('<i class="fas fa-file-excel me-1"></i> Export to Excel', $exportUrl, ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters: from/to dates, developer, project, client (Yii1 parity) -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">From Date</label>
                    <?= DatePicker::widget([
                        'name' => 'from_date', 'value' => $fromDate,
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['placeholder' => 'From', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">To Date</label>
                    <?= DatePicker::widget([
                        'name' => 'to_date', 'value' => $toDate,
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['placeholder' => 'To', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Employee</label>
                    <?= Select2::widget([
                        'name' => 'developer_id', 'value' => $devFilter,
                        'data' => ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options' => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small mb-1">Project</label>
                    <?= Select2::widget([
                        'name' => 'project_id', 'value' => $projFilter,
                        'data' => ArrayHelper::map($projects, 'id', 'title'),
                        'options' => ['placeholder' => 'All Projects'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small mb-1">Client</label>
                    <?= Select2::widget([
                        'name' => 'client_id', 'value' => $cliFilter,
                        'data' => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options' => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button class="btn btn-secondary btn-sm flex-fill">Filter</button>
                </div>
                <div class="col-12">
                    <small class="text-muted">Default shows yesterday's reports. Set From/To to override.</small>
                    <?= Html::a('Reset', ['yesterday'], ['class' => 'btn btn-link btn-sm py-0 px-1']) ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($devSummary)): ?>
    <!-- Developer Summary -->
    <div class="row g-2 mb-3">
        <?php foreach ($devSummary as $devName => $secs): ?>
        <div class="col-auto">
            <div class="card border-0 shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
                <span class="fw-semibold small"><?= Html::encode($devName) ?></span>
                <span class="badge bg-primary-subtle text-primary"><?= $fmt($secs) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Developer</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Task</th>
                        <th class="text-center">Time</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No work reports for yesterday.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $wr): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td><span class="fw-semibold"><?= Html::encode($wr->developer->developer_name ?? '—') ?></span></td>
                        <td class="text-muted small"><?= Html::encode($wr->client->client_name ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($wr->project->title ?? '—') ?></td>
                        <td class="small" style="max-width:260px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= Html::encode($wr->task ?? '') ?>">
                                <?= Html::encode($wr->task ?? '—') ?>
                            </div>
                        </td>
                        <td class="text-center fw-semibold <?= $wr->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                            <?= $fmt((int)$wr->total_time) ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:140px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $wr->work_id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
