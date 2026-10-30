<?php
/** @var \yii\web\View $this */
/** @var \backend\models\MasterSetting $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Invoice Settings';
?>
<div class="settings-invoice">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Invoice Settings</h5>
    </div>

    <p class="text-muted small mb-3">Fields with <span class="text-danger">*</span> are required.</p>

    <div class="card border-0 shadow-sm" style="max-width:400px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(); ?>
            <div class="mb-3">
                <?= $form->field($model, 'invoice_lut_arn')
                    ->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50])
                    ->label('Invoice LUT ARN <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
            <?= Html::submitButton('Save', ['class' => 'btn btn-primary btn-sm px-4']) ?>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
