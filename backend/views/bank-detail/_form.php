<?php
/** @var \yii\web\View $this */
/** @var \backend\models\BankDetail $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$in = fn(array $extra = []) => $extra + ['class' => 'form-control form-control-sm'];
?>
<?php $form = ActiveForm::begin(['id' => 'bank-form', 'options' => ['class' => 'ui-form']]); ?>
<?= $form->errorSummary($model, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
<p class="ui-req-note">Fields marked <b>*</b> are required.</p>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Bank</div>
    <div class="card-body row g-3">
        <div class="col-md-6"><?= $form->field($model, 'bank_name')->textInput($in(['maxlength' => 255, 'placeholder' => 'e.g. HDFC Bank', 'autocomplete' => 'off'])) ?></div>
        <div class="col-md-6"><?= $form->field($model, 'branch')->textInput($in(['maxlength' => 255, 'placeholder' => 'Branch name or area'])) ?></div>
        <div class="col-md-6"><?= $form->field($model, 'ifsc')->textInput($in(['maxlength' => 11, 'placeholder' => 'e.g. HDFC0000123', 'class' => 'form-control form-control-sm mono js-upper', 'autocomplete' => 'off']))
            ->hint('11 characters; the 5th is always 0.') ?></div>
        <div class="col-md-6"><?= $form->field($model, 'swift')->textInput($in(['maxlength' => 11, 'placeholder' => 'e.g. HDFCINBB', 'class' => 'form-control form-control-sm mono js-upper', 'autocomplete' => 'off']))
            ->hint('8 or 11 characters, used for payments from abroad.') ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Account</div>
    <div class="card-body row g-3">
        <div class="col-md-6"><?= $form->field($model, 'acc_name')->textInput($in(['maxlength' => 255, 'placeholder' => 'Name exactly as on the account'])) ?></div>
        <div class="col-md-6"><?= $form->field($model, 'acc_no')->textInput($in(['maxlength' => 20, 'inputmode' => 'numeric', 'placeholder' => '6–20 digits', 'class' => 'form-control form-control-sm mono js-digits', 'autocomplete' => 'off'])) ?></div>
        <div class="col-12">
            <?= $form->field($model, 'is_default')->checkbox(['uncheck' => 0])->label('Default account — listed first and pre-selected on new invoices') ?>
        </div>
    </div>
</div>

<div class="ui-form-actions">
    <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . ($model->isNewRecord ? 'Add account' : 'Save changes'), ['class' => 'btn btn-primary btn-sm px-4']) ?>
    <?= Html::a('Cancel', $model->isNewRecord ? ['index'] : ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>
<?php ActiveForm::end(); ?>
