<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadMaster $model */

use backend\models\LeadMaster;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = $model->isNewRecord ? 'Add Lead Master' : 'Update Lead Master';
$sm = ['class' => 'form-control form-control-sm'];
?>
<div class="lead-master-form" style="max-width:900px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?= $this->render('_form-css') ?>
            <?php $form = ActiveForm::begin(['id' => 'lead-master-form', 'options' => ['class' => 'lead-form-scope']]); ?>
            <?= $form->errorSummary($model, ['class' => 'alert alert-danger py-2 small']) ?>
            <p class="text-muted small mb-3">Fields marked <span class="text-danger fw-bold">*</span> are required.</p>
            <div class="row g-3">
                <div class="col-md-4"><?= $form->field($model, 'account_name')->textInput($sm + ['maxlength' => 200, 'placeholder' => 'e.g. Acme Pvt Ltd']) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'first_name')->textInput($sm + ['maxlength' => 100, 'placeholder' => 'e.g. John']) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'last_name')->textInput($sm + ['maxlength' => 100, 'placeholder' => 'e.g. Mathew']) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'designation')->textInput($sm + ['maxlength' => 250, 'placeholder' => 'e.g. Purchase Manager']) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'email_id')->input('email', $sm + ['maxlength' => 100, 'placeholder' => 'name@company.com']) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'phone_number')->textInput($sm + ['maxlength' => 25, 'inputmode' => 'tel', 'placeholder' => 'e.g. +91 98765 43210']) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'city')->textInput($sm + ['maxlength' => 100, 'placeholder' => 'e.g. Chennai']) ?></div>
                <div class="col-md-4"><?= $form->field($model, 'status')->dropDownList(LeadMaster::statusOptions(), ['class' => 'form-select form-select-sm']) ?></div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($model->isNewRecord ? 'Submit' : 'Update', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
