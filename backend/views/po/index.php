<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $clientOptions */
/** @var array $filters */

use backend\models\PoDetail;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\LinkPager;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

$this->title = 'Purchase Orders';

$statusBadge = static function (int $s): string {
    $map = [
        PoDetail::STATUS_PENDING     => ['Pending',     'bg-warning text-dark'],
        PoDetail::STATUS_IN_PROGRESS => ['In Progress', 'bg-info text-dark'],
        PoDetail::STATUS_DELIVERED   => ['Delivered',   'bg-success'],
        PoDetail::STATUS_CANCELLED   => ['Cancelled',   'bg-danger'],
    ];
    [$label, $cls] = $map[$s] ?? ['—', 'bg-secondary'];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
};
?>
<style>
.po-list-wrap { padding:0; }
.po-list-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px 20px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04); }
.po-list-head { padding-bottom:14px; margin-bottom:14px; border-bottom:2px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
.po-list-head h4{ margin:0; font-weight:700; color:#1e293b; }
.po-list-card .list-table { width:100%; border-collapse:separate; border-spacing:0; font-size:.85rem; }
.po-list-card .list-table thead th { background:#f1f5f9; color:#334155; font-weight:600; font-size:.7rem; text-transform:uppercase; letter-spacing:.4px; padding:7px 8px; border-bottom:1px solid #e2e8f0; text-align:left; white-space:nowrap; }
.po-list-card .list-table tbody td { padding:7px 8px; border-bottom:1px solid #f1f5f9; color:#1e293b; vertical-align:middle; font-size:.8rem; }
.po-list-card .list-table tbody tr:last-child td { border-bottom:0; }
.po-list-card .list-table tbody tr:hover { background:#f8fafc; }
.po-list-card .filter-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; margin-bottom:14px; }
.po-list-card .filter-card .form-label { font-size:.72rem; color:#64748b; font-weight:600; margin-bottom:3px; }
</style>

<div class="po-list-wrap">
    <div class="po-list-card">
        <div class="po-list-head">
            <h4><i class="fas fa-list me-2 text-primary"></i> Purchase Orders</h4>
            <small class="text-muted"><?= (int) $dataProvider->getTotalCount() ?> total</small>
        </div>

        <?php Pjax::begin([
            'id'              => 'po-list-pjax',
            'timeout'         => 5000,
            'enablePushState' => true,
            'linkSelector'    => '#po-list-pjax .pagination a, #po-list-pjax .btn-clear-filters',
            'formSelector'    => '#po-filter-form',
        ]); ?>

        <!-- Filters -->
        <div class="filter-card">
            <?php $form = ActiveForm::begin([
                'id'       => 'po-filter-form',
                'method'   => 'get',
                'action'   => ['index'],
                'options'  => ['data-pjax' => '1'],
            ]); ?>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-lg-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="<?= Html::encode($filters['search'] ?? '') ?>" class="form-control form-control-sm" placeholder="PO number, particulars or company…">
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label">Client</label>
                    <?= Select2::widget([
                        'name' => 'client_id',
                        'value' => (int) ($filters['client_id'] ?? 0) ?: null,
                        'data' => $clientOptions,
                        'options' => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach (PoDetail::statusOptions() as $k => $v): ?>
                            <option value="<?= (int) $k ?>" <?= (string) ($filters['status'] ?? '') === (string) $k ? 'selected' : '' ?>>
                                <?= Html::encode($v) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-12 col-lg-3 d-flex gap-1">
                    <?= Html::submitButton('Filter', ['class' => 'btn btn-secondary btn-sm']) ?>
                    <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm btn-clear-filters']) ?>
                </div>
            </div>
            <?php ActiveForm::end(); ?>
        </div>

        <div class="table-responsive">
            <table class="list-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>PO Number</th>
                        <th>PO Date</th>
                        <th>Client</th>
                        <th>Particulars</th>
                        <th class="text-end">Value (₹)</th>
                        <th>Delivery Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $pagination = $dataProvider->getPagination();
                $offset     = $pagination ? $pagination->getOffset() : 0;
                ?>
                <?php foreach ($dataProvider->getModels() as $i => $po): /** @var PoDetail $po */
                    $client     = $po->getEffectiveClient();
                    $clientName = $client->client_name   ?? '';
                    $companyNm  = $client->company_name  ?? '';
                ?>
                    <tr>
                        <td class="text-muted"><?= $offset + $i + 1 ?></td>
                        <td><strong><?= Html::encode($po->po_number) ?></strong></td>
                        <td><?= $po->date ? date('d M Y', strtotime($po->date)) : '—' ?></td>
                        <td>
                            <?php if ($clientName !== ''): ?>
                                <div class="fw-semibold"><?= Html::encode($clientName) ?></div>
                                <?php if ($companyNm !== ''): ?>
                                    <div class="small text-muted"><?= Html::encode($companyNm) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:260px; white-space:pre-line;"><?= Html::encode($po->particulars) ?></td>
                        <td class="text-end"><?= Html::encode(number_format((float) $po->po_cost, 0)) ?></td>
                        <td><?= $po->delivery_date ? date('d M Y', strtotime($po->delivery_date)) : '—' ?></td>
                        <td><?= $statusBadge((int) $po->po_status) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$dataProvider->getCount()): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No purchase orders found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="small text-muted">
                <?php
                $total = (int) $dataProvider->getTotalCount();
                $count = (int) $dataProvider->getCount();
                $from  = $count ? $offset + 1 : 0;
                $to    = $offset + $count;
                echo $count
                    ? "Showing {$from}–{$to} of {$total}"
                    : 'No records';
                ?>
            </div>
            <?= LinkPager::widget([
                'pagination' => $dataProvider->pagination,
                'options' => ['class' => 'pagination pagination-sm mb-0'],
            ]) ?>
        </div>

        <?php Pjax::end(); ?>
    </div>
</div>
