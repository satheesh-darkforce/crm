<?php

/** @var yii\web\View $this */
/** @var backend\models\DcDetail        $dc */
/** @var backend\models\DcDetail        $inward */
/** @var backend\models\DeliveryDetail  $delivery */
/** @var backend\models\DcDocumentFile  $document */
/** @var backend\models\ClientModel|null $client */
/** @var array $productOptions   id => item_name */
/** @var array $existingProducts */
/** @var int|null $displayDcNo */

use common\components\UserAccess;
use kartik\date\DatePicker;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$isEdit = !$dc->isNewRecord;
$this->title = ($isEdit ? 'Edit Return — Inward ' : 'Add Return — Inward ') . ($inward->inward_dc_no ?? '');

$displayDcNo      ??= $dc->dc_no;
$existingProducts ??= [];

if (!empty($dc->dc_date)) {
    $dc->dc_date = date('Y-m-d', strtotime((string) $dc->dc_date));
}

$this->registerCssFile('@web/css/tom-select.bootstrap5.min.css');
$this->registerJsFile('@web/js/tom-select.complete.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<style>
.form-wrapper      { background-color: #f8fafc; padding: 40px 20px; border-radius: 15px; }
.section-block     { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.section-header    { display: flex; align-items: center; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
.section-header h4 { margin-bottom: 0; color: #1e293b; font-weight: 700; font-size: 1.15rem; text-transform: uppercase; letter-spacing: .5px; }
.header-icon       { background: #3b82f6; color: #fff; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: .9rem; }
.custom-form .form-label { font-weight: 600; color: #64748b; font-size: .85rem; margin-bottom: 8px; }
.form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.table-container   { border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; }
#product-table thead { background-color: #f1f5f9; font-weight: 600; color: #475569; }
.action-footer     { background: #fff; padding: 20px 30px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 20px; }
.btn-preview, .btn-save { border-radius: 8px; padding: 12px 30px; font-weight: 600; }
.btn-save          { box-shadow: 0 4px 10px rgba(34,197,94,.2); }
.required label:after { content: " *"; color: red; font-weight: bold; }
.required-mark     { color: red; font-weight: bold; }
.serial-number     { min-height: 36px; }
</style>

<div class="d-flex justify-content-between mt-4 mb-2">
    <div><h3><?= $isEdit ? 'Edit Return' : 'Add Return' ?> for Inward DC #<?= Html::encode($inward->inward_dc_no) ?></h3></div>
    <div><a href="<?= Url::to(['/dc/inward-index']) ?>" class="btn btn-secondary">Back</a></div>
</div>

<div class="form-wrapper">
    <?php $form = ActiveForm::begin([
        'id' => 'add-return-form',
        'options' => ['enctype' => 'multipart/form-data'],
        'fieldConfig' => [
            'options' => ['class' => 'form-group mb-2'],
            'labelOptions' => ['class' => 'form-label'],
            'inputOptions' => ['class' => 'form-control'],
        ],
    ]); ?>

    <?= Html::activeHiddenInput($dc, 'client_id') ?>

    <!-- Client & Basic Details ─────────────────────────────────────────── -->
    <div class="section-block">
        <div class="section-header">
            <div class="header-icon"><i class="fas fa-user"></i></div>
            <h4>Client &amp; Basic Details</h4>
        </div>
        <div class="row g-4">
            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">Client Name</label>
                <input class="form-control" value="<?= Html::encode($client->client_name ?? '') ?>" disabled>
            </div>
            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">Company Name</label>
                <input class="form-control" value="<?= Html::encode($client->company_name ?? '') ?>" disabled>
            </div>
            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">Inward DC No</label>
                <input class="form-control" value="<?= Html::encode($inward->inward_dc_no) ?>" disabled>
            </div>
            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">Outward DC No</label>
                <input class="form-control" value="<?= Html::encode($displayDcNo) ?>" disabled placeholder="Auto generated">
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

            <div class="col-12 col-md-6 col-lg-6">
                <label class="form-label">Client Address</label>
                <textarea class="form-control" rows="2" disabled><?= Html::encode($client->address ?? '') ?></textarea>
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
                <?= $form->field($document, 'quotation_no')->textInput(['placeholder' => 'Quotation Number'])
                    ->label('Quotation No') ?>
                <?= $form->field($document, 'quotation_path')
                    ->fileInput(['class' => 'form-control', 'data-has-existing' => (!empty($document->quotation_path) ? 1 : 0)])
                    ->label('Quotation File') ?>
                <?php if ($isEdit && !empty($document->quotation_path)): ?>
                    <div class="mt-2"><a href="<?= Url::to('@web/' . ltrim($document->quotation_path, '/')) ?>" target="_blank">View current quotation</a></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 col-lg-4">
                <?= $form->field($document, 'invoice_no')->textInput(['placeholder' => 'Invoice Number'])->label('Invoice No') ?>
                <?= $form->field($document, 'invoice_path')->fileInput([
                    'class' => 'form-control', 'data-has-existing' => (!empty($document->invoice_path) ? 1 : 0),
                ])->label('Invoice File') ?>
                <?php if ($isEdit && !empty($document->invoice_path)): ?>
                    <div class="mt-2"><a href="<?= Url::to('@web/' . ltrim($document->invoice_path, '/')) ?>" target="_blank">View current invoice</a></div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-4 col-lg-4">
                <?= $form->field($document, 'po_no')->textInput(['placeholder' => 'PO Number'])->label('PO No') ?>
                <?= $form->field($document, 'po_path')->fileInput([
                    'class' => 'form-control', 'data-has-existing' => (!empty($document->po_path) ? 1 : 0),
                ])->label('PO File') ?>
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

<!-- Add Product Modal ────────────────────────────────────────────────── -->
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

<!-- Pick Product Modal ──────────────────────────────────────────────── -->
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
$downloadId     = Yii::$app->request->get('download_id');
$downloadSubmit = (int) Yii::$app->request->get('download_submit', 0);
$downloadUrl    = $downloadId ? Url::to(['/dc/challan-pdf', 'id' => $downloadId, 'submit' => $downloadSubmit]) : '';
$afterDownloadUrl = Url::to(['/dc/inward-index']);

$this->registerJs(<<<'JS'
/* Product creation */
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
        error: function () { if (window.Swal) Swal.fire({icon:'error', title:'Error', text:'Error saving product.'}); }
    });
});

document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function () {
    $('#hsn-code, #unit-name, #product-name').val('');
});

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

$('#add-row').on('click', function () {
    var url = $(this).data('url');
    $.ajax({
        url: url, type: 'POST',
        dataType: 'json',
        success: function (res) {
            window.deliverySelect.clearOptions();
            window.deliverySelect.clear(true);
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
        error: function () { if (window.Swal) Swal.fire({icon:'error', title:'Error', text:'Error loading products'}); }
    });
});

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
        error: function () { if (window.Swal) Swal.fire({icon:'error', title:'Error', text:'Error'}); }
    });
});

$(document).on('input', '.product-quantity', function () {
    this.value = this.value.replace(/[^0-9]/g, '').replace(/^0+/, '');
});

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
JS);

