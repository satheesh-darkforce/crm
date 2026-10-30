<?php
/** @var \yii\web\View $this */
/** @var \backend\models\InwardInvoice $model */
/** @var \backend\models\Vendor[] $vendors */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Create Purchase Invoice' : 'Update Purchase Invoice';

// Resolve vendor
$vendor = null;
if ($model->vendor_id) {
    $vendor = \backend\models\Vendor::findOne($model->vendor_id);
}

// Tax field readonly based on vendor_type (matches Yii1)
$igstRequired = false;
$sgstRequired = false;
$cgstRequired = false;
if ($vendor && $vendor->vendor_type) {
    if ((int)$vendor->vendor_type === 1) { $igstRequired = true; }
    elseif ((int)$vendor->vendor_type === 2) { $sgstRequired = true; $cgstRequired = true; }
}

$vendorTypes = [1 => 'Inter State', 2 => 'Intra State', 3 => 'International'];

// IOT products for Select2 in the input row
$iotProducts = \backend\models\IotProduct::find()->where(['status' => 1])->orderBy('product_name')->all();
$iotProductData = ArrayHelper::map($iotProducts, 'id', 'product_name');

// Payment ledger categories (is_payment_ledger = 1)
$paymentTypes = ArrayHelper::map(
    \backend\models\AccLedgerCategory::find()->where(['status' => 1, 'is_payment_ledger' => 1])->all(),
    'id', 'title'
);

// AJAX URLs
$ledgerCatUrl   = Url::to(['/inward-invoice/autocomplete-ledger-category']);
$ledgerUrl      = Url::to(['/inward-invoice/autocomplete-ledger']);
$paymentLedgerUrl = Url::to(['/inward-invoice/get-payment-ledgers']);
?>
<style>
.invoice_table { table-layout: fixed; width: 100%; }
.invoice_table th, .invoice_table td { font-size: .72rem; padding: 2px 4px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; }
.invoice_table input, .invoice_table select, .invoice_table textarea { font-size: .72rem; }
.invoice_table .form-control, .invoice_table .form-select { padding: .1rem .25rem; }
.invoice_table .serial { width: 32px; text-align: center; }
.invoice_table .col-s2  { width: 120px; } /* ledger cat, ledger, product */
.invoice_table .col-hsn { width: 55px; }
.invoice_table .col-num { width: 62px; }  /* qty, price, item total, pct, amt */
.invoice_table .col-tot { width: 72px; }  /* total amount */
.totals-label { text-align: right; font-weight: 600; font-size: .78rem; }
/* Select2 compact inside table */
.invoice_table .select2-container { font-size: .72rem; }
.invoice_table .select2-container .select2-selection--single { min-height: 24px; padding: 0 3px; }
.invoice_table .select2-container .select2-selection--single .select2-selection__rendered { line-height: 24px; font-size: .72rem; }
.invoice_table .select2-container .select2-selection--single .select2-selection__arrow { height: 24px; }
</style>

