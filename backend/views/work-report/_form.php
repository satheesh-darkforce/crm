<?php
/**
 * Reusable form partial for create & update.
 *
 * @var \yii\web\View             $this
 * @var \backend\models\WorkReport $model
 * @var \backend\models\Project[]  $projects
 * @var \backend\models\Developer[] $developers  passed only on update (admin)
 * @var bool                      $isUpdate   true when editing an existing record
 */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$isUpdate   = $isUpdate   ?? false;
$developers = $developers ?? [];

$userType = Yii::$app->user->identity->usertype ?? 2;
$isAdmin  = ($userType == 1 || $userType == 4);

// Format stored seconds as HH:MM for display
$totalTimeHHMM = '';
if ($isUpdate && $model->total_time > 0) {
    $totalTimeHHMM = sprintf('%02d:%02d', intdiv((int)$model->total_time, 3600), intdiv((int)$model->total_time % 3600, 60));
}

$projectOptions   = \yii\helpers\ArrayHelper::map($projects,   'id', 'title');
$developerOptions = \yii\helpers\ArrayHelper::map($developers, 'id', 'developer_name');

// Parse stored from_time/to_time (either "HH:MM AM" 12h or "HH:MM" 24h) into [HH, MM, AMPM].
$parseTime12 = function (?string $val): array {
    $val = trim((string)$val);
    if ($val === '') return ['', '', 'AM'];
    if (preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $val, $m)) {
        return [str_pad($m[1], 2, '0', STR_PAD_LEFT), $m[2], strtoupper($m[3])];
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $val, $m)) {
        $h = (int)$m[1];
        $ap = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12; if ($h12 === 0) $h12 = 12;
        return [str_pad((string)$h12, 2, '0', STR_PAD_LEFT), $m[2], $ap];
    }
    return ['', '', 'AM'];
};
[$ftH, $ftM, $ftAP] = $parseTime12($model->from_time);
[$ttH, $ttM2, $ttAP] = $parseTime12($model->to_time);
?>

<?php $form = ActiveForm::begin([
    'id'                     => 'work-report-form',
    'method'                 => 'post',
    'enableClientValidation' => true,
    'enableAjaxValidation'   => false,
    'options'                => ['class' => ''],
]); ?>

