<?php
/**
 * Record-payment form (receipts page and create-receipt page). Mode fields toggle in invoices.js.
 *
 * @var \yii\web\View $this
 * @var \backend\models\Invoice $invoice
 * @var float  $balance
 * @var string $from     where to return: receipts | create-receipt
 */

use backend\components\SafeUpload;
use backend\models\InvoiceReceipt;
use kartik\date\DatePicker;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$cur  = (string)($invoice->currency !== '' ? $invoice->currency : '₹');
$date = fn(string $name, string $label, string $id, bool $req = false) => '<label class="form-label" for="' . $id . '">' . $label . '</label>' . DatePicker::widget([
    'name' => 'InvoiceReceipt[' . $name . ']', 'value' => $name === 'receipt_date' ? date('Y-m-d') : '', 'type' => DatePicker::TYPE_INPUT,
    'options' => ['id' => $id, 'class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off', 'required' => $req],
    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true, 'endDate' => $name === 'cheque_date' ? null : date('Y-m-d')],
]);
$text = fn(string $name, string $label, string $placeholder = '') => '<label class="form-label" for="rc-' . $name . '">' . $label . '</label>'
    . '<input type="text" id="rc-' . $name . '" name="InvoiceReceipt[' . $name . ']" class="form-control form-control-sm" maxlength="255" placeholder="' . Html::encode($placeholder) . '">';
?>
<form method="post" action="<?= Url::to(['add-receipt', 'id' => $invoice->id]) ?>" enctype="multipart/form-data" class="ui-form js-receipt-form" novalidate
      data-balance="<?= round($balance, 2) ?>" data-currency="<?= Html::encode($cur) ?>">
    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
    <input type="hidden" name="_from" value="<?= Html::encode($from) ?>">
    <div class="row g-3">
        <div class="col-sm-6 required">
            <label class="form-label" for="rc-amount">Amount received (<?= Html::encode($cur) ?>)</label>
            <input type="number" id="rc-amount" name="InvoiceReceipt[receipt_amount]" class="form-control form-control-sm text-end" step="0.01" min="0.01"
                   max="<?= round($balance, 2) ?>" value="<?= number_format($balance, 2, '.', '') ?>" required>
            <div class="form-text">Balance due <?= Html::encode($cur) ?> <?= number_format($balance, 2) ?></div>
        </div>
        <div class="col-sm-6 required"><?= $date('receipt_date', 'Receipt date', 'rc-date', true) ?></div>
        <div class="col-12 required">
            <label class="form-label" for="rc-mode">Paid by</label>
            <?= Html::dropDownList('InvoiceReceipt[payment_mode]', InvoiceReceipt::MODE_BANK, InvoiceReceipt::modeOptions(), ['id' => 'rc-mode', 'class' => 'form-select form-select-sm js-receipt-mode']) ?>
        </div>

        <div class="col-12" data-receipt-mode="<?= InvoiceReceipt::MODE_BANK ?>">
            <div class="row g-3">
                <div class="col-sm-6"><?= $text('bank_ref_no', 'Transaction ref. / UTR', 'e.g. UTR123456') ?></div>
                <div class="col-sm-6"><?= $date('bank_date', 'Transfer date', 'rc-bank-date') ?></div>
                <div class="col-sm-6"><?= $text('bank_sender_bank', 'From bank') ?></div>
                <div class="col-sm-6"><?= $text('bank_received_bank', 'Into bank') ?></div>
            </div>
        </div>
        <div class="col-12" data-receipt-mode="<?= InvoiceReceipt::MODE_CHEQUE ?>" hidden>
            <div class="row g-3">
                <div class="col-sm-6"><?= $text('cheque_no', 'Cheque no.') ?></div>
                <div class="col-sm-6"><?= $date('cheque_date', 'Cheque date', 'rc-cheque-date') ?></div>
                <div class="col-sm-6"><?= $text('cheque_sender_bank', 'Drawn on bank') ?></div>
                <div class="col-sm-6"><?= $date('cheque_deposit_date', 'Deposited on', 'rc-cheque-dep') ?></div>
            </div>
        </div>
        <div class="col-12" data-receipt-mode="<?= InvoiceReceipt::MODE_CASH ?>" hidden>
            <div class="row g-3"><div class="col-sm-6"><?= $date('cash_date', 'Cash received on', 'rc-cash-date') ?></div></div>
        </div>
        <div class="col-12" data-receipt-mode="<?= InvoiceReceipt::MODE_PAYPAL ?>" hidden>
            <div class="row g-3">
                <div class="col-sm-6"><?= $text('paypal_ref_no', 'PayPal transaction ID') ?></div>
                <div class="col-sm-6"><?= $date('paypal_payment_date', 'PayPal date', 'rc-paypal-date') ?></div>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label" for="rc-remarks">Remarks</label>
            <textarea id="rc-remarks" name="InvoiceReceipt[payment_remarks]" class="form-control form-control-sm" rows="2" maxlength="5000" placeholder="Optional"></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="rc-files">Attachments</label>
            <input type="file" id="rc-files" name="attachments[]" multiple class="form-control form-control-sm" accept="<?= SafeUpload::acceptAttribute() ?>">
            <div class="form-text">Payment advice or screenshot — PDF, images, Office files; up to <?= SafeUpload::MAX_BYTES / 1024 / 1024 ?> MB each.</div>
        </div>
        <div class="col-12 ui-form-actions">
            <button type="submit" class="btn btn-primary btn-sm px-4"><i class="fas fa-check me-1"></i>Record payment</button>
        </div>
    </div>
</form>
