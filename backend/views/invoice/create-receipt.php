<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Invoice $invoice */
/** @var \backend\models\InvoiceReceipt $receipt */

use kartik\date\DatePicker;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Create Receipt';

$client  = $invoice->client;
$pending = max(0, round((float)$invoice->net_total - (float)$invoice->tds_amount - (float)$invoice->paid_amount, 2));
$maxAmt  = ceil($pending);

$paymentStatusLabel = match ((int)$invoice->payment_status) {
    1 => 'Partial Paid',
    2 => 'Paid',
    default => 'Pending',
};

$clientTypeLabel = '';
if ($client) {
    $clientTypeLabel = match ((int)$client->client_type) {
        1 => 'Inter-State',
        2 => 'Intra-State',
        3 => 'International',
        default => '',
    };
}
?>

<h4 class="mb-3"><strong>Create</strong> <span class="text-muted">Receipt</span></h4>

<!-- ── Invoice Info Card (matches Yii1 layout: 4 columns) ── -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <!-- Column 1: Client Info -->
            <div class="col-md-3">
                <p><strong>Client Name :</strong><?= Html::encode($client->client_name ?? '') ?></p>
                <p><strong>Company Name :</strong> <?= Html::encode($client->company_name ?? '') ?></p>
                <p><strong>Address :</strong><?= Html::encode($client->address ?? '') ?></p>
                <p><strong>GST Number :</strong><?= Html::encode($client->gst_no ?? '') ?></p>
            </div>
            <!-- Column 2: Invoice Info -->
            <div class="col-md-3">
                <p><strong>Invoice Number :</strong> <?= Html::encode($invoice->invoice_no) ?></p>
                <p><strong>Invoice Date :</strong> <?= $invoice->invoice_date ? date('d-m-Y', strtotime($invoice->invoice_date)) : '' ?></p>
                <p><strong>Invoice Title :</strong> <?= Html::encode($invoice->title) ?></p>
                <p><strong>Invoice Type :</strong> <?= Html::encode($clientTypeLabel) ?></p>
                <p><strong>Currency :</strong> <?= Html::encode($invoice->currency) ?></p>
            </div>
            <!-- Column 3: Amounts -->
            <div class="col-md-3">
                <p><strong>Taxable Amount :</strong> <?= number_format((float)$invoice->grand_total, 2) ?></p>
                <p><strong>SGST Amount :</strong> <?= number_format((float)$invoice->sgst_amount, 2) ?></p>
                <p><strong>CGST Amount :</strong><?= number_format((float)$invoice->cgst_amount, 2) ?></p>
                <p><strong>IGST Amount :</strong><?= number_format((float)$invoice->igst_amount, 2) ?></p>
                <p><strong>Net Amount :</strong> <?= number_format((float)$invoice->net_total, 2) ?></p>
                <p><strong>TDS Amount :</strong> <?= number_format((float)$invoice->tds_amount, 2) ?></p>
            </div>
            <!-- Column 4: Payment Status -->
            <div class="col-md-3">
                <p><strong>Paid Amount :</strong><?= number_format((float)$invoice->paid_amount, 2) ?></p>
                <p><strong>Pending Amount :</strong> <?= number_format($pending, 2) ?></p>
                <p><strong>Payment Status :</strong> <?= $paymentStatusLabel ?></p>
            </div>
        </div>
    </div>
</div>