<div class="row g-3">

    <?php if ($isAdmin && !empty($developers)): ?>
    <!-- Admin can pick (create) or reassign (update) the developer -->
    <div class="col-md-4">
        <?= $form->field($model, 'developer_id')->widget(Select2::class, [
            'data'          => $developerOptions,
            'options'       => ['placeholder' => '— Select Developer —', 'class' => 'form-select-sm'],
            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
        ])->label('Developer <span class="text-danger">*</span>', ['encode' => false]) ?>
    </div>
    <?php elseif (!$isAdmin && !$isUpdate): ?>
        <?= Html::activeHiddenInput($model, 'developer_id') ?>
    <?php endif; ?>

    <div class="col-md-3">
        <?php
        if (!$isUpdate && empty($model->date)) {
            $model->date = date('Y-m-d');
        }
        ?>
        <?= $form->field($model, 'date')->widget(DatePicker::class, [
            'type'          => DatePicker::TYPE_INPUT,
            'options'       => ['class' => 'form-control form-control-sm'],
            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => date('Y-m-d')],
        ])->label('Date <span class="text-danger">*</span>', ['encode' => false]) ?>
    </div>

    <!-- BUG-10 fix: wrap project with $form->field() for inline validation errors -->
    <div class="col-md-4">
        <?= $form->field($model, 'project_id')->widget(Select2::class, [
            'data'          => $projectOptions,
            'options'       => ['placeholder' => '— Select Project —', 'class' => 'form-select-sm'],
            // BUG-8 fix: added 'width' => '100%'
            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
        ])->label('Project <span class="text-danger">*</span>', ['encode' => false]) ?>
    </div>

    <div class="col-md-2">
        <label class="form-label">From Time</label>
        <div class="d-flex gap-1">
            <select id="wr-from-h" class="form-select form-select-sm" style="min-width:64px" title="Hour">
                <option value="">HH</option>
                <?php for ($h = 1; $h <= 12; $h++): $hh = sprintf('%02d', $h); ?>
                    <option value="<?= $hh ?>" <?= $ftH === $hh ? 'selected' : '' ?>><?= $hh ?></option>
                <?php endfor; ?>
            </select>
            <select id="wr-from-m" class="form-select form-select-sm" style="min-width:64px" title="Minute">
                <option value="">MM</option>
                <?php for ($m = 0; $m <= 59; $m++): $mm = sprintf('%02d', $m); ?>
                    <option value="<?= $mm ?>" <?= $ftM === $mm ? 'selected' : '' ?>><?= $mm ?></option>
                <?php endfor; ?>
            </select>
            <select id="wr-from-ap" class="form-select form-select-sm" style="min-width:64px" title="AM/PM">
                <option value="AM" <?= $ftAP === 'AM' ? 'selected' : '' ?>>AM</option>
                <option value="PM" <?= $ftAP === 'PM' ? 'selected' : '' ?>>PM</option>
            </select>
        </div>
        <?= Html::activeHiddenInput($model, 'from_time', ['id' => 'wr-from-time']) ?>
        <?php if ($model->hasErrors('from_time')): ?>
            <div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('from_time')) ?></div>
        <?php endif; ?>
    </div>

    <div class="col-md-2">
        <label class="form-label">To Time</label>
        <div class="d-flex gap-1">
            <select id="wr-to-h" class="form-select form-select-sm" style="min-width:64px" title="Hour">
                <option value="">HH</option>
                <?php for ($h = 1; $h <= 12; $h++): $hh = sprintf('%02d', $h); ?>
                    <option value="<?= $hh ?>" <?= $ttH === $hh ? 'selected' : '' ?>><?= $hh ?></option>
                <?php endfor; ?>
            </select>
            <select id="wr-to-m" class="form-select form-select-sm" style="min-width:64px" title="Minute">
                <option value="">MM</option>
                <?php for ($m = 0; $m <= 59; $m++): $mm = sprintf('%02d', $m); ?>
                    <option value="<?= $mm ?>" <?= $ttM2 === $mm ? 'selected' : '' ?>><?= $mm ?></option>
                <?php endfor; ?>
            </select>
            <select id="wr-to-ap" class="form-select form-select-sm" style="min-width:64px" title="AM/PM">
                <option value="AM" <?= $ttAP === 'AM' ? 'selected' : '' ?>>AM</option>
                <option value="PM" <?= $ttAP === 'PM' ? 'selected' : '' ?>>PM</option>
            </select>
        </div>
        <?= Html::activeHiddenInput($model, 'to_time', ['id' => 'wr-to-time']) ?>
        <?php if ($model->hasErrors('to_time')): ?>
            <div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('to_time')) ?></div>
        <?php endif; ?>
    </div>

    <div class="col-md-2">
        <?php
        // Display existing seconds value as HH:MM, then split into separate
        // Hours / Minutes selectors below. The hidden field is what posts.
        if (!$model->hasErrors('total_time') && $totalTimeHHMM !== '') {
            $model->total_time = $totalTimeHHMM;
        }
        $ttPreset = (string)$model->total_time;
        $ttParts  = explode(':', $ttPreset);
        $ttH      = isset($ttParts[0]) && $ttParts[0] !== '' ? str_pad((string)(int)$ttParts[0], 2, '0', STR_PAD_LEFT) : '';
        $ttM      = isset($ttParts[1]) && $ttParts[1] !== '' ? str_pad((string)(int)$ttParts[1], 2, '0', STR_PAD_LEFT) : '';
        ?>
        <label class="form-label">Total Time <span class="text-danger">*</span></label>
        <div class="d-flex gap-1">
            <select id="wr-total-hh" class="form-select form-select-sm" required title="Hours (00–23)">
                <option value="">HH</option>
                <?php for ($h = 0; $h <= 23; $h++): $hh = sprintf('%02d', $h); ?>
                    <option value="<?= $hh ?>" <?= $ttH === $hh ? 'selected' : '' ?>><?= $hh ?></option>
                <?php endfor; ?>
            </select>
            <select id="wr-total-mm" class="form-select form-select-sm" required title="Minutes (00–59)">
                <option value="">MM</option>
                <?php for ($m = 0; $m <= 59; $m++): $mm = sprintf('%02d', $m); ?>
                    <option value="<?= $mm ?>" <?= $ttM === $mm ? 'selected' : '' ?>><?= $mm ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <input type="hidden" id="wr-total-time" name="WorkReport[total_time]" value="<?= \yii\helpers\Html::encode($ttPreset) ?>">
        <div class="form-text">Pick HH and MM (24-hour, no AM/PM).</div>
        <?php if ($model->hasErrors('total_time')): ?>
            <div class="invalid-feedback d-block"><?= \yii\helpers\Html::encode($model->getFirstError('total_time')) ?></div>
        <?php endif; ?>
    </div>

    <div class="col-md-6">
        <?= $form->field($model, 'task')->textInput([
            'maxlength'   => 5000,
            'class'       => 'form-control form-control-sm',
            'placeholder' => 'Brief task description',
            'required'    => true,
        ])->label('Task <span class="text-danger">*</span>', ['encode' => false]) ?>
    </div>

    <div class="col-12">
        <?= $form->field($model, 'description')->textarea([
            'rows'        => 3,
            'class'       => 'form-control form-control-sm',
            'placeholder' => 'Additional details (optional)',
        ])->label('Description') ?>
    </div>

    <!-- BUG-9 fix: preserve task_id so the task system link is not lost on update -->
    <?php if ($isUpdate && $model->task_id): ?>
        <input type="hidden" name="WorkReport[task_id]" value="<?= (int)$model->task_id ?>">
    <?php endif; ?>
