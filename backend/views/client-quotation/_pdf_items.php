<?php
/**
 * Items page of the quotation PDF (mPDF): line items, GST summary, total in
 * words, bank details, terms, notes and signatory — the layout Green Divine's
 * own quote sheet uses.
 *
 * @var backend\models\ClientQuotation $model
 * @var backend\models\BankDetail|null $bank
 */

use backend\components\LineItems;
use backend\models\ClientQuotationItem;
use yii\helpers\Html;

$items = $model->items;
$mode  = (int)($model->tax_mode ?: LineItems::TAX_INTRA);
$t     = LineItems::totals(array_map(fn(ClientQuotationItem $i) => $i->toLine(), $items), $mode);
$m     = fn($v, int $dp = 2) => number_format((float)$v, $dp);
$qty   = fn($v) => rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.');
$client = $model->client;
$hasDisc = $t['discount_total'] > 0;
$lines = fn(?string $text) => array_filter(array_map('trim', explode("\n", (string)$text)));
?>
<style>
    .qi { font-family: sans-serif; font-size: 9.5pt; color: #1e293b; }
    .qi h2 { font-size: 13pt; margin: 0 0 2mm; color: #0f7a2e; }
    .qi .meta td { padding: 1mm 0; vertical-align: top; }
    .qi table.items { width: 100%; border-collapse: collapse; margin-top: 4mm; }
    .qi table.items th { background: #0f7a2e; color: #fff; font-size: 8.5pt; padding: 2mm; text-align: left; }
    .qi table.items td { border-bottom: 0.2mm solid #e2e8f0; padding: 2mm; vertical-align: top; }
    .qi .r { text-align: right; }
    .qi table.sum { width: 75mm; border-collapse: collapse; margin-left: auto; margin-top: 3mm; }
    .qi table.sum td { padding: 1.2mm 2mm; }
    .qi table.sum tr.grand td { border-top: 0.4mm solid #0f7a2e; font-weight: bold; font-size: 11pt; }
    .qi .box { border: 0.2mm solid #e2e8f0; padding: 3mm; margin-top: 4mm; }
    .qi .muted { color: #64748b; }
    .qi ul { margin: 1mm 0 0 4mm; padding: 0; }
</style>
<div class="qi">
    <h2>Quotation <?= Html::encode($model->quote_no) ?></h2>
    <table class="meta" width="100%">
        <tr>
            <td width="55%">
                <strong>Bill to</strong><br>
                <?= Html::encode($client->company_name ?? $client->client_name ?? '') ?><br>
                <?= nl2br(Html::encode($model->address ?: ($client->address ?? ''))) ?>
                <?php if (!empty($client->phone)): ?><br>Mobile: <?= Html::encode($client->phone) ?><?php endif; ?>
                <?php if (!empty($client->gst_no)): ?><br>GSTIN: <?= Html::encode($client->gst_no) ?><?php endif; ?>
            </td>
            <td width="45%" class="r">
                Date: <?= Html::encode($model->quote_date ? date('d M Y', strtotime($model->quote_date)) : '') ?><br>
                <?php if ($model->valid_until): ?>Valid until: <?= date('d M Y', strtotime($model->valid_until)) ?><br><?php endif; ?>
                <?php if ($model->project_name): ?>Project: <?= Html::encode($model->project_name) ?><br><?php endif; ?>
                <span class="muted"><?= Html::encode(LineItems::taxModeOptions()[$mode] ?? '') ?></span>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
        <tr>
            <th width="6%">#</th><th>Item</th><th width="11%">HSN/SAC</th><th width="12%" class="r">Qty</th><th width="12%" class="r">Rate (₹)</th>
            <?php if ($hasDisc): ?><th width="7%" class="r">Disc</th><?php endif; ?>
            <th width="9%" class="r">GST</th><th width="14%" class="r">Amount (₹)</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $it): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= Html::encode($it->description) ?></td>
                <td><?= Html::encode($it->hsn_sac ?: '-') ?></td>
                <td class="r"><?= $qty($it->quantity) ?> <?= Html::encode($it->unit) ?></td>
                <td class="r"><?= $m($it->unit_price) ?></td>
                <?php if ($hasDisc): ?><td class="r"><?= (float)$it->discount_percent ? (float)$it->discount_percent . '%' : '-' ?></td><?php endif; ?>
                <td class="r"><?= $mode === LineItems::TAX_NONE ? '-' : (float)$it->gst_rate . '%' ?></td>
                <td class="r"><?= $m($it->line_total) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="sum">
        <tr><td>Sub-total</td><td class="r"><?= $m($t['sub_total']) ?></td></tr>
        <?php if ($hasDisc): ?><tr><td>Discount</td><td class="r">-<?= $m($t['discount_total']) ?></td></tr><?php endif; ?>
        <tr><td>Taxable amount</td><td class="r"><?= $m($t['taxable_total']) ?></td></tr>
        <?php foreach ($t['by_rate'] as $rate => $r): ?>
            <?php if ($mode === LineItems::TAX_INTRA): $c = round($r['gst'] / 2, 2); ?>
                <tr><td>CGST @<?= (float)$rate / 2 ?>%</td><td class="r"><?= $m($c) ?></td></tr>
                <tr><td>SGST @<?= (float)$rate / 2 ?>%</td><td class="r"><?= $m($r['gst'] - $c) ?></td></tr>
            <?php else: ?>
                <tr><td>IGST @<?= (float)$rate ?>%</td><td class="r"><?= $m($r['gst']) ?></td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($t['round_off'] != 0): ?><tr><td>Round off</td><td class="r"><?= $m($t['round_off']) ?></td></tr><?php endif; ?>
        <tr class="grand"><td>Total (₹)</td><td class="r"><?= $m($t['rounded_total'], 0) ?></td></tr>
    </table>
    <div class="box"><strong>Amount in words:</strong> <?= Html::encode(LineItems::amountInWords($t['rounded_total'])) ?></div>

    <table width="100%" style="margin-top:4mm">
        <tr>
            <td width="50%" style="vertical-align:top">
                <?php if ($bank): ?>
                    <strong>Bank details</strong><br>
                    Name: <?= Html::encode($bank->acc_name) ?><br>
                    Account No: <?= Html::encode($bank->acc_no) ?><br>
                    IFSC: <?= Html::encode($bank->ifsc) ?><br>
                    Bank: <?= Html::encode(trim($bank->bank_name . ', ' . $bank->branch, ', ')) ?>
                <?php endif; ?>
            </td>
            <td width="50%" style="vertical-align:top">
                <?php if ($terms = $lines($model->terms)): ?>
                    <strong>Terms and conditions</strong>
                    <ul><?php foreach ($terms as $l): ?><li><?= Html::encode($l) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if ($notes = $lines($model->notes)): ?>
                    <br><strong>Notes</strong>
                    <ul><?php foreach ($notes as $l): ?><li><?= Html::encode($l) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <div style="text-align:right; margin-top:14mm"><strong>Authorised signatory</strong></div>
</div>
