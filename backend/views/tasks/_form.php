<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Tasks $task */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\Developer[] $allDevelopers */
/** @var int[] $assignedDevIds */

use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$assignedDevIds = $assignedDevIds ?? [];
$expHours   = $task->hours_of_work ? intdiv((int)$task->hours_of_work, 3600) : 0;
$expMinutes = $task->hours_of_work ? intdiv((int)$task->hours_of_work % 3600, 60) : 0;
?>

<?php $form = ActiveForm::begin(); ?>

<!-- Row 1: Project | Assigned To | Hours of Work -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <?= $form->field($task, 'project_id')
            ->label('Project <span class="text-danger">*</span>', ['encode' => false])
            ->widget(Select2::class, [
                'data'          => ArrayHelper::map($projects, 'id', 'title'),
                'options'       => ['placeholder' => 'Select Project', 'class' => 'form-control form-control-sm'],
                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
            ]) ?>
    </div>
    <div class="col-md-4">
        <div class="mb-0">
            <label class="form-label form-label-sm">Assign To (Developers) <span class="text-danger">*</span></label>
            <?= Select2::widget([
                'name'          => 'developer_ids[]',
                'value'         => $assignedDevIds,
                'data'          => ArrayHelper::map($allDevelopers, 'id', 'developer_name'),
                'options'       => [
                    'multiple'    => true,
                    'placeholder' => 'Select Developers',
                    'class'       => 'form-control form-control-sm',
                ],
                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
            ]) ?>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label form-label-sm">Hours Of Work</label>
        <div class="d-flex gap-2 align-items-center">
            <input type="number" id="exp_hours" name="expected_hours"
                   min="0" value="<?= $expHours ?>"
                   class="form-control form-control-sm" style="width:70px" placeholder="h">
            <span class="text-muted">:</span>
            <input type="number" id="exp_minutes" name="expected_minutes"
                   min="0" max="59" value="<?= $expMinutes ?>"
                   class="form-control form-control-sm" style="width:70px" placeholder="m">
        </div>
    </div>
</div>

<!-- Row 2: Task | Assigned Date | Expected Completion Date -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <?= $form->field($task, 'task')
            ->label('Task <span class="text-danger">*</span>', ['encode' => false])
            ->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Task name']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($task, 'assigned_date')
            ->label('Assigned Date <span class="text-danger">*</span>', ['encode' => false])
            ->widget(DatePicker::class, [
                'type'          => DatePicker::TYPE_INPUT,
                'options'       => ['placeholder' => 'yyyy-mm-dd', 'class' => 'form-control form-control-sm'],
                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
            ]) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($task, 'expected_completion_date')
            ->label('Expected Completion Date')
            ->widget(DatePicker::class, [
                'type'          => DatePicker::TYPE_INPUT,
                'options'       => ['placeholder' => 'yyyy-mm-dd', 'class' => 'form-control form-control-sm'],
                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
            ]) ?>
    </div>
</div>

<!-- Description -->
<div class="row g-3 mb-3">
    <div class="col-md-12">
        <?= $form->field($task, 'description')
            ->label('Description')
            ->textarea(['rows' => 3, 'class' => 'form-control form-control-sm', 'placeholder' => 'Describe the task']) ?>
    </div>
</div>

<div class="d-flex gap-2 mt-2">
    <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
</div>

<?php ActiveForm::end(); ?>
