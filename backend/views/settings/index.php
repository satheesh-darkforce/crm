<?php
/** @var \yii\web\View $this */
/** @var \backend\models\MasterSetting $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Master Settings';
?>
<div class="settings-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Master Settings</h5>
    </div>

    <div class="card border-0 shadow-sm" style="max-width:560px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(); ?>
            <div class="row g-3">
                <div class="col-12">
                    <?= $form->field($model, 'invoice_lut_arn')
                        ->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50])
                        ->hint('Used on LUT/ARN exempt invoices for export billing.') ?>
                </div>
            </div>
            <div class="mt-3">
                <?= Html::submitButton('Save Settings', ['class' => 'btn btn-primary btn-sm px-4']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
