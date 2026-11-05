<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task $task */
/** @var array $projects    id => title */
/** @var array $developers  user_id => name */

use backend\models\Task;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$secs    = (int)$task->expected_time;
$hours   = Yii::$app->request->post('expected_hours', $secs ? intdiv($secs, 3600) : '');
$minutes = Yii::$app->request->post('expected_minutes', $secs ? intdiv($secs % 3600, 60) : '');
$hmError = $task->getFirstError('expected_time');

// Keep the current (possibly inactive) project / assignee selectable on edit.
if ($task->project_id && !isset($projects[$task->project_id])) {
    $projects[$task->project_id] = ($task->project->title ?? 'Project #' . $task->project_id) . ' (inactive)';
}
if ($task->assigned_to && !isset($developers[$task->assigned_to])) {
    $developers[$task->assigned_to] = ($task->assignedTo->username ?? 'User #' . $task->assigned_to) . ' (inactive)';
}
$in = fn(string $placeholder, array $extra = []) => $extra + ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder];
?>
<?php $form = ActiveForm::begin(['id' => 'task-form', 'options' => ['class' => 'ui-form']]); ?>
<?= $form->errorSummary($task, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
<p class="ui-req-note">Fields marked <b>*</b> are required.</p>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Task</div>
    <div class="card-body row g-3">
        <div class="col-lg-6"><?= $form->field($task, 'task')->textInput($in('e.g. Build the login page', ['maxlength' => 255]))->label('Task name') ?></div>
        <div class="col-lg-6"><?= $form->field($task, 'project_id')->widget(Select2::class, [
            'data' => $projects, 'options' => ['placeholder' => 'Select project'], 'size' => Select2::SMALL,
            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
        ]) ?></div>
        <div class="col-12"><?= $form->field($task, 'description')->textarea($in('What needs to be done, links, acceptance notes', ['rows' => 3, 'maxlength' => 10000])) ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Assignment &amp; schedule</div>
    <div class="card-body row g-3">
        <div class="col-lg-4 col-md-6"><?= $form->field($task, 'assigned_to')->widget(Select2::class, [
            'data' => $developers, 'options' => ['placeholder' => 'Select employee'], 'size' => Select2::SMALL,
            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
        ])->hint($task->isNewRecord ? 'Assigning to yourself skips the accept step.' : '') ?></div>
        <div class="col-lg-2 col-md-3 col-6"><?= $form->field($task, 'priority')->dropDownList(Task::priorityOptions(), ['class' => 'form-select form-select-sm']) ?></div>
        <div class="col-lg-2 col-md-3 col-6"><?= $form->field($task, 'assign_type')->dropDownList(Task::assignTypeOptions(), ['class' => 'form-select form-select-sm']) ?></div>
        <div class="col-lg-2 col-md-6"><?= $form->field($task, 'expected_delivery_date')->widget(DatePicker::class, [
            'type' => DatePicker::TYPE_INPUT,
            'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true] + ($task->isNewRecord ? ['startDate' => date('Y-m-d')] : []),
        ])->label('Deadline') ?></div>
        <div class="col-lg-2 col-md-6">
            <div class="mb-3 required">
                <label class="form-label" for="expected_hours">Work required</label>
                <div class="hm-pair">
                    <input type="number" id="expected_hours" name="expected_hours" min="0" max="1000" step="1" value="<?= Html::encode((string)$hours) ?>"
                           class="form-control form-control-sm <?= $hmError ? 'is-invalid' : '' ?>" placeholder="0" aria-label="Hours"><span>h</span>
                    <input type="number" id="expected_minutes" name="expected_minutes" min="0" max="59" step="1" value="<?= Html::encode((string)$minutes) ?>"
                           class="form-control form-control-sm <?= $hmError ? 'is-invalid' : '' ?>" placeholder="0" aria-label="Minutes"><span>m</span>
                </div>
                <?php if ($hmError): ?><div class="invalid-feedback d-block"><?= Html::encode($hmError) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="ui-form-actions">
    <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . ($task->isNewRecord ? 'Create Task' : 'Update Task'), ['class' => 'btn btn-primary btn-sm px-4']) ?>
    <?= Html::a('Cancel', $task->isNewRecord ? ['assigned-tasks'] : ['view', 'id' => $task->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>
<?php ActiveForm::end(); ?>
