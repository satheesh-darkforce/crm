<?php

/** @var yii\web\View $this */
/** @var backend\models\DcDetail        $dc */
/** @var backend\models\DeliveryDetail  $delivery */
/** @var backend\models\DcDocumentFile  $document */
/** @var backend\models\ClientModel|null $client */
/** @var array $productOptions   id => item_name */
/** @var array $existingProducts */
/** @var bool  $isEdit */
/** @var int|null $displayDcNo */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$displayDcNo      ??= $dc->dc_no;
$existingProducts ??= [];

// DB may store dc_date / dc_return_date as DATETIME — strip the time
// component so the DatePicker input shows a bare "Y-m-d".
if (!empty($dc->dc_date)) {
    $dc->dc_date = date('Y-m-d', strtotime((string) $dc->dc_date));
}
if (!empty($dc->dc_return_date)) {
    $dc->dc_return_date = date('Y-m-d', strtotime((string) $dc->dc_return_date));
}

// Local TomSelect assets (no CDN).
$this->registerCssFile('@web/css/tom-select.bootstrap5.min.css');
$this->registerJsFile('@web/js/tom-select.complete.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<div class="ui-page dc-form">
<div class="ui-head">
    <div>
        <h1><?= $isEdit ? 'Update Delivery Challan' : 'Delivery Challan' ?></h1>
        <div class="ui-sub"><?= $isEdit ? 'Edit the challan, then preview or save.' : 'Fill in the client, delivery and items, then preview or save.' ?></div>
    </div>
    <div class="ui-actions">
        <a href="<?= Url::to(['/quotation-dashboard/index']) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="form-wrapper">
    <?php $form = ActiveForm::begin([
        'id' => 'challan-form',
        'options' => ['enctype' => 'multipart/form-data'],
        'fieldConfig' => [
            'options' => ['class' => 'form-group mb-2'],
            'labelOptions' => ['class' => 'form-label'],
            'inputOptions' => ['class' => 'form-control'],
        ],
    ]); ?>

    <!-- Client & Basic Details ─────────────────────────────────────────── -->
    <div class="section-block">
        <div class="section-header">
            <div class="header-icon"><i class="fas fa-user"></i></div>
            <h4>Client &amp; Basic Details</h4>
        </div>
        <div class="row g-4">
            <?php if (!$client): ?>
                <div class="col-12 col-md-6 col-lg-6 custom-form">
                    <?= $form->field($dc, 'client_id', ['options' => ['class' => 'mb-2 required']])->widget(Select2::class, [
                        'data' => \yii\helpers\ArrayHelper::map(
                            \backend\models\ClientModel::find()->where(['status' => 1])->orderBy('client_name')->asArray()->all(),
                            'id',
                            fn($c) => $c['client_name'] . ' — ' . $c['company_name']
                        ),
                        'options' => ['placeholder' => 'Select Client', 'id' => 'dc-client-picker'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Client') ?>
                </div>
            <?php else: ?>
                <?= Html::activeHiddenInput($dc, 'client_id') ?>
            <?php endif; ?>

            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">Client Name</label>
                <input id="dc-client-name-display" class="form-control" value="<?= Html::encode($client->client_name ?? '') ?>" disabled placeholder="Client Name">
            </div>
            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">Company Name</label>
                <input id="dc-company-name-display" class="form-control" value="<?= Html::encode($client->company_name ?? '') ?>" disabled placeholder="Company Name">
            </div>
            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">GST No</label>
                <input id="dc-gst-display" class="form-control" value="<?= Html::encode($client->gst_no ?? '') ?>" disabled placeholder="GST Number">
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">DC Number</label>
                <input class="form-control" value="<?= Html::encode($displayDcNo) ?>" disabled placeholder="DC Number">
                <?= Html::activeHiddenInput($dc, 'dc_no') ?>
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <?= $form->field($dc, 'ewbc_no')->textInput(['placeholder' => 'EWay Number']) ?>
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form required">
                <?= $form->field($dc, 'dc_date')->widget(DatePicker::class, [
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['id' => 'dc-date-input', 'placeholder' => 'Select date...', 'class' => 'form-control'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                ])->label('DC Date') ?>
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form required">
                <?= $form->field($dc, 'dc_type')->dropDownList(
                    [1 => 'Returnable', 2 => 'Sale'],
                    ['prompt' => 'Select DC Type', 'id' => 'dc-type', 'class' => 'form-select']
                )->label('DC Type') ?>
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form dc-return required" <?= (int)$dc->dc_type === 1 ? '' : 'style="display:none;"' ?>>
                <?= $form->field($dc, 'dc_return_date')->widget(DatePicker::class, [
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['id' => 'dc-return-date-input', 'placeholder' => 'Select date...', 'class' => 'form-control'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                ])->label('DC Return Date') ?>
            </div>

            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label">Address</label>
                <textarea id="dc-address-display" class="form-control" rows="2" disabled placeholder="Enter Full Address"><?= Html::encode($client->address ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Product Details ─────────────────────────────────────────────────── -->
    <div class="section-block">
        <div class="section-header d-flex justify-content-between w-100">
            <div class="d-flex align-items-center">
                <div class="header-icon"><i class="fas fa-boxes"></i></div>
                <h4>Product Details <span class="required-mark">*</span></h4>
            </div>
            <?php if (UserAccess::checkAccess('delivery-product/ajax-create')): ?>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus me-1"></i> Add Product
                </button>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <table class="table mb-0" id="product-table">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Serial Number <span class="required-mark">*</span></th>
                        <th>Item Name</th>
                        <th>HSN Code</th>
                        <th>Quantity <span class="required-mark">*</span></th>
                        <th>Units</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingProducts as $row): $idx = $row['index']; ?>
                        <tr>
                            <td><?= (int) $idx + 1 ?></td>
                            <td>
                                <textarea class="form-control w-75 serial-number"
                                          name="ProductDetails[<?= $idx ?>][serial_no]"
                                          placeholder="Enter serial numbers (comma-separated)"><?= Html::encode($row['serial_no']) ?></textarea>
                            </td>
                            <td>
                                <?= Html::encode($row['item_name']) ?>
                                <input type="hidden" name="ProductDetails[<?= $idx ?>][item_name]" value="<?= Html::encode($row['item_name']) ?>">
                            </td>
                            <td>
                                <?= Html::encode($row['hsn_code']) ?>
                                <input type="hidden" name="ProductDetails[<?= $idx ?>][hsn_code]" value="<?= Html::encode($row['hsn_code']) ?>">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm product-quantity"
                                       style="max-width:90px;"
                                       name="ProductDetails[<?= $idx ?>][quantity]" value="<?= Html::encode($row['quantity']) ?>" placeholder="Qty">
                                <input type="hidden" name="ProductDetails[<?= $idx ?>][id]"    value="<?= Html::encode($row['id']) ?>">
                                <input type="hidden" name="ProductDetails[<?= $idx ?>][units]" value="<?= Html::encode($row['units']) ?>">
                            </td>
                            <td><?= Html::encode($row['units']) ?></td>
                            <td class="text-end px-4"><button type="button" class="remove-row btn btn-danger btn-sm">X</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-end mt-3">
            <button type="button" id="add-row"
                    data-url="<?= Url::to(['/delivery-product/ajax-list']) ?>"
                    class="btn btn-outline-success btn-sm rounded-circle">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>

    <!-- Quotation & Documentation ──────────────────────────────────────── -->
    <div class="section-block">
        <div class="section-header">
            <div class="header-icon"><i class="fas fa-file-contract"></i></div>
            <h4>Quotation &amp; Documentation</h4>
        </div>
        <div class="row g-4">
            <div class="col-12 col-md-4 col-lg-4">
                <?= $form->field($document, 'quotation_no')
                    ->textInput(['placeholder' => 'Quotation Number']) ?>
                <?= $form->field($document, 'quotation_path')
                    ->fileInput(['class' => 'form-control', 'accept' => 'application/pdf,.pdf', 'data-has-existing' => (!empty($document->quotation_path) ? 1 : 0)])
                    ->label('Quotation File') ?>
                <?php if ($isEdit && !empty($document->quotation_path)): ?>
                    <div class="mt-2"><a href="<?= Url::to('@web/' . ltrim($document->quotation_path, '/')) ?>" target="_blank">View current quotation</a></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 col-lg-4">
                <?= $form->field($document, 'invoice_no')->textInput(['placeholder' => 'Invoice Number']) ?>
                <?= $form->field($document, 'invoice_path')->fileInput([
                    'class' => 'form-control',
                    'accept' => 'application/pdf,.pdf',
                    'data-has-existing' => (!empty($document->invoice_path) ? 1 : 0),
                ]) ?>
                <?php if ($isEdit && !empty($document->invoice_path)): ?>
                    <div class="mt-2"><a href="<?= Url::to('@web/' . ltrim($document->invoice_path, '/')) ?>" target="_blank">View current invoice</a></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 col-lg-4">
                <?= $form->field($document, 'po_no')->textInput(['placeholder' => 'PO Number']) ?>
                <?= $form->field($document, 'po_path')->fileInput([
                    'class' => 'form-control',
                    'accept' => 'application/pdf,.pdf',
                    'data-has-existing' => (!empty($document->po_path) ? 1 : 0),
                ]) ?>
                <?php if ($isEdit && !empty($document->po_path)): ?>
                    <div class="mt-2"><a href="<?= Url::to('@web/' . ltrim($document->po_path, '/')) ?>" target="_blank">View current PO</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delivery & Logistics ──────────────────────────────────────────── -->
    <div class="section-block">
        <div class="section-header">
            <div class="header-icon"><i class="fas fa-truck"></i></div>
            <h4>Delivery &amp; Logistics</h4>
        </div>
        <div class="row g-4">
            <div class="col-12 col-md-6 col-lg-3"><?= $form->field($delivery, 'consern_name')->textInput(['placeholder' => 'Concern Name']) ?></div>
            <div class="col-12 col-md-6 col-lg-3"><?= $form->field($delivery, 'gst_no')->textInput(['placeholder' => 'GST Number']) ?></div>
            <div class="col-12 col-md-6 col-lg-3"><?= $form->field($delivery, 'place')->textInput(['placeholder' => 'Place of Supply']) ?></div>
            <div class="col-12 col-md-6 col-lg-3"><?= $form->field($delivery, 'contact_no')->textInput([
                'placeholder' => 'Contact Number',
                'maxlength' => 10,
                'inputmode' => 'numeric',
                'pattern' => '\d{10}',
                'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,10);",
            ]) ?></div>
            <div class="col-12 col-md-6 col-lg-3"><?= $form->field($delivery, 'alt_contact_no')->textInput([
                'placeholder' => 'Alt Contact',
                'maxlength' => 10,
                'inputmode' => 'numeric',
                'pattern' => '\d{10}',
                'oninput' => "this.value=this.value.replace(/\\D/g,'').slice(0,10);",
            ]) ?></div>
            <div class="col-12 col-md-6 col-lg-3"><?= $form->field($delivery, 'vehicle_no')->textInput(['placeholder' => 'Vehicle Number']) ?></div>
            <div class="col-12 col-md-6 col-lg-3">
                <?= $form->field($dc, 'dc_delivery_type')->dropDownList(
                    [1 => 'Self', 2 => 'Customer Pickup', 3 => 'Courier'],
                    ['prompt' => 'Select Delivery Type', 'class' => 'form-select']
                )->label('Delivery Type') ?>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <?= $form->field($dc, 'dc_delivery_description')->textarea(['placeholder' => 'Delivery Description', 'rows' => 1]) ?>
            </div>
            <div class="col-12 col-md-12 col-lg-6">
                <?= $form->field($delivery, 'address')->textarea(['placeholder' => 'Delivery Address', 'rows' => 2]) ?>
            </div>
        </div>
    </div>

    <input type="hidden" name="action" id="form-action">

    <div class="action-footer shadow-sm">
        <?= Html::submitButton('<i class="fas fa-eye me-2"></i> Preview Challan',  ['class' => 'btn btn-outline-primary btn-preview', 'name' => 'action', 'value' => 'preview']) ?>
        <?= Html::submitButton('<i class="fas fa-save me-2"></i> Save &amp; Download', ['class' => 'btn btn-success btn-save',      'name' => 'action', 'value' => 'submit']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<!-- Modal 1: Add Product to catalog ───────────────────────────────────── -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5">Add Product Details</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="product-form">
                <div class="modal-body">
                    <input type="hidden" id="product-add-url" value="<?= Url::to(['/delivery-product/ajax-create']) ?>">
                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control mb-2" id="product-name" required placeholder="Enter product name">
                    <label class="form-label">HSN Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control mb-2" id="hsn-code" required placeholder="Enter HSN code">
                    <label class="form-label">Unit Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control mb-2" id="unit-name" required placeholder="e.g. PCS, NOS, MTR">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success btn-click">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Pick existing products to add to challan ─────────────────── -->
<div class="modal fade" id="productmodal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body product-model">
                <select id="delivery-addresss" multiple placeholder="Select Products...">
                    <?php foreach ($productOptions as $pid => $pname): ?>
                        <option value="<?= (int) $pid ?>"><?= Html::encode($pname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="model-footer text-center">
                <button type="button" class="btn btn-success my-4 product-select"
                        data-url="<?= Url::to(['/delivery-product/ajax-get']) ?>">Submit</button>
            </div>
        </div>
    </div>
</div>

<?php
$getClientUrl = Url::to(['/dc/get-client-info']);

$this->registerJs(<<<JS
/* Client picker → auto-fill Client info + Delivery & Logistics defaults.
   Tracks auto-filled values so that changing or clearing the client resets
   only the fields the user hasn't manually edited. */
var dcAutoFilled = {};

var dcDeliveryPairs = [
    ['#deliverydetail-consern_name',   'client_name'],
    ['#deliverydetail-gst_no',         'gst_no'],
    ['#deliverydetail-contact_no',     'phone'],
    ['#deliverydetail-alt_contact_no', 'alt_phone'],
    ['#deliverydetail-address',        'address'],
];

function dcResetAutoFilled() {
    dcDeliveryPairs.forEach(function (pair) {
        var sel = pair[0], key = pair[1];
        var \$e = $(sel);
        if (!\$e.length) return;
        if (dcAutoFilled[key] !== undefined && \$e.val() === dcAutoFilled[key]) {
            \$e.val('');
        }
    });
    dcAutoFilled = {};
}

$('#dc-client-picker').on('change', function () {
    var id = $(this).val();

    // Always reset the read-only client info block and any previously
    // auto-filled Delivery & Logistics fields up-front — this covers both
    // the "clear" case and the "change to a different client" case.
    $('#dc-client-name-display,#dc-company-name-display,#dc-gst-display').val('');
    $('#dc-address-display').val('');
    dcResetAutoFilled();

    if (!id) return;

    $.post('$getClientUrl', { id: id, _csrf: (window.yii ? yii.getCsrfToken() : '') }, function (r) {
        if (!r || !r.success || !r.client) return;
        var c = r.client;
        $('#dc-client-name-display').val(c.client_name || '');
        $('#dc-company-name-display').val(c.company_name || '');
        $('#dc-gst-display').val(c.gst_no || '');
        $('#dc-address-display').val(c.address || '');
        // Prefill Delivery & Logistics only where the user hasn't typed
        // something custom. Remember what we wrote so a future change/clear
        // can undo it without touching user edits.
        dcDeliveryPairs.forEach(function (pair) {
            var sel = pair[0], key = pair[1];
            var \$e = $(sel);
            if (!\$e.length || \$e.val()) return;
            var v = c[key] || '';
            \$e.val(v);
            dcAutoFilled[key] = v;
        });
    }, 'json');
});
JS);

$downloadId     = Yii::$app->request->get('download_id');
$downloadSubmit = (int) Yii::$app->request->get('download_submit', 0);
$downloadUrl    = $downloadId ? Url::to(['/dc/challan-pdf', 'id' => $downloadId, 'submit' => $downloadSubmit]) : '';
$afterDownloadUrl = Url::to(['/dc/index']);

$this->registerJs(<<<'JS'
/* ══════════════════════════════════════════════════════════════════════
   DC form — product creation + selection (matches delivery/common.js)
   ══════════════════════════════════════════════════════════════════════ */

// ── Modal 1: add new product to catalog ────────────────────────────────
$(document).on('submit', '#product-form', function (e) {
    e.preventDefault();
    var productname = $('#product-name').val();
    var hsn         = $('#hsn-code').val();
    var unit        = $('#unit-name').val();
    var url         = $('#product-add-url').val();

    $.ajax({
        url: url, type: 'POST',
        data: { productname: productname, hsn: hsn, unit: unit, _csrf: (window.yii ? yii.getCsrfToken() : '') },
        dataType: 'json',
        success: function (res) {
            if (res.success) {
                if (window.Swal) Swal.fire({toast:true, position:'top-end', icon:'success', title: res.msg, showConfirmButton:false, timer: 3000, width:'auto'});
                // Also inject into the select-modal's TomSelect so it can be picked immediately.
                if (window.deliverySelect && res.id) {
                    window.deliverySelect.addOption({ value: String(res.id), text: productname });
                    window.deliverySelect.refreshOptions(false);
                }
                $('#hsn-code').val(''); $('#unit-name').val(''); $('#product-name').val('');
                bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
            } else {
                if (window.Swal) Swal.fire({toast:true, position:'top-end', icon:'error', title: res.msg || 'Failed to add', showConfirmButton:false, timer: 4000, width:'auto'});
            }
        },
        error: function () { Swal.fire({icon:'error', title:'Error', text:'Error saving product.'}); }
    });
});

document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function () {
    $('#hsn-code, #unit-name, #product-name').val('');
});

// ── Modal 2: pick existing products and append rows ────────────────────
window.deliverySelect = new TomSelect('#delivery-addresss', {
    plugins: ['remove_button'],
    maxItems: null,
    create: false,
    placeholder: 'Select Product...',
    searchField: ['text'],
    onItemAdd: function (value) {
        var exists = $("#product-table input[name$='[id]'][value='" + value + "']").length > 0;
        if (exists) {
            if (window.Swal) Swal.fire({toast:true, position:'top-end', icon:'warning', title:'Product already selected', timer: 2000, showConfirmButton: false});
            this.removeItem(value);
        }
        this.close();
    },
    onDropdownOpen: function () {
        var self = this;
        $("#product-table input[name$='[id]']").each(function () {
            self.removeOption($(this).val());
        });
    },
});

// "+" button: refresh product list + open product-picker modal
$('#add-row').on('click', function () {
    var url = $(this).data('url');
    $.ajax({
        url: url, type: 'POST',
        dataType: 'json',
        success: function (res) {
            window.deliverySelect.clearOptions();
            window.deliverySelect.clear(true);
            // Exclude products already in the table.
            var existing = {};
            $("#product-table input[name$='[id]']").each(function () { existing[$(this).val()] = true; });
            (res.data || []).forEach(function (item) {
                if (!existing[String(item.id)]) {
                    window.deliverySelect.addOption({ value: String(item.id), text: item.item_name });
                }
            });
            window.deliverySelect.refreshOptions(false);
            new bootstrap.Modal(document.getElementById('productmodal')).show();
        },
        error: function () { Swal.fire({icon:'error', title:'Error', text:'Error loading products'}); }
    });
});

// Submit inside product-picker: POST selected ids, append rows
$('.product-select').on('click', function () {
    var product_id = $('#delivery-addresss').val();
    var url        = $(this).data('url');
    if (!product_id || !product_id.length) {
        if (window.Swal) Swal.fire({toast:true, position:'top-end', icon:'error', title:'Please select a product', timer: 2500});
        return;
    }
    $.ajax({
        url: url, type: 'POST',
        data: { product_id: product_id, _csrf: (window.yii ? yii.getCsrfToken() : '') },
        dataType: 'json',
        success: function (res) {
            if (!res.success || !res.data || !res.data.length) return;
            var index = $('#product-table tbody tr').length;
            $.each(res.data, function (i, p) {
                var row =
                    '<tr>' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td><textarea class="form-control w-75 serial-number" name="ProductDetails[' + index + '][serial_no]" placeholder="Enter serial numbers (comma-separated)"></textarea></td>' +
                    '<td>' + $('<div>').text(p.item_name).html() +
                        '<input type="hidden" name="ProductDetails[' + index + '][item_name]" value="' + $('<div>').text(p.item_name).html() + '">' +
                    '</td>' +
                    '<td>' + $('<div>').text(p.hsn_code).html() +
                        '<input type="hidden" name="ProductDetails[' + index + '][hsn_code]" value="' + $('<div>').text(p.hsn_code).html() + '">' +
                    '</td>' +
                    '<td>' +
                        '<input type="text" name="ProductDetails[' + index + '][quantity]" class="form-control form-control-sm product-quantity" style="max-width:90px;" placeholder="Qty">' +
                        '<input type="hidden" name="ProductDetails[' + index + '][id]"    value="' + p.id + '">' +
                        '<input type="hidden" name="ProductDetails[' + index + '][units]" value="' + $('<div>').text(p.units).html() + '">' +
                    '</td>' +
                    '<td>' + $('<div>').text(p.units).html() + '</td>' +
                    '<td class="text-end px-4"><button type="button" class="remove-row btn btn-danger btn-sm">X</button></td>' +
                    '</tr>';
                $('#product-table tbody').append(row);
                index++;
            });
            bootstrap.Modal.getInstance(document.getElementById('productmodal')).hide();
            window.deliverySelect.clear();
        },
        error: function () { Swal.fire({icon:'error', title:'Error', text:'Something went wrong.'}); }
    });
});

// Quantity: digits only, no leading zeros
$(document).on('input', '.product-quantity', function () {
    this.value = this.value.replace(/[^0-9]/g, '').replace(/^0+/, '');
});

// Remove row + re-index names / S.no
$(document).on('click', '.remove-row', function () {
    $(this).closest('tr').remove();
    $('#product-table tbody tr').each(function (i, tr) {
        $(tr).find('input, textarea').each(function () {
            var name = $(this).attr('name');
            if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
        });
        $(tr).find('td:first').text(i + 1);
    });
});

// ── DC Type / Return Date gating ──────────────────────────────────────
(function () {
    function toggleReturnDate() {
        var t = $('#dc-type').val();
        $('.dc-return').toggle(t == '1');
        if (t != '1') $('#dc-return-date-input').val('');
    }
    $('#dc-type').on('change', toggleReturnDate);
    toggleReturnDate();

    var dcSel = '#dc-date-input', rtSel = '#dc-return-date-input', lastDc = $(dcSel).val();
    function syncReturnDatePicker() {
        var dcVal = $(dcSel).val(), rtVal = $(rtSel).val();
        if (!dcVal) return;
        var pkr = $(rtSel).closest('.input-group.date');
        if (!pkr.length) pkr = $(rtSel);
        if (typeof pkr.kvDatepicker === 'function' && pkr.data('datepicker')) pkr.kvDatepicker('setStartDate', dcVal);
        else if (typeof pkr.datepicker === 'function' && pkr.data('datepicker')) pkr.datepicker('setStartDate', dcVal);
        $(rtSel).attr('min', dcVal);
        if (rtVal && rtVal < dcVal) {
            $(rtSel).val('');
            if (window.Swal) Swal.fire({icon:'warning', title:'Return Date', text:'Return date cannot be less than the DC date.'});
        }
    }
    $(document).ready(function () { setTimeout(syncReturnDatePicker, 0); });
    $(document).on('change changeDate', dcSel, function () {
        var cur = $(dcSel).val();
        if (cur !== lastDc) { $(rtSel).val(''); lastDc = cur; }
        syncReturnDatePicker();
    });
    $(document).on('change changeDate', rtSel, syncReturnDatePicker);
})();
JS);

// Client-side validation — mirrors delivery/web/js/common.js beforeSubmit flow.
$this->registerJs(<<<'JS'
(function () {
    var clickedAction = '';

    $(document).on('click', "#challan-form button[type='submit']", function () {
        clickedAction = $(this).val();
        $('#form-action').val(clickedAction);
    });

    function toast(icon, title) {
        if (window.Swal) {
            Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, timer: 3000, showConfirmButton: false });
        } else {
            alert(title);
        }
    }

    $('#challan-form').on('beforeSubmit', function () {
        var $form = $(this);

        // 1. At least one product row
        if ($('#product-table tbody tr').length === 0) {
            toast('error', 'Please add at least one product');
            return false;
        }

        // 2. Quantity + serial number per row
        var invalid = false, msg = '';
        $('.product-quantity').each(function () {
            var v = $(this).val();
            if (!v || Number(v) <= 0) {
                invalid = true; msg = msg || 'Please fill all Quantities';
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        $('.serial-number').each(function () {
            var v = $(this).val();
            if (!v || !v.trim()) {
                invalid = true; msg = msg || 'Please fill all Serial Numbers';
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        if (invalid) { toast('error', msg); return false; }

        // 3. Contact numbers — exactly 10 digits (alt is optional)
        var $contact = $('#deliverydetail-contact_no');
        var $altContact = $('#deliverydetail-alt_contact_no');
        var contactVal = ($contact.val() || '').trim();
        var altContactVal = ($altContact.val() || '').trim();
        if (!/^\d{10}$/.test(contactVal)) {
            toast('error', 'Contact Number must be exactly 10 digits');
            $contact.addClass('is-invalid').focus();
            return false;
        }
        $contact.removeClass('is-invalid');
        if (altContactVal && !/^\d{10}$/.test(altContactVal)) {
            toast('error', 'Alt Contact Number must be exactly 10 digits');
            $altContact.addClass('is-invalid').focus();
            return false;
        }
        $altContact.removeClass('is-invalid');

        // 4. Quotation & Documentation
        var $qNo = $('#dcdocumentfile-quotation_no');
        if (!$qNo.val() || !$qNo.val().trim()) {
            toast('error', 'Quotation Number is required');
            $qNo.addClass('is-invalid').focus();
            return false;
        }
        $qNo.removeClass('is-invalid');

        function fileRequired($num, $file, label) {
            var num = ($num && $num.val()) ? $num.val().trim() : '';
            var hasExisting = Number($file.data('has-existing')) === 1;
            var hasNew = $file[0] && $file[0].files && $file[0].files.length > 0;
            var hasFile = hasExisting || hasNew;
            var numberLabel = label.replace(' File', '') + ' Number';
            // Number given but no file
            if (num && !hasFile) {
                toast('error', 'Please upload the ' + label + ' for the ' + numberLabel + ' given');
                $num.removeClass('is-invalid');
                $file.addClass('is-invalid').focus();
                return false;
            }
            // File given but no number
            if (hasFile && !num) {
                toast('error', numberLabel + ' is required when ' + label + ' is uploaded');
                $file.removeClass('is-invalid');
                $num.addClass('is-invalid').focus();
                return false;
            }
            $num.removeClass('is-invalid');
            $file.removeClass('is-invalid');
            return true;
        }

        var $qFile = $('#dcdocumentfile-quotation_path');
        var qHasExisting = Number($qFile.data('has-existing')) === 1;
        var qHasNew = $qFile[0] && $qFile[0].files && $qFile[0].files.length > 0;
        if (!qHasExisting && !qHasNew) {
            toast('error', 'Quotation File is required');
            return false;
        }

        if (!fileRequired($('#dcdocumentfile-invoice_no'), $('#dcdocumentfile-invoice_path'), 'Invoice File')) return false;
        if (!fileRequired($('#dcdocumentfile-po_no'),      $('#dcdocumentfile-po_path'),      'PO File'))      return false;

        // 4. Confirmation Swal (matches delivery)
        var text  = 'All validations passed. Do you want to continue?';
        var dctype = $('#dc-type').val();
        var poNo   = $('#dcdocumentfile-po_no').val();
        if (dctype == '2' && (!poNo || !poNo.trim())) {
            text = 'You selected Sale DC without a PO number.';
        }

        if (window.Swal) {
            Swal.fire({
                title: 'Submit Challan?',
                html:  '<span style="color:red;">' + text + '</span>',
                icon:  'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Continue',
                confirmButtonColor: '#3e4195',
            }).then(function (result) {
                if (result.isConfirmed) {
                    $form.off('beforeSubmit');
                    $form.get(0).submit();
                }
            });
            return false;
        }
        return true;
    });
})();
JS);

// Auto-trigger download after a successful submit (matches delivery), then refresh to listing
if ($downloadId) {
    $this->registerJs(<<<JS
    setTimeout(function () {
        var dlFrame = document.createElement('iframe');
        dlFrame.style.display = 'none';
        dlFrame.src = '$downloadUrl';
        document.body.appendChild(dlFrame);
        setTimeout(function () { window.location.href = '$afterDownloadUrl'; }, 1500);
    }, 500);
JS);
}
?>
</div>