</div>

<div class="mt-3">
    <?= Html::submitButton($isUpdate ? 'Update Work Report' : 'Save Work Report', ['class' => 'btn btn-primary btn-sm']) ?>
    <?= Html::a('Cancel', [$isAdmin ? 'index' : 'my-work-reports'], ['class' => 'btn btn-secondary btn-sm ms-2']) ?>
</div>

<?php ActiveForm::end(); ?>

<?php
$this->registerJs(<<<'JS'
(function ($) {
    var $form  = $('#work-report-form');
    var $from  = $('#wr-from-time');
    var $to    = $('#wr-to-time');
    var $total = $('#wr-total-time');   // hidden HH:MM combined value
    var $hh    = $('#wr-total-hh');
    var $mm    = $('#wr-total-mm');
    var $fh    = $('#wr-from-h');
    var $fm    = $('#wr-from-m');
    var $fap   = $('#wr-from-ap');
    var $th    = $('#wr-to-h');
    var $tm    = $('#wr-to-m');
    var $tap   = $('#wr-to-ap');
    if (!$form.length || !$total.length) return;

    function hhmmToMins(val) {
        if (!val) return null;
        var p = String(val).split(':');
        if (p.length < 2) return null;
        var h = parseInt(p[0], 10);
        var m = parseInt(p[1], 10);
        if (isNaN(h) || isNaN(m) || h < 0 || m < 0 || m > 59) return null;
        return h * 60 + m;
    }

    // Convert "HH:MM AM/PM" or 24h "HH:MM" to minutes-since-midnight.
    function timeStrToMins(val) {
        if (!val) return null;
        val = String(val).trim();
        var m12 = val.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
        if (m12) {
            var h = parseInt(m12[1], 10) % 12;
            if (m12[3].toUpperCase() === 'PM') h += 12;
            return h * 60 + parseInt(m12[2], 10);
        }
        return hhmmToMins(val);
    }

    function syncFrom() {
        var h = $fh.val(), m = $fm.val(), ap = $fap.val();
        $from.val((h && m) ? (h + ':' + m + ' ' + ap) : '');
    }
    function syncTo() {
        var h = $th.val(), m = $tm.val(), ap = $tap.val();
        $to.val((h && m) ? (h + ':' + m + ' ' + ap) : '');
    }
    $fh.add($fm).add($fap).on('change', syncFrom);
    $th.add($tm).add($tap).on('change', syncTo);
    syncFrom();
    syncTo();

    // Combine the two selectors into the hidden HH:MM field on every change.
    function syncTotal() {
        var h = $hh.val();
        var m = $mm.val();
        $total.val((h !== '' && m !== '') ? (h + ':' + m) : '');
    }
    $hh.on('change', syncTotal);
    $mm.on('change', syncTotal);
    syncTotal();

    // Validate at submit-time: total_time must fit inside the From→To window.
    $form.on('submit', function (e) {
        syncFrom();
        syncTo();
        syncTotal();
        var totalMins = hhmmToMins($total.val());
        if (totalMins === null || totalMins < 1) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Invalid Total Time', text: 'Please pick Hours and Minutes for Total Time (at least one minute).' });
            } else {
                alert('Please pick Hours and Minutes for Total Time (at least one minute).');
            }
            ($hh.val() === '' ? $hh : $mm).focus();
            return false;
        }

        var fromMins = $from && $from.length ? timeStrToMins($from.val()) : null;
        var toMins   = $to   && $to.length   ? timeStrToMins($to.val())   : null;
        if (fromMins !== null && toMins !== null) {
            if (toMins <= fromMins) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Invalid Time Range', text: 'To Time must be later than From Time.' });
                } else {
                    alert('To Time must be later than From Time.');
                }
                $to.focus();
                return false;
            }
            var windowMins = toMins - fromMins;
            if (totalMins > windowMins) {
                e.preventDefault();
                var hh = Math.floor(windowMins / 60);
                var mm = windowMins % 60;
                var winLabel = (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Total Time Too Large',
                        text: 'Total Time cannot exceed the window between From (' + $from.val() + ') and To (' + $to.val() + ') — max ' + winLabel + '.'
                    });
                } else {
                    alert('Total Time cannot exceed ' + winLabel + ' (the From–To window).');
                }
                $hh.focus();
                return false;
            }
        }
    });
})(jQuery);
JS, \yii\web\View::POS_READY);
?>