<div class="iot-purchase-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['purchased'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin([
                'successCssClass' => '',
                'options' => ['enctype' => 'multipart/form-data'],
            ]); ?>

            <p class="text-muted small mb-3">Fields with <span class="text-danger">*</span> are required.</p>

            <!-- Vendor Info (read-only) -->
            <?php if ($vendor): ?>
            <div class="mb-3" style="font-size:.875rem">
                <p class="mb-1"><strong>Company Name :</strong> <?= Html::encode($vendor->company_name) ?></p>
                <p class="mb-1"><strong>Vendor Type :</strong> <?= Html::encode($vendorTypes[(int)$vendor->vendor_type] ?? '—') ?></p>
                <p class="mb-1"><strong>GST Number :</strong> <?= Html::encode($vendor->gst_no ?: '—') ?></p>
                <p class="mb-1"><strong>Address :</strong> <?= Html::encode($vendor->address ?: '—') ?></p>
            </div>
            <?= Html::activeHiddenInput($model, 'vendor_id') ?>
            <?php else: ?>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <?= $form->field($model, 'vendor_id')->label('Vendor <span class="text-danger">*</span>', ['encode' => false])->widget(Select2::class, [
                        'data' => ArrayHelper::map($vendors, 'id', 'company_name'),
                        'options' => ['placeholder' => 'Select vendor…'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Header fields -->
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <?= $form->field($model, 'invoice_number')->label('Invoice Number <span class="text-danger">*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Invoice Number', 'maxlength' => 250]) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'invoice_date')->label('Invoice Date <span class="text-danger">*</span>', ['encode' => false])->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'value' => $model->invoice_date ?: date('Y-m-d'),
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select a Date'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'title')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Title', 'maxlength' => 500]) ?>
                </div>
            </div>

            <!-- Line Items Table (matches Yii1) -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm invoice_table" id="line-items">
                    <thead class="table-light">
                        <tr>
                            <th class="serial">#</th>
                            <th class="col-s2">Ledger Cat <span class="text-danger">*</span></th>
                            <th class="col-s2">Ledger <span class="text-danger">*</span></th>
                            <th class="col-s2">Products <span class="text-danger">*</span></th>
                            <th class="col-hsn">HSN</th>
                            <th class="col-num">Qty <span class="text-danger">*</span></th>
                            <th class="col-num">Price/Qty <span class="text-danger">*</span></th>
                            <th class="col-num">Item Total <span class="text-danger">*</span></th>
                            <th class="col-num">SGST %</th>
                            <th class="col-num">SGST Amt</th>
                            <th class="col-num">CGST %</th>
                            <th class="col-num">CGST Amt</th>
                            <th class="col-num">IGST %</th>
                            <th class="col-num">IGST Amt</th>
                            <th class="col-tot">Total Amt <span class="text-danger">*</span></th>
                            <th class="serial"></th>
                        </tr>
                    </thead>
                    <tbody id="tblbody">
                        <!-- Input row (first row for adding items) -->
                        <tr class="input-row" id="rowid-input">
                            <td class="serial"></td>
                            <td>
                                <select id="inp-ledger-cat" style="width:100%">
                                    <option value=""></option>
                                </select>
                                <input type="hidden" id="inp-ledger-cat-id" value="">
                            </td>
                            <td>
                                <select id="inp-ledger" style="width:100%">
                                    <option value=""></option>
                                </select>
                            </td>
                            <td>
                                <select id="inp-product" style="width:100%">
                                    <option value="">Select Product</option>
                                    <?php foreach ($iotProducts as $p): ?>
                                        <option value="<?= $p->id ?>"><?= Html::encode($p->product_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" id="inp-hsn" class="form-control form-control-sm" placeholder="HSN"></td>
                            <td><input type="number" id="inp-qty" step="0.01" min="0" class="form-control form-control-sm" value="1"></td>
                            <td><input type="number" id="inp-price" step="0.01" min="0" class="form-control form-control-sm" placeholder="0.00"></td>
                            <td><input type="number" id="inp-item-total" step="0.01" min="0" class="form-control form-control-sm"></td>
                            <td><input type="number" id="inp-sgst-pct" step="0.01" min="0" max="100" class="form-control form-control-sm" <?= !$sgstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" id="inp-sgst-amt" step="0.01" min="0" class="form-control form-control-sm" <?= !$sgstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" id="inp-cgst-pct" step="0.01" min="0" max="100" class="form-control form-control-sm" <?= !$cgstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" id="inp-cgst-amt" step="0.01" min="0" class="form-control form-control-sm" <?= !$cgstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" id="inp-igst-pct" step="0.01" min="0" max="100" class="form-control form-control-sm" <?= !$igstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" id="inp-igst-amt" step="0.01" min="0" class="form-control form-control-sm" <?= !$igstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" id="inp-total-amt" step="0.01" min="0" class="form-control form-control-sm" readonly></td>
                            <td><button type="button" id="itemadd" class="btn btn-sm btn-success" style="font-weight:bold;padding:1px 7px">+</button></td>
                        </tr>

                        <!-- Existing saved rows (edit mode) -->
                        <?php if (!$isNew && !empty($model->lineItems)):
                            $rowNum = 1;
                            foreach ($model->lineItems as $di => $d):
                                $ledgerName = '';
                                $catName = '';
                                if ($d->ledger_id) {
                                    $ledger = \backend\models\AccLedger::findOne($d->ledger_id);
                                    if ($ledger) {
                                        $ledgerName = $ledger->title;
                                        $cat = $ledger->category;
                                        $catName = $cat ? $cat->title : '';
                                    }
                                }
                        ?>
                        <tr class="item-row" id="rowid<?= $rowNum ?>">
                            <td class="text-center sno"><?= $rowNum ?></td>
                            <td><textarea class="form-control form-control-sm" name="ledger_cat[]" rows="1" readonly><?= Html::encode($catName) ?></textarea></td>
                            <td>
                                <textarea class="form-control form-control-sm" name="ledger[]" rows="1" readonly><?= Html::encode($ledgerName) ?></textarea>
                                <input type="hidden" name="ledger_ids[]" value="<?= (int)$d->ledger_id ?>">
                            </td>
                            <td>
                                <input class="form-control form-control-sm" name="product_text[]" value="<?= Html::encode($d->product) ?>" readonly>
                                <input type="hidden" name="product_id[]" value="<?= (int)$d->product_id ?>">
                            </td>
                            <td><textarea class="form-control form-control-sm" name="hsn[]" rows="1"><?= Html::encode($d->hsn) ?></textarea></td>
                            <td><input type="number" name="qty[]" step="0.01" min="0" class="form-control form-control-sm row-qty" value="<?= $d->qty ?>"></td>
                            <td><input type="number" name="price_per_qty[]" step="0.01" min="0" class="form-control form-control-sm row-price" value="<?= $d->price_per_qty ?>"></td>
                            <td><input type="number" name="item_total[]" step="0.01" min="0" class="form-control form-control-sm row-item-total" value="<?= $d->item_total ?>"></td>
                            <td><input type="number" name="sgst_percent[]" step="0.01" min="0" max="100" class="form-control form-control-sm row-sgst-pct" value="<?= $d->sgst_percent ?>" <?= !$sgstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" name="sgst_amount[]" step="0.01" min="0" class="form-control form-control-sm row-sgst-amt" value="<?= $d->sgst_amount ?>" <?= !$sgstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" name="cgst_percent[]" step="0.01" min="0" max="100" class="form-control form-control-sm row-cgst-pct" value="<?= $d->cgst_percent ?>" <?= !$cgstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" name="cgst_amount[]" step="0.01" min="0" class="form-control form-control-sm row-cgst-amt" value="<?= $d->cgst_amount ?>" <?= !$cgstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" name="igst_percent[]" step="0.01" min="0" max="100" class="form-control form-control-sm row-igst-pct" value="<?= $d->igst_percent ?>" <?= !$igstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" name="igst_amount[]" step="0.01" min="0" class="form-control form-control-sm row-igst-amt" value="<?= $d->igst_amount ?>" <?= !$igstRequired ? 'readonly' : '' ?>></td>
                            <td><input type="number" name="total_amount[]" step="0.01" min="0" class="form-control form-control-sm row-total-amt" value="<?= $d->total_amount ?>"></td>
                            <td><button type="button" class="btn btn-sm btn-danger remove-row" style="font-weight:bold;padding:1px 7px" data-row="<?= $rowNum ?>">−</button></td>
                        </tr>
                        <?php $rowNum++; endforeach; endif; ?>
                    </tbody>
                    <!-- Totals -->
                    <tbody>
                        <tr><td colspan="16">&nbsp;</td></tr>
                        <tr>
                            <td colspan="14" class="totals-label">Grand Total <span class="text-danger">*</span></td>
                            <td><input type="number" name="grandtotal" id="grandtotal" step="0.01" min="0" class="form-control form-control-sm" value="<?= $model->grand_total ?? 0 ?>"></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="14" class="totals-label"><?= $sgstRequired ? 'SGST Amount <span class="text-danger">*</span>' : 'SGST Amount' ?></td>
                            <td><input type="number" name="sgstamount" id="sgstamount" step="0.01" min="0" class="form-control form-control-sm" value="<?= $model->sgst_amount ?? 0 ?>" <?= !$sgstRequired ? 'readonly' : '' ?>></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="14" class="totals-label"><?= $cgstRequired ? 'CGST Amount <span class="text-danger">*</span>' : 'CGST Amount' ?></td>
                            <td><input type="number" name="cgstamount" id="cgstamount" step="0.01" min="0" class="form-control form-control-sm" value="<?= $model->cgst_amount ?? 0 ?>" <?= !$cgstRequired ? 'readonly' : '' ?>></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="14" class="totals-label"><?= $igstRequired ? 'IGST Amount <span class="text-danger">*</span>' : 'IGST Amount' ?></td>
                            <td><input type="number" name="igstamount" id="igstamount" step="0.01" min="0" class="form-control form-control-sm" value="<?= $model->igst_amount ?? 0 ?>" <?= !$igstRequired ? 'readonly' : '' ?>></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="14" class="totals-label">Round Off</td>
                            <td><input type="number" name="round_off" id="round-off" step="0.01" class="form-control form-control-sm" value="<?= $model->round_off ?? 0 ?>"></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="14" class="totals-label">Net Amount <span class="text-danger">*</span></td>
                            <td><input type="number" name="nettotal" id="nettotal" step="0.01" min="0" class="form-control form-control-sm fw-bold" value="<?= $model->net_total ?? 0 ?>"></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Payment Section -->
            <div class="row g-3 mt-3">
                <div class="col-md-2">
                    <?= $form->field($model, 'payment_status')->dropDownList(
                        ['0' => 'Not Paid', '1' => 'Paid'],
                        ['class' => 'form-select form-select-sm', 'prompt' => 'Select Payment Status', 'id' => 'payment-status']
                    ) ?>
                </div>
            </div>
            <div class="row g-3" id="payment-fields" style="<?= $model->payment_status == 1 ? '' : 'display:none' ?>">
                <div class="col-md-2">
                    <?= $form->field($model, 'payment_type')->dropDownList($paymentTypes, [
                        'class' => 'form-select form-select-sm',
                        'prompt' => 'Select Payment Type',
                        'id' => 'payment-type',
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'payment_ledger_id')->dropDownList(
                        !$isNew && $model->payment_ledger_id
                            ? ArrayHelper::map(\backend\models\AccLedger::find()->where(['id' => $model->payment_ledger_id, 'status' => 1])->all(), 'id', 'title')
                            : [],
                        ['class' => 'form-select form-select-sm', 'prompt' => 'Select Payment Ledger', 'id' => 'payment-ledger']
                    )->label('Payment Ledger') ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'payment_date')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select a Date'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'payment_amount')->textInput(['class' => 'form-control form-control-sm', 'type' => 'number', 'step' => '0.01', 'placeholder' => '0.00']) ?>
                </div>
                <div class="col-md-2">
                    <?= $form->field($model, 'tds_amount')->textInput(['class' => 'form-control form-control-sm', 'type' => 'number', 'step' => '0.01', 'placeholder' => '0.00'])->label('TDS Amount') ?>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <?= $form->field($model, 'payment_remarks')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Payment Remarks', 'maxlength' => 5000]) ?>
                </div>
            </div>

            <!-- Attachments -->
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['purchased'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$sgstRO = $sgstRequired ? 'false' : 'true';
$cgstRO = $cgstRequired ? 'false' : 'true';
$igstRO = $igstRequired ? 'false' : 'true';
$existingRows = (!$isNew && !empty($model->lineItems)) ? count($model->lineItems) : 0;
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$this->registerJs(<<<JS
var rowIdx = $existingRows;
var sgstReadonly = $sgstRO, cgstReadonly = $cgstRO, igstReadonly = $igstRO;

/* ── Ledger Category — Select2 with AJAX search (matches Yii1 autocomplete) ── */
$('#inp-ledger-cat').select2({
    placeholder: 'Search Ledger Category…',
    allowClear: true,
    width: '100%',
    minimumInputLength: 1,
    ajax: {
        url: '$ledgerCatUrl',
        dataType: 'json',
        delay: 250,
        data: function(params) { return { term: params.term }; },
        processResults: function(data) {
            return { results: $.map(data, function(item) { return { id: item.id, text: item.label }; }) };
        },
        cache: true
    }
});

$('#inp-ledger-cat').on('select2:select', function(e) {
    $('#inp-ledger-cat-id').val(e.params.data.id);
    loadLedgers(e.params.data.id);
});
$('#inp-ledger-cat').on('select2:clear', function() {
    $('#inp-ledger-cat-id').val('');
    $('#inp-ledger').html('<option value=""></option>').val('').trigger('change');
});

function loadLedgers(catId) {
    $('#inp-ledger').html('<option value="">Loading…</option>').trigger('change');
    $.post('$ledgerUrl', { '$csrfParam': '$csrfToken', categoryId: catId }, function(r) {
        var opts = '<option value=""></option>';
        if (r.status == 1 && r.data) {
            $.each(r.data, function(id, title) { opts += '<option value="'+id+'">'+title+'</option>'; });
        }
        $('#inp-ledger').html(opts).trigger('change');
    }, 'json');
}

/* ── Ledger — Select2 (populated after category selection) ── */
$('#inp-ledger').select2({ placeholder: 'Select Ledger', allowClear: true, width: '100%' });

/* ── Product — Select2 with search ── */
$('#inp-product').select2({ placeholder: 'Select Product', allowClear: true, width: '100%' });

/* ── Input row calculations ── */
function recalcInput() {
    var qty = pf('#inp-qty'), rate = pf('#inp-price');
    var it = qty * rate;
    $('#inp-item-total').val(it.toFixed(2));
    var sp = pf('#inp-sgst-pct'), cp = pf('#inp-cgst-pct'), ip = pf('#inp-igst-pct');
    var sa = it*sp/100, ca = it*cp/100, ia = it*ip/100;
    $('#inp-sgst-amt').val(sa.toFixed(2));
    $('#inp-cgst-amt').val(ca.toFixed(2));
    $('#inp-igst-amt').val(ia.toFixed(2));
    $('#inp-total-amt').val((it+sa+ca+ia).toFixed(2));
}
$('#inp-qty,#inp-price,#inp-item-total,#inp-sgst-pct,#inp-cgst-pct,#inp-igst-pct').on('input', recalcInput);

/* ── Add row on + click ── */
$('#itemadd').on('click', function() {
    var product_text = $('#inp-product option:selected').text().trim();
    var product_val = $('#inp-product').val();
    var ledger_cat_data = $('#inp-ledger-cat').select2('data');
    var ledger_cat = (ledger_cat_data && ledger_cat_data.length) ? ledger_cat_data[0].text : '';
    var ledger_text = $('#inp-ledger option:selected').text().trim();
    var ledger_id = $('#inp-ledger').val();

    if (!ledger_cat) { Swal.fire({icon:'warning', title:'Required', text:'Please select ledger category'}); $('#inp-ledger-cat').focus(); return; }
    if (!ledger_id)  { Swal.fire({icon:'warning', title:'Required', text:'Please select ledger'}); $('#inp-ledger').focus(); return; }
    if (!product_val) { Swal.fire({icon:'warning', title:'Required', text:'Please select product'}); $('#inp-product').focus(); return; }

    var qty = $('#inp-qty').val(), ppq = $('#inp-price').val(), it = $('#inp-item-total').val();
    if (!qty) { Swal.fire({icon:'warning', title:'Required', text:'Please enter quantity'}); return; }
    if (!ppq) { Swal.fire({icon:'warning', title:'Required', text:'Please enter price per quantity'}); return; }

    rowIdx++;
    var n = rowIdx;
    var ro_s = sgstReadonly ? 'readonly' : '', ro_c = cgstReadonly ? 'readonly' : '', ro_i = igstReadonly ? 'readonly' : '';

    var row = '<tr class="item-row" id="rowid'+n+'">' +
        '<td class="text-center sno"></td>' +
        '<td><textarea class="form-control form-control-sm" name="ledger_cat[]" rows="1" readonly>'+escH(ledger_cat)+'</textarea></td>' +
        '<td><textarea class="form-control form-control-sm" name="ledger[]" rows="1" readonly>'+escH(ledger_text)+'</textarea>' +
        '<input type="hidden" name="ledger_ids[]" value="'+ledger_id+'"></td>' +
        '<td><input class="form-control form-control-sm" name="product_text[]" value="'+escH(product_text)+'" readonly>' +
        '<input type="hidden" name="product_id[]" value="'+product_val+'"></td>' +
        '<td><textarea class="form-control form-control-sm" name="hsn[]" rows="1">'+escH($('#inp-hsn').val())+'</textarea></td>' +
        '<td><input type="number" name="qty[]" step="0.01" min="0" class="form-control form-control-sm row-qty" value="'+qty+'"></td>' +
        '<td><input type="number" name="price_per_qty[]" step="0.01" min="0" class="form-control form-control-sm row-price" value="'+ppq+'"></td>' +
        '<td><input type="number" name="item_total[]" step="0.01" min="0" class="form-control form-control-sm row-item-total" value="'+v('#inp-item-total')+'"></td>' +
        '<td><input type="number" name="sgst_percent[]" step="0.01" min="0" max="100" class="form-control form-control-sm row-sgst-pct" value="'+v('#inp-sgst-pct')+'" '+ro_s+'></td>' +
        '<td><input type="number" name="sgst_amount[]" step="0.01" min="0" class="form-control form-control-sm row-sgst-amt" value="'+v('#inp-sgst-amt')+'" '+ro_s+'></td>' +
        '<td><input type="number" name="cgst_percent[]" step="0.01" min="0" max="100" class="form-control form-control-sm row-cgst-pct" value="'+v('#inp-cgst-pct')+'" '+ro_c+'></td>' +
        '<td><input type="number" name="cgst_amount[]" step="0.01" min="0" class="form-control form-control-sm row-cgst-amt" value="'+v('#inp-cgst-amt')+'" '+ro_c+'></td>' +
        '<td><input type="number" name="igst_percent[]" step="0.01" min="0" max="100" class="form-control form-control-sm row-igst-pct" value="'+v('#inp-igst-pct')+'" '+ro_i+'></td>' +
        '<td><input type="number" name="igst_amount[]" step="0.01" min="0" class="form-control form-control-sm row-igst-amt" value="'+v('#inp-igst-amt')+'" '+ro_i+'></td>' +
        '<td><input type="number" name="total_amount[]" step="0.01" min="0" class="form-control form-control-sm row-total-amt" value="'+v('#inp-total-amt')+'"></td>' +
        '<td><button type="button" class="btn btn-sm btn-danger remove-row" style="font-weight:bold;padding:1px 7px" data-row="'+n+'">\u2212</button></td>' +
        '</tr>';

    $('#tblbody').append(row);
    bindItemRow($('#rowid'+n));
    updateSerials();
    recalcTotals();

    // Clear input row
    $('#inp-ledger-cat').val(null).trigger('change'); $('#inp-ledger-cat-id').val('');
    $('#inp-ledger').html('<option value=""></option>').val(null).trigger('change');
    $('#inp-product').val(null).trigger('change'); $('#inp-hsn').val('');
    $('#inp-qty').val('1'); $('#inp-price').val('');
    $('#inp-item-total,#inp-sgst-pct,#inp-sgst-amt,#inp-cgst-pct,#inp-cgst-amt,#inp-igst-pct,#inp-igst-amt,#inp-total-amt').val('');
});

/* ── Helpers ── */
function pf(sel) { return parseFloat($(sel).val()) || 0; }
function v(sel) { return $(sel).val() || ''; }
function escH(s) { return $('<div>').text(s||'').html(); }

function updateSerials() {
    var i = 1;
    $('#tblbody .item-row').each(function() { $(this).find('.sno').text(i++); });
}

function recalcTotals() {
    var grand = 0, sgst = 0, cgst = 0, igst = 0;
    $('#tblbody .item-row').each(function() {
        grand += parseFloat($(this).find('.row-item-total').val()) || 0;
        sgst  += parseFloat($(this).find('.row-sgst-amt').val()) || 0;
        cgst  += parseFloat($(this).find('.row-cgst-amt').val()) || 0;
        igst  += parseFloat($(this).find('.row-igst-amt').val()) || 0;
    });
    $('#grandtotal').val(grand.toFixed(2));
    $('#sgstamount').val(sgst.toFixed(2));
    $('#cgstamount').val(cgst.toFixed(2));
    $('#igstamount').val(igst.toFixed(2));
    var roundOff = parseFloat($('#round-off').val()) || 0;
    $('#nettotal').val((grand + sgst + cgst + igst + roundOff).toFixed(2));
}

function recalcItemRow(row) {
    var qty = parseFloat(row.find('.row-qty').val()) || 0;
    var ppq = parseFloat(row.find('.row-price').val()) || 0;
    var it = qty * ppq;
    row.find('.row-item-total').val(it.toFixed(2));
    var sp = parseFloat(row.find('.row-sgst-pct').val()) || 0;
    var cp = parseFloat(row.find('.row-cgst-pct').val()) || 0;
    var ip = parseFloat(row.find('.row-igst-pct').val()) || 0;
    row.find('.row-sgst-amt').val((it*sp/100).toFixed(2));
    row.find('.row-cgst-amt').val((it*cp/100).toFixed(2));
    row.find('.row-igst-amt').val((it*ip/100).toFixed(2));
    var sa = parseFloat(row.find('.row-sgst-amt').val()) || 0;
    var ca = parseFloat(row.find('.row-cgst-amt').val()) || 0;
    var ia = parseFloat(row.find('.row-igst-amt').val()) || 0;
    row.find('.row-total-amt').val((it+sa+ca+ia).toFixed(2));
    recalcTotals();
}

function bindItemRow(row) {
    row.find('.row-qty,.row-price,.row-sgst-pct,.row-cgst-pct,.row-igst-pct').on('input', function() { recalcItemRow(row); });
    row.find('.remove-row').on('click', function() {
        if (confirm('Are you sure want to remove this?')) {
            row.remove();
            updateSerials();
            recalcTotals();
        }
    });
}

$('#tblbody .item-row').each(function() { bindItemRow($(this)); });
$('#round-off').on('input', recalcTotals);
updateSerials();
recalcTotals();

/* ── Payment toggle ── */
$('#payment-status').on('change', function() {
    $('#payment-fields').toggle(this.value === '1');
});

/* ── Payment type → load payment ledgers ── */
$('#payment-type').on('change', function() {
    var typeId = $(this).val();
    if (!typeId) { $('#payment-ledger').html('<option value="">Select Payment Ledger</option>'); return; }
    $.post('$paymentLedgerUrl', { '$csrfParam': '$csrfToken', payment_type: typeId }, function(r) {
        var opts = '<option value="">Select Payment Ledger</option>';
        if (r.status == 1 && r.data) {
            $.each(r.data, function(id, title) { opts += '<option value="'+id+'">'+title+'</option>'; });
        }
        $('#payment-ledger').html(opts);
    }, 'json');
});
JS);
?>
