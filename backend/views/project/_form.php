<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Project $project */
/** @var \backend\models\ClientModel[] $clients */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[] $existingPhases */

use backend\models\Project;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

$isUpdate       = !$project->isNewRecord;
$existingPhases = $existingPhases ?? [];
$msOptions      = Project::milestoneStatusOptions();

// Legacy (Yii1) projects may have no estimate; those stay optional until filled (see Project::rules()).
$legacyEmpty = fn(string $attr) => $isUpdate && (float)$project->getOldAttribute($attr) <= 0;

// Selected employees (JSON list of developer ids, Yii1 format).
$selectedEmployees = is_string($project->employee_id) ? (json_decode($project->employee_id, true) ?: []) : (array)$project->employee_id;

$in = fn(string $placeholder, array $extra = []) => $extra + ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder];
?>
<?php $form = ActiveForm::begin([
    'id' => 'project-form',
    'options' => ['class' => 'ui-form', 'data-milestone-statuses' => Json::encode($msOptions)],
    'validateOnBlur' => false,
    'validateOnChange' => false,
]); ?>

<?= $form->errorSummary($project, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
<p class="ui-req-note">Fields marked <b>*</b> are required.</p>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Project details</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6"><?= $form->field($project, 'industry_type')->dropDownList(Project::industryOptions(), ['class' => 'form-select form-select-sm', 'prompt' => 'Select industry…'])
            ->hint('IOT projects can skip estimation hours.') ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($project, 'client_id')->widget(Select2::class, [
            'data' => ArrayHelper::map($clients, 'id', 'client_name') + ($project->client_id && !in_array($project->client_id, ArrayHelper::getColumn($clients, 'id'))
                ? [$project->client_id => ($project->client->client_name ?? 'Client #' . $project->client_id) . ' (inactive)'] : []),
            'options' => ['placeholder' => 'Select client…'],
            'size' => Select2::SMALL,
            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
        ]) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($project, 'title')->textInput($in('e.g. Acme e-commerce website', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($project, 'project_type')->dropDownList(Project::typeOptions(), ['class' => 'form-select form-select-sm', 'prompt' => 'Select type…'])
            ->hint('Non Productive projects can skip the cost.') ?></div>
        <div class="col-lg-3 col-md-6">
            <?= $form->field($project, 'total_estimation_cost')->textInput($in('e.g. 150000', [
                'id' => 'total_estimation_cost', 'inputmode' => 'decimal', 'maxlength' => 13,
                'data-legacy-empty' => $legacyEmpty('total_estimation_cost') ? 1 : null,
            ]))->label('Estimation Cost (₹)') ?>
        </div>
        <div class="col-lg-3 col-md-6">
            <?= $form->field($project, 'total_estimation_hours')->textInput($in('e.g. 120', [
                'id' => 'total_estimation_hours', 'inputmode' => 'decimal', 'maxlength' => 9,
                'value' => $project->getEstimationHoursInput(),
                'data-legacy-empty' => $legacyEmpty('total_estimation_hours') ? 1 : null,
            ]))->label('Estimation Hours')->hint('In hours, up to ' . number_format(Project::MAX_ESTIMATION_HOURS) . '.') ?>
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="project-employee-id">Employees</label>
            <?= Select2::widget([
                'name' => 'Project[employee_id][]',
                'value' => $selectedEmployees,
                'data' => ArrayHelper::map($developers, 'id', 'developer_name'),
                'options' => ['placeholder' => 'Select employees', 'multiple' => true, 'id' => 'project-employee-id'],
                'size' => Select2::SMALL,
                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
            ]) ?>
        </div>
        <div class="col-12"><?= $form->field($project, 'description')->textarea($in('What the project covers', ['rows' => 3])) ?></div>
    </div>
</div>

<?php if (!$project->main_project_id): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">
        <span>Phases &amp; milestones <span class="ui-section-meta">(optional)</span></span>
        <span class="phase-sum ui-section-meta">Milestone hours <span id="phase-sum">0</span> of <span id="phase-total">—</span></span>
    </div>
    <div class="card-body">
        <div class="phase-builder row g-2 align-items-end mb-3">
            <div class="col-4 col-md-2">
                <label class="form-label mb-1" for="phase_input">Phase</label>
                <input type="number" id="phase_input" class="form-control form-control-sm" min="1" max="15" step="1" placeholder="1–15">
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label mb-1" for="milestone_input">Milestone</label>
                <input type="number" id="milestone_input" class="form-control form-control-sm" min="1" max="15" step="1" placeholder="1–15">
            </div>
            <div class="col-4 col-md-2">
                <label class="form-label mb-1" for="milestone_hours_input">Hours</label>
                <input type="number" id="milestone_hours_input" class="form-control form-control-sm" min="0.01" step="0.01" placeholder="e.g. 40">
            </div>
            <div class="col-12 col-md-3">
                <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btn-add-phase"><i class="fas fa-plus me-1"></i>Add milestone</button>
            </div>
            <div class="col-12 col-md-3 small text-muted">Add phases in order (1, 2, …); milestones in each phase too.</div>
        </div>

        <div id="phase-empty" class="text-muted small" <?= $existingPhases ? 'style="display:none"' : '' ?>>No milestones yet. With milestones, Estimation Hours must equal their total.</div>
        <div id="phase-table-section" class="table-responsive" <?= $existingPhases ? '' : 'style="display:none"' ?>>
            <table class="table list-table ui-table stack mb-0" id="phase-table">
                <thead class="table-light">
                <tr><th>Phase</th><th>Milestone</th><th>Hours</th><th>Status</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody id="phase-tbody">
                <?php foreach ($existingPhases as $ep): ?>
                    <?php $hrs = round((int)$ep->milestone_hours / 3600, 2); ?>
                    <tr data-phase="<?= (int)$ep->phase_value ?>" data-milestone="<?= (int)$ep->milestone_value ?>">
                        <td data-label="Phase"><?= (int)$ep->phase_value ?></td>
                        <td data-label="Milestone"><?= (int)$ep->milestone_value ?></td>
                        <td data-label="Hours"><?= $hrs ?><input type="hidden" class="js-phase-hours" value="<?= $hrs ?>"></td>
                        <td data-label="Status">
                            <?= Html::dropDownList('', (int)$ep->milestone_status, $msOptions, [
                                'class' => 'form-select form-select-sm js-phase-status',
                                'data-project' => $ep->main_project_id, 'data-phase' => (int)$ep->phase_value, 'data-milestone' => (int)$ep->milestone_value,
                                'aria-label' => 'Status for phase ' . (int)$ep->phase_value . ' milestone ' . (int)$ep->milestone_value,
                            ]) ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm js-delete-phase" data-id="<?= $ep->id ?>" aria-label="Delete milestone"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="small text-muted mt-2 mb-0">Saved milestones change immediately; new ones are saved with the project.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="ui-form-actions">
    <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . ($isUpdate ? 'Update Project' : 'Create Project'), ['class' => 'btn btn-primary btn-sm px-4']) ?>
    <?= Html::a('Cancel', $isUpdate ? ['view', 'id' => $project->id] : ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>

<?php ActiveForm::end(); ?>
