<?php
/** @var \yii\web\View $this */
/** @var \backend\models\UploadPayslip $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use kartik\date\DatePicker;

$this->title = 'Upload Payslip';
?>
<div class="ui-page payslip-upload-create">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" style="max-width:520px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

            <div class="mb-3">
                <?= $form->field($model, 'month_year')->widget(DatePicker::class, [
                    'type'          => DatePicker::TYPE_INPUT,
                    'options'       => ['placeholder' => 'Select Month & Year', 'class' => 'form-control form-control-sm'],
                    'pluginOptions' => [
                        'autoclose'   => true,
                        'format'      => 'yyyy-mm-dd',
                        'startView'   => 'months',
                        'minViewMode' => 'months',
                        'endDate'     => date('Y-m-d', strtotime('-1 month')),
                    ],
                ])->label('Month & Year') ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Upload Payslip <span class="text-danger fw-bold">(CSV file only)</span></label>
                <input type="file" name="UploadPayslip[file_path]" class="form-control form-control-sm" accept=".csv">
                <?php if ($model->hasErrors('file_path')): ?>
                    <div class="text-danger small mt-1"><?= $model->getFirstError('file_path') ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <!-- Sample Excel Sheet — same reference image as Yii1 -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 fw-semibold">Sample Excel Sheet</h6>
                <small class="text-muted">Reference layout — match these column headers when preparing your CSV.</small>
            </div>
            <div class="border rounded p-2 bg-light" style="overflow-x:auto">
                <a href="<?= Yii::$app->request->baseUrl ?>/images/upload_payslip_excel_sheet.png"
                   target="_blank" title="Click to open full size">
                    <img src="<?= Yii::$app->request->baseUrl ?>/images/upload_payslip_excel_sheet.png"
                         alt="Sample Sheet"
                         style="display:block;min-width:1100px;max-width:100%;height:auto">
                </a>
            </div>
        </div>
    </div>
</div>
