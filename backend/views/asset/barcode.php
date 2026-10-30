<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Asset $model */

use yii\bootstrap5\Html;

$this->title = 'Barcode — ' . $model->product_name;
?>
<style>
@media print {
    .no-print { display: none !important; }
    body { background: #fff; }
    .barcode-card { box-shadow: none !important; border: 1px solid #ccc !important; }
}
.barcode-strip { font-family: 'Libre Barcode 128', 'Courier New', monospace; font-size: 3.5rem; letter-spacing: .05em; line-height: 1; }
</style>

<!-- Load Google barcode font from CDN if available (graceful fallback to code display) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">

<div class="asset-barcode">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h5 class="fw-semibold mb-0">Asset Barcode</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-times"></i> Print</button>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="d-flex justify-content-center">
        <div class="card border shadow-sm barcode-card" style="width:320px">
            <div class="card-body text-center py-4">
                <div class="mb-1 small text-muted fw-semibold text-uppercase" style="letter-spacing:.08em">
                    <?= Html::encode($model->product_name) ?>
                </div>
                <?php if ($model->model): ?>
                <div class="mb-2 small text-muted"><?= Html::encode($model->model) ?></div>
                <?php endif; ?>
                <!-- Barcode strip using font rendering -->
                <div class="barcode-strip my-3"><?= Html::encode($model->barcode) ?></div>
                <!-- Human-readable code below bars -->
                <div class="fw-bold" style="font-size:.95rem;letter-spacing:.12em;font-family:monospace">
                    <?= Html::encode($model->barcode) ?>
                </div>
                <?php if ($model->brand): ?>
                <div class="mt-2 small text-muted"><?= Html::encode($model->brand->brand_name) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="text-center mt-3 no-print">
        <small class="text-muted">Asset ID: <?= $model->id ?> &mdash; Barcode: <code><?= Html::encode($model->barcode) ?></code></small>
    </div>
</div>
