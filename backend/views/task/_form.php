<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task $task */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$expSecs    = (int)($task->expected_time ?? 0);
$expHours   = intdiv($expSecs, 3600);
$expMinutes = intdiv($expSecs % 3600, 60);
?>

<?php $form = ActiveForm::begin(); ?>

<!-- Row 1: Project | Assign Type | Assigned To | Priority -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <?= $form->field($task, 'project_id')->label('Project <span class="text-danger">*</span>', ['encode' => false])->widget(Select2::class, [
            'data'          => ArrayHelper::map($projects, 'id', 'title'),
            'options'       => ['placeholder' => 'Select Project', 'class' => 'form-control form-control-sm'],
            'pluginOptions' => ['allowClear' => true],
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($task, 'assign_type')->label('Assign Type <span class="text-danger">*</span>', ['encode' => false])->dropDownList(
            [1 => 'Full', 2 => 'Partial'],
            ['class' => 'form-select form-select-sm', 'prompt' => 'Select Assign Type']
        ) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($task, 'assigned_to')->label('Assigned To <span class="text-danger">*</span>', ['encode' => false])->widget(Select2::class, [
            'data'          => ArrayHelper::map($developers, 'user_id', 'developer_name'),
            'options'       => ['placeholder' => 'Select Developer', 'class' => 'form-control form-control-sm'],
            'pluginOptions' => ['allowClear' => true],
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($task, 'priority')->label('Priority <span class="text-danger">*</span>', ['encode' => false])->dropDownList(
            [1 => 'High', 2 => 'Medium', 3 => 'Low'],
            ['class' => 'form-select form-select-sm', 'prompt' => 'Select Priority']
        ) ?>
    </div>
</div>

<!-- Row 2: Task | Expected Delivery Date | Hour Of Work Required | Description -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <?= $form->field($task, 'task')->label('Task <span class="text-danger">*</span>', ['encode' => false])->textInput([
            'class'       => 'form-control form-control-sm',
            'placeholder' => 'Task name',
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($task, 'expected_delivery_date')->label('Expected Delivery Date <span class="text-danger">*</span>', ['encode' => false])->widget(DatePicker::class, [
            'type'          => DatePicker::TYPE_INPUT,
            'options'       => ['placeholder' => 'yyyy-mm-dd', 'class' => 'form-control form-control-sm'],
            'pluginOptions' => [
                'autoclose'  => true,
                'format'     => 'yyyy-mm-dd',
                'startDate'  => date('Y-m-d'),
                'todayHighlight' => true,
            ],
        ]) ?>
    </div>
    <div class="col-md-3">
        <label class="form-label form-label-sm">Hour Of Work Required <span class="text-danger">*</span></label>
        <div class="d-flex gap-2 align-items-center">
            <input type="number" id="expected_hours" name="expected_hours"
                   min="0" value="<?= $expHours ?>"
                   class="form-control form-control-sm" style="width:70px" placeholder="h">
            <input type="number" id="expected_minutes" name="expected_minutes"
                   min="0" max="59" value="<?= $expMinutes ?>"
                   class="form-control form-control-sm" style="width:70px" placeholder="m">
        </div>
        <?= $form->field($task, 'expected_time')->hiddenInput(['id' => 'task-expected_time'])->label(false) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($task, 'description')->label('Description')->textarea([
            'rows'  => 3,
            'class' => 'form-control form-control-sm',
            'placeholder' => 'Describe the task',
        ]) ?>
    </div>
</div>

<div class="d-flex gap-2 mt-2">
    <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm', 'id' => 'task-submit-btn']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
</div>

<?php ActiveForm::end(); ?>

<?php
$this->registerJs(<<<'JS'
jQuery('#task-submit-btn').on('click', function (e) {
    var h = parseInt(jQuery('#expected_hours').val()) || 0;
    var m = parseInt(jQuery('#expected_minutes').val()) || 0;
    var total = h * 3600 + m * 60;
    if (total < 60) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Invalid Hours',
            text: 'Hour Of Work Required must be at least 1 minute.'
        });
        jQuery('#expected_hours').focus();
        return false;
    }
    jQuery('#task-expected_time').val(total);
});
JS, \yii\web\View::POS_READY);
?>
