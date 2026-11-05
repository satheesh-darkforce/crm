<?php
/** @var \yii\web\View $this */
/** @var \backend\models\IotProduct $model */
/** @var \backend\models\IotCategory[] $categories */
/** @var \backend\models\IotBrand[] $brands */
/** @var \backend\models\IotLocation[] $locations */
/** @var \backend\models\IotTags[] $allTags */
/** @var \backend\models\IotUploads[] $uploads */
/** @var string|null $formToken */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add IOT Product' : "Edit: {$model->product_name}";
?>
<div class="ui-page iot-product-form">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin([
                'options'         => ['class' => 'row g-3', 'enctype' => 'multipart/form-data'],
                'successCssClass' => '',
            ]);
            $req = '<span class="text-danger">*</span>'; ?>

            <?php if (!empty($formToken)): ?>
                <?= Html::hiddenInput('form_token', $formToken) ?>
            <?php endif; ?>

            <?php if ($model->hasErrors()): ?>
            <div class="col-12">
                <div class="alert alert-danger py-2 small">
                    <?= $form->errorSummary($model, ['header' => '<strong>Please fix the following errors:</strong>']) ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-12">
                <p class="help-block small text-muted mb-0">Fields with <span class="text-danger">*</span> are required.</p>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Product Code <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm bg-light" value="<?= Html::encode($model->product_code) ?>" readonly>
                <?= Html::activeHiddenInput($model, 'product_code') ?>
            </div>

            <div class="col-md-4">
                <?= $form->field($model, 'product_name')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm', 'placeholder' => 'Product Name'])->label("Product Name $req") ?>
            </div>

            <div class="col-md-3">
                <?= $form->field($model, 'part_number')->textInput(['maxlength' => true, 'class' => 'form-control form-control-sm', 'placeholder' => 'Part Number'])->label("Part Number $req") ?>
            </div>

            <div class="col-md-2">
                <?= $form->field($model, 'stock_qty')->textInput(['type' => 'number', 'min' => 0, 'class' => 'form-control form-control-sm', 'placeholder' => 'e.g. 10'])->label("Stock Qty $req") ?>
            </div>

            <div class="col-md-3">
                <?= $form->field($model, 'cat_id')->widget(Select2::class, [
                    'data'          => ArrayHelper::map($categories, 'id', 'name'),
                    'options'       => ['placeholder' => 'Select Category…', 'id' => 'cat_id'],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ])->label("Category $req") ?>
            </div>

            <div class="col-md-3">
                <?php
                $subcatOptions = [];
                if ($model->cat_id) {
                    $subs = \backend\models\IotSubcategory::find()->where(['category_id' => $model->cat_id, 'status' => 1])->all();
                    foreach ($subs as $s) $subcatOptions[$s->id] = $s->name;
                }
                ?>
                <?= $form->field($model, 'subcat_id')->dropDownList(
                    $subcatOptions,
                    ['prompt' => 'Select Sub-Category', 'class' => 'form-select form-select-sm', 'id' => 'subcat_id']
                )->label("Sub-Category $req") ?>
            </div>

            <div class="col-md-3">
                <?= $form->field($model, 'brand_id')->widget(Select2::class, [
                    'data'          => ArrayHelper::map($brands, 'id', 'brand_name'),
                    'options'       => ['placeholder' => 'Select Brand…'],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ]) ?>
            </div>

            <div class="col-md-3">
                <?= $form->field($model, 'location_id')->widget(Select2::class, [
                    'data'          => ArrayHelper::map($locations, 'id', 'location'),
                    'options'       => ['placeholder' => 'Select Location…'],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ]) ?>
            </div>

            <div class="col-md-4">
                <?php
                $tagData   = ArrayHelper::map($allTags, 'id', 'name');
                $tagsValue = is_array($model->tags) ? $model->tags : ($model->tags ? explode(',', $model->tags) : []);
                ?>
                <?= $form->field($model, 'tags')->widget(Select2::class, [
                    'data'          => $tagData,
                    'value'         => $tagsValue,
                    'options'       => ['placeholder' => 'Select Tags…', 'multiple' => true],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ])->label('Tags') ?>
            </div>

            <div class="col-md-4">
                <?= $form->field($model, 'description')->textarea(['rows' => 2, 'class' => 'form-control form-control-sm', 'placeholder' => 'Description…']) ?>
            </div>

            <!-- File Uploads -->
            <div class="col-12">
                <label class="form-label">Attachments</label>

                <?php if (!empty($uploads)): ?>
                <ul class="list-group list-group-flush mb-2" id="saved-upload-list">
                    <?php foreach ($uploads as $up): ?>
                    <li class="list-group-item d-flex align-items-center gap-2 px-0 py-1" id="saved-<?= $up->id ?>">
                        <span class="small flex-grow-1">
                            <a href="<?= Yii::$app->request->baseUrl . Html::encode($up->path) ?>" target="_blank" rel="noreferrer noopener" class="text-decoration-none">
                                <?= Html::encode(basename($up->path)) ?>
                            </a>
                        </span>
                        <button type="button" class="btn btn-outline-danger btn-sm"
                                onclick="deleteSavedFile(<?= $up->id ?>, this)">&times;</button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <input type="file" id="data-sheet-input" name="data_sheet[]" multiple
                       accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.csv"
                       class="form-control form-control-sm">
                <small class="form-text text-muted d-block mt-1">Allowed: JPG, JPEG, PNG, PDF, DOC, DOCX, XLS, XLSX, CSV. Max 5 MB per file.</small>
                <div id="dup-alert" class="alert alert-warning alert-dismissible py-1 px-2 mt-1 small" style="display:none">
                    <span id="dup-msg"></span>
                    <button type="button" class="btn-close btn-close-sm" onclick="this.parentElement.style.display='none'"></button>
                </div>
                <ul class="list-group list-group-flush mt-1" id="new-upload-list"></ul>
            </div>

            <div class="col-12 d-flex gap-2 mt-2">
                <?= Html::submitButton($isNew ? 'Create Product' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?php if ($isNew): ?>
                    <?= Html::submitButton('Save & Add New', ['class' => 'btn btn-outline-primary btn-sm px-4', 'name' => 'add_new', 'value' => '1']) ?>
                <?php endif; ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$getSubcategoryUrl = \yii\helpers\Url::to(['/iot-product/get-subcategory']);
$deleteUploadUrl = \yii\helpers\Url::to(['/iot-product/delete-upload']);
$this->registerJs(<<<JS
// Category → subcategory AJAX
$('#cat_id').on('change', function() {
    var catId = \$(this).val();
    var \$subSel = \$('#subcat_id');
    \$subSel.html('<option value="">Select Sub-Category</option>');
    if (!catId) return;
    \$.post('{$getSubcategoryUrl}',
        {'{$csrfParam}': '{$csrfToken}', cat_id: catId},
        function(data) {
            \$.each(data, function(i, s) { \$subSel.append(new Option(s.name, s.id)); });
        }
    );
});

// File upload — accumulate files, inject via XHR on submit
var dt       = new DataTransfer();
var picker   = document.getElementById('data-sheet-input');
var newList  = document.getElementById('new-upload-list');
var dupAlert = document.getElementById('dup-alert');
var dupMsg   = document.getElementById('dup-msg');

var ALLOWED_EXTS = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx','csv'];
var MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB

picker.addEventListener('change', function() {
    var dupes = [], rejectedExt = [], rejectedSize = [];
    Array.from(this.files).forEach(function(f) {
        var ext = (f.name.split('.').pop() || '').toLowerCase();
        if (ALLOWED_EXTS.indexOf(ext) === -1) { rejectedExt.push(f.name); return; }
        if (f.size > MAX_FILE_BYTES)         { rejectedSize.push(f.name); return; }
        var exists = Array.from(dt.files).some(function(x) { return x.name === f.name && x.size === f.size; });
        if (exists) { dupes.push(f.name); } else { dt.items.add(f); }
    });
    this.value = '';
    renderList();
    var msgs = [];
    if (rejectedExt.length)  msgs.push('Disallowed type: ' + rejectedExt.join(', '));
    if (rejectedSize.length) msgs.push('Over 5 MB: ' + rejectedSize.join(', '));
    if (dupes.length)        msgs.push('Already added: ' + dupes.join(', '));
    if (msgs.length) {
        dupMsg.textContent = msgs.join(' • ');
        dupAlert.style.display = '';
    } else {
        dupAlert.style.display = 'none';
    }
});

newList.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-idx]');
    if (!btn) return;
    var idx = parseInt(btn.dataset.idx);
    var nd = new DataTransfer();
    Array.from(dt.files).forEach(function(f, i) { if (i !== idx) nd.items.add(f); });
    dt = nd;
    renderList();
});

