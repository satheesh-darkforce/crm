<?php
/** @var \yii\web\View $this */
/** @var \backend\models\AssetMaintenance $model */

use backend\models\AssetMaintenance;
use yii\bootstrap5\Html;

$this->title = 'Maintenance Detail';

$ptLabels = AssetMaintenance::paymentTypeLabels();
$pmLabels = AssetMaintenance::paymentModeLabels();
?>
<div class="maintenance-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Maintenance Record #<?= $model->id ?></h5>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update-maintenance', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['maintenance'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3" style="font-size:.875rem">
                <div class="col-md-4">
                    <span class="text-muted d-block">Asset</span>
                    <strong><?= Html::encode($model->asset->product_name ?? '—') ?></strong>
                    <?php if ($model->asset && $model->asset->barcode): ?>
                        <div class="text-muted small" style="font-family:monospace"><?= Html::encode($model->asset->barcode) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Service Type / Provider</span>
                    <?= Html::encode($model->service_provider ?? '—') ?>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Service Agent Name</span>
                    <?= Html::encode($model->service_provider_name ?? '—') ?>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Contact Number</span>
                    <?= Html::encode($model->sp_number ?? '—') ?>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Attended By</span>
                    <?= Html::encode($model->developer->developer_name ?? '—') ?>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Payment Type</span>
                    <span class="badge bg-<?= (int)$model->payment_type === 2 ? 'warning text-dark' : 'success' ?>">
                        <?= $ptLabels[$model->payment_type] ?? '—' ?>
                    </span>
                </div>
                <?php if ((int)$model->payment_type === 2): ?>
                <div class="col-md-4">
                    <span class="text-muted d-block">Service Cost</span>
                    <strong>₹<?= number_format((float)$model->service_cost, 2) ?></strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted d-block">Payment Mode</span>
                    <?= $pmLabels[$model->payment_mode] ?? '—' ?>
                </div>
                    <?php if ((int)$model->payment_mode === 2 && $model->gpay_number): ?>
                    <div class="col-md-4">
                        <span class="text-muted d-block">GPay Number</span>
                        <?= Html::encode($model->gpay_number) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ((int)$model->payment_mode === 3 && $model->bank_number): ?>
                    <div class="col-md-4">
                        <span class="text-muted d-block">Bank Account Details</span>
                        <?= Html::encode($model->bank_number) ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="col-md-4">
                    <span class="text-muted d-block">Invoice Bill</span>
                    <?php if ($model->invoice_bill): ?>
                        <a href="<?= \backend\controllers\Controller::uploadUrl($model->invoice_bill) ?>" target="_blank" class="text-primary">View Bill</a>
                    <?php else: ?>
                        <span class="text-muted">No bill uploaded</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
