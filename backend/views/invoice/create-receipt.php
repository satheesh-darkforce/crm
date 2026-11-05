<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Invoice $invoice */
/** @var \backend\models\InvoiceReceipt $receipt */

use backend\models\Invoice;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Record Payment';
$client  = $invoice->client;
$balance = $invoice->getBalanceAmount();
$isPaid  = (int)$invoice->payment_status === Invoice::PAYMENT_STATUS_PAID || $balance <= 0;
$isOff   = (int)$invoice->invoice_status !== 1;
?>
<div class="ui-page invoice-create-receipt" style="max-width:1100px">
    <div class="ui-head">
        <div>
            <h1>Record payment · <span class="mono"><?= Html::encode($invoice->invoice_no ?: '#' . $invoice->id) ?></span></h1>
            <div class="ui-sub"><?= Html::encode($client->company_name ?? '') ?><?= $invoice->invoice_date ? ' · invoiced ' . date('d M Y', strtotime($invoice->invoice_date)) : '' ?></div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('invoice/receipts')): ?>
                <?= Html::a('All receipts', ['receipts', 'id' => $invoice->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Invoices', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <?= $this->render('_summary', ['invoice' => $invoice]) ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header ui-section">Payment</div>
                <div class="card-body">
                    <?php if ($isOff): ?>
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'ban', 'title' => 'Invoice cancelled', 'text' => 'Payments cannot be recorded on a cancelled invoice.', 'small' => true]) ?>
                    <?php elseif ($isPaid): ?>
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'check-circle', 'title' => 'Fully paid', 'text' => 'Nothing is left to collect on this invoice.', 'small' => true]) ?>
                    <?php else: ?>
                        <?= $this->render('_receipt-form', ['invoice' => $invoice, 'balance' => $balance, 'from' => 'create-receipt']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header ui-section">Invoice</div>
                <table class="table ui-detail mb-0">
                    <tr><th scope="row">Title</th><td><?= Html::encode($invoice->title) ?></td></tr>
                    <tr><th scope="row">Client</th><td><?= Html::encode($client->company_name ?? '—') ?><div class="small text-muted"><?= Html::encode($client->client_name ?? '') ?></div></td></tr>
                    <tr><th scope="row">GSTIN</th><td class="mono"><?= Html::encode($client->gst_no ?: '—') ?></td></tr>
                    <tr><th scope="row">Currency</th><td><?= Html::encode($invoice->currency) ?></td></tr>
                    <tr><th scope="row">GST</th><td class="num"><?= implode(' · ', array_filter([
                        (float)$invoice->sgst_amount ? 'SGST ' . number_format((float)$invoice->sgst_amount, 2) : '',
                        (float)$invoice->cgst_amount ? 'CGST ' . number_format((float)$invoice->cgst_amount, 2) : '',
                        (float)$invoice->igst_amount ? 'IGST ' . number_format((float)$invoice->igst_amount, 2) : '',
                    ])) ?: '—' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