<!-- ── Receipt Form (matches Yii1 _form.php) ── -->
<div class="card">
    <div class="card-body">
        <?php $form = ActiveForm::begin([
            'id' => 'receipt-form',
            'action' => Url::to(['add-receipt', 'id' => $invoice->id]),
            'method' => 'post',
            'options' => ['enctype' => 'multipart/form-data'],
        ]); ?>
        <?= Html::hiddenInput('_from', 'create-receipt') ?>

        <div class="row g-3">
            <!-- Receipt Date -->
            <div class="col-md-4">
                <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
                <?= DatePicker::widget([
                    'model' => $receipt,
                    'attribute' => 'receipt_date',
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm receiptdate', 'required' => true, 'readonly' => true],
                    'pluginOptions' => [
                        'autoclose' => true,
                        'format' => 'yyyy-mm-dd',
                        'endDate' => date('Y-m-d'),
                    ],
                ]) ?>
            </div>

            <!-- Receipt Amount -->
            <div class="col-md-4">
                <label class="form-label">Receipt Amount <span class="text-danger">*</span></label>
                <?= Html::activeInput('number', $receipt, 'receipt_amount', [
                    'class' => 'form-control form-control-sm receiptamount',
                    'min' => '0.01',
                    'max' => $maxAmt,
                    'step' => '0.01',
                    'required' => true,
                    'value' => '0.00',
                ]) ?>
            </div>

            <!-- Payment Mode -->
            <div class="col-md-4">
                <label class="form-label">Payment Mode <span class="text-danger">*</span></label>
                <?= Html::activeDropDownList($receipt, 'payment_mode', [
                    '' => 'Select Payment Mode',
                    1 => 'Cash',
                    2 => 'Cheque',
                    3 => 'Bank',
                    4 => 'Paypal',
                    5 => 'Others',
                ], ['class' => 'form-select form-select-sm', 'id' => 'payment-mode-sel', 'required' => true]) ?>
            </div>
        </div>

        <!-- Cash fields -->
        <div class="row g-3 mt-1" id="cashdiv" style="display:none">
            <div class="col-md-3">
                <label class="form-label">Cash Date</label>
                <?= DatePicker::widget([
                    'model' => $receipt, 'attribute' => 'cash_date',
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm datetime', 'readonly' => true],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => date('Y-m-d')],
                ]) ?>
            </div>
        </div>

        <!-- Cheque fields -->
        <div class="row g-3 mt-1" id="chequediv" style="display:none">
            <div class="col-md-2">
                <?= $form->field($receipt, 'cheque_sender_bank')->textInput(['class' => 'form-control form-control-sm'])->label('Sender Bank') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($receipt, 'cheque_no')->textInput(['class' => 'form-control form-control-sm'])->label('Cheque No') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($receipt, 'cheque_amount')->input('number', ['class' => 'form-control form-control-sm amount', 'step' => '0.01', 'min' => 0])->label('Cheque Amount') ?>
            </div>
            <div class="col-md-2">
                <label class="form-label">Cheque Date</label>
                <?= DatePicker::widget([
                    'model' => $receipt, 'attribute' => 'cheque_date',
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm datetime', 'readonly' => true],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => date('Y-m-d')],
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($receipt, 'cheque_received_bank')->textInput(['class' => 'form-control form-control-sm'])->label('Received Bank') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($receipt, 'cheque_received_ac_no')->textInput(['class' => 'form-control form-control-sm'])->label('Received A/c No') ?>
            </div>
        </div>

        <!-- Bank fields -->
        <div class="row g-3 mt-1" id="bankdiv" style="display:none">
            <div class="col-md-2">
                <?= $form->field($receipt, 'bank_sender_bank')->textInput(['class' => 'form-control form-control-sm'])->label('Sender Bank') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($receipt, 'bank_ref_no')->textInput(['class' => 'form-control form-control-sm'])->label('Reference No') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($receipt, 'bank_amount')->input('number', ['class' => 'form-control form-control-sm amount', 'step' => '0.01', 'min' => 0])->label('Bank Amount') ?>
            </div>
            <div class="col-md-2">
                <label class="form-label">Bank Date</label>
                <?= DatePicker::widget([
                    'model' => $receipt, 'attribute' => 'bank_date',
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm datetime', 'readonly' => true],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => date('Y-m-d')],
                ]) ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($receipt, 'bank_received_bank')->textInput(['class' => 'form-control form-control-sm'])->label('Received Bank') ?>
            </div>
            <div class="col-md-2">
                <?= $form->field($receipt, 'bank_received_ac_no')->textInput(['class' => 'form-control form-control-sm'])->label('Received A/c No') ?>
            </div>
        </div>

        <!-- PayPal fields -->
        <div class="row g-3 mt-1" id="paypaldiv" style="display:none">
            <div class="col-md-3">
                <?= $form->field($receipt, 'paypal_ref_no')->textInput(['class' => 'form-control form-control-sm'])->label('PayPal Ref No') ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($receipt, 'paypal_amount')->input('number', ['class' => 'form-control form-control-sm amount', 'step' => '0.01', 'min' => 0])->label('PayPal Amount') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">PayPal Date</label>
                <?= DatePicker::widget([
                    'model' => $receipt, 'attribute' => 'paypal_payment_date',
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm datetime', 'readonly' => true],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => date('Y-m-d')],
                ]) ?>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <!-- Payment Remarks -->
            <div class="col-md-4">
                <label class="form-label">Payment Remarks</label>
                <?= Html::activeTextarea($receipt, 'payment_remarks', [
                    'class' => 'form-control form-control-sm',
                    'rows' => 2,
                ]) ?>
            </div>

            <!-- Attachments -->
            <div class="col-md-4">
                <label class="form-label">Attachments</label>
                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
            </div>
        </div>

        <div class="mt-3">
            <?= Html::submitButton('Submit', ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<?php
// ── Payment mode toggle JS (matches Yii1 updatepaymenttype) ──
$this->registerJs(<<<'JS'
var modeSel = document.getElementById('payment-mode-sel');
function updatepaymenttype() {
    var mode = modeSel ? modeSel.value : '';
    var receiptDate   = document.querySelector('.receiptdate')?.value || '';
    var receiptAmount = document.querySelector('.receiptamount')?.value || '';

    ['cashdiv','chequediv','bankdiv','paypaldiv'].forEach(function(id) {
        var div = document.getElementById(id);
        if (div) {
            div.querySelectorAll('input[type=text], input[type=number], textarea').forEach(function(el){ el.value = ''; });
            div.style.display = 'none';
        }
    });

    var targetDiv = null;
    if (mode == '1') targetDiv = document.getElementById('cashdiv');
    if (mode == '2') targetDiv = document.getElementById('chequediv');
    if (mode == '3') targetDiv = document.getElementById('bankdiv');
    if (mode == '4') targetDiv = document.getElementById('paypaldiv');

    if (targetDiv) {
        targetDiv.querySelectorAll('.datetime').forEach(function(el){ el.value = receiptDate; });
        targetDiv.querySelectorAll('.amount').forEach(function(el){ el.value = receiptAmount; });
        targetDiv.style.display = '';
    }
}

if (modeSel) modeSel.addEventListener('change', updatepaymenttype);

// Sync receipt date/amount changes to visible sub-fields
document.querySelectorAll('.receiptamount, .receiptdate').forEach(function(el) {
    el.addEventListener('change', function() {
        var receiptDate   = document.querySelector('.receiptdate')?.value || '';
        var receiptAmount = document.querySelector('.receiptamount')?.value || '';
        ['cashdiv','chequediv','bankdiv','paypaldiv'].forEach(function(id) {
            var div = document.getElementById(id);
            if (div && div.style.display !== 'none') {
                div.querySelectorAll('.datetime').forEach(function(el){ el.value = receiptDate; });
                div.querySelectorAll('.amount').forEach(function(el){ el.value = receiptAmount; });
            }
        });
    });
});
JS, \yii\web\View::POS_READY);
?>
