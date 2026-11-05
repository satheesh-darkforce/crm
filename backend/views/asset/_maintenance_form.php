<?php
/** @var \yii\web\View $this */
/** @var \backend\models\AssetMaintenance $model */
/** @var \backend\models\Asset[] $assets */
/** @var \backend\models\Developer[] $developers */

use backend\models\AssetMaintenance;
use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\select2\Select2;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Maintenance Record' : 'Edit Maintenance Record';

$preAsset = Yii::$app->request->get('asset_id');
if ($isNew && $preAsset) { $model->asset_id = (int)$preAsset; }

$assetMap      = ArrayHelper::map($assets, 'id', 'barcode');
$developerMap  = ArrayHelper::map($developers, 'id', 'developer_name');
$paymentType   = (int)($model->payment_type ?? 0);
$paymentMode   = (int)($model->payment_mode ?? 0);
$req = '<span class="text-danger">*</span>';

// Pre-load brand/model for edit mode
$assetBrand = '';
$assetModel = '';
if (!$isNew && $model->asset_id) {
    $relAsset = $model->asset;
    if ($relAsset) {
        $assetBrand = $relAsset->brand->brand_name ?? '';
        $assetModel = $relAsset->model ?? '';
    }
}
?>
<div class="ui-page asset-maintenance-form">
    <div class="ui-head">
        <div>
            <h1><?= $this->title ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['maintenance'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <p class="mb-2"><strong>Fields with <span class="text-danger">*</span> are required.</strong></p>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
            <div class="row g-3">

                <!-- Row 1: Asset + Brand + Model -->
                <div class="col-md-4">
                    <?= $form->field($model, 'asset_id')->widget(Select2::class, [
                        'data'          => $assetMap,
                        'options'       => ['placeholder' => 'Select Asset…', 'id' => 'maintenance-asset-id'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Asset Id ' . $req, ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Asset Brand</label>
                    <input type="text" id="asset-brand-display" class="form-control form-control-sm" readonly
                           placeholder="Auto-filled from selected asset"
                           value="<?= Html::encode($assetBrand) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Asset Model</label>
                    <input type="text" id="asset-model-display" class="form-control form-control-sm" readonly
                           placeholder="Auto-filled from selected asset"
                           value="<?= Html::encode($assetModel) ?>">
                </div>

                <!-- Row 2: Service Provider + Agent + Contact -->
                <div class="col-md-4">
                    <?= $form->field($model, 'service_provider')->textInput([
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'Service provider name',
                        'maxlength' => 250,
                    ])->label('Service Provider ' . $req, ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'service_provider_name')->textInput([
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'Service agent name',
                        'maxlength' => 250,
                    ])->label('Service Agent ' . $req, ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'sp_number')->textInput([
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'Contact number (10-15 digits)',
                        'minlength' => 10,
                        'maxlength' => 15,
                    ])->label('Contact Number ' . $req, ['encode' => false]) ?>
                </div>

                <!-- Row 3: Attended By + Service Type -->
                <div class="col-md-4">
                    <?= $form->field($model, 'attended')->widget(Select2::class, [
                        'data'          => $developerMap,
                        'options'       => ['placeholder' => 'Select Employee'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Attended By ' . $req, ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'payment_type')->dropDownList(
                        AssetMaintenance::paymentTypeLabels(),
                        [
                            'prompt' => 'Select Service Type',
                            'class'  => 'form-select form-select-sm',
                            'id'     => 'maintenance-payment-type',
                        ]
                    )->label('Service Type ' . $req, ['encode' => false]) ?>
                </div>

                <!-- Paid-only fields -->
                <div id="paid-fields" class="col-12 <?= $paymentType === 2 ? '' : 'd-none' ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <?= $form->field($model, 'service_cost')->textInput([
                                'class'       => 'form-control form-control-sm',
                                'type'        => 'number',
                                'step'        => '0.01',
                                'min'         => '0.01',
                                'maxlength'   => 7,
                                'placeholder' => '0.00',
                            ])->label('Service Cost ' . $req, ['encode' => false]) ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'payment_mode')->dropDownList(
                                AssetMaintenance::paymentModeLabels(),
                                [
                                    'prompt' => 'Select Payment Mode',
                                    'class'  => 'form-select form-select-sm',
                                    'id'     => 'maintenance-payment-mode',
                                ]
                            )->label('Payment Mode ' . $req, ['encode' => false]) ?>
                        </div>
                        <div id="gpay-field" class="col-md-4 <?= $paymentMode === 2 ? '' : 'd-none' ?>">
                            <?= $form->field($model, 'gpay_number')->textInput([
                                'class'       => 'form-control form-control-sm',
                                'placeholder' => 'GPay number',
                                'maxlength'   => 15,
                            ])->label('Gpay Number ' . $req, ['encode' => false]) ?>
                        </div>
                        <div id="bank-field" class="col-md-4 <?= $paymentMode === 3 ? '' : 'd-none' ?>">
                            <?= $form->field($model, 'bank_number')->textInput([
                                'class'       => 'form-control form-control-sm',
                                'placeholder' => 'Bank account / reference',
                                'maxlength'   => 100,
                            ])->label('Bank Account Details ' . $req, ['encode' => false]) ?>
                        </div>
                        <!-- Invoice Bill Upload -->
                        <div class="col-md-4">
                            <label class="form-label">Service Bill <?= $req ?></label>
                            <?php if (!$isNew && $model->invoice_bill): ?>
                                <div id="existing-bill" class="mb-1 small d-flex align-items-center gap-2">
                                    <a href="<?= \backend\controllers\Controller::uploadUrl($model->invoice_bill) ?>" target="_blank" class="text-primary">
                                        <?= Html::encode(basename($model->invoice_bill)) ?>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" id="delete-existing-bill" data-id="<?= $model->id ?>" style="font-size:.65rem"><i class="fas fa-times"></i></button>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="invoice_bill_file" class="form-control form-control-sm<?= $model->hasErrors('invoice_bill') ? ' is-invalid' : '' ?>" accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                            <?php if ($model->hasErrors('invoice_bill')): ?><div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('invoice_bill')) ?></div><?php endif; ?>
                            <small class="text-muted">Max 10 MB (pdf, jpg, jpeg, png, doc, docx)</small>
                        </div>
                    </div>
                </div>

            </div>
            <input type="hidden" id="sub_key" name="sub_key" value="1">
            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm px-4', 'id' => 'btn-submit']) ?>
                <?php if ($isNew): ?>
                    <?= Html::submitButton('Save & Add New', ['class' => 'btn btn-outline-primary btn-sm px-4', 'id' => 'btn-submit-new']) ?>
                <?php endif; ?>
                <?= Html::a('Cancel', ['maintenance'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$getAssetValueUrl = Url::to(['/asset/get-asset-value']);
$deleteBillUrl = Url::to(['/asset/delete-maintenance-bill']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();

$js = <<<JS
(function () {
    // Asset AJAX: fetch brand & model on asset selection
    $('#maintenance-asset-id').on('change', function() {
        var assetId = $(this).val();
        if (!assetId) {
            $('#asset-brand-display').val('');
            $('#asset-model-display').val('');
            return;
        }
        $.ajax({
            url: '{$getAssetValueUrl}',
            type: 'POST',
            data: { asset_id: assetId, '{$csrfParam}': '{$csrfToken}' },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    $('#asset-brand-display').val(resp.brand || '');
                    $('#asset-model-display').val(resp.model || '');
                }
            }
        });
    });

    // Payment Type toggle
    var ptSel = document.getElementById('maintenance-payment-type');
    var pmSel = document.getElementById('maintenance-payment-mode');
    var paid  = document.getElementById('paid-fields');
    var gpay  = document.getElementById('gpay-field');
    var bank  = document.getElementById('bank-field');

    function togglePaid() {
        if (ptSel.value == '2') { paid.classList.remove('d-none'); }
        else { paid.classList.add('d-none'); }
    }
    function togglePayMode() {
        gpay.classList.add('d-none');
        bank.classList.add('d-none');
        if (pmSel.value == '2') gpay.classList.remove('d-none');
        if (pmSel.value == '3') bank.classList.remove('d-none');
    }
    if (ptSel) ptSel.addEventListener('change', togglePaid);
    if (pmSel) pmSel.addEventListener('change', togglePayMode);

    // Numeric-only for contact & service cost
    $(document).on('keypress', '#assetmaintenance-sp_number, #assetmaintenance-service_cost', function(e) {
        var c = e.which;
        if (!(c >= 48 && c <= 57) && c !== 46) { e.preventDefault(); }
    });

    // Alpha-only for service provider / agent
    $('#assetmaintenance-service_provider_name, #assetmaintenance-service_provider').on('input', function() {
        $(this).val($(this).val().replace(/[^a-zA-Z\s]/g, ''));
    });

    // File size validation
    $('input[name="invoice_bill_file"]').on('change', function() {
        if (this.files && this.files[0] && this.files[0].size > 10 * 1024 * 1024) {
            Swal.fire({icon:'warning', title:'File too large', text:'File size must be smaller than 10 MB'});
            $(this).val('');
        }
    });

    // Submit buttons
    $('#btn-submit').on('click', function() { $('#sub_key').val('1'); });
    $('#btn-submit-new').on('click', function() { $('#sub_key').val('0'); });

    // Delete existing bill
    $('#delete-existing-bill').on('click', function() {
        if (!confirm('Are you sure you want to delete this file?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: '{$deleteBillUrl}',
            type: 'POST',
            data: { id: id, '{$csrfParam}': '{$csrfToken}' },
            dataType: 'json',
            success: function(d) {
                if (d.success) { $('#existing-bill').html('<span class="text-muted small">Deleted</span>'); }
                else { Swal.fire({icon:'error', title:'Error', text: d.message || 'Delete failed.'}); }
            }
        });
    });
})();
JS;
$this->registerJs($js);
?>
