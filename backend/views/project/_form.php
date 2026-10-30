<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Project $project */
/** @var \backend\models\ClientModel[] $clients */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use yii\web\View;

$projectTypeOptions  = [1 => 'Productive', 2 => 'Non Productive'];
$industryTypeOptions = [1 => 'IT', 2 => 'IOT'];
$isUpdate = !$project->isNewRecord;

// On edit: convert seconds → hours for display
if ($isUpdate && $project->total_estimation_hours) {
    $displayHours = round($project->total_estimation_hours / 3600, 2);
} else {
    $displayHours = $project->total_estimation_hours;
}

// Decode employee_id JSON for Select2 pre-selection
$selectedEmployees = [];
if (!empty($project->employee_id) && is_string($project->employee_id)) {
    $selectedEmployees = json_decode($project->employee_id, true) ?: [];
} elseif (is_array($project->employee_id)) {
    $selectedEmployees = $project->employee_id;
}

// Existing phases (only on update)
$existingPhaseRows = $existingPhases ?? [];
$statusLabels = [0 => 'Not started', 1 => 'In-progress', 2 => 'Delivered', 3 => 'Closed'];
?>

<div class="card border-0 shadow-sm">
    <div class="card-body">

        <p class="text-muted small mb-3">Fields with <span class="text-danger">*</span> are required.</p>

        <?php $form = ActiveForm::begin([
            'id'                     => 'project-form',
            'enableClientValidation' => true,
            'enableAjaxValidation'   => false,
            'validateOnSubmit'       => true,
            'validateOnBlur'         => false,
            'validateOnChange'       => false,
        ]); ?>

        <?= $form->errorSummary($project, ['class' => 'alert alert-danger']) ?>

        <!-- Row 1: Industry Type | Client | Project Title | Project Type -->
        <div class="row mb-3">
            <div class="col-sm-3">
                <?= $form->field($project, 'industry_type')->dropDownList($industryTypeOptions, [
                    'class'  => 'form-select form-select-sm',
                    'prompt' => 'Select industry...',
                ])->label('Industry Type <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
            <div class="col-sm-3">
                <?= $form->field($project, 'client_id')->widget(Select2::class, [
                    'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                    'options'       => ['placeholder' => 'Select client...'],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ])->label('Client <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
            <div class="col-sm-3">
                <?= $form->field($project, 'title')->textInput([
                    'maxlength'   => 255,
                    'class'       => 'form-control form-control-sm',
                    'placeholder' => 'Project Title',
                ])->label('Project Title <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
            <div class="col-sm-3">
                <?= $form->field($project, 'project_type')->dropDownList($projectTypeOptions, [
                    'class'  => 'form-select form-select-sm',
                    'prompt' => 'Select type...',
                ])->label('Project Type <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
        </div>

        <!-- Row 2: Estimation Cost | Estimation Hours | Description -->
        <div class="row mb-3">
            <div class="col-sm-4">
                <?= $form->field($project, 'total_estimation_cost')->textInput([
                    'type'        => 'number',
                    'min'         => 0,
                    'max'         => 9999999999,
                    'step'        => '0.01',
                    'maxlength'   => 13,
                    'class'       => 'form-control form-control-sm',
                    'placeholder' => 'Total Estimation Cost',
                    'id'          => 'total_estimation_cost',
                ])->label('Total Estimation Cost <span class="text-danger" id="cost-required-marker">*</span>', ['encode' => false]) ?>
            </div>
            <div class="col-sm-4">
                <?= $form->field($project, 'total_estimation_hours')->textInput([
                    'type'        => 'number',
                    'min'         => 0,
                    'max'         => 500000,
                    'step'        => '0.01',
                    'maxlength'   => 7,
                    'class'       => 'form-control form-control-sm',
                    'placeholder' => 'e.g. 120 (hours)',
                    'id'          => 'total_estimation_hours',
                    'value'       => $displayHours,
                ])->label('Total Estimation Hours <span class="text-danger" id="hours-required-marker">*</span> <small class="text-muted">(max 500,000)</small>', ['encode' => false]) ?>
            </div>
            <div class="col-sm-4">
                <?= $form->field($project, 'description')->textarea([
                    'rows'  => 1,
                    'class' => 'form-control form-control-sm',
                    'placeholder' => 'Description',
                ])->label('Description <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
        </div>

        <!-- Row 3: Phase inputs | Employee (only for main projects) -->
        <?php if (!$project->main_project_id): ?>
        <div class="row mb-3 align-items-end">
            <div class="col-sm-2">
                <label class="form-label">Add Phase</label>
                <input type="number" id="phase_input" class="form-control form-control-sm" min="1" max="15" step="1" placeholder="Phase">
            </div>
            <div class="col-sm-2">
                <label class="form-label">Add Milestone</label>
                <input type="number" id="milestone_input" class="form-control form-control-sm" min="1" max="15" step="1" placeholder="Milestone">
            </div>
            <div class="col-sm-2">
                <label class="form-label">Add Total Hours</label>
                <input type="number" id="milestone_hours_input" class="form-control form-control-sm" min="1" step="1" placeholder="Hours">
            </div>
            <div class="col-sm-2 d-flex gap-2">
                <button type="button" class="btn btn-success btn-sm" id="btn-add-phase">+</button>
                <button type="button" class="btn btn-secondary btn-sm" id="btn-toggle-phase"><?= !empty($existingPhaseRows) ? 'Hide Phase' : 'Show Phase' ?></button>
            </div>
            <div class="col-sm-4">
                <label class="form-label" for="project-employee-id">Employee</label>
                <?= Select2::widget([
                    'name'          => 'Project[employee_id][]',
                    'value'         => $selectedEmployees,
                    'data'          => ArrayHelper::map($developers, 'id', 'developer_name'),
                    'options'       => ['placeholder' => 'Select Employee', 'multiple' => true, 'id' => 'project-employee-id'],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ]) ?>
            </div>
        </div>

        <!-- Phase Table (existing + new rows) -->
        <div id="phase-table-section" class="mb-3" <?= empty($existingPhaseRows) ? 'style="display:none"' : '' ?>>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="phase-table">
                    <thead class="table-light">
                        <tr>
                            <th>Phase</th>
                            <th>Milestone</th>
                            <th>Total Hours</th>
                            <th>Status</th>
                            <th style="width:60px">Delete</th>
                        </tr>
                    </thead>
                    <tbody id="phase-tbody">
                        <?php foreach ($existingPhaseRows as $ep):
                            $epHours = $ep->milestone_hours ? sprintf('%02d', $ep->milestone_hours / 3600) : '0';
                        ?>
                        <tr class="existing-phase-row phaserow" data-id="<?= $ep->id ?>">
                            <td>
                                <input class="phase_data form-control form-control-sm" type="text"
                                       name="phase_value_old[]" value="<?= (int)$ep->phase_value ?>" readonly>
                            </td>
                            <td>
                                <input class="form-control form-control-sm milestone<?= (int)$ep->phase_value ?>" type="text"
                                       name="milestone_value_old[<?= (int)$ep->phase_value ?>][]" value="<?= (int)$ep->milestone_value ?>" readonly>
                            </td>
                            <td>
                                <input class="form-control form-control-sm existing-hours" type="text"
                                       name="hours_value_old[<?= (int)$ep->phase_value ?>][]" value="<?= $epHours ?>" readonly>
                            </td>
                            <td>
                                <select class="form-select form-select-sm existing-phase-status"
                                        id="milestone_status<?= (int)$ep->phase_value ?><?= (int)$ep->milestone_value ?>"
                                        data-project-id="<?= $ep->main_project_id ?>"
                                        data-phase="<?= (int)$ep->phase_value ?>"
                                        data-ms="<?= (int)$ep->milestone_value ?>"
                                        onclick="oldStatus=this.value;">
                                    <?php foreach ($statusLabels as $sv => $sl): ?>
                                        <option value="<?= $sv ?>" <?= (int)$ep->milestone_status === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm btn-delete-existing-phase"
                                        data-id="<?= $ep->id ?>">X</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="mt-3">
            <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm px-4', 'id' => 'project-submit']) ?>
        </div>

        <?php ActiveForm::end(); ?>

    </div>
</div>

<?php
$changePhaseUrl = \yii\helpers\Url::to(['change-phase-status']);
$deletePhaseUrl = \yii\helpers\Url::to(['delete-phase']);
$csrfParam      = Yii::$app->request->csrfParam;
$csrfToken      = Yii::$app->request->getCsrfToken();

$this->registerJs(<<<JS

var oldStatus = '';

// ── On load: ensure existing phases are visible ─────────────────────
if (jQuery('.existing-phase-row').length > 0) {
    jQuery('#phase-table-section').show();
    jQuery('#btn-toggle-phase').text('Hide Phase');
    setTimeout(function() {
        var el = document.getElementById('phase-table-section');
        if (el) el.scrollIntoView({behavior: 'smooth', block: 'center'});
    }, 300);
}

// ── Allow only decimal numbers ──────────────────────────────────────
jQuery('#total_estimation_cost, #total_estimation_hours').on('keypress', function(e) {
    var char = String.fromCharCode(e.which);
    if (!/[\\d.]/.test(char)) { e.preventDefault(); }
    if (char === '.' && this.value.indexOf('.') !== -1) { e.preventDefault(); }
});

// ── Project Type → Cost field handling ──────────────────────────────
// When Non-Productive (2) is selected, cost defaults to 0 and is optional.
function applyProjectTypeCostRule() {
    var t = jQuery('#project-project_type').val();
    var \$cost = jQuery('#total_estimation_cost');
    var \$marker = jQuery('#cost-required-marker');
    if (t === '2') {
        \$marker.hide();
        if (\$cost.val() === '' || \$cost.val() === null) { \$cost.val('0'); }
    } else {
        \$marker.show();
    }
}
jQuery(document).on('change', '#project-project_type', applyProjectTypeCostRule);
applyProjectTypeCostRule();

// ── Industry Type → Estimation Hours handling ───────────────────────
// When IOT (2) is selected, hours defaults to 0 and is optional.
function applyIndustryTypeHoursRule() {
    var t = jQuery('#project-industry_type').val();
    var \$hours = jQuery('#total_estimation_hours');
    var \$marker = jQuery('#hours-required-marker');
    if (t === '2') {
        \$marker.hide();
        if (\$hours.val() === '' || \$hours.val() === null) { \$hours.val('0'); }
    } else {
        \$marker.show();
    }
}
jQuery(document).on('change', '#project-industry_type', applyIndustryTypeHoursRule);
applyIndustryTypeHoursRule();

// ── Phase Table Toggle ──────────────────────────────────────────────
jQuery('#btn-toggle-phase').on('click', function() {
    var section = jQuery('#phase-table-section');
    if (section.is(':visible')) {
        section.slideUp();
        jQuery(this).text('Show Phase');
    } else {
        section.slideDown();
        jQuery(this).text('Hide Phase');
    }
    return false;
});

// ── Check table visibility ──────────────────────────────────────────
function checktable() {
    if (jQuery('.phaserow').length >= 1) {
        jQuery('#phase-table-section').show();
        jQuery('#btn-toggle-phase').show().text('Hide Phase');
    } else {
        jQuery('#phase-table-section').hide();
        jQuery('#btn-toggle-phase').text('Show Phase');
    }
}

// ── Add Phase Row ───────────────────────────────────────────────────
var phaseRowCount = 0;

jQuery('#btn-add-phase').on('click', function() {
    var phase     = jQuery('#phase_input').val();
    var milestone = jQuery('#milestone_input').val();
    var hours     = jQuery('#milestone_hours_input').val();

    if (!phase || !milestone || !hours) {
        Swal.fire({icon:'error', title:'Validation', text:'Please fill Phase, Milestone and Hours'}); return;
    }
    phase = parseInt(phase); milestone = parseInt(milestone); hours = parseFloat(hours);
    if (phase < 1 || phase > 15) {
        Swal.fire({icon:'error', title:'Validation', text:'Phase must be between 1 and 15'}); return;
    }
    if (milestone < 1 || milestone > 15) {
        Swal.fire({icon:'error', title:'Validation', text:'Milestone must be between 1 and 15'}); return;
    }
    if (hours <= 0) {
        Swal.fire({icon:'error', title:'Validation', text:'Hours must be greater than 0'}); return;
    }

    // Sequential phase validation
    var arr = [];
    jQuery('.phase_data').each(function() {
        var v = parseInt(jQuery(this).val());
        if (arr.indexOf(v) === -1) arr.push(v);
    });
    if (arr.length > 0) {
        var maxPhase = Math.max.apply(null, arr);
        if (phase > maxPhase + 1) {
            Swal.fire({icon:'error', title:'Sequential Error', text:'Please add Phase ' + (maxPhase + 1) + ' before adding Phase ' + phase});
            return;
        }
    } else if (phase !== 1) {
        Swal.fire({icon:'error', title:'Sequential Error', text:'First phase must be 1'}); return;
    }

    // Sequential milestone validation within phase
    var msArr = [];
    var msDuplicate = false;
    jQuery('.milestone' + phase).each(function() {
        var v = parseInt(jQuery(this).val());
        if (v === milestone) { msDuplicate = true; }
        if (msArr.indexOf(v) === -1) msArr.push(v);
    });
    if (msDuplicate) {
        Swal.fire({icon:'error', title:'Duplicate', text:'Milestone ' + milestone + ' already exists in Phase ' + phase}); return;
    }
    if (msArr.length > 0) {
        var maxMs = Math.max.apply(null, msArr);
        if (milestone > maxMs + 1) {
            Swal.fire({icon:'error', title:'Sequential Error', text:'Please add Milestone ' + (maxMs + 1) + ' for Phase ' + phase + ' first'});
            return;
        }
    } else if (milestone !== 1) {
        Swal.fire({icon:'error', title:'Sequential Error', text:'First milestone in Phase ' + phase + ' must be 1'}); return;
    }

    phaseRowCount++;
    var row = '<tr id="phase-row-' + phaseRowCount + '" class="new-phase-row phaserow">' +
        '<td><input class="phase_data form-control form-control-sm" type="text" name="phase_value[]" value="' + phase + '" readonly></td>' +
        '<td><input class="form-control form-control-sm milestone' + phase + '" type="text" name="milestone_value[' + phase + '][]" value="' + milestone + '" readonly></td>' +
        '<td><input class="form-control form-control-sm new-hours" type="text" name="hours_value[' + phase + '][]" value="' + hours + '" readonly></td>' +
        '<td><select name="status_value[' + phase + '][]" class="form-select form-select-sm">' +
            '<option value="0" selected>Not started</option><option value="1">In-progress</option>' +
            '<option value="2">Delivered</option><option value="3">Closed</option></select></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm btn-delete-phase" data-row="' + phaseRowCount + '">X</button></td>' +
        '</tr>';

    jQuery('#phase-tbody').append(row);
    checktable();

    jQuery('#phase_input').val('');
    jQuery('#milestone_input').val('');
    jQuery('#milestone_hours_input').val('');
    jQuery('#phase_input').focus();
});

// ── Delete new phase row ────────────────────────────────────────────
jQuery('#phase-tbody').on('click', '.btn-delete-phase', function() {
    jQuery('#phase-row-' + jQuery(this).data('row')).remove();
    checktable();
});

// ── AJAX: Change existing phase status ──────────────────────────────
jQuery('#phase-tbody').on('change', '.existing-phase-status', function() {
    var el = jQuery(this);
    var projectId = el.data('project-id');
    var phaseVal  = el.data('phase');
    var msVal     = el.data('ms');
    var statusVal = el.val();

    if (!confirm('Are you sure to change this status?')) {
        el.val(oldStatus);
        return false;
    }

    jQuery.post('$changePhaseUrl', {
        project_id: projectId, phase_value: phaseVal,
        milestone_value: msVal, status_value: statusVal,
        '$csrfParam': '$csrfToken'
    }).done(function(res) {
        if (res.success) {
            Swal.fire({icon:'success', title:'Status updated!', timer:1500, showConfirmButton:false});
        } else {
            Swal.fire({icon:'error', title:'Error', text: res.message || 'Failed to update status'});
            el.val(oldStatus);
        }
    });
});

// ── AJAX: Delete existing phase ─────────────────────────────────────
jQuery('#phase-tbody').on('click', '.btn-delete-existing-phase', function() {
    var btn = jQuery(this);
    var phaseId = btn.data('id');

    if (!confirm('Are you sure to delete this phase? Related work reports will also be deleted.')) {
        return false;
    }

    jQuery.post('$deletePhaseUrl' + '?id=' + phaseId, {
        '$csrfParam': '$csrfToken'
    }).done(function(res) {
        if (res.success) {
            btn.closest('tr').fadeOut(300, function() { jQuery(this).remove(); checktable(); });
            Swal.fire({icon:'success', title:'Deleted!', timer:1500, showConfirmButton:false});
        } else {
            Swal.fire({icon:'error', title:'Error', text: res.message || 'Failed to delete'});
        }
    });
});

// ── Form Submit Validation ──────────────────────────────────────────
jQuery('#project-form').on('beforeSubmit', function() {
    var totalHours = parseFloat(jQuery('#total_estimation_hours').val()) || 0;
    var totalCost  = parseFloat(jQuery('#total_estimation_cost').val()) || 0;

    // Range guards — match server-side limits to prevent INT(11) overflow.
    if (totalHours < 0 || totalHours > 500000) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Hours',
            text: 'Total Estimation Hours must be between 0 and 500,000.'
        });
        return false;
    }
    if (totalCost < 0 || totalCost > 9999999999) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Cost',
            text: 'Total Estimation Cost must be between 0 and 9,999,999,999.'
        });
        return false;
    }

    var projType = jQuery('#project-project_type').val();
    if (projType === '1' && totalCost <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Cost',
            text: 'Total Estimation Cost must be greater than 0 for Productive projects.'
        });
        return false;
    }

    var sum = 0;

    // Sum existing phase hours
    jQuery('.existing-hours').each(function() {
        var v = parseFloat(jQuery(this).val());
        if (!isNaN(v)) sum += v;
    });
    // Sum new phase hours
    jQuery('.new-hours').each(function() {
        var v = parseFloat(jQuery(this).val());
        if (!isNaN(v)) sum += v;
    });

    if (sum > 0 && totalHours > 0 && totalHours !== sum) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Error',
            text: 'Total Estimation Hours (' + totalHours + ') should be equal to sum of Milestone total hours (' + sum + ')'
        });
        return false;
    }

    return true;
});

JS, View::POS_READY);
?>
