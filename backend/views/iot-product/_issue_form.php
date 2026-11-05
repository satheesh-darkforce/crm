<?php
/** @var \yii\web\View $this */
/** @var \backend\models\IotConsumption $model */
/** @var \backend\models\IotProduct[] $products */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\IotConsumptionProducts[] $existing */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Issue Products' : 'Edit Issue Request';

$existing = $existing ?? [];
$productMap = [];
foreach ($products as $p) $productMap[$p->id] = $p->product_name . ' (Stock: ' . $p->stock_qty . ')';

/** @var \backend\models\User $user */
$user      = Yii::$app->user->identity;
$isDevRole = $user && method_exists($user, 'isDeveloper') ? $user->isDeveloper() : false;
$selfDev   = $isDevRole ? \backend\models\Developer::findOne(['user_id' => $user->user_id, 'status' => 1]) : null;
if ($selfDev && empty($model->developer_id)) {
    $model->developer_id = $selfDev->id;
}
?>
<style>
.product-row td{vertical-align:middle}
</style>

<div class="ui-page issue-form">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['issued'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['action' => $isNew ? ['create-issue'] : ['update-issue', 'id' => $model->id], 'id' => 'issue-form']); ?>

            <p class="help-block small text-muted mb-2">Fields with <span class="text-danger">*</span> are required.</p>
            <?php if ($model->hasErrors()): ?>
                <div class="alert alert-danger py-2 small">
                    <?= $form->errorSummary($model, ['header' => '<strong>Please fix the following errors:</strong>']) ?>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <?= $form->field($model, 'project_id')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'Select Project…'],
                        'pluginOptions' => ['allowClear' => false, 'width' => '100%'],
                    ])->label('Project <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?php
                        // Yii1 parity: if logged-in user is a developer, the dropdown is locked to their own record.
                        $devData = $isDevRole && $selfDev
                            ? [$selfDev->id => $selfDev->developer_name]
                            : ArrayHelper::map($developers, 'id', 'developer_name');
                    ?>
                    <?= $form->field($model, 'developer_id')->widget(Select2::class, [
                        'data'          => $devData,
                        'options'       => [
                            'placeholder' => 'Select Developer…',
                            'disabled'    => $isDevRole,
                        ],
                        'pluginOptions' => ['allowClear' => false, 'width' => '100%'],
                    ])->label('Developer <span class="text-danger">*</span>', ['encode' => false]) ?>
                    <?php if ($isDevRole && $selfDev): ?>
                        <?= Html::activeHiddenInput($model, 'developer_id') ?>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'requested_date')->widget(\kartik\date\DatePicker::class, [
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'value'         => $model->requested_date ? date('Y-m-d', strtotime($model->requested_date)) : date('Y-m-d'),
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => [
                            'autoclose' => true,
                            'format'    => 'yyyy-mm-dd',
                            'startDate' => date('Y-m-d'),  // Yii1 parity: minDate = today
                        ],
                    ])->label('Required Date <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
            </div>

            <h6 class="fw-semibold mb-2">Products to Issue</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm border" id="productTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50%">Product</th>
                            <th style="width:20%">Quantity</th>
                            <th style="width:20%">Available</th>
                            <th style="width:10%"></th>
                        </tr>
                    </thead>
                    <tbody id="productBody">
                        <?php if (empty($existing)): ?>
                        <tr class="product-row">
                            <td>
                                <select name="product_id[]" class="form-select form-select-sm product-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p->id ?>" data-stock="<?= $p->stock_qty ?>"><?= Html::encode($p->product_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="quantity[]" class="form-control form-control-sm qty-input" min="1" max="10000" step="1" value="1" placeholder="e.g. 5" required>
                                <small class="qty-msg text-danger d-none"></small>
                            </td>
                            <td><span class="stock-avail text-muted">—</span></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($existing as $ex): ?>
                            <tr class="product-row">
                                <td>
                                    <select name="product_id[]" class="form-select form-select-sm product-select" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= $p->id ?>" data-stock="<?= $p->stock_qty ?>" <?= $ex->product_id == $p->id ? 'selected' : '' ?>><?= Html::encode($p->product_name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="quantity[]" class="form-control form-control-sm qty-input" min="1" max="10000" step="1" value="<?= $ex->quantity ?>" placeholder="e.g. 5" required>
                                    <small class="qty-msg text-danger d-none"></small>
                                </td>
                                <td><span class="stock-avail text-muted"><?= $ex->product->stock_qty ?? '—' ?></span></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="fas fa-times"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addProductRow"><i class="fas fa-plus me-1"></i>Add product</button>

            <div class="d-flex gap-2">
                <?= Html::submitButton($isNew ? 'Submit Request' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4', 'name' => 'save_action', 'value' => 'save']) ?>
                <?php if ($isNew): ?>
                    <?= Html::submitButton('Save &amp; Add New', ['class' => 'btn btn-outline-primary btn-sm px-4', 'name' => 'save_action', 'value' => 'save_new', 'encode' => false]) ?>
                <?php endif; ?>
                <?= Html::a('Cancel', ['issued'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$allProductsJson = json_encode(array_map(fn($p) => ['id' => $p->id, 'name' => $p->product_name, 'stock' => $p->stock_qty], $products));
$this->registerJs(<<<JS
var allProducts = {$allProductsJson};

function buildProductRow(selectedId, qty) {
    var options = '<option value="">Select Product</option>';
    allProducts.forEach(function(p) {
        options += '<option value="' + p.id + '" data-stock="' + p.stock + '"' + (p.id == selectedId ? ' selected' : '') + '>' + p.name + ' (Stock: ' + p.stock + ')</option>';
    });
    return '<tr class="product-row">'
        + '<td><select name="product_id[]" class="form-select form-select-sm product-select" required>' + options + '</select></td>'
        + '<td>'
        +   '<input type="number" name="quantity[]" class="form-control form-control-sm qty-input" min="1" max="10000" step="1" value="' + (qty || 1) + '" placeholder="e.g. 5" required>'
        +   '<small class="qty-msg text-danger d-none"></small>'
        + '</td>'
        + '<td><span class="stock-avail text-muted">\u2014</span></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger remove-row">\u2715</button></td>'
        + '</tr>';
}

// Turn a plain product <select> into a searchable Select2.
function initProductSelect2(sel) {
    var \$sel = jQuery(sel);
    if (\$sel.data('select2')) { \$sel.select2('destroy'); }
    \$sel.select2({
        width: '100%',
        placeholder: 'Select Product\u2026',
        allowClear: false
    });
}

// Collect product ids chosen on every row except the given one.
function takenProductIds(exceptSel) {
    var ids = [];
    jQuery('#productBody .product-select').each(function() {
        if (this === exceptSel) return;
        if (this.value) ids.push(String(this.value));
    });
    return ids;
}

// Rebuild a single select's option list so products already chosen on OTHER
// rows are excluded \u2014 its own current selection is always kept.
function rebuildProductSelect(sel) {
    var current = String(sel.value || '');
    var taken   = takenProductIds(sel);
    var html    = '<option value="">Select Product</option>';
    allProducts.forEach(function(p) {
        var id = String(p.id);
        if (taken.indexOf(id) === -1 || id === current) {
            html += '<option value="' + p.id + '" data-stock="' + p.stock + '"'
                  + (id === current ? ' selected' : '') + '>'
                  + p.name + ' (Stock: ' + p.stock + ')</option>';
        }
    });
    sel.innerHTML = html;
    sel.value = current;
}

// Refresh every row's dropdown so a product can only be picked once across
// all rows. A re-entrancy flag stops the change.select2 re-render from
// looping back through the 'change' handler that called us.
var refreshingSelects = false;
function refreshProductSelects() {
    if (refreshingSelects) return;
    refreshingSelects = true;
    jQuery('#productBody .product-select').each(function() {
        rebuildProductSelect(this);
        jQuery(this).trigger('change.select2');
    });
    refreshingSelects = false;
}

document.getElementById('addProductRow').addEventListener('click', function() {
    document.getElementById('productBody').insertAdjacentHTML('beforeend', buildProductRow(0, 1));
    var newRow = document.querySelector('#productBody .product-row:last-child');
    if (newRow) {
        initProductSelect2(newRow.querySelector('.product-select'));
        validateQty(newRow);
    }
    refreshProductSelects();
});

// Bound via jQuery (not addEventListener): Select2 fires its change through
// jQuery's .trigger('change'), which native addEventListener listeners do NOT
// receive \u2014 so the Available column would never update otherwise.
jQuery('#productBody').on('change', '.product-select', function() {
    if (refreshingSelects) return;   // ignore the rebuild's own change.select2
    var row   = this.closest('tr');
    var opt   = this.selectedOptions[0];
    var stock = opt ? (opt.dataset.stock || '\u2014') : '\u2014';
    row.querySelector('.stock-avail').textContent = stock;
    validateQty(row);
    // A pick here frees/locks products on the other rows.
    refreshProductSelects();
});

// Inline live validation: empty / 0 / negative / exceeds-stock all show a
// message under the qty input AND keep the submit buttons disabled until
// every row is valid (so the user can't even press Submit while wrong).
function validateQty(row) {
    var qtyInput = row.querySelector('.qty-input');
    var sel      = row.querySelector('.product-select');
    var opt      = sel ? sel.selectedOptions[0] : null;
    var stock    = (opt && opt.dataset.stock !== undefined && opt.dataset.stock !== '')
                   ? parseInt(opt.dataset.stock, 10) : null;
    var raw      = (qtyInput.value || '').trim();
    var qty      = parseInt(raw, 10);
    var msgEl    = row.querySelector('.qty-msg');
    var msg      = '';

    if (raw === '') {
        msg = 'Quantity is required.';
    } else if (isNaN(qty) || qty < 1) {
        msg = 'Quantity must be 1 or more (zero / negative not allowed).';
    } else if (stock !== null && qty > stock) {
        msg = 'Only ' + stock + ' available in stock.';
    }

    if (msg) {
        qtyInput.classList.add('is-invalid');
        qtyInput.title = msg;
        if (msgEl) { msgEl.textContent = msg; msgEl.classList.remove('d-none'); }
    } else {
        qtyInput.classList.remove('is-invalid');
        qtyInput.title = '';
        if (msgEl) { msgEl.textContent = ''; msgEl.classList.add('d-none'); }
    }
    updateSubmitState();
}

function updateSubmitState() {
    var anyInvalid = document.querySelectorAll('#productBody .qty-input.is-invalid').length > 0;
    document.querySelectorAll('#issue-form button[name="save_action"]').forEach(function(btn) {
        btn.disabled = anyInvalid;
    });
}

// Block typing of `-`, `+`, `e`, `E` in the quantity input (HTML number type
// otherwise allows negatives and scientific notation like 1e3).
document.getElementById('productBody').addEventListener('keydown', function(e) {
    if (e.target.classList.contains('qty-input')) {
        if (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E') {
            e.preventDefault();
        }
    }
});

document.getElementById('productBody').addEventListener('input', function(e) {
    if (e.target.classList.contains('qty-input')) {
        // Strip any leftover non-digit chars (handles paste of "-5", "1e3", etc.).
        var cleaned = e.target.value.replace(/[^0-9]/g, '');
        if (cleaned !== e.target.value) e.target.value = cleaned;
        validateQty(e.target.closest('tr'));
    }
});

function showError(title, text) {
    if (window.Swal) Swal.fire({icon: 'error', title: title, text: text});
    else alert(title + (text ? ': ' + text : ''));
}

// Form-level guard before submit. Yii1 parity:
//  - Project, Developer, Required Date are required (Select2 hides the native
//    select so HTML5 `required` doesn't reliably fire — re-check explicitly).
//  - Required Date must be today or later (Yii1 sets minDate via the picker).
//  - At least one product row must exist.
//  - Every row must have a product + a quantity ≥ 1 within available stock.
//  - Same product cannot appear on two rows.
document.getElementById('issue-form').addEventListener('submit', function(e) {
    var projectEl = document.getElementById('iotconsumption-project_id')
                 || document.querySelector('select[name="IotConsumption[project_id]"]');
    var devEl     = document.getElementById('iotconsumption-developer_id')
                 || document.querySelector('select[name="IotConsumption[developer_id]"]');
    // Kartik DatePicker may render the input inside a wrapper, so look up by
    // id first (the Yii-generated `iotconsumption-requested_date`) and fall
    // back to the model[attr] name selector.
    var dateEl    = document.getElementById('iotconsumption-requested_date')
                 || document.querySelector('input[name="IotConsumption[requested_date]"]');

    if (projectEl && !projectEl.value) {
        e.preventDefault();
        showError('Project required', 'Please select a project before submitting.');
        return false;
    }
    if (devEl && !devEl.disabled && !devEl.value) {
        e.preventDefault();
        showError('Developer required', 'Please select a developer before submitting.');
        return false;
    }
    if (dateEl) {
        var dv = (dateEl.value || '').trim();
        if (!dv) {
            e.preventDefault();
            showError('Required Date missing', 'Please pick a required date.');
            return false;
        }
        // Compare on day-precision (strip time-of-day on both sides).
        var picked = new Date(dv);
        var today  = new Date();
        today.setHours(0, 0, 0, 0);
        if (isNaN(picked.getTime()) || picked < today) {
            e.preventDefault();
            showError('Invalid Required Date', 'Required date cannot be in the past.');
            return false;
        }
    }

    var rows = document.querySelectorAll('.product-row');
    if (rows.length === 0) {
        e.preventDefault();
        showError('No products', 'Please add at least one product.');
        return false;
    }

    var seen = {};
    for (var i = 0; i < rows.length; i++) {
        var row      = rows[i];
        var sel      = row.querySelector('.product-select');
        var qtyInput = row.querySelector('.qty-input');
        var opt      = sel ? sel.selectedOptions[0] : null;
        var pid      = sel ? sel.value : '';
        var pname    = opt ? (opt.textContent || '').trim() : '';
        var qty      = parseInt(qtyInput.value, 10);
        var stock    = (opt && opt.dataset.stock) ? parseInt(opt.dataset.stock, 10) : null;
        var rowNo    = i + 1;

        if (!pid) {
            e.preventDefault();
            showError('Product missing', 'Row ' + rowNo + ': please select a product.');
            sel.focus();
            return false;
        }
        if (isNaN(qty) || qty < 1) {
            e.preventDefault();
            showError('Invalid quantity', 'Row ' + rowNo + ' (' + pname + '): quantity must be 1 or more.');
            qtyInput.focus();
            return false;
        }
        if (stock !== null && qty > stock) {
            e.preventDefault();
            showError('Quantity exceeds stock', 'Row ' + rowNo + ' (' + pname + '): only ' + stock + ' available.');
            qtyInput.focus();
            return false;
        }
        if (seen[pid]) {
            e.preventDefault();
            showError('Duplicate product', '"' + pname + '" is selected on more than one row.');
            sel.focus();
            return false;
        }
        seen[pid] = true;
    }
});

document.getElementById('productBody').addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-row');
    if (!btn) return;
    var rows = document.querySelectorAll('.product-row');
    if (rows.length > 1) {
        btn.closest('tr').remove();
        updateSubmitState();
        // Removing a row releases its product back to the other dropdowns.
        refreshProductSelects();
    }
});

// Init stock display + run validation for every row on page load so the
// submit button reflects the true state immediately (e.g. an edit that
// already has a quantity > current stock). Also turn each product <select>
// into a searchable Select2 and trim cross-row duplicates.
document.querySelectorAll('#productBody .product-row').forEach(function(row) {
    var sel = row.querySelector('.product-select');
    var opt = sel ? sel.selectedOptions[0] : null;
    var stock = opt ? opt.dataset.stock : null;
    if (stock) row.querySelector('.stock-avail').textContent = stock;
    if (sel) initProductSelect2(sel);
    validateQty(row);
});
refreshProductSelects();
JS, \yii\web\View::POS_READY);
?>
