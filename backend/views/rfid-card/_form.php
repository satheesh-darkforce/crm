<?php
/** @var \yii\web\View $this */
/** @var \backend\models\RfidCard $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add RFID Card' : 'Edit RFID Card';
?>
<div class="rfid-card-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= $this->title ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:480px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['id' => 'rfid-card-form']); ?>
            <p class="help-block small text-muted mb-2">Fields with <span class="text-danger">*</span> are required.</p>
            <?= $form->errorSummary($model, ['class' => 'alert alert-danger small py-2']) ?>
            <div class="row g-3">
                <div class="col-12">
                    <?= $form->field($model, 'code')->label('RFID Code <span class="text-danger">*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm font-monospace', 'maxlength' => 100, 'placeholder' => 'e.g. A1B2C3D4']) ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? 'Add Card' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
