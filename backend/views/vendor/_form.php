<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Vendor $model */

use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use backend\models\Vendor;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Vendor' : 'Edit Vendor — ' . Html::encode($model->company_name);
?>
<div class="vendor-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= $isNew ? 'Add Vendor' : 'Edit Vendor' ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if ($isNew): ?>
                <div class="alert alert-info py-2 small mb-3">
                    <strong>Note:</strong> A ledger will be created in the name of <strong>Company Name</strong>.
                </div>
            <?php else: ?>
                <div class="alert alert-info py-2 small mb-3">
                    <strong>Note:</strong> Ledger name will be updated if <strong>Company Name</strong> is changed.
                </div>
            <?php endif; ?>

            <?php $form = ActiveForm::begin(['enableClientValidation' => true]); ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <?= $form->field($model, 'company_name')->textInput([
                        'class'       => 'form-control form-control-sm',
                        'placeholder' => 'Enter company name',
                        'maxlength'   => true,
                    ])->label('Company Name <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'vendor_type')->widget(Select2::class, [
                        'data'          => Vendor::typeLabels(),
                        'options'       => ['placeholder' => '— Select Vendor Type —'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Vendor Type <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'gst_no')->textInput([
                        'class'       => 'form-control form-control-sm',
                        'placeholder' => 'e.g. 29ABCDE1234F1Z5',
                        'maxlength'   => 15,
                        'style'       => 'text-transform:uppercase',
                        'oninput'     => 'this.value=this.value.toUpperCase()',
                    ])->label('GST Number')->hint('15-character GSTIN: 2-digit state code + PAN + entity + Z + check digit') ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'address')->textarea([
                        'class' => 'form-control form-control-sm',
                        'rows'  => 3,
                        'placeholder' => 'Enter full address',
                    ])->label('Address') ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? 'Add Vendor' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
