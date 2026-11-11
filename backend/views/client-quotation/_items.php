<?php
/**
 * Quotation items table with GST summary (quotation page).
 *
 * @var yii\web\View $this
 * @var backend\models\ClientQuotation $model
 */

use backend\components\LineItems;
use backend\models\ClientQuotationItem;
use yii\bootstrap5\Html;

$items = $model->items;
if (!$items) {
    return;
}
$mode   = (int)($model->tax_mode ?: LineItems::TAX_INTRA);
$lines  = array_map(fn(ClientQuotationItem $i) => $i->toLine(), $items);
$t      = LineItems::totals($lines, $mode);
$money  = fn($v, int $dp = 2) => '₹' . Yii::$app->formatter->asDecimal((float)$v, $dp);
$qty    = fn($v) => rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.');
$hasDisc = $t['discount_total'] > 0;
?>
<div class="cq-section">
    <div class="cq-section-head">
        <span class="cq-section-icon icon-info"><i class="fas fa-list-ul"></i></span>
        <h5>Items <span class="text-muted fw-normal small">· <?= Html::encode(LineItems::taxModeOptions()[$mode] ?? '') ?></span></h5>
    </div>
    <div class="table-responsive">
        <table class="table list-table ui-table stack mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:36px">#</th><th>Item</th><th>HSN / SAC</th><th class="text-end">Qty</th><th class="text-end">Unit price</th>
                <?php if ($hasDisc): ?><th class="text-end">Disc</th><?php endif; ?>
                <th class="text-end">Taxable</th><th class="text-end">GST</th><th class="text-end">Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td class="cell-num muted"><?= $i + 1 ?></td>
                    <td class="cell-title wrap">
                        <?= Html::encode($it->description) ?>
                        <?php if ($it->product_id && $it->product): ?><div class="sub"><?= Html::a(Html::encode($it->product->product_code ?: 'Product'), ['/product/view', 'id' => $it->product_id], ['class' => 'text-muted']) ?></div><?php endif; ?>
                    </td>
                    <td class="mono" data-label="HSN / SAC"><?= Html::encode($it->hsn_sac ?: '—') ?></td>
                    <td class="text-end num nowrap" data-label="Qty"><?= $qty($it->quantity) ?> <?= Html::encode($it->unit) ?></td>
                    <td class="text-end num nowrap" data-label="Unit price"><?= $money($it->unit_price) ?><?= $it->unit ? '<span class="text-muted small"> / ' . Html::encode($it->unit) . '</span>' : '' ?></td>
                    <?php if ($hasDisc): ?><td class="text-end num" data-label="Disc"><?= (float)$it->discount_percent ? (float)$it->discount_percent . '%' : '—' ?></td><?php endif; ?>
                    <td class="text-end num" data-label="Taxable"><?= $money($it->taxable_amount) ?></td>
                    <td class="text-end num nowrap" data-label="GST"><?= $mode === LineItems::TAX_NONE ? '—' : $money($it->gst_amount) . ' <span class="text-muted small">(' . (float)$it->gst_rate . '%)</span>' ?></td>
                    <td class="text-end num fw-semibold" data-label="Amount"><?= $money($it->line_total) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <?php if (trim((string)$model->terms) !== ''): ?>
                <div class="small fw-semibold text-uppercase text-muted mb-1">Terms &amp; Conditions</div>
                <ul class="small mb-2 ps-3"><?php foreach (array_filter(array_map('trim', explode("\n", (string)$model->terms))) as $l): ?><li><?= Html::encode($l) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if (trim((string)$model->notes) !== ''): ?>
                <div class="small fw-semibold text-uppercase text-muted mb-1">Notes</div>
                <ul class="small mb-0 ps-3"><?php foreach (array_filter(array_map('trim', explode("\n", (string)$model->notes))) as $l): ?><li><?= Html::encode($l) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <table class="table table-sm mb-1 ms-md-auto" style="max-width:360px">
                <tr><th class="fw-normal text-muted">Sub-total</th><td class="text-end num"><?= $money($t['sub_total']) ?></td></tr>
                <?php if ($hasDisc): ?><tr><th class="fw-normal text-muted">Discount</th><td class="text-end num">−<?= $money($t['discount_total']) ?></td></tr><?php endif; ?>
                <tr><th class="fw-normal text-muted">Taxable amount</th><td class="text-end num"><?= $money($t['taxable_total']) ?></td></tr>
                <?php foreach ($t['by_rate'] as $rate => $r): ?>
                    <?php if ($mode === LineItems::TAX_INTRA): $c = round($r['gst'] / 2, 2); ?>
                        <tr><th class="fw-normal text-muted">CGST @<?= (float)$rate / 2 ?>%</th><td class="text-end num"><?= $money($c) ?></td></tr>
                        <tr><th class="fw-normal text-muted">SGST @<?= (float)$rate / 2 ?>%</th><td class="text-end num"><?= $money($r['gst'] - $c) ?></td></tr>
                    <?php else: ?>
                        <tr><th class="fw-normal text-muted">IGST @<?= (float)$rate ?>%</th><td class="text-end num"><?= $money($r['gst']) ?></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($t['round_off'] != 0): ?><tr><th class="fw-normal text-muted">Round off</th><td class="text-end num"><?= $money($t['round_off']) ?></td></tr><?php endif; ?>
                <tr><th>Total</th><td class="text-end num fw-bold fs-6"><?= $money($t['rounded_total'], 0) ?></td></tr>
            </table>
            <div class="small text-muted text-md-end"><?= Html::encode(LineItems::amountInWords($t['rounded_total'])) ?></div>
        </div>
    </div>
</div>
