<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Project $model */
/** @var \backend\models\ClientModel[] $clients */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[] $phases existing phases (update only) */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$phases = $phases ?? [];
$selectedEmployees = $model->employee_id ? json_decode($model->employee_id, true) : [];
?>

<?php $form = ActiveForm::begin(['options' => ['id' => 'pm-project-form']]); ?>

<div class="row g-3">
    <div class="col-md-8">
        <?= $form->field($model, 'title')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Enter project title']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($model, 'client_id')->widget(Select2::class, [
            'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
            'options'       => ['placeholder' => 'Select client…'],
            'pluginOptions' => ['allowClear' => true],
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($model, 'project_type')->dropDownList([
            1 => 'Fixed Price', 2 => 'Hourly', 3 => 'Retainer', 4 => 'Internal'
        ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select type…']) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($model, 'project_status')->dropDownList([
            \backend\models\Project::PROJECT_STATUS_ACTIVE    => 'Active',
            \backend\models\Project::PROJECT_STATUS_COMPLETED => 'Completed',
            \backend\models\Project::PROJECT_STATUS_ON_HOLD   => 'On Hold',
            \backend\models\Project::PROJECT_STATUS_CANCELLED => 'Cancelled',
        ], ['class' => 'form-select form-select-sm']) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($model, 'total_estimation_hours')->label('Est. Hours')
            ->input('number', ['step' => '0.5', 'min' => '0', 'class' => 'form-control form-control-sm',
                               'value' => $model->total_estimation_hours ? round($model->total_estimation_hours / 3600, 2) : '']) ?>
    </div>
    <div class="col-md-3">
        <?= $form->field($model, 'total_estimation_cost')->label('Est. Cost (₹)')
            ->input('number', ['step' => '0.01', 'min' => '0', 'class' => 'form-control form-control-sm']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($model, 'date')->label('Start Date')
            ->widget(DatePicker::class, [
                'type'          => DatePicker::TYPE_INPUT,
                'value'         => $model->date ? date('Y-m-d', strtotime($model->date)) : date('Y-m-d'),
                'options'       => ['class' => 'form-control form-control-sm'],
                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
            ]) ?>
    </div>
    <div class="col-md-4">
        <label class="form-label">Assign Developers</label>
        <?= Select2::widget([
            'name'          => 'Project[employee_id][]',
            'value'         => $selectedEmployees,
            'data'          => ArrayHelper::map($developers, 'user_id', 'developer_name'),
            'options'       => ['placeholder' => 'Select developers…', 'multiple' => true],
            'pluginOptions' => ['allowClear' => true],
        ]) ?>
    </div>
    <div class="col-12">
        <?= $form->field($model, 'description')->textarea(['rows' => 3, 'class' => 'form-control form-control-sm', 'placeholder' => 'Brief description of the project']) ?>
    </div>
</div>

<!-- Phases & Milestones section -->
<hr class="my-4">
<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0 fw-semibold">Phases &amp; Milestones</h6>
    <button type="button" class="btn btn-outline-primary btn-sm" id="add-phase-btn">+ Add Phase</button>
</div>

<?php if (!empty($phases)): ?>
<div class="card border-0 bg-light mb-3 p-2">
    <small class="text-muted fw-semibold mb-1 d-block">Existing Phases</small>
    <table class="table table-sm mb-0">
        <thead><tr><th>Phase</th><th>Milestone</th><th>Hours</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($phases as $ph): ?>
        <tr>
            <td>P<?= $ph->phase_value ?></td>
            <td>M<?= $ph->milestone_value ?></td>
            <td><?= $ph->milestone_hours ? round($ph->milestone_hours / 3600, 1) . ' h' : '—' ?></td>
            <td><?= $ph->milestone_status ? 'Done' : 'Pending' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div id="phases-container"></div>

<template id="phase-row-tpl">
    <div class="phase-group card border-0 shadow-sm mb-3 p-3" data-phase="__PHASE__">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="text-primary small">Phase __PHASE__</strong>
            <button type="button" class="btn btn-outline-secondary btn-sm add-milestone-btn"
                    data-phase="__PHASE__">+ Milestone</button>
        </div>
        <input type="hidden" name="phase_value[]" value="__PHASE__">
        <div class="milestones-container-__PHASE__"></div>
    </div>
</template>

<template id="milestone-row-tpl">
    <div class="row g-2 align-items-center mb-2 milestone-row">
        <div class="col-md-3">
            <input type="number" name="milestone_value[__PHASE__][]" class="form-control form-control-sm"
                   placeholder="Milestone #" min="1">
        </div>
        <div class="col-md-3">
            <input type="number" name="hours_value[__PHASE__][]" class="form-control form-control-sm"
                   placeholder="Hours" min="0" step="0.5">
        </div>
        <div class="col-md-3">
            <select name="status_value[__PHASE__][]" class="form-select form-select-sm">
                <option value="0">Pending</option>
                <option value="1">Done</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm remove-milestone-btn"><i class="fas fa-times"></i></button>
        </div>
    </div>
</template>

<div class="d-flex gap-2 mt-4">
    <?= Html::submitButton($model->isNewRecord ? 'Create PM Project' : 'Update PM Project', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>

<?php ActiveForm::end(); ?>

<script>
(function () {
    let phaseCounter = 0;

    document.getElementById('add-phase-btn').addEventListener('click', function () {
        phaseCounter++;
        const tpl = document.getElementById('phase-row-tpl').innerHTML
            .replaceAll('__PHASE__', phaseCounter);
        const div = document.createElement('div');
        div.innerHTML = tpl;
        document.getElementById('phases-container').appendChild(div.firstElementChild);
    });

    document.getElementById('phases-container').addEventListener('click', function (e) {
        // Add milestone
        if (e.target.classList.contains('add-milestone-btn')) {
            const phase = e.target.dataset.phase;
            const tpl = document.getElementById('milestone-row-tpl').innerHTML
                .replaceAll('__PHASE__', phase);
            const div = document.createElement('div');
            div.innerHTML = tpl;
            document.querySelector('.milestones-container-' + phase).appendChild(div.firstElementChild);
        }
        // Remove milestone
        if (e.target.classList.contains('remove-milestone-btn')) {
            e.target.closest('.milestone-row').remove();
        }
    });
})();
</script>
