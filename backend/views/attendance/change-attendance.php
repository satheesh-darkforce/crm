<?php
/** @var \yii\web\View $this */
/** @var \backend\models\DailyAttendance $model */
/** @var array $filters Monthly Attendance filters to preserve (developer_id, team, branch, ex_employee, year, month) */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$filters = $filters ?? [];
$backUrl = array_merge(['monthly-attendance'], $filters);

$this->title = 'Change Attendance';

$leaveLabel = [
    1 => 'Present',
    2 => 'Present (Half)',
    3 => 'Absent (LOP)',
    4 => 'Holiday',
    5 => 'Late',
    6 => 'Permission',
    7 => 'Leave (CL)',
    8 => 'Half Day Leave',
];

// Pre-select the per-session Late controls from the current record state:
//   office work   → 'office' (not late at all — never counted anywhere)
//   late + exempt → 'excuse' (admin marked Present, not counted for LOP)
//   late          → 'late'   (counts toward LOP)
//   otherwise     → 'ontime'
$sessionState = static function (int $isLate, int $isExempt, int $isOffice): string {
    if ($isOffice)            { return 'office'; }
    if ($isLate && $isExempt) { return 'excuse'; }
    if ($isLate)              { return 'late'; }
    return 'ontime';
};
$morningSel = $sessionState((int)$model->morning_late, (int)$model->morning_lop_exempt, (int)$model->morning_office_work);
$eveningSel = $sessionState((int)$model->evening_late, (int)$model->evening_lop_exempt, (int)$model->evening_office_work);
$lateOpts = [
    'ontime' => 'On time',
    'late'   => 'Late (count LOP)',
    'excuse' => 'Present — excuse late',
    'office' => 'Office Work (not late)',
];
?>
<div class="ui-page change-attendance">
    <div class="ui-head">
        <div>
            <h1>Change Attendance</h1>
            <div class="ui-sub"><?= Html::encode($model->developer->developer_name ?? '—') ?>
                &mdash; <?= Html::encode(date('d M Y (l)', strtotime($model->attendance_date))) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('&larr; Back', $backUrl, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <!-- Current status -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <small class="text-muted">Current Status:</small>
            <strong><?= Html::encode($leaveLabel[$model->leave_type] ?? 'Not Set') ?></strong>
            <?php if ($model->in_time): ?>
                &nbsp;|&nbsp; In: <span class="text-success"><?= date('h:i A', strtotime($model->in_time)) ?></span>
            <?php endif; ?>
            <?php if ($model->out_time): ?>
                &nbsp;|&nbsp; Out: <span class="text-danger"><?= date('h:i A', strtotime($model->out_time)) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="max-width:520px">
        <div class="card-body">
            <form method="post" action="<?= Url::to(['change-attendance', 'id' => $model->id]) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <?php foreach ($filters as $fk => $fv): ?>
                    <?= Html::hiddenInput($fk, $fv) ?>
                <?php endforeach; ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Attendance Type <span class="text-danger">*</span></label>
                    <select name="attendance" id="attendance-type" class="form-select form-select-sm" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($leaveLabel as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $model->leave_type == $val ? 'selected' : '' ?>><?= Html::encode($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Per-session controls for Late (5) -->
                <div id="late-fields" class="row g-2 mb-3" style="display:none">
                    <div class="col-12">
                        <small class="text-muted">
                            Choose <strong>Present — excuse late</strong> to forgive a session's late
                            (recorded as late, but not counted toward LOP). Choose
                            <strong>Office Work (not late)</strong> when the session was spent on official
                            work outside — it is never counted as late anywhere. The first 2 counted
                            lates each month are free.
                        </small>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Morning Check-in</label>
                        <select name="late_morning" class="form-select form-select-sm">
                            <?php foreach ($lateOpts as $v => $lbl): ?>
                                <option value="<?= $v ?>" <?= $morningSel === $v ? 'selected' : '' ?>><?= Html::encode($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Evening Check-out</label>
                        <select name="late_evening" class="form-select form-select-sm">
                            <?php foreach ($lateOpts as $v => $lbl): ?>
                                <option value="<?= $v ?>" <?= $eveningSel === $v ? 'selected' : '' ?>><?= Html::encode($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Morning/Evening fields for Half Day Leave (8) -->
                <div id="leave-fields" class="row g-2 mb-3" style="display:none">
                    <div class="col-6">
                        <label class="form-label">Morning Leave</label>
                        <select name="leave_morning" class="form-select form-select-sm">
                            <option value="0">No</option>
                            <option value="1" <?= $model->morning_leave ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Evening Leave</label>
                        <select name="leave_evening" class="form-select form-select-sm">
                            <option value="0">No</option>
                            <option value="1" <?= $model->evening_leave ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Update Attendance</button>
                    <?= Html::a('Cancel', $backUrl, ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $this->registerJs(<<<JS
function toggleAttendanceFields() {
    var v = parseInt(jQuery('#attendance-type').val());
    jQuery('#late-fields').css('display',  (v === 5) ? 'flex' : 'none');
    jQuery('#leave-fields').css('display', (v === 8) ? 'flex' : 'none');
}
jQuery('#attendance-type').on('change', toggleAttendanceFields);
toggleAttendanceFields();
JS, \yii\web\View::POS_READY); ?>