$this->registerJs(<<<'JS'
/* Form submit validation — same rules as DC create */
(function () {
    var clickedAction = '';
    $(document).on('click', "#add-return-form button[type='submit']", function () {
        clickedAction = $(this).val();
        $('#form-action').val(clickedAction);
    });

    function toast(icon, title) {
        if (window.Swal) Swal.fire({ toast:true, position:'top-end', icon:icon, title:title, timer:3000, showConfirmButton:false });
    }

    $('#add-return-form').on('beforeSubmit', function () {
        var $form = $(this);

        if ($('#product-table tbody tr').length === 0) {
            toast('error', 'Please add at least one product');
            return false;
        }
        var invalid = false, msg = '';
        $('.product-quantity').each(function () {
            var v = $(this).val();
            if (!v || Number(v) <= 0) { invalid = true; msg = msg || 'Please fill all Quantities'; $(this).addClass('is-invalid'); }
            else { $(this).removeClass('is-invalid'); }
        });
        $('.serial-number').each(function () {
            var v = $(this).val();
            if (!v || !v.trim()) { invalid = true; msg = msg || 'Please fill all Serial Numbers'; $(this).addClass('is-invalid'); }
            else { $(this).removeClass('is-invalid'); }
        });
        if (invalid) { toast('error', msg); return false; }

        /* Contact numbers — exactly 10 digits (alt is optional). */
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

        /* Number + file pairing — if a number is given, the matching file is mandatory.
           An existing file (edit mode) counts as present. */
        function pairCheck(noId, fileId, label) {
            var $no = $(noId), $f = $(fileId);
            var num = ($no && $no.val()) ? $no.val().trim() : '';
            if (!num) return true;
            var hasNew      = $f[0] && $f[0].files && $f[0].files.length > 0;
            var hasExisting = Number($f.data('has-existing')) === 1;
            if (!hasNew && !hasExisting) {
                toast('error', 'Please upload the ' + label + ' file for the ' + label + ' number given');
                $f.addClass('is-invalid');
                return false;
            }
            $f.removeClass('is-invalid');
            return true;
        }
        if (!pairCheck('#dcdocumentfile-quotation_no', '#dcdocumentfile-quotation_path', 'Quotation')) return false;
        if (!pairCheck('#dcdocumentfile-invoice_no',   '#dcdocumentfile-invoice_path',   'Invoice'))   return false;
        if (!pairCheck('#dcdocumentfile-po_no',        '#dcdocumentfile-po_path',        'PO'))        return false;

        /* Preview opens in a new tab and never persists — skip the
           "Submit Return?" confirmation and let the form post directly. */
        if (clickedAction === 'preview') {
            return true;
        }

        if (window.Swal) {
            Swal.fire({
                title: 'Submit Return?',
                text:  'All validations passed. Do you want to continue?',
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
