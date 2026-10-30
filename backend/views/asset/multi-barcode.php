<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Asset[] $models */

use yii\bootstrap5\Html;

$this->title = 'Print Multiple Barcodes';
?>
<style>
@media print {
    .no-print { display: none !important; }
    body { background: #fff; margin: 0; }
    .barcode-grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 8px; }
    .barcode-item { border: 1px solid #ccc; page-break-inside: avoid; }
}
.barcode-strip { font-family: 'Libre Barcode 128', 'Courier New', monospace; font-size: 3rem; letter-spacing: .05em; line-height: 1; }
</style>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">

<div class="asset-multi-barcode">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h5 class="fw-semibold mb-0">Print Multiple Barcodes <span class="badge bg-secondary ms-1"><?= count($models) ?></span></h5>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-times"></i> Print All</button>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back to Assets', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="barcode-grid d-flex flex-wrap gap-3">
        <?php foreach ($models as $asset): ?>
        <div class="barcode-item card border shadow-sm text-center" style="width:220px">
            <div class="card-body py-3 px-2">
                <div class="small text-muted fw-semibold text-uppercase mb-1" style="font-size:.72rem;letter-spacing:.06em">
                    <?= Html::encode(mb_substr($asset->product_name, 0, 28) . (mb_strlen($asset->product_name) > 28 ? '…' : '')) ?>
                </div>
                <div class="barcode-strip my-2"><?= Html::encode($asset->barcode) ?></div>
                <div style="font-size:.8rem;letter-spacing:.1em;font-family:monospace;font-weight:600">
                    <?= Html::encode($asset->barcode) ?>
                </div>
                <?php if ($asset->brand): ?>
                <div class="mt-1" style="font-size:.7rem;color:#888"><?= Html::encode($asset->brand->brand_name) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
