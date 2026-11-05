<?php
/**
 * Work report form (create & update). Time pickers are wired in tasks.js.
 *
 * @var \yii\web\View              $this
 * @var \backend\models\WorkReport $model
 * @var \backend\models\Project[]  $projects
 * @var \backend\models\Developer[] $developers  admins only
 * @var bool                       $isUpdate
 */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$isUpdate   = $isUpdate ?? false;
$developers = $developers ?? [];
$userType   = Yii::$app->user->identity->usertype ?? 2;
$isAdmin    = ($userType == 1 || $userType == 4);

$projectOptions   = ArrayHelper::map($projects, 'id', 'title');
$developerOptions = ArrayHelper::map($developers, 'id', 'developer_name');
// Keep the report's current project selectable even if it is no longer in progress.
if ($model->project_id && !isset($projectOptions[$model->project_id])) {
    $projectOptions[$model->project_id] = $model->project->title ?? 'Project #' . $model->project_id;
}

// Stored "HH:MM AM" (Yii1) or 24h "HH:MM" → [HH, MM, AM|PM] for the pickers.
$parseTime12 = function (?string $val): array {
    $val = trim((string)$val);
    if (preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $val, $m)) {
        return [str_pad($m[1], 2, '0', STR_PAD_LEFT), $m[2], strtoupper($m[3])];
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $val, $m)) {
        $h = (int)$m[1];
        return [str_pad((string)(($h % 12) ?: 12), 2, '0', STR_PAD_LEFT), $m[2], $h >= 12 ? 'PM' : 'AM'];
    }
    return ['', '', 'AM'];
};
[$ftH, $ftM, $ftAP] = $parseTime12($model->from_time);
[$ttH, $ttM, $ttAP] = $parseTime12($model->to_time);

// Total time: seconds → HH / MM (a re-shown invalid "HH:MM" entry is kept as typed).
$tt = (string)$model->total_time;
if (ctype_digit($tt) && (int)$tt > 0) {
    [$totH, $totM] = [sprintf('%02d', intdiv((int)$tt, 3600)), sprintf('%02d', intdiv((int)$tt % 3600, 60))];
} else {
    [$totH, $totM] = ['', ''];
}
$hours   = array_combine($r = array_map(fn($h) => sprintf('%02d', $h), range(1, 12)), $r);
$minutes = array_combine($r = array_map(fn($m) => sprintf('%02d', $m), range(0, 59)), $r);
$pickTime = function (string $prefix, string $h, string $m, string $ap, string $label) use ($hours, $minutes) {
    return '<label class="form-label" for="wr-' . $prefix . '-h">' . $label . '</label><div class="d-flex gap-1">'
        . Html::dropDownList('', $h, $hours, ['id' => "wr-$prefix-h", 'class' => 'form-select form-select-sm', 'prompt' => 'HH', 'aria-label' => "$label hour"])
        . Html::dropDownList('', $m, $minutes, ['id' => "wr-$prefix-m", 'class' => 'form-select form-select-sm', 'prompt' => 'MM', 'aria-label' => "$label minute"])
        . Html::dropDownList('', $ap, ['AM' => 'AM', 'PM' => 'PM'], ['id' => "wr-$prefix-ap", 'class' => 'form-select form-select-sm', 'aria-label' => "$label AM or PM"])
        . '</div>';
};
?>
<?php $form = ActiveForm::begin(['id' => 'work-report-form', 'options' => ['class' => 'ui-form']]); ?>
<?= $form->errorSummary($model, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
<p class="ui-req-note">Fields marked <b>*</b> are required.</p>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">What you worked on</div>
    <div class="card-body row g-3">
        <?php if ($isAdmin && $developerOptions): ?>
            <div class="col-lg-4 col-md-6"><?= $form->field($model, 'developer_id')->widget(Select2::class, [
                'data' => $developerOptions, 'options' => ['placeholder' => 'Select employee'], 'size' => Select2::SMALL,
                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
            ])->label('Employee') ?></div>
        <?php elseif (!$isAdmin && !$isUpdate): ?>
            <?= Html::activeHiddenInput($model, 'developer_id') ?>
        <?php endif; ?>
        <div class="col-lg-3 col-md-6"><?= $form->field($model, 'date')->widget(DatePicker::class, [
            'type' => DatePicker::TYPE_INPUT,
            'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => date('Y-m-d'), 'todayHighlight' => true],
        ]) ?></div>
        <div class="col-lg-5 col-md-12"><?= $form->field($model, 'project_id')->widget(Select2::class, [
            'data' => $projectOptions, 'options' => ['placeholder' => 'Select project'], 'size' => Select2::SMALL,
            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
        ])->hint($isUpdate ? '' : 'Only projects in progress are listed.') ?></div>
        <div class="col-12"><?= $form->field($model, 'task')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 5000, 'placeholder' => 'e.g. Fixed checkout bug and deployed']) ?></div>
        <div class="col-12"><?= $form->field($model, 'description')->textarea(['class' => 'form-control form-control-sm', 'rows' => 3, 'placeholder' => 'More detail (optional)']) ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Time</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6">
            <?= $pickTime('from', $ftH, $ftM, $ftAP, 'From') ?>
            <?= Html::activeHiddenInput($model, 'from_time', ['id' => 'wr-from-time']) ?>
            <?php if ($model->hasErrors('from_time')): ?><div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('from_time')) ?></div><?php endif; ?>
        </div>
        <div class="col-lg-3 col-md-6">
            <?= $pickTime('to', $ttH, $ttM, $ttAP, 'To') ?>
            <?= Html::activeHiddenInput($model, 'to_time', ['id' => 'wr-to-time']) ?>
            <?php if ($model->hasErrors('to_time')): ?><div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('to_time')) ?></div><?php endif; ?>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="required">
                <label class="form-label" for="wr-total-hh">Total time</label>
                <div class="d-flex gap-1">
                    <?= Html::dropDownList('', $totH, array_combine($r = array_map(fn($h) => sprintf('%02d', $h), range(0, 23)), $r),
                        ['id' => 'wr-total-hh', 'class' => 'form-select form-select-sm' . ($model->hasErrors('total_time') ? ' is-invalid' : ''), 'prompt' => 'HH', 'aria-label' => 'Hours']) ?>
                    <?= Html::dropDownList('', $totM, $minutes,
                        ['id' => 'wr-total-mm', 'class' => 'form-select form-select-sm' . ($model->hasErrors('total_time') ? ' is-invalid' : ''), 'prompt' => 'MM', 'aria-label' => 'Minutes']) ?>
                </div>
                <input type="hidden" id="wr-total-time" name="WorkReport[total_time]" value="<?= $totH !== '' ? Html::encode("$totH:$totM") : '' ?>">
                <?php if ($model->hasErrors('total_time')): ?>
                    <div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('total_time')) ?></div>
                <?php else: ?>
                    <div class="form-text">Hours and minutes actually worked (24-hour).</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 small text-muted align-self-center">From and To are optional; when both are set, the total must fit between them.</div>
    </div>
</div>

<?php if ($isUpdate && $model->task_id): ?>
    <input type="hidden" name="WorkReport[task_id]" value="<?= (int)$model->task_id ?>">
<?php endif; ?>

<div class="ui-form-actions">
    <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . ($isUpdate ? 'Update Work Report' : 'Save Work Report'), ['class' => 'btn btn-primary btn-sm px-4']) ?>
    <?= Html::a('Cancel', [UserAccess::checkAccess('work-report/index') ? 'index' : 'my-work-reports'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>
<?php ActiveForm::end(); ?>
