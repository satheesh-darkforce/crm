<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeaveSettings $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Leave Settings';

$durationOptions = [
    '' => 'Select Duration',
    '01:00:00' => '1 Hour',
    '02:00:00' => '2 Hours',
    '03:00:00' => '3 Hours',
    '04:00:00' => '4 Hours',
    '05:00:00' => '5 Hours',
    '06:00:00' => '6 Hours',
    '07:00:00' => '7 Hours',
    '08:00:00' => '8 Hours',
];
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
</style>

<div class="leave-settings">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Leave Settings</h5>
            <small class="text-muted">Configure permission limits, grace time and CL quota</small>
        </div>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:600px">
        <div class="card-body">
            <?php $form = ActiveForm::begin([
                'id'      => 'leave-settings-form',
                'method'  => 'post',
                'options' => ['class' => 'row g-3'],
            ]); ?>

            <div class="col-md-6">
                <?= $form->field($model, 'monthly_allowed_cl')->label('Monthly Allowed CL <span class="text-danger">*</span>', ['encode' => false])->textInput([
                    'class'       => 'form-control form-control-sm',
                    'type'        => 'number',
                    'min'         => 1,
                    'max'         => 10,
                    'placeholder' => 'e.g. 1 (between 1 and 10)',
                ]) ?>
            </div>

            <div class="col-md-6">
                <?= $form->field($model, 'no_of_permissions')->label('No. of Permissions Allowed Per Month <span class="text-danger">*</span>', ['encode' => false])->textInput([
                    'class' => 'form-control form-control-sm',
                    'type'  => 'number',
                    'min'   => 1,
                    'max'   => 20,
                    'placeholder' => 'e.g. 2 (between 1 and 20)',
                ]) ?>
            </div>

            <div class="col-md-6">
                <?= $form->field($model, 'duration')->label('Duration Per Permission (in hours) <span class="text-danger">*</span>', ['encode' => false])->dropDownList($durationOptions, [
                    'class' => 'form-select form-select-sm',
                ]) ?>
            </div>

            <div class="col-md-6">
                <?= $form->field($model, 'check_in_grace_time')->label('Check-in Grace Time (in minutes) <span class="text-danger">*</span>', ['encode' => false])->textInput([
                    'class' => 'form-control form-control-sm',
                    'type'  => 'number',
                    'min'   => 0,
                    'max'   => 120,
                    'placeholder' => 'e.g. 15 (0 to 120)',
                ]) ?>
            </div>

            <div class="col-12">
                <?= Html::submitButton('Save Settings', ['class' => 'btn btn-primary btn-sm']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm ms-1']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
