<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Asset $model */
/** @var \backend\models\AssetCategory[] $categories */
/** @var \backend\models\AssetSubcategory[] $subcats */
/** @var \backend\models\AssetBrand[] $brands */
/** @var \backend\models\AssetLocation[] $locations */
/** @var \backend\models\Vendor[] $vendors */
/** @var \backend\models\AssetTag[] $tags */
/** @var \backend\models\AssetUpload[] $uploads */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use backend\models\AssetUpload;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Asset' : 'Edit Asset: ' . $model->product_name;

$tagMap       = ArrayHelper::map($tags, 'id', 'name');
$selectedTags = !empty($model->tags) ? explode(',', $model->tags) : [];

// Group existing uploads by type
$uploadsByType = [];
foreach ($uploads as $u) {
    $uploadsByType[$u->upload_type][] = $u;
}
$uploadTypeLabels = AssetUpload::uploadTypeLabels();
?>
<div class="ui-page asset-form">
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
            <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

            <p class="help-block small text-muted mb-2">Fields with <span class="text-danger">*</span> are required.</p>
            <?php if ($model->hasErrors()): ?>
                <div class="alert alert-danger py-2 small">
                    <?= $form->errorSummary($model, ['header' => '<strong>Please fix the following errors:</strong>']) ?>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <?= $form->field($model, 'product_name')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Product name'])
                        ->label('Product Name <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'model')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Model number']) ?>
                </div>
                <?php if (!$isNew && $model->barcode): ?>
                <div class="col-md-4">
                    <label class="form-label">Barcode</label>
                    <div class="form-control form-control-sm bg-light" style="font-family:monospace"><?= Html::encode($model->barcode) ?></div>
                    <small class="text-muted">Auto-generated on save</small>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <?= $form->field($model, 'brand_id')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($brands, 'id', 'brand_name'),
                        'options'       => ['placeholder' => 'Select Brand…'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Brand <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'asset_cat_id')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($categories, 'id', 'name'),
                        'options'       => ['placeholder' => 'Select Category…', 'id' => 'cat-select'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Category <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sub Category <span class="text-danger">*</span></label>
                    <select name="Asset[asset_subcat_id]" id="subcat-select" class="form-select form-select-sm">
                        <option value="">Select Sub Category</option>
                        <?php foreach ($subcats as $s): ?>
                            <option value="<?= $s->id ?>" <?= $model->asset_subcat_id == $s->id ? 'selected' : '' ?>><?= Html::encode($s->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($model->hasErrors('asset_subcat_id')): ?>
                        <div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('asset_subcat_id')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4" id="vendor-field-wrap">
                    <?= $form->field($model, 'vendor_id')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($vendors, 'id', 'company_name'),
                        'options'       => ['placeholder' => 'Select Vendor…', 'id' => 'vendor-select'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Vendor <span class="text-danger">*</span>', ['encode' => false]) ?>
                    <div style="margin-top:-8px">
                        <button type="button" class="btn btn-link btn-sm p-0 text-primary" id="open-add-vendor" style="font-size:.78rem">+ Add New Vendor</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'asset_loc')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($locations, 'id', 'loc'),
                        'options'       => ['placeholder' => 'Select Location…'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Location <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'date_of_purchase')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ])->label('Date of Purchase <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'date_of_expire')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ])->label('Expire Date') ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'bill')->textInput(['class' => 'form-control form-control-sm', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => 'Bill amount'])->label('Bill Amount (₹)') ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'service_contact')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Service contact', 'maxlength' => 250]) ?>
                </div>

                <!-- Tags -->
                <div class="col-md-4">
                    <label class="form-label">Tags</label>
                    <?= Select2::widget([
                        'name'          => 'Asset[tags]',
                        'value'         => $selectedTags,
                        'data'          => $tagMap,
                        'options'       => ['placeholder' => 'Select tags…', 'multiple' => true, 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>

                <!-- Warranty -->
                <div class="col-12"><hr class="my-1"><strong class="small text-muted text-uppercase">Warranty</strong></div>
                <div class="col-md-4">
                    <?= $form->field($model, 'warranty')->dropDownList(
                        [0 => 'No', 1 => 'Yes'],
                        ['class' => 'form-select form-select-sm', 'id' => 'warranty-select']
                    ) ?>
                </div>
                <div class="col-md-4 warranty-date-col" style="<?= $model->warranty ? '' : 'display:none' ?>">
                    <?= $form->field($model, 'warranty_start_date')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ])->label('Warranty Start <span class="text-danger warranty-req-mark">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4 warranty-date-col" style="<?= $model->warranty ? '' : 'display:none' ?>">
                    <?= $form->field($model, 'warranty_end_date')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ])->label('Warranty End <span class="text-danger warranty-req-mark">*</span>', ['encode' => false]) ?>
                </div>

                <!-- AMC -->
                <div class="col-12"><hr class="my-1"><strong class="small text-muted text-uppercase">AMC</strong></div>
                <div class="col-md-4">
                    <?= $form->field($model, 'amc')->dropDownList(
                        [0 => 'No', 1 => 'Yes'],
                        ['class' => 'form-select form-select-sm', 'id' => 'amc-select']
                    ) ?>
                </div>
                <div class="col-md-4 amc-date-col" style="<?= $model->amc ? '' : 'display:none' ?>">
                    <?= $form->field($model, 'amc_start_date')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ])->label('AMC Start <span class="text-danger amc-req-mark">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4 amc-date-col" style="<?= $model->amc ? '' : 'display:none' ?>">
                    <?= $form->field($model, 'amc_end_date')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ])->label('AMC End <span class="text-danger amc-req-mark">*</span>', ['encode' => false]) ?>
                </div>

                <div class="col-md-12">
                    <?= $form->field($model, 'description')->textarea(['rows' => 3, 'class' => 'form-control form-control-sm', 'placeholder' => 'Optional notes about this asset…']) ?>
                </div>

                <!-- File Uploads -->
                <div class="col-12"><hr class="my-1"><strong class="small text-muted text-uppercase">Documents &amp; Files</strong></div>

                <div class="col-md-3">
                    <label class="form-label">Purchase Bill</label>
                    <input type="file" name="billupload[]" class="form-control form-control-sm" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                    <?php if (!empty($uploadsByType[1])): ?>
                        <div class="mt-1">
                            <?php foreach ($uploadsByType[1] as $u): ?>
                                <div class="d-flex align-items-center gap-1 mb-1 upload-row" data-id="<?= $u->id ?>">
                                    <a href="<?= \backend\controllers\Controller::uploadUrl($u->path) ?>" target="_blank" class="small text-truncate" style="max-width:150px"><?= Html::encode(basename($u->path)) ?></a>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 delete-upload" data-id="<?= $u->id ?>" style="font-size:.7rem"><i class="fas fa-times"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 warranty-upload-col" style="<?= $model->warranty ? '' : 'display:none' ?>">
                    <label class="form-label">Warranty Document</label>
                    <input type="file" name="warrantyupload[]" class="form-control form-control-sm warranty-upload-input" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                    <?php if (!empty($uploadsByType[2])): ?>
                        <div class="mt-1">
                            <?php foreach ($uploadsByType[2] as $u): ?>
                                <div class="d-flex align-items-center gap-1 mb-1 upload-row" data-id="<?= $u->id ?>">
                                    <a href="<?= \backend\controllers\Controller::uploadUrl($u->path) ?>" target="_blank" class="small text-truncate" style="max-width:150px"><?= Html::encode(basename($u->path)) ?></a>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 delete-upload" data-id="<?= $u->id ?>" style="font-size:.7rem"><i class="fas fa-times"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 amc-upload-col" style="<?= $model->amc ? '' : 'display:none' ?>">
                    <label class="form-label">AMC Document</label>
                    <input type="file" name="amcupload[]" class="form-control form-control-sm amc-upload-input" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                    <?php if (!empty($uploadsByType[3])): ?>
                        <div class="mt-1">
                            <?php foreach ($uploadsByType[3] as $u): ?>
                                <div class="d-flex align-items-center gap-1 mb-1 upload-row" data-id="<?= $u->id ?>">
                                    <a href="<?= \backend\controllers\Controller::uploadUrl($u->path) ?>" target="_blank" class="small text-truncate" style="max-width:150px"><?= Html::encode(basename($u->path)) ?></a>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 delete-upload" data-id="<?= $u->id ?>" style="font-size:.7rem"><i class="fas fa-times"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Product Image</label>
                    <input type="file" name="product_image_upload[]" class="form-control form-control-sm" multiple accept=".jpg,.jpeg,.png,.gif,.webp">
                    <?php if (!empty($uploadsByType[4])): ?>
                        <div class="mt-1">
                            <?php foreach ($uploadsByType[4] as $u): ?>
                                <div class="d-flex align-items-center gap-1 mb-1 upload-row" data-id="<?= $u->id ?>">
                                    <a href="<?= \backend\controllers\Controller::uploadUrl($u->path) ?>" target="_blank" class="small text-truncate" style="max-width:150px"><?= Html::encode(basename($u->path)) ?></a>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 delete-upload" data-id="<?= $u->id ?>" style="font-size:.7rem"><i class="fas fa-times"></i></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? 'Add Asset' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1" aria-labelledby="addVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="addVendorModalLabel">Add Vendor</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                        <input type="text" id="new-vendor-name" class="form-control form-control-sm" placeholder="Company name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">GST Number</label>
                        <input type="text" id="new-vendor-gst" class="form-control form-control-sm" placeholder="GST number">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea id="new-vendor-address" class="form-control form-control-sm" rows="2" placeholder="Vendor address"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="save-vendor-btn">Add Vendor</button>
            </div>
        </div>
    </div>
