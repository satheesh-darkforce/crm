<?php
/** @var \yii\web\View $this */
/** @var \backend\models\UploadPayslip $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use kartik\date\DatePicker;

$this->title = 'Update Payslip Upload';
?>
<div class="ui-page payslip-upload-update">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="max-width:520px">
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

            <?php if ($model->file_path): ?>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Current File</label>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary"><?= Html::encode(basename($model->file_path)) ?></span>
                    <?= Html::a('Download', ['download', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm']) ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Replace CSV File <span class="text-danger fw-bold">(CSV file only)</span></label>
                <input type="file" name="UploadPayslip[file_path]" class="form-control form-control-sm" accept=".csv">
                <div class="form-text">Leave empty to keep the existing file.</div>
                <?php if ($model->hasErrors('file_path')): ?>
                    <div class="text-danger small mt-1"><?= $model->getFirstError('file_path') ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton('Update', ['class' => 'btn btn-primary btn-sm']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
