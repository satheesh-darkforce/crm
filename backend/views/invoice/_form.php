<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Invoice $invoice */
/** @var \backend\models\ClientModel[] $clients */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\BankDetail[] $banks */
/** @var \backend\models\Currency[] $currencies */
/** @var \backend\models\ClientModel|null $preClient */
/** @var string|null $nextNo */
/** @var array $clientTypes  [client_id => client_type] */
/** @var array $hsnCodes */
/** @var string $lutArn */
/** @var int|string $invoiceCategoryParam */

use backend\models\BankDetail;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$isNew      = $invoice->isNewRecord;
$isProforma = ((int)$invoice->invoice_role === \backend\models\Invoice::ROLE_PROFORMA);
$catId      = (int)($invoiceCategoryParam ?? $invoice->invoice_category ?? 1);
$isSale     = ($catId === 2);

// Client info (from URL pre-selection or from invoice for edit mode)
$client = $preClient;
if (!$client && !$isNew && $invoice->client_id) {
    $client = \backend\models\ClientModel::findOne($invoice->client_id);
}

$categoryLabel   = match ($catId) { 1 => 'Service invoice', 2 => 'Sales invoice', default => 'Invoice' };
$clientTypeLabel = $client ? match ((int)$client->client_type) { 1 => 'Inter-state', 2 => 'Intra-state', 3 => 'International', default => 'Not set' } : '';

// Tax rates come from the invoice (set by the controller from master settings), never hard-coded.
$sgstPct = (float)($invoice->sgst_percent ?? 0);
$cgstPct = (float)($invoice->cgst_percent ?? 0);
$igstPct = (float)($invoice->igst_percent ?? 0);

$transactionChargePct = $invoice->transaction_charge_percent ?? 0;

/** @var bool|null $useLines  product line items with GST per line (InvoiceController::usesLineItems()) */
/** @var array $lines */
/** @var string[] $lineErrors */
$useLines   = (bool)($useLines ?? false);
// Green Divine mode: a domestic invoice is always INR, rate 1, no transaction charge — those fields are hidden.
$hideMoney  = \backend\components\Crm::simple() && $client && in_array((int)$client->client_type, [1, 2], true) && count($currencies ?? []) <= 1;
$lines      = $lines ?? [];
$lineErrors = $lineErrors ?? [];
// New invoices start on the default bank account.
if ($isNew && !$invoice->bank_id) {
    $invoice->bank_id = BankDetail::defaultId();
}
?>

<?php $form = ActiveForm::begin([
    'id' => 'invoice-form',
    'options' => ['onsubmit' => 'return validate()', 'class' => 'ui-form'],
]); ?>

