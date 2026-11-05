<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadMaster $model */

use backend\models\LeadMaster;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = $model->isNewRecord ? 'Add Lead Master' : 'Update Lead Master';
$in = fn(string $placeholder, array $extra = []) => ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder] + $extra;
?>
<div class="ui-page lead-master-form" style="max-width:920px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub"><?= $model->isNewRecord ? 'A prospect you can convert into a lead later.' : Html::encode($model->account_name) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">Prospect details</div>
        <div class="card-body">
            <?php $form = ActiveForm::begin(['id' => 'lead-master-form', 'options' => ['class' => 'ui-form']]); ?>
            <?= $form->errorSummary($model, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
            <p class="ui-req-note">Fields marked <b>*</b> are required.</p>
            <div class="row g-3">
                <div class="col-md-4"><?= $form->field($model, 'account_name')->textInput($in('e.g. Acme Pvt Ltd', ['maxlength' => 200])) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'first_name')->textInput($in('e.g. John', ['maxlength' => 100])) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'last_name')->textInput($in('e.g. Mathew', ['maxlength' => 100])) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'designation')->textInput($in('e.g. Purchase Manager', ['maxlength' => 250])) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'email_id')->input('email', $in('name@company.com', ['maxlength' => 100])) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'phone_number')->textInput($in('e.g. +91 98765 43210', ['maxlength' => 25, 'inputmode' => 'tel'])) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'city')->textInput($in('e.g. Chennai', ['maxlength' => 100])) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'status')->dropDownList(LeadMaster::statusOptions(), ['class' => 'form-select form-select-sm']) ?></div>
            </div>
            <div class="ui-form-actions mb-0">
                <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . ($model->isNewRecord ? 'Save Prospect' : 'Update Prospect'), ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
