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

// Invoice category label & color
$categoryLabel = match ($catId) {
    1 => 'Service Invoice',
    2 => 'Sales Invoice',
    default => 'Invoice',
};
$categoryColor = ($catId == 1) ? 'green' : 'red';

// Client type label
$clientTypeLabel = '';
if ($client) {
    $clientTypeLabel = match ((int)$client->client_type) {
        1 => 'Inter-State',
        2 => 'Intra-State',
        3 => 'International',
        default => 'Not Set',
    };
}

// Tax percentages
$sgstPct = 0; $cgstPct = 0; $igstPct = 0;
if ($client && !$client->tax_exemption) {
    if ((int)$client->client_type === 2) { $sgstPct = 9; $cgstPct = 9; }
    elseif ((int)$client->client_type === 1) { $igstPct = 18; }
}

// Transaction charge default
$transactionChargePct = $invoice->transaction_charge_percent ?? 0;
?>

<?php $form = ActiveForm::begin([
    'id' => 'invoice-form',
    'options' => ['onsubmit' => 'return validate()'],
]); ?>

<?= $form->errorSummary($invoice) ?>

<!-- ─── Top Card: Client Info (left) + Form Fields (right) ─── -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <!-- LEFT: Read-only client info -->
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <th class="text-nowrap" style="width:200px">Invoice Category :</th>
                        <td>
                            <span style="background-color:yellow; font-weight:bold; color:<?= $categoryColor ?>">
                                <?= Html::encode($categoryLabel) ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Invoice Number :</th>
                        <td>
                            <span style="background-color:yellow; font-weight:bold; color:<?= $categoryColor ?>">
                                <?= Html::encode($nextNo ?? 'Auto-generated') ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($client): ?>
                    <tr>
                        <th>Client Name :</th>
                        <td><?= Html::encode($client->client_name) ?></td>
                    </tr>
                    <tr>
                        <th>Company Name :</th>
                        <td><?= Html::encode($client->company_name) ?></td>
                    </tr>
                    <tr>
                        <th>Client Type :</th>
                        <td><?= Html::encode($clientTypeLabel) ?></td>
                    </tr>
                    <tr>
                        <th>GST Number :</th>
                        <td><?= Html::encode($client->gst_no) ?></td>
                    </tr>
                    <tr>
                        <th>Tax Exemption Applicable? :</th>
                        <td><?= $client->tax_exemption == 1 ? 'Yes' : 'No' ?></td>
                    </tr>
                    <?php if ($client->tax_exemption == 1): ?>
                    <tr>
                        <th>Tax Exemption Reason :</th>
                        <td><?= Html::encode($client->tax_exemption_reason) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Address :</th>
                        <td><?= Html::encode($client->address) ?></td>
                    </tr>
                    <?php if ((int)$client->client_type === 3 && $lutArn): ?>
                    <tr>
                        <th>LUT ARN :</th>
                        <td><?= Html::encode($lutArn) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-danger">No client selected. Please use the invoice modal to select a client first.</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- RIGHT: Editable form fields -->
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Invoice Title <span class="text-danger">*</span></label>
                    <?= Html::activeTextInput($invoice, 'title', [
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'Invoice Title',
                        'required' => true,
                        'maxlength' => 500,
                    ]) ?>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6 mb-3">
                        <label class="form-label">Currency <span class="text-danger">*</span></label>
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
                    <div class="col-sm-6 mb-3">
                        <label class="form-label">Currency Exchange Rate <span class="text-danger">*</span></label>
                        <?= Html::activeInput('number', $invoice, 'currency_exchange_rate', [
                            'id' => 'currency_exchange_rate',
                            'class' => 'form-control form-control-sm',
                            'min' => '1',
                            'max' => '10000',
                            'step' => '0.01',
                            'required' => true,
                            'placeholder' => 'Currency Exchange Rate',
                        ]) ?>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6 mb-3">
                        <label class="form-label">Transaction Charge %</label>
                        <?= Html::activeInput('number', $invoice, 'transaction_charge_percent', [
                            'id' => 'transaction_charge_percent',
                            'class' => 'form-control form-control-sm',
                            'min' => '0',
                            'max' => '100',
                            'step' => '1',
                            'value' => $transactionChargePct,
                        ]) ?>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label class="form-label">Payment Bank Info</label>
                        <?php
                        $bankData = ArrayHelper::map($banks ?? [], 'id', function ($b) {
                            return $b->bank_name;
                        });
                        echo Select2::widget([
                            'name' => 'Invoice[bank_id]',
                            'value' => $invoice->bank_id,
                            'data' => $bankData,
                            'options' => ['placeholder' => 'Select Bank', 'id' => 'invoice-bank-id'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]);
                        ?>
                    </div>
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

<!-- ─── Bottom Card: Line Items Table ─── -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm" id="invoice-items-table">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:<?= $isSale ? '12%' : '15%' ?>" class="text-danger">HSN/SAC <span>*</span></th>
                        <th style="width:<?= $isSale ? '32%' : '60%' ?>" class="text-danger">Particulars <span>*</span></th>
                        <?php if ($isSale): ?>
                            <th style="width:12%">Model</th>
                            <th style="width:8%">Qty</th>
                            <th style="width:12%">Rate</th>
                        <?php endif; ?>
                        <th style="width:<?= $isSale ? '14%' : '20%' ?>" class="text-danger">Amount <span>*</span></th>
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

        <div class="mt-3">
            <?= Html::submitButton('Preview', ['class' => 'btn btn-primary']) ?>
        </div>
    </div>
</div>

<?php ActiveForm::end(); ?>

<?php
// ─── GST no. warning for domestic clients (matches Yii1) ───
if ($isNew && $client && in_array((int)$client->client_type, [1, 2]) && empty($client->gst_no)):
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
$this->registerJs(<<<JS
var IS_SALE = $isSaleJs;


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

// Prevent ActiveForm's jQuery submit handler from double-firing validate()
$('#invoice-form').off('submit');

// ── Form validation (matches Yii1) ──
var _validating = false;
window.validate = function() {
    if (_validating) return false;
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

// Run updategrandtotal for edit mode
if (!$isNew) {
    $this->registerJs(<<<JS2
    updateserialnumber();
    updategrandtotal();
    JS2, \yii\web\View::POS_READY);
}
?>
