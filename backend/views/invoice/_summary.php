<?php
/**
 * Invoice money summary tiles (receipt pages).
 *
 * @var \backend\models\Invoice $invoice
 */

use backend\models\Invoice;
use yii\bootstrap5\Html;

$cur     = (string)($invoice->currency !== '' ? $invoice->currency : '₹');
$fmt     = fn($v) => Html::encode($cur) . ' ' . number_format((float)$v, 2);
$balance = $invoice->getBalanceAmount();
$tone    = [Invoice::PAYMENT_STATUS_UNPAID => 'danger', Invoice::PAYMENT_STATUS_PARTIAL => 'warning', Invoice::PAYMENT_STATUS_PAID => 'success'][(int)$invoice->payment_status] ?? 'neutral';
?>
<div class="ui-tiles">
    <div class="ui-tile"><div class="t-label">Net total</div><div class="t-value num"><?= $fmt($invoice->net_total) ?></div>
        <div class="t-sub">Taxable <?= $fmt($invoice->grand_total) ?></div></div>
    <div class="ui-tile"><div class="t-label">TDS</div><div class="t-value num"><?= (float)$invoice->tds_amount ? $fmt($invoice->tds_amount) : '—' ?></div>
        <div class="t-sub"><?= (float)$invoice->tds_percent ? (float)$invoice->tds_percent . '%' : 'None deducted' ?></div></div>
    <div class="ui-tile"><div class="t-label">Received</div><div class="t-value num"><?= $fmt($invoice->paid_amount) ?></div>
        <div class="t-sub"><span class="ui-pill tone-<?= $tone ?> plain"><?= Html::encode($invoice->getPaymentStatusLabel()) ?></span></div></div>
    <div class="ui-tile is-accent"><div class="t-label">Balance due</div><div class="t-value num"><?= $fmt($balance) ?></div>
        <div class="t-sub"><?= $balance > 0 ? 'Net − TDS − received' : 'Nothing left to collect' ?></div></div>
</div>
