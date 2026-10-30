<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var int   $grandTotal   (in seconds) */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int|null $cliFilter */
/** @var int|null $projFilter */
/** @var \backend\models\ClientModel[] $clients */
/** @var \backend\models\Project[] $projects */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Client based cumulative report';

// total_time is stored in SECONDS — format as HH:MM
$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '00:00';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$hasDateFilter = !empty($fromDate) || !empty($toDate);

// Build export URL preserving the current filter set (Yii1 parity).
$exportUrl = Url::to(array_filter([
    '/work-report/export-client-cumulative-report',
    'from_date'  => $fromDate,
    'to_date'    => $toDate,
    'client_id'  => $cliFilter,
    'project_id' => $projFilter,
], fn($v) => $v !== null && $v !== ''));
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
</style>

<div class="work-report-cumulative-client">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
        <?php if (UserAccess::checkAccess('work-report/export-client-cumulative-report')): ?>
        <?= Html::a('<i class="fas fa-file-excel me-1"></i> Export to Excel', $exportUrl, ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'client_id',
                        'value'         => $cliFilter,
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'All Clients', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'project_id',
                        'value'         => $projFilter,
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'All Projects', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $fromDate,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'From date', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $toDate,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'To date', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?= Html::a('Clear', ['cumulative-client'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Client</th>
                        <th>Project</th>
                        <th class="text-center">Total Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-5">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $row):
                        $secs = (int)($row['total_secs'] ?? 0);
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold"><?= Html::encode($row['client_name'] ?? '—') ?></td>
                        <td><?= Html::encode($row['title'] ?? '—') ?></td>
                        <td class="text-center fw-semibold"><?= $fmt($secs) ?></td>
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
        <div class="card-footer bg-light d-flex justify-content-between align-items-center py-2 border-top">
            <span class="fw-semibold">Total</span>
            <span class="fw-bold"><?= $fmt($grandTotal) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>
