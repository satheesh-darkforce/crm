<?php
/**
 * Items table for invoices with GST per line (product line items). Used by
 * _invoice_doc.php (preview, print, PDF) in place of the classic table.
 *
 * Columns: # · HSN/SAC · Particulars · Qty · Rate · GST · Amount (taxable)
 * Totals:  Item total · CGST/SGST (intra-state) or IGST (inter-state) per rate ·
 *          Sub total · Round off · TOTAL
 *
 * @var \backend\models\Invoice $model
 * @var string $headerCurrency
 */

$details = $model->details ?? [];
$byRate  = [];
$hasDisc = false;
foreach ($details as $d) {
    $r = (string)(float)$d->gst_rate;
    if ((float)$d->gst_amount > 0) {
        $byRate[$r] = round(($byRate[$r] ?? 0) + (float)$d->gst_amount, 2);
    }
    if ((float)$d->discount_percent > 0) {
        $hasDisc = true;
    }
}
ksort($byRate, SORT_NUMERIC);
$intra = (int)$model->invoice_type === 2;
$inter = (int)$model->invoice_type === 1;
$cols  = 7 + ($hasDisc ? 1 : 0);
$pre   = '<td colspan="' . ($cols - 2) . '" class="tot-label">';
$qty   = fn($v) => rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.');
?>
<table align="center" class="inv-items">
    <tr>
        <th width="3%" class="center sno-col">#</th>
        <th width="11%" class="center">HSN/SAC</th>
        <th>PARTICULARS</th>
        <th width="11%" class="center">QTY</th>
        <th width="11%" class="center">RATE</th>
        <?php if ($hasDisc): ?><th width="7%" class="center">DISC</th><?php endif; ?>
        <th width="7%" class="center">GST</th>
        <th width="15%" class="center">AMOUNT (<?= htmlspecialchars($headerCurrency) ?>)</th>
    </tr>
    <?php foreach ($details as $i => $d): ?>
        <tr>
            <td class="center sno-col"><?= $i + 1 ?></td>
            <td class="center" style="white-space:nowrap;"><?= htmlspecialchars((string)$d->sac) ?></td>
            <td class="particulars-cell"><?= htmlspecialchars((string)$d->particulars) ?></td>
            <td class="center"><?= $d->quantity !== null ? $qty($d->quantity) . ($d->unit ? ' ' . htmlspecialchars($d->unit) : '') : '&nbsp;' ?></td>
            <td class="num"><?= $d->unit_rate !== null ? number_format((float)$d->unit_rate, 2) : '&nbsp;' ?></td>
            <?php if ($hasDisc): ?><td class="center"><?= (float)$d->discount_percent > 0 ? (float)$d->discount_percent . '%' : '-' ?></td><?php endif; ?>
            <td class="center"><?= (float)$d->gst_rate ?>%</td>
            <td class="num"><?= number_format((float)$d->amount, 2) ?></td>
        </tr>
    <?php endforeach; ?>

    <tr class="ghost"><?= $pre ?>Item Total</td><td colspan="2" class="tot-value"><?= number_format((float)$model->grand_total, 2) ?></td></tr>
    <?php foreach ($byRate as $rate => $gst): ?>
        <?php if ($intra): $c = round($gst / 2, 2); ?>
            <tr class="ghost"><?= $pre ?>CGST @ <?= (float)$rate / 2 ?>%</td><td colspan="2" class="tot-value"><?= number_format($c, 2) ?></td></tr>
            <tr class="ghost"><?= $pre ?>SGST @ <?= (float)$rate / 2 ?>%</td><td colspan="2" class="tot-value"><?= number_format($gst - $c, 2) ?></td></tr>
        <?php elseif ($inter): ?>
            <tr class="ghost"><?= $pre ?>IGST @ <?= (float)$rate ?>%</td><td colspan="2" class="tot-value"><?= number_format($gst, 2) ?></td></tr>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($intra || $inter): ?>
        <tr class="ghost"><?= $pre ?>Sub Total</td><td colspan="2" class="tot-value"><?= number_format((float)$model->sub_total, 2) ?></td></tr>
    <?php endif; ?>
    <?php if ((float)$model->round_off != 0): ?>
        <tr class="ghost"><?= $pre ?>Round Off</td><td colspan="2" class="tot-value"><?= number_format((float)$model->round_off, 2) ?></td></tr>
    <?php endif; ?>
    <tr class="ghost tot-final"><?= $pre ?>TOTAL</td><td colspan="2" class="tot-value"><?= number_format((float)$model->net_total, 2) ?></td></tr>
</table>
