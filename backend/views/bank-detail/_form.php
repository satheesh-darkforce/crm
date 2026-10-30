<?php
/** @var \yii\web\View $this */
/** @var \backend\models\BankDetail $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Bank Account' : 'Edit Bank Account';
?>
<div class="bank-detail-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= $this->title ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:600px">
        <div class="card-body">
            <p class="text-muted small mb-3">Fields with <span class="text-danger">*</span> are required.</p>
            <?php $form = ActiveForm::begin(); ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <?= $form->field($model, 'bank_name')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'e.g. HDFC Bank'])->label('Bank Name <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'branch')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'Branch name / location'])->label('Branch <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'acc_name')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'Account holder name'])->label('Account Name <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'acc_no')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'Account number', 'onkeypress' => 'return (event.charCode >= 48 && event.charCode <= 57) || event.charCode === 0'])->label('Account Number <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'ifsc')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'e.g. HDFC0000123'])->label('IFSC Code <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'swift')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'e.g. HDFCINBB'])->label('SWIFT Code <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'is_default')->dropDownList([0 => 'No', 1 => 'Yes'], ['class' => 'form-select form-select-sm'])->label('Set as Default') ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? 'Add Account' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