</div>

<?php
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$subcatUrl = Url::to(['/asset/get-subcategory']);
$deleteUploadUrl = Url::to(['/asset/delete-upload']);
$addVendorUrl = Url::to(['/asset/add-vendor']);

$js = <<<JS
// Category → Subcategory cascade
$('#cat-select').on('change', function() {
    var catId = $(this).val();
    var \$subSel = $('#subcat-select');
    \$subSel.html('<option value="">Select Sub Category</option>');
    if (!catId) return;
    $.post('{$subcatUrl}', {'{$csrfParam}': '{$csrfToken}', cat_id: catId}, function(data) {
        $.each(data, function(i, s) {
            \$subSel.append(new Option(s.name, s.id));
        });
    });
});

// Warranty / AMC toggle — show/hide date cols + file upload col, clear values & validation when No
function toggleSection(selectId, dateColClass, markClass, uploadColClass, uploadInputClass, nameStart, nameEnd) {
    var on = parseInt($('#' + selectId).val(), 10) === 1;
    $('.' + dateColClass).toggle(on);
    $('.' + markClass).toggle(on);
    $('.' + uploadColClass).toggle(on);
    if (!on) {
        [nameStart, nameEnd].forEach(function (n) {
            var sel = '[name="' + n + '"]';
            $(sel).val('');
            $(sel).closest('.form-group').removeClass('has-error').find('.help-block').text('');
        });
        // Clear the file upload selection when the section is switched off
        $('.' + uploadInputClass).val('');
    }
}
$('#warranty-select').on('change', function() {
    toggleSection('warranty-select', 'warranty-date-col', 'warranty-req-mark',
                  'warranty-upload-col', 'warranty-upload-input',
                  'Asset[warranty_start_date]', 'Asset[warranty_end_date]');
});
$('#amc-select').on('change', function() {
    toggleSection('amc-select', 'amc-date-col', 'amc-req-mark',
                  'amc-upload-col', 'amc-upload-input',
                  'Asset[amc_start_date]', 'Asset[amc_end_date]');
});
// Initial sync on page load
toggleSection('warranty-select', 'warranty-date-col', 'warranty-req-mark',
              'warranty-upload-col', 'warranty-upload-input',
              'Asset[warranty_start_date]', 'Asset[warranty_end_date]');