<?= $form->errorSummary($invoice, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
<p class="ui-req-note">Fields marked <b>*</b> are required.</p>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header ui-section d-flex align-items-center gap-2">Bill to
                <span class="ui-pill tone-<?= $isSale ? 'warning' : 'accent' ?> ms-auto"><?= Html::encode($categoryLabel) ?></span>
            </div>
            <?php if ($client): ?>
                <table class="table ui-detail mb-0">
                    <tr><th scope="row">Invoice no.</th><td class="mono fw-semibold"><?= Html::encode($nextNo ?: 'Assigned on save') ?></td></tr>
                    <tr><th scope="row">Company</th><td><?= Html::encode($client->company_name) ?><div class="small text-muted"><?= Html::encode($client->client_name) ?></div></td></tr>
                    <tr><th scope="row">Client type</th><td><?= Html::encode($clientTypeLabel) ?></td></tr>
                    <tr><th scope="row">GSTIN</th><td class="mono"><?= $client->gst_no ? Html::encode($client->gst_no) : (\backend\components\Crm::simple() ? '<span class="ui-pill plain" title="The invoice is issued without a GSTIN (B2C)">Not registered (B2C)</span>' : '<span class="ui-pill tone-warning plain">Not set</span>') ?></td></tr>
                    <tr><th scope="row">Tax exemption</th><td><?= $client->tax_exemption == 1 ? 'Yes — ' . Html::encode($client->tax_exemption_reason) : 'No' ?></td></tr>
                    <tr><th scope="row">Address</th><td><?= nl2br(Html::encode($client->address)) ?></td></tr>
                    <?php if ((int)$client->client_type === 3 && $lutArn): ?>
                        <tr><th scope="row">LUT ARN</th><td class="mono"><?= Html::encode($lutArn) ?></td></tr>
                    <?php endif; ?>
                </table>
            <?php else: ?>
                <div class="card-body">
                    <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'user-tie', 'title' => 'No client selected', 'small' => true,
                        'text' => 'Start from "New tax invoice" or "New proforma" in the menu and pick the client there.']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header ui-section">Invoice details</div>
            <div class="card-body row g-3">
                <div class="col-12 required">
                    <label class="form-label" for="invoice-title">Invoice title</label>
                    <?= Html::activeTextInput($invoice, 'title', [
                        'class' => 'form-control form-control-sm', 'placeholder' => 'e.g. Website maintenance — November', 'required' => true, 'maxlength' => 500,
                    ]) ?>
                </div>
                <div class="col-sm-6 required<?= $hideMoney ? ' d-none' : '' ?>">
                    <label class="form-label" for="invoice-currency-sel">Currency</label>
                    <select id="invoice-currency-sel" name="Invoice[currency]" class="form-select form-select-sm" required>
                        <?php foreach ($currencies ?? [] as $cur): ?>
                        <option value="<?= $cur->id ?>" data-currencycode="<?= Html::encode($cur->currency) ?>"
                            <?= (!$isNew && $invoice->currency == $cur->currency) ? 'selected' : '' ?>>
                            <?= Html::encode($cur->title) ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($currencies)): ?>
                        <option value="">—</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-sm-6 required<?= $hideMoney ? ' d-none' : '' ?>">
                    <label class="form-label" for="currency_exchange_rate">Exchange rate (to ₹)</label>
                    <?= Html::activeInput('number', $invoice, 'currency_exchange_rate', [
                        'id' => 'currency_exchange_rate', 'class' => 'form-control form-control-sm text-end',
                        'min' => '1', 'max' => '10000', 'step' => '0.01', 'required' => !$hideMoney, 'placeholder' => '1.00',
                    ]) ?>
                </div>
                <div class="col-sm-6<?= $hideMoney ? ' d-none' : '' ?>">
                    <label class="form-label" for="transaction_charge_percent">Transaction charge %</label>
                    <?= Html::activeInput('number', $invoice, 'transaction_charge_percent', [
                        'id' => 'transaction_charge_percent', 'class' => 'form-control form-control-sm text-end',
                        'min' => '0', 'max' => '100', 'step' => '1', 'value' => $transactionChargePct,
                    ]) ?>
                    <div class="form-text">Added to each line as you add it; locked once lines exist.</div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="invoice-bank-id">Payment bank</label>
                    <?= Select2::widget([
                        'name' => 'Invoice[bank_id]', 'value' => $invoice->bank_id,
                        'data' => ArrayHelper::map($banks ?? [], 'id', fn($b) => $b->bank_name . ' · ' . substr($b->acc_no, -4)),
                        'options' => ['placeholder' => 'Select bank', 'id' => 'invoice-bank-id'], 'size' => Select2::SMALL,
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                    <div class="form-text">Printed on the invoice under "Payment bank info".</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden fields for data passed via URL params -->
<?= Html::activeHiddenInput($invoice, 'client_id') ?>
<?= Html::activeHiddenInput($invoice, 'invoice_role') ?>
<?= Html::activeHiddenInput($invoice, 'invoice_category', ['value' => $catId]) ?>
<?= Html::activeHiddenInput($invoice, 'project_id') ?>
<?= Html::activeHiddenInput($invoice, 'phase_value') ?>
<?= Html::activeHiddenInput($invoice, 'milestone_value') ?>
<?= Html::hiddenInput('Invoice[lut_arn]', ($client && (int)$client->client_type === 3) ? $lutArn : '') ?>

<!-- ─── Line items ─── -->
<div class="card border-0 shadow-sm mb-3">
    <?php if ($useLines): ?>
    <div class="card-header ui-section">Line items <span class="ui-section-meta">Pick products — price, unit and GST fill in; GST is worked out per item</span></div>
    <div class="card-body">
        <?= Html::activeHiddenInput($invoice, 'quotation_id') ?>
        <?php if ($invoice->quotation_id && ($q = \backend\models\ClientQuotation::findOne($invoice->quotation_id))): ?>
            <div class="alert alert-info py-2 small"><i class="fas fa-file-signature me-1"></i>Items copied from quotation <strong><?= Html::encode($q->quote_no) ?></strong>. You can still change them.</div>
        <?php endif; ?>
        <?= $this->render('@backend/views/_ui/_line_items', [
            'name'    => 'InvoiceItems',
            'lines'   => $lines,
            'taxMode' => \backend\components\LineItems::taxModeFor($client),
            'errors'  => $lineErrors,
        ]) ?>
    <?php else: ?>
    <div class="card-header ui-section">Line items <span class="ui-section-meta">Fill the first row, then press + to add it</span></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm list-table invoice-items mb-0" id="invoice-items-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:<?= $isSale ? '12%' : '15%' ?>">HSN/SAC <b class="text-danger">*</b></th>
                        <th style="width:<?= $isSale ? '32%' : '60%' ?>">Particulars <b class="text-danger">*</b></th>
                        <?php if ($isSale): ?>
                            <th style="width:12%">Model</th>
                            <th style="width:8%">Qty</th>
                            <th style="width:12%">Rate</th>
                        <?php endif; ?>
                        <th style="width:<?= $isSale ? '14%' : '20%' ?>">Amount <b class="text-danger">*</b></th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="tblbody">
                    <!-- Input row (always visible for adding new items) -->
                    <tr class="trows" id="rowid1">
                        <td></td>
                        <td>
                            <select name="hsn_sac_1" id="hsn_sac_1" class="form-select form-select-sm">
                                <option value="" selected>Select HSN/SAC</option>
                                <?php foreach ($hsnCodes as $hsn): ?>
                                <option value="<?= Html::encode($hsn) ?>"><?= Html::encode($hsn) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <textarea class="form-control form-control-sm" name="particular1" id="particular1" rows="1" placeholder="Enter particulars / description"></textarea>
                        </td>
                        <?php if ($isSale): ?>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="model_1" id="model_1" placeholder="Model" maxlength="50">
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm" name="qty_1" id="qty_1" min="1" step="1" inputmode="numeric" pattern="[0-9]*" placeholder="0" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm" name="rate_1" id="rate_1" min="0" step="0.01" placeholder="0.00">
                        </td>
                        <?php endif; ?>
                        <td>
                            <input type="number" class="form-control form-control-sm" name="particularamount1" id="particularamount1" min="0" step="0.01" placeholder="0.00">
                        </td>
                        <td>
                            <button type="button" id="itemadd" class="btn btn-sm btn-success fw-bold px-2">+</button>
                        </td>
                    </tr>
                    <?php
                    // Pre-load existing items for edit mode
                    $nor = 2;
                    if (!$isNew && !empty($invoice->details)):
                        foreach ($invoice->details as $detail):
                            $detModel = $detail->hasAttribute('model') ? (string)$detail->model : '';
                            $detQty   = $detail->hasAttribute('quantity') && $detail->quantity !== null
                                ? rtrim(rtrim(number_format((float)$detail->quantity, 4, '.', ''), '0'), '.')
                                : '';
                            $detRate  = $detail->hasAttribute('unit_rate') && $detail->unit_rate !== null
                                ? number_format((float)$detail->unit_rate, 2, '.', '')
                                : '';
                    ?>
                    <tr class="trows2" id="rowid<?= $nor ?>">
                        <td><input type="text" class="form-control form-control-sm" name="sno[]" id="sno" readonly value="<?= $nor ?>" /></td>
                        <td><input type="text" class="form-control form-control-sm" name="hsn_sac[]" id="hsn_sac" readonly value="<?= Html::encode($detail->sac) ?>" /></td>
                        <td><textarea class="form-control form-control-sm" name="particular[]" id="particular" readonly rows="1"><?= Html::encode($detail->particulars) ?></textarea></td>
                        <?php if ($isSale): ?>
                            <td><input type="text" class="form-control form-control-sm" name="model[]" readonly value="<?= Html::encode($detModel) ?>" /></td>
                            <td><input type="text" class="form-control form-control-sm" name="qty[]" readonly value="<?= Html::encode($detQty) ?>" /></td>
                            <td><input type="text" class="form-control form-control-sm" name="rate[]" readonly value="<?= Html::encode($detRate) ?>" /></td>
                        <?php endif; ?>
                        <td><input type="text" class="form-control form-control-sm particularamount" name="particularamount[]" id="particularamount" readonly value="<?= number_format((float)$detail->amount, 2, '.', '') ?>" /></td>
                        <td><button type="button" class="btn btn-sm btn-danger fw-bold px-2" onclick="return deleterow(<?= $nor ?>)">-</button></td>
                    </tr>
                    <?php
                            $nor++;
                        endforeach;
                    endif;
                    ?>
                </tbody>
                <!-- Totals section -->
                <?php
                    // Empty leading <th>s so the label/value pair lines up with the Amount column.
                    // Service: 5 cols → 2 empty + label + value + button.
                    // Sale:    8 cols → 5 empty + label + value + button.
                    $emptyCells  = str_repeat('<th></th>', $isSale ? 5 : 2);
                    $emptyCellsG = str_repeat('<th></th>', $isSale ? 5 : 2); // same prefix; we keep the original 1-cell offset out
                ?>
                <tbody id="tblbody2">
                    <tr>
                        <?= $emptyCellsG ?>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end"><strong><span id="grandtotal">00.00</span></strong></th>
                        <th></th>
                    </tr>
                    <?php if ($client && (int)$client->client_type === 1): // Inter-State → IGST ?>
                    <tr class="gst-row">
                        <?= $emptyCells ?>
                        <th class="text-end">IGST @ <?= $igstPct ?>%</th>
                        <th class="text-end"><strong><span id="igsttotal">00.00</span></strong></th>
                        <th></th>
                    </tr>
                    <?php elseif ($client && (int)$client->client_type === 2): // Intra-State → SGST + CGST ?>
                    <tr class="gst-row">
                        <?= $emptyCells ?>
                        <th class="text-end">SGST @ <?= $sgstPct ?>%</th>
                        <th class="text-end"><strong><span id="sgsttotal">00.00</span></strong></th>
                        <th></th>
                    </tr>
                    <tr class="gst-row">
                        <?= $emptyCells ?>
                        <th class="text-end">CGST @ <?= $cgstPct ?>%</th>
                        <th class="text-end"><strong><span id="cgsttotal">00.00</span></strong></th>
                        <th></th>
                    </tr>
                    <?php endif; // International → no GST rows ?>

                    <?php if ($client && in_array((int)$client->client_type, [1, 2])): ?>
                    <tr>
                        <?= $emptyCells ?>
                        <th class="text-end">Sub Total</th>
                        <th class="text-end"><strong><span id="subtotal">00.00</span></strong></th>
                        <th></th>
                    </tr>
                    <tr>
                        <?= $emptyCells ?>
                        <th class="text-end">Round off</th>
                        <th class="text-end"><strong><span id="roundoff">00.00</span></strong></th>
                        <th></th>
                    </tr>
                    <?php endif; ?>

                    <tr>
                        <?= $emptyCells ?>
                        <th class="text-end">Net Total</th>
                        <th class="text-end"><strong><span id="nettotal">00.00</span></strong></th>
                        <th></th>
                    </tr>
                </tbody>
            </table>
            <input type="hidden" id="nor" value="<?= $nor ?>" />
        </div>
    <?php endif; /* classic editor */ ?>

        <!-- Terms & Conditions (optional — shown on preview/PDF only when filled) -->
        <div class="row mt-3">
            <div class="col-12">
                <?= $form->field($invoice, 'terms_and_conditions')->textarea([
                    'rows'        => 4,
                    'class'       => 'form-control form-control-sm',
                    'placeholder' => 'Optional. Leave blank to hide this section on the preview and PDF.',
                    'maxlength'   => true,
                ])->label('Terms &amp; Conditions <small class="text-muted fw-normal">(optional)</small>', ['encode' => false]) ?>
            </div>
        </div>

        <div class="ui-form-actions mt-3">
            <?= Html::submitButton('<i class="fas fa-eye me-1"></i>Preview', ['class' => 'btn btn-primary btn-sm px-4']) ?>
            <span class="small text-muted">Nothing is final until you choose "Save and download" on the preview.</span>
        </div>
    </div>
</div>

<?php ActiveForm::end(); ?>

<?php
// ─── GST no. warning for domestic clients (matches Yii1) ───
// Green Divine mode: most buyers are homeowners without a GSTIN, so no blocking popup (the Bill-to box says B2C).
if ($isNew && $client && in_array((int)$client->client_type, [1, 2]) && empty($client->gst_no) && !\backend\components\Crm::simple()):
    $indexUrl = \yii\helpers\Url::to(['invoice/index']);
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'warning',
        title: 'No GST Number',
        text: 'Are you sure to add invoice without GST Number?',
        showCancelButton: true,
        confirmButtonText: 'Yes, continue',
        cancelButtonText: 'No, go back'
    }).then(function(result) {
        if (!result.isConfirmed) {
            window.location = '<?= $indexUrl ?>';
        }
    });
});
</script>
<?php endif; ?>

