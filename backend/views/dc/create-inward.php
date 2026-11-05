<?php

/** @var yii\web\View $this */
/** @var backend\models\DcDetail        $dc */
/** @var backend\models\ClientModel|null $client */
/** @var array $productOptions   id => item_name */
/** @var array $existingProducts */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Add Inward DC';

if (!empty($dc->dc_date)) {
    $dc->dc_date = date('Y-m-d', strtotime((string) $dc->dc_date));
}
if (!empty($dc->dc_return_date)) {
    $dc->dc_return_date = date('Y-m-d', strtotime((string) $dc->dc_return_date));
}

$this->registerCssFile('@web/css/tom-select.bootstrap5.min.css');
$this->registerJsFile('@web/js/tom-select.complete.min.js', ['depends' => [\yii\web\JqueryAsset::class]]);
?>

<div class="ui-page dc-form">
<div class="ui-head">
    <div>
        <h1>Add Inward Delivery Challan</h1>
        <div class="ui-sub">Record items a client has sent in.</div>
    </div>
    <div class="ui-actions">
        <a href="<?= Url::to(['/dc/inward-index']) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="form-wrapper">
    <?php $form = ActiveForm::begin([
        'id' => 'inward-dc-form',
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
            <h4>Client &amp; Inward Details</h4>
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
                        'options' => ['placeholder' => 'Select Client', 'id' => 'inward-client-picker'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Client') ?>
                </div>
            <?php else: ?>
                <?= Html::activeHiddenInput($dc, 'client_id') ?>
            <?php endif; ?>

            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">Client Name</label>
                <input id="inward-client-name-display" class="form-control" value="<?= Html::encode($client->client_name ?? '') ?>" disabled placeholder="Client Name">
            </div>
            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <label class="form-label">Company Name</label>
                <input id="inward-company-name-display" class="form-control" value="<?= Html::encode($client->company_name ?? '') ?>" disabled placeholder="Company Name">
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form required">
                <?= $form->field($dc, 'inward_dc_no')->textInput([
                    'maxlength' => 10,
                    'placeholder' => 'Enter Inward DC No',
                ])->label('Inward DC No') ?>
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form">
                <?= $form->field($dc, 'ewbc_no')->textInput(['placeholder' => 'EWay Number (optional)'])->label('EWay Number') ?>
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form required">
                <?= $form->field($dc, 'dc_date')->widget(DatePicker::class, [
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['id' => 'inward-dc-date-input', 'placeholder' => 'Select date...', 'class' => 'form-control'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                ])->label('DC Date') ?>
            </div>

            <div class="col-12 col-md-6 col-lg-3 custom-form required">
                <?= $form->field($dc, 'dc_return_date')->widget(DatePicker::class, [
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['id' => 'inward-return-date-input', 'placeholder' => 'Select date...', 'class' => 'form-control'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                ])->label('Expected Return Date') ?>
            </div>

            <div class="col-12 col-md-6 col-lg-6">
                <label class="form-label">Client Address</label>
                <textarea id="inward-address-display" class="form-control" rows="2" disabled placeholder="Client Address"><?= Html::encode($client->address ?? '') ?></textarea>
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
                <tbody></tbody>
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

    <div class="action-footer shadow-sm">
        <?= Html::submitButton('<i class="fas fa-save me-2"></i> Save Inward DC', ['class' => 'btn btn-warning']) ?>
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
/* Client picker → auto-fill Client info */
$('#inward-client-picker').on('change', function () {
    var id = $(this).val();
    if (!id) {
        $('#inward-client-name-display,#inward-company-name-display').val('');
        $('#inward-address-display').val('');
        return;
    }
    $.post('$getClientUrl', { id: id, _csrf: (window.yii ? yii.getCsrfToken() : '') }, function (r) {
        if (!r || !r.success || !r.client) return;
        var c = r.client;
        $('#inward-client-name-display').val(c.client_name || '');
        $('#inward-company-name-display').val(c.company_name || '');
        $('#inward-address-display').val(c.address || '');
    }, 'json');
});
JS);

$this->registerJs(<<<'JS'
/* Expected Return Date must be >= DC Date ─────────────────────────────── */
(function () {
    var dcSel = '#inward-dc-date-input', rtSel = '#inward-return-date-input';
    var lastDc = $(dcSel).val();

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
            if (window.Swal) Swal.fire({icon:'warning', title:'Expected Return Date', text:'Expected return date cannot be earlier than the DC date.'});
        }
    }

    $(document).ready(function () { setTimeout(syncReturnDatePicker, 0); });
    $(document).on('change changeDate', dcSel, function () {
        var cur = $(dcSel).val();
        if (cur !== lastDc) { $(rtSel).val(''); lastDc = cur; }
        syncReturnDatePicker();
    });
    $(document).on('change changeDate', rtSel, function () {
        var dcVal = $(dcSel).val(), rtVal = $(rtSel).val();
        if (dcVal && rtVal && rtVal < dcVal) {
            $(rtSel).val('');
            if (window.Swal) Swal.fire({icon:'warning', title:'Expected Return Date', text:'Expected return date cannot be earlier than the DC date.'});
        }
    });
})();
JS);

$this->registerJs(<<<'JS'
/* Product creation + selection (same mechanics as add-dc) */
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

/* Expected Return Date must be >= DC Date */
(function () {
    var dcSel = '#inward-dc-date-input', rtSel = '#inward-return-date-input', lastDc = $(dcSel).val();
    function sync() {
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
    $(document).on('change changeDate', dcSel, function () {
        var cur = $(dcSel).val();
        if (cur !== lastDc) { $(rtSel).val(''); lastDc = cur; }
        sync();
    });
    $(document).on('change changeDate', rtSel, sync);
})();
JS);

/* Client-side validation — no page refresh on error */
$this->registerJs(<<<'JS'
(function () {
    function toast(icon, title) {
        if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: icon, title: title, timer: 3000, showConfirmButton: false });
    }
    $('#inward-dc-form').on('beforeSubmit', function () {
        var $form = $(this);

        /* Required field checks (bypassed by model's whenClient that depends on #dc-type) */
        function need($el, label) {
            var v = $el.val();
            if (!v || !$.trim(v)) {
                toast('error', label + ' is required');
                $el.addClass('is-invalid').focus();
                return false;
            }
            $el.removeClass('is-invalid');
            return true;
        }
        if (!$('#dcdetail-client_id').length || !$.trim($('#dcdetail-client_id').val() || '')) {
            /* client_id may be hidden (prefilled). Skip if input doesn't exist. */
        }
        if ($('#dcdetail-inward_dc_no').length && !need($('#dcdetail-inward_dc_no'), 'Inward DC No')) return false;
        if ($('#inward-dc-date-input').length && !need($('#inward-dc-date-input'), 'DC Date')) return false;
        if ($('#inward-return-date-input').length && !need($('#inward-return-date-input'), 'Expected Return Date')) return false;

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

        if (window.Swal) {
            Swal.fire({
                title: 'Save Inward DC?',
                text:  'All validations passed. Do you want to continue?',
                icon:  'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Save',
                confirmButtonColor: '#f59e0b',
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
?>
</div>