toggleSection('amc-select', 'amc-date-col', 'amc-req-mark',
              'amc-upload-col', 'amc-upload-input',
              'Asset[amc_start_date]', 'Asset[amc_end_date]');

// Tags: convert multi-select values to comma-separated string before submit
$('form').on('beforeSubmit', function() {
    var tagVals = $('[name="Asset[tags][]"]').val();
    if (tagVals && tagVals.length > 0) {
        $('[name="Asset[tags][]"]').attr('name', '');
        if ($('#hidden-tags').length === 0) {
            $(this).append('<input type="hidden" id="hidden-tags" name="Asset[tags]" value="' + tagVals.join(',') + '">');
        } else {
            $('#hidden-tags').val(tagVals.join(','));
        }
    } else {
        if ($('#hidden-tags').length === 0) {
            $(this).append('<input type="hidden" id="hidden-tags" name="Asset[tags]" value="">');
        } else {
            $('#hidden-tags').val('');
        }
    }
    return true;
});

// Delete upload AJAX
$(document).on('click', '.delete-upload', function() {
    if (!confirm('Delete this file?')) return;
    var id = $(this).data('id');
    var \$row = $(this).closest('.upload-row');
    $.post('{$deleteUploadUrl}', {'{$csrfParam}': '{$csrfToken}', id: id}, function(d) {
        if (d.success) { \$row.remove(); }
        else { Swal.fire({icon:'error', title:'Error', text: d.message || 'Delete failed.'}); }
    }, 'json');
});

// Open Add Vendor modal
$('#open-add-vendor').on('click', function(e) {
    e.preventDefault();
    var modal = new bootstrap.Modal(document.getElementById('addVendorModal'));
    modal.show();
});

// Save Vendor AJAX
$('#save-vendor-btn').on('click', function() {
    var \$btn    = $(this);
    var name    = $.trim($('#new-vendor-name').val());
    var gst     = $.trim($('#new-vendor-gst').val());
    var address = $.trim($('#new-vendor-address').val());

    if (!name) {
        Swal.fire({icon:'warning', title:'Required', text:'Vendor name is required.'});
        return;
    }

    \$btn.prop('disabled', true);
    $.ajax({
        url: '{$addVendorUrl}',
        type: 'POST',
        dataType: 'json',
        data: {
            '{$csrfParam}': '{$csrfToken}',
            name: name,
            gst_no: gst,
            address: address
        },
        success: function(d) {
            \$btn.prop('disabled', false);
            if (d.success) {
                var newOpt = new Option(d.name, d.id, true, true);
                $('#vendor-select').append(newOpt).trigger('change');
                $('#new-vendor-name').val('');
                $('#new-vendor-gst').val('');
                $('#new-vendor-address').val('');
                bootstrap.Modal.getInstance(document.getElementById('addVendorModal')).hide();
            } else {
                Swal.fire({icon:'error', title:'Error', text: d.message || 'Failed to add vendor.'});
            }
        },
        error: function() {
            \$btn.prop('disabled', false);
            Swal.fire({icon:'error', title:'Error', text:'Request failed. Please try again.'});
        }
    });
});
JS;

$this->registerJs($js);
?>
