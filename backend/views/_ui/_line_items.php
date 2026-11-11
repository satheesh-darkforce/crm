<?php
/**
 * Line-item editor for quotations and invoices.
 *
 * Pick a product (search by name or code) and its description, HSN/SAC, unit,
 * unit price and GST fill in; quantity is entered in the product's unit. Every
 * field stays editable. Totals update live; the server recalculates on save
 * (LineItems::read()).
 *
 * @var yii\web\View $this
 * @var string $name      field prefix, e.g. 'QuoteItems' → QuoteItems[0][quantity]
 * @var array  $lines     rows as LineItems / ClientQuotationItem::toLine() return them
 * @var int    $taxMode   LineItems::TAX_* (initial)
 * @var string|null $taxModeInput  CSS selector of a select that changes the tax mode live
 * @var string|null $totalTarget   CSS selector of an input to fill with the rounded total
 * @var string[] $errors
 */

use backend\assets\LineItemsAsset;
use backend\components\LineItems;
use backend\models\Product;
use yii\bootstrap5\Html;
use yii\helpers\Json;
use yii\helpers\Url;

LineItemsAsset::register($this);
$lines   = $lines ?: [[]];
$errors  = $errors ?? [];
$units   = Product::unitOptions();
$gst     = Product::gstOptions();
$countUnits = [];
foreach (Product::UNIT_GROUPS as $g) {
    if (!$g['decimals']) {
        $countUnits = array_merge($countUnits, array_keys($g['units']));
    }
}
$fmt = fn($v, int $d = 2) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float)$v, $d, '.', ''), '0'), '.');

$row = function (int $i, array $l) use ($name, $units, $gst, $fmt) {
    $f = fn(string $k) => $name . "[$i][$k]";
    $productLabel = '';
    if (!empty($l['product_id'])) {
        $p = Product::findOne((int)$l['product_id']);
        $productLabel = $p ? trim(($p->product_code ? $p->product_code . ' · ' : '') . $p->item_name) : 'Product #' . (int)$l['product_id'];
    }
    ob_start(); ?>
    <div class="li-row" data-line>
        <div class="li-cell li-num" aria-hidden="true"><span class="li-index"><?= $i + 1 ?></span></div>
        <div class="li-cell li-item">
            <label class="li-label">Product</label>
            <select class="form-select form-select-sm js-li-product" name="<?= $f('product_id') ?>" aria-label="Product">
                <?php if (!empty($l['product_id'])): ?><option value="<?= (int)$l['product_id'] ?>" selected><?= Html::encode($productLabel) ?></option><?php endif; ?>
            </select>
            <label class="li-label mt-1">Description</label>
            <input type="text" class="form-control form-control-sm js-li-desc" name="<?= $f('description') ?>" value="<?= Html::encode($l['description'] ?? '') ?>" maxlength="500" placeholder="Description shown on the document">
        </div>
        <div class="li-cell li-hsn">
            <label class="li-label">HSN / SAC</label>
            <input type="text" class="form-control form-control-sm mono js-li-hsn" name="<?= $f('hsn_sac') ?>" value="<?= Html::encode($l['hsn_sac'] ?? '') ?>" maxlength="8" inputmode="numeric">
        </div>
        <div class="li-cell li-qty">
            <label class="li-label">Qty</label>
            <div class="input-group input-group-sm">
                <input type="number" class="form-control form-control-sm num js-li-qty" name="<?= $f('quantity') ?>" value="<?= $fmt($l['quantity'] ?? '', 3) ?>" min="0" step="any" placeholder="0">
                <?= Html::dropDownList($f('unit'), $l['unit'] ?? '', $units, ['class' => 'form-select form-select-sm js-li-unit', 'prompt' => 'Unit', 'aria-label' => 'Unit']) ?>
            </div>
        </div>
        <div class="li-cell li-price">
            <label class="li-label">Unit price</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text">₹</span>
                <input type="number" class="form-control form-control-sm num js-li-price" name="<?= $f('unit_price') ?>" value="<?= $fmt($l['unit_price'] ?? '') ?>" min="0" step="0.01" placeholder="0.00">
            </div>
        </div>
        <div class="li-cell li-disc">
            <label class="li-label">Disc %</label>
            <input type="number" class="form-control form-control-sm num js-li-disc" name="<?= $f('discount_percent') ?>" value="<?= $fmt($l['discount_percent'] ?? '') ?>" min="0" max="100" step="0.01" placeholder="0">
        </div>
        <div class="li-cell li-gst">
            <label class="li-label">GST</label>
            <?= Html::dropDownList($f('gst_rate'), isset($l['gst_rate']) && $l['gst_rate'] !== '' ? (string)(float)$l['gst_rate'] : '18', $gst, ['class' => 'form-select form-select-sm js-li-gst', 'aria-label' => 'GST rate']) ?>
        </div>
        <div class="li-cell li-amount">
            <label class="li-label">Amount</label>
            <div class="num fw-semibold js-li-amount">—</div>
            <div class="small text-muted js-li-amount-sub"></div>
        </div>
        <div class="li-cell li-remove">
            <button type="button" class="btn btn-outline-secondary btn-sm js-li-remove" title="Remove item" aria-label="Remove item"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <?php return ob_get_clean();
};
?>
<div class="li-editor" data-line-items
     data-name="<?= Html::encode($name) ?>"
     data-search-url="<?= Html::encode(Url::to(['/product/search'])) ?>"
     data-tax-mode="<?= (int)$taxMode ?>"
     data-tax-mode-input="<?= Html::encode($taxModeInput ?? '') ?>"
     data-total-target="<?= Html::encode($totalTarget ?? '') ?>"
     data-count-units="<?= Html::encode(Json::encode($countUnits)) ?>">

    <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small mb-2"><ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= Html::encode($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="li-head" aria-hidden="true">
        <div>#</div><div>Product / description</div><div>HSN / SAC</div><div>Qty</div><div>Unit price</div><div>Disc %</div><div>GST</div><div class="text-end">Amount</div><div></div>
    </div>
    <div class="li-body" data-lines>
        <?php foreach (array_values($lines) as $i => $l): ?>
            <?= $row($i, $l) ?>
        <?php endforeach; ?>
    </div>
    <template data-line-template><?= $row(999999, []) ?></template>

    <div class="li-footer">
        <div>
            <button type="button" class="btn btn-outline-primary btn-sm js-li-add"><i class="fas fa-plus me-1"></i>Add item</button>
            <span class="small text-muted ms-2 d-none d-md-inline">Pick a product to fill the price, unit and GST — you can still change them.</span>
        </div>
        <table class="li-totals" aria-live="polite">
            <tr><th>Sub-total</th><td class="num js-li-t-sub">₹0.00</td></tr>
            <tr class="js-li-t-disc-row d-none"><th>Discount</th><td class="num js-li-t-disc">−₹0.00</td></tr>
            <tr><th>Taxable amount</th><td class="num js-li-t-taxable">₹0.00</td></tr>
            <tbody class="js-li-t-gst"></tbody>
            <tr class="li-grand"><th>Total</th><td class="num js-li-t-grand">₹0</td></tr>
        </table>
    </div>
</div>