<?php
// ─── JavaScript ───
$clientTypeJs = $client ? (int)$client->client_type : 0;
$isNewJs = $isNew ? 'true' : 'false';
$isSaleJs = $isSale ? 'true' : 'false';
$submitTitle = $isProforma ? 'Submit Proforma Invoice' : 'Submit Invoice';
$submitText  = $isProforma ? 'Are you sure to submit this proforma invoice?' : 'Are you sure to submit?';
$useLinesJs = $useLines ? 'true' : 'false';
$this->registerJs(<<<JS
var IS_SALE = $isSaleJs;
var USE_LINES = $useLinesJs;


if (!USE_LINES) { // classic editor only
// ── Auto-calc amount from qty × rate (sale only) ──
function _esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
if (IS_SALE) {
    var _qtyEl  = document.getElementById('qty_1');
    var _rateEl = document.getElementById('rate_1');
    var _amtEl  = document.getElementById('particularamount1');
    function _recalcAmt() {
        var q = parseFloat(_qtyEl.value);
        var r = parseFloat(_rateEl.value);
        if (!isNaN(q) && q > 0 && !isNaN(r) && r > 0) {
            _amtEl.value = (q * r).toFixed(2);
        }
    }
    if (_qtyEl)  _qtyEl.addEventListener('input', _recalcAmt);
    if (_rateEl) _rateEl.addEventListener('input', _recalcAmt);
}

// ── Add item row ──
document.getElementById('itemadd').addEventListener('click', function() {
    var hsn_sac = document.getElementById('hsn_sac_1').value;
    var particular = document.getElementById('particular1').value;
    var particularamount = document.getElementById('particularamount1').value;

    // Sale-only optional fields
    var modelV = '', qtyV = '', rateV = '';
    if (IS_SALE) {
        modelV = (document.getElementById('model_1') || {}).value || '';
        qtyV   = (document.getElementById('qty_1')   || {}).value || '';
        rateV  = (document.getElementById('rate_1')  || {}).value || '';
        // If amount is blank but qty + rate present, compute amount.
        if (particularamount === '' && qtyV !== '' && rateV !== '') {
            particularamount = (parseFloat(qtyV) * parseFloat(rateV)).toFixed(2);
        }
    }

    if (hsn_sac === '') { Swal.fire({icon:'error',title:'Required',text:'Please enter HSN/SAC value'}); document.getElementById('hsn_sac_1').focus(); return false; }
    if (particular === '') { Swal.fire({icon:'error',title:'Required',text:'Please enter particular'}); document.getElementById('particular1').focus(); return false; }
    if (particularamount === '') { Swal.fire({icon:'error',title:'Required',text:'Please enter amount'}); document.getElementById('particularamount1').focus(); return false; }

    var transaction_charge_percent = parseFloat(document.getElementById('transaction_charge_percent').value) || 0;
    if (transaction_charge_percent < 0 || transaction_charge_percent > 100) {
        Swal.fire({icon:'error',title:'Invalid',text:'Please enter a valid Transaction charge percent between 0 to 100'});
        document.getElementById('transaction_charge_percent').focus();
        return false;
    }

    var transaction_charge_amt = parseFloat((particularamount * transaction_charge_percent) / 100);
    var final_amt = parseFloat(particularamount) + parseFloat(transaction_charge_amt);

    var nor = parseInt(document.getElementById('nor').value);

    var sale_cells = '';
    if (IS_SALE) {
        sale_cells =
            '<td><input type="text" class="form-control form-control-sm" name="model[]" readonly value="' + _esc(modelV) + '" /></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="qty[]" readonly value="' + _esc(qtyV) + '" /></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="rate[]" readonly value="' + _esc(rateV) + '" /></td>';
    }

    var new_row = '<tr class="trows2" id="rowid' + (++nor) + '">' +
        '<td><input type="text" class="form-control form-control-sm" name="sno[]" id="sno" readonly value="" /></td>' +
        '<td><input type="text" class="form-control form-control-sm" name="hsn_sac[]" id="hsn_sac" readonly value="' + _esc(hsn_sac) + '" /></td>' +
        '<td><textarea class="form-control form-control-sm" name="particular[]" id="particular" readonly rows="1">' + _esc(particular) + '</textarea></td>' +
        sale_cells +
        '<td><input type="text" class="form-control form-control-sm particularamount" name="particularamount[]" id="particularamount" readonly value="' + parseFloat(final_amt).toFixed(2) + '" /></td>' +
        '<td><button type="button" class="btn btn-sm btn-danger fw-bold px-2" onclick="return deleterow(' + nor + ')">-</button></td>' +
        '</tr>';

    document.getElementById('tblbody').insertAdjacentHTML('beforeend', new_row);
    document.getElementById('nor').value = nor;

    document.getElementById('hsn_sac_1').value = '';
    document.getElementById('particular1').value = '';
    document.getElementById('particularamount1').value = '';
    if (IS_SALE) {
        var m = document.getElementById('model_1'); if (m) m.value = '';
        var q = document.getElementById('qty_1');   if (q) q.value = '';
        var r = document.getElementById('rate_1');  if (r) r.value = '';
    }

    updateserialnumber();
    updategrandtotal();
});

// ── Delete row ──
window.deleterow = function(rno) {
    var row = document.getElementById('rowid' + rno);
    if (row) row.remove();
    updateserialnumber();
    updategrandtotal();
};

// ── Update serial numbers ──
window.updateserialnumber = function() {
    var ii = 1;
    document.querySelectorAll('.trows2').forEach(function(row) {
        var snoInput = row.querySelector('[name="sno[]"]');
        if (snoInput) snoInput.value = ii++;
    });
};

// ── Update grand total + taxes ──
window.updategrandtotal = function() {
    var total = 0;
    document.querySelectorAll('.particularamount').forEach(function(el) {
        total += parseFloat(el.value) || 0;
    });

    var sgst_percent = {$sgstPct};
    var cgst_percent = {$cgstPct};
    var igst_percent = {$igstPct};

    var sgst_amount = parseFloat((total * sgst_percent) / 100);
    var cgst_amount = parseFloat((total * cgst_percent) / 100);
    var igst_amount = parseFloat((total * igst_percent) / 100);

    var sub_total = 0;
    var net_total = 0;
    var round_off = 0;

    var grandEl = document.getElementById('grandtotal');
    if (grandEl) grandEl.textContent = total.toFixed(2);

    var clientType = {$clientTypeJs};

    if (clientType === 1) {
        // Inter-state → IGST only
        net_total = parseFloat(total + igst_amount);
        var el = document.getElementById('sgsttotal'); if (el) el.textContent = '0.00';
        el = document.getElementById('cgsttotal'); if (el) el.textContent = '0.00';
        el = document.getElementById('igsttotal'); if (el) el.textContent = igst_amount.toFixed(2);
    } else if (clientType === 2) {
        // Intra-state → SGST + CGST
        net_total = parseFloat(total + sgst_amount + cgst_amount);
        var el = document.getElementById('sgsttotal'); if (el) el.textContent = sgst_amount.toFixed(2);
        el = document.getElementById('cgsttotal'); if (el) el.textContent = cgst_amount.toFixed(2);
        el = document.getElementById('igsttotal'); if (el) el.textContent = '0.00';
    } else {
        // International → no tax
        net_total = parseFloat(total);
        var el = document.getElementById('sgsttotal'); if (el) el.textContent = '0.00';
        el = document.getElementById('cgsttotal'); if (el) el.textContent = '0.00';
        el = document.getElementById('igsttotal'); if (el) el.textContent = '0.00';
    }

    sub_total = net_total;
    net_total = Math.round(sub_total);
    round_off = net_total - sub_total;

    var netEl = document.getElementById('nettotal'); if (netEl) netEl.textContent = net_total.toFixed(2);
    var subEl = document.getElementById('subtotal'); if (subEl) subEl.textContent = sub_total.toFixed(2);
    var roEl  = document.getElementById('roundoff');  if (roEl) roEl.textContent = round_off.toFixed(2);

    // Lock transaction charge percent once items are added
    var tcEl = document.getElementById('transaction_charge_percent');
    if (tcEl) tcEl.readOnly = (net_total > 0);
};

} // end classic editor

// Prevent ActiveForm's jQuery submit handler from double-firing validate()
$('#invoice-form').off('submit');

// ── Form validation (matches Yii1) ──
var _validating = false;
window.validate = function() {
    if (_validating) return false;
    if (USE_LINES) { return validateLines(); }
    _validating = true;
    var particular = document.getElementById('particular1').value;
    var particularamount = document.getElementById('particularamount1').value;

    // Sale: derive amount from qty × rate if user left amount empty.
    if (IS_SALE && particularamount === '') {
        var qv = (document.getElementById('qty_1')  || {}).value || '';
        var rv = (document.getElementById('rate_1') || {}).value || '';
        if (qv !== '' && rv !== '' && parseFloat(qv) > 0 && parseFloat(rv) > 0) {
            particularamount = (parseFloat(qv) * parseFloat(rv)).toFixed(2);
            document.getElementById('particularamount1').value = particularamount;
        }
    }

    if (particular !== '' && particularamount === '') {
        document.getElementById('itemadd').click();
        _validating = false;
        return false;
    }
    // Auto-add if both fields filled
    if (particular !== '' && particularamount !== '') {
        document.getElementById('itemadd').click();
    }

    var cnt = 0;
    var hasMissing = false;
    document.querySelectorAll('.trows2').forEach(function(row) {
        var partEl = row.querySelector('[name="particular[]"]');
        if (partEl && partEl.value.trim() === '') {
            hasMissing = true;
        } else {
            cnt++;
        }
    });

    if (hasMissing) {
        Swal.fire({icon:'error',title:'Missing Data',text:'Particular is missing!'});
        _validating = false;
        return false;
    }

    if (cnt === 0) {
        Swal.fire({icon:'error',title:'Missing Data',text:'Particular or Amount missing! Please fill the fields with exact data'});
        _validating = false;
        return false;
    }

    var transaction_charge_percent = parseFloat(document.getElementById('transaction_charge_percent').value) || 0;
    if (transaction_charge_percent < 0 || transaction_charge_percent > 100) {
        Swal.fire({icon:'error',title:'Invalid',text:'Invalid transaction charge percent. Please change it between 0 to 100'});
        _validating = false;
        return false;
    }

    // Single SweetAlert confirmation before submit
    Swal.fire({
        icon: 'question',
        title: '$submitTitle',
        text: '$submitText',
        showCancelButton: true,
        confirmButtonText: 'Yes, submit',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        _validating = false;
        if (result.isConfirmed) {
            // Remove onsubmit handler to prevent re-trigger, then submit
            var form = document.getElementById('invoice-form');
            form.removeAttribute('onsubmit');
            form.submit();
        }
    });
    return false; // Always prevent default; SweetAlert handles submit
};

// ── Product line items: at least one item with a description, quantity and price ──
window.validateLines = function() {
    var filled = 0, problem = '';
    $('[data-line-items] [data-line]').each(function (i) {
        var desc = $.trim($(this).find('.js-li-desc').val()), qty = parseFloat($(this).find('.js-li-qty').val()), price = $(this).find('.js-li-price').val();
        var prod = $(this).find('.js-li-product').val();
        if (!desc && !prod && !$(this).find('.js-li-qty').val() && price === '') { return; }
        filled++;
        if (!problem && !desc && !prod) { problem = 'Item ' + (i + 1) + ': pick a product or enter a description.'; }
        if (!problem && !(qty > 0)) { problem = 'Item ' + (i + 1) + ': enter a quantity.'; }
        if (!problem && price === '') { problem = 'Item ' + (i + 1) + ': enter the unit price.'; }
    });
    if (!filled) { problem = 'Add at least one item.'; }
    if (problem) { Swal.fire({icon: 'error', title: 'Check the items', text: problem}); return false; }
    _validating = true;
    Swal.fire({icon: 'question', title: '$submitTitle', text: '$submitText', showCancelButton: true, confirmButtonText: 'Yes, submit', cancelButtonText: 'Cancel'})
        .then(function (r) {
            _validating = false;
            if (r.isConfirmed) { var form = document.getElementById('invoice-form'); form.removeAttribute('onsubmit'); form.submit(); }
        });
    return false;
};

// ── Currency exchange rate via API (matches Yii1) ──
window.set_currency_exchange_rate = function() {
    var rateEl = document.getElementById('currency_exchange_rate');
    if (rateEl) rateEl.value = '';

    var sel = document.getElementById('invoice-currency-sel');
    if (!sel) return;
    var selected = sel.options[sel.selectedIndex];
    if (!selected) return;
    var currencyCode = selected.getAttribute('data-currencycode') || '';

    if (currencyCode !== '') {
        if (currencyCode === '\u20b9') currencyCode = 'INR';
        if (currencyCode === 'INR') {
            if (rateEl) rateEl.value = '1.00';
            return;
        }
        var requestURL = 'https://api.exchangerate.host/latest?base=' + currencyCode + '&symbols=INR';
        var request = new XMLHttpRequest();
        request.open('GET', requestURL);
        request.responseType = 'json';
        request.send();
        request.onload = function() {
            var exchange_rate = 1;
            var response = request.response;
            if (response && response.rates && response.rates.INR > 0) {
                exchange_rate = response.rates.INR;
                if (rateEl) rateEl.value = exchange_rate.toFixed(2);
            }
        };
    }
};

// ── On currency change ──
var currSel = document.getElementById('invoice-currency-sel');
if (currSel) {
    currSel.addEventListener('change', set_currency_exchange_rate);
}

// ── Init on load (only fetch rate for new invoices; edit keeps saved value) ──
var isNew = $isNewJs;
if (isNew) {
    set_currency_exchange_rate();
}

JS, \yii\web\View::POS_READY);

// Run updategrandtotal for edit mode (classic editor)
if (!$isNew && !$useLines) {
    $this->registerJs(<<<JS2
    updateserialnumber();
    updategrandtotal();
    JS2, \yii\web\View::POS_READY);
}
?>
