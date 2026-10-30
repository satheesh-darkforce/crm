<?php
/** @var \yii\web\View $this */
/** @var \backend\models\PmTask $task */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;
?>

<?php $form = ActiveForm::begin(); ?>

<div class="row g-3">
    <div class="col-md-6">
        <?= $form->field($task, 'project_id')->label('Project <span class="text-danger">*</span>', ['encode' => false])->widget(Select2::class, [
            'data'          => ArrayHelper::map($projects, 'id', 'title'),
            'options'       => ['placeholder' => 'Select PM project…'],
            'pluginOptions' => ['allowClear' => true],
        ]) ?>
    </div>
    <div class="col-md-6">
        <?= $form->field($task, 'assigned_to')->label('Assigned To <span class="text-danger">*</span>', ['encode' => false])->widget(Select2::class, [
            'data'          => ArrayHelper::map($developers, 'user_id', 'developer_name'),
            'options'       => ['placeholder' => 'Assign to developer…'],
            'pluginOptions' => ['allowClear' => true],
        ]) ?>
    </div>
    <div class="col-12">
        <?= $form->field($task, 'task')->label('Task Name <span class="text-danger">*</span>', ['encode' => false])
            ->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Brief task title']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($task, 'priority')->label('Priority <span class="text-danger">*</span>', ['encode' => false])->dropDownList([
            \backend\models\PmTask::PRIORITY_HIGH   => 'High',
            \backend\models\PmTask::PRIORITY_MEDIUM => 'Medium',
            \backend\models\PmTask::PRIORITY_LOW    => 'Low',
        ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select priority…']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($task, 'assign_type')->label('Assign Type <span class="text-danger">*</span>', ['encode' => false])->dropDownList([
            \backend\models\PmTask::ASSIGN_TYPE_FULL    => 'Full',
            \backend\models\PmTask::ASSIGN_TYPE_PARTIAL => 'Partial',
        ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select Assign Type']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($task, 'expected_delivery_date')->label('Expected Delivery Date <span class="text-danger">*</span>', ['encode' => false])
            ->widget(DatePicker::class, [
                'type'          => DatePicker::TYPE_INPUT,
                'value'         => $task->expected_delivery_date ? date('Y-m-d', strtotime($task->expected_delivery_date)) : '',
                'options'       => ['placeholder' => 'YYYY-MM-DD', 'class' => 'form-control form-control-sm'],
                'pluginOptions' => [
                    'autoclose'      => true,
                    'format'         => 'yyyy-mm-dd',
                    'startDate'      => date('Y-m-d'),
                    'todayHighlight' => true,
                ],
            ]) ?>
    </div>
    <div class="col-md-3">
        <label class="form-label">Expected Hours</label>
        <?php
        $totalSecs = (int)$task->expected_time;
        $eh = intdiv($totalSecs, 3600);
        $em = intdiv($totalSecs % 3600, 60);
        ?>
        <input type="number" name="expected_hour" class="form-control form-control-sm"
               placeholder="Hours" min="0" value="<?= $task->isNewRecord ? '' : $eh ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Expected Minutes</label>
        <input type="number" name="expected_minute" class="form-control form-control-sm"
               placeholder="Minutes" min="0" max="59" value="<?= $task->isNewRecord ? '' : $em ?>">
    </div>
    <div class="col-md-6">
        <?= $form->field($task, 'parent_id')->label('Parent Task ID (leave 0 for main task)')
            ->textInput(['class' => 'form-control form-control-sm', 'placeholder' => '0']) ?>
    </div>
    <div class="col-12">
        <?= $form->field($task, 'description')
            ->textarea(['rows' => 3, 'class' => 'form-control form-control-sm', 'placeholder' => 'Describe the task in detail']) ?>
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <?= Html::submitButton($task->isNewRecord ? 'Create PM Task' : 'Update PM Task', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>

<?php ActiveForm::end(); ?>
