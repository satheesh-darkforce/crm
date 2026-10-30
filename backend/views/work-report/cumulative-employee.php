<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var int   $grandTotal */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int|null $devFilter */
/** @var int|null $projFilter */
/** @var int|null $cliFilter */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[]   $projects */
/** @var \backend\models\ClientModel[] $clients */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Employee based cumulative report';

// total_time stored in SECONDS
$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '00:00';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

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
.work-report-cumulative-employee .table-responsive{overflow-x:auto!important}
.cumulative-total-row{background:linear-gradient(90deg,#e8f5e9 0,#fff8e1 100%);border-top:2px solid #198754!important}
</style>

<div class="work-report-cumulative-employee">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
        <?php if (UserAccess::checkAccess('work-report/export-developer-cumulative-report')): ?>
        <?= Html::a('<i class="fas fa-file-excel me-1"></i> Export to Excel', $exportUrl, ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">From Date</label>
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $fromDate,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'From date', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">To Date</label>
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $toDate,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'To date', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Employee</label>
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => $devFilter,
                        'data'          => ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small mb-1">Project</label>
                    <?= Select2::widget([
                        'name'          => 'project_id',
                        'value'         => $projFilter,
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'All Projects'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small mb-1">Client</label>
                    <?= Select2::widget([
                        'name'          => 'client_id',
                        'value'         => $cliFilter,
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button class="btn btn-secondary btn-sm flex-fill">Filter</button>
                </div>
                <div class="col-12">
                    <?= Html::a('Clear All', ['cumulative-employee'], ['class' => 'btn btn-link btn-sm py-0 px-1']) ?>
                </div>
            </form>
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
                        <th>Project</th>
                        <th>Client</th>
                        <th class="text-center">Total Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $row): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold"><?= Html::encode($row['developer_name'] ?? '—') ?></td>
                        <td><?= Html::encode($row['project_title'] ?? '—') ?></td>
                        <td class="text-muted"><?= Html::encode($row['client_name'] ?? '—') ?></td>
                        <td class="text-center fw-semibold"><?= $fmt((int)($row['total_secs'] ?? 0)) ?></td>
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
        <?php if ($grandTotal > 0): ?>
        <div class="card-footer cumulative-total-row d-flex justify-content-between align-items-center py-3 border-top">
            <span class="fs-6 fw-bold text-uppercase" style="letter-spacing:.05em">Total</span>
            <span class="fs-5 fw-bold text-success"><?= $fmt($grandTotal) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