function renderList() {
    newList.innerHTML = '';
    Array.from(dt.files).forEach(function(f, i) {
        var li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center gap-2 px-0 py-1';
        li.innerHTML = '<span class="small flex-grow-1">' + f.name + '</span>'
            + '<button type="button" class="btn btn-outline-danger btn-sm" data-idx="' + i + '">&times;</button>';
        newList.appendChild(li);
    });
}

// Submit handling — NATIVE form POST (no XHR), mirroring Yii1's plain
// multipart submit. Yii1 used a normal form, so validation, the
// Post/Redirect/Get, and the success flash all behaved correctly. The old
// XHR override caused: (1) "error popup but product still added" when a slow
// connection fired onerror after the server had inserted, (2) double inserts
// from its native-resubmit fallback, and (3) flaky client validation.
//
// We keep the multi-pick file accumulator (dt), but on a VALID submit we copy
// the accumulated files into the real <input name="data_sheet[]"> so the
// native POST carries them.
(function() {
    var theForm = picker.closest('form');

    // Remember which submit button was clicked so its name/value (add_new=1
    // for "Save & Add New") is still posted after we disable the buttons.
    var clickedButton = null;
    theForm.addEventListener('click', function(e) {
        var btn = e.target.closest && e.target.closest('[type="submit"]');
        if (btn) clickedButton = btn;
    }, true);

    var locked = false;

    // ActiveForm fires beforeSubmit ONLY after client validation passes, so
    // we never lock the buttons or move files on a validation failure.
    jQuery(theForm).on('beforeSubmit', function() {
        if (locked) return false;   // guard against a second in-flight submit
        locked = true;

        // Move accumulated files into the named input for the native POST.
        try { picker.files = dt.files; } catch (e) {}

        // Preserve the clicked submitter — disabled buttons are NOT posted.
        if (clickedButton && clickedButton.name) {
            var hidden = theForm.querySelector('input[type="hidden"][data-submitter="1"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.setAttribute('data-submitter', '1');
                theForm.appendChild(hidden);
            }
            hidden.name  = clickedButton.name;
            hidden.value = clickedButton.value || '1';
        }

        // Block double-click double-submit (belt-and-braces with the server
        // idempotency token in actionCreate).
        Array.prototype.slice.call(theForm.querySelectorAll('[type="submit"]'))
            .forEach(function(b) { b.disabled = true; });

        return true;   // proceed with the native submit
    });
}());

function deleteSavedFile(id, btn) {
    Swal.fire({
        icon: 'question',
        title: 'Delete File?',
        text: 'Are you sure you want to delete this file?',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        btn.disabled = true;
        \$.ajax({
            url: '{$deleteUploadUrl}',
            type: 'POST',
            data: {'{$csrfParam}': '{$csrfToken}', id: id},
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    document.getElementById('saved-' + id).remove();
                    Swal.fire({icon: 'success', title: 'Deleted', text: 'File deleted successfully.', timer: 1500, showConfirmButton: false});
                } else {
                    btn.disabled = false;
                    Swal.fire({icon: 'error', title: 'Error', text: 'Failed to delete file.'});
                }
            },
            error: function() {
                btn.disabled = false;
                Swal.fire({icon: 'error', title: 'Error', text: 'Request failed. Please try again.'});
            }
        });
    });
}
JS
); ?>
