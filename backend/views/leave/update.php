<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeaveRequests $model */
/** @var \backend\models\Developer[] $developers */
/** @var bool $isAdmin */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\models\LeaveRequests;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use yii\helpers\ArrayHelper;

$this->title = 'Edit Leave Request';

$req = '<span class="text-danger">*</span>';
$minDate = $isAdmin ? date('Y-m-d', strtotime('-100 days')) : date('Y-m-d', strtotime('+1 day'));
$maxDate = date('Y-m-d', strtotime('+30 days'));
?>
<div class="ui-page leave-update">
    <div class="ui-head">
        <div>
            <h1><?= $this->title ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back',
            $isAdmin ? ['index'] : ['my-requests'],
            ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:700px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['id' => 'leave-requests-form']); ?>

            <?php if ($model->hasErrors()): ?>
            <div class="alert alert-danger py-2 mb-3">
                <?php foreach ($model->getFirstErrors() as $err): ?>
                    <div class="small"><?= Html::encode($err) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="row g-3">
                <?php if ($isAdmin): ?>
                <div class="col-md-6">
                    <label class="form-label">Employee <?= $req ?></label>
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => $model->developer_id,
                        'data'          => ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'Select Employee'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <?php endif; ?>

                <?php $isHalf = $model->isHalfDayLeave(); ?>
                <div class="col-md-6">
                    <label class="form-label">Leave Duration <?= $req ?></label>
                    <select name="duration" id="leave-duration" class="form-select form-select-sm">
                        <option value="full" <?= !$isHalf ? 'selected' : '' ?>>Full Day</option>
                        <option value="half" <?= $isHalf ? 'selected' : '' ?>>Half Day</option>
                    </select>
                </div>

                <div class="col-md-6" id="leave-session-wrap" style="<?= $isHalf ? '' : 'display:none' ?>">
                    <label class="form-label">Session <?= $req ?></label>
                    <select name="permission_session" id="leave-session" class="form-select form-select-sm">
                        <option value="1" <?= (int)$model->permission_session === LeaveRequests::SESSION_MORNING ? 'selected' : '' ?>>Morning (First Half)</option>
                        <option value="2" <?= (int)$model->permission_session === LeaveRequests::SESSION_EVENING ? 'selected' : '' ?>>Evening (Second Half)</option>
                    </select>
                </div>

                <div class="col-md-6" id="leave-days-wrap">
                    <label class="form-label">No. of Days <?= $req ?></label>
                    <?= Html::input('number', 'no_of_days', $model->no_of_days, [
                        'id'    => 'leave-days',
                        'class' => 'form-control form-control-sm',
                        'step'  => '0.5',
                        'min'   => '0.5',
                        'required' => true,
                        'onkeypress' => 'return allowDecimalNumbers(event)',
                    ]) ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Reason <?= $req ?></label>
                    <?= Html::input('text', 'reason', $model->reason, [
                        'class'     => 'form-control form-control-sm',
                        'maxlength' => 2000,
                        'required'  => true,
                    ]) ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label">From Date <?= $req ?></label>
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $model->from_date,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-from-date', 'class' => 'form-control form-control-sm', 'readonly' => true],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd',
                            'startDate' => $minDate, 'endDate' => $maxDate,
                        ],
                    ]) ?>
                </div>

                <div class="col-md-6" id="leave-todate-wrap">
                    <label class="form-label">To Date <?= $req ?></label>
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $model->to_date,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-to-date', 'class' => 'form-control form-control-sm', 'readonly' => true],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd',
                            'startDate' => $minDate, 'endDate' => $maxDate,
                        ],
                    ]) ?>
                </div>

                <div class="col-12">
                    <label class="form-label">Description</label>
                    <?= Html::textarea('description', $model->description, [
                        'class'     => 'form-control form-control-sm',
                        'rows'      => 5,
                        'maxlength' => 5000,
                    ]) ?>
                </div>

                <?php if ($isAdmin): ?>
                <div class="col-md-6">
                    <label class="form-label">Leave Type <?= $req ?></label>
                    <?php /* Permission is not offered here — it is created and edited from the
                             Permission menu, and the leave lists exclude leave_type=2. */ ?>
                    <?= Html::dropDownList('leave_type', $model->leave_type, [
                        LeaveRequests::TYPE_LEAVE  => 'Leave',
                        LeaveRequests::TYPE_ABSENT => 'Absent',
                    ], ['class' => 'form-select form-select-sm']) ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm px-4']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
<?php $this->registerJs(<<<'JS'
function allowDecimalNumbers(e) {
    var charCode = (e.which) ? e.which : e.keyCode;
    if (charCode == 46) return true;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) return false;
    return true;
}
$(function(){
    $(document).on('keydown', '#filter-from-date, #filter-to-date', function(e) { e.preventDefault(); });

    var $from = $('#filter-from-date');
    var $to   = $('#filter-to-date');

    $from.on('changeDate', function (e) {
        if (!e.date) { return; }
        $to.kvDatepicker('setStartDate', e.date);
        var toVal = $to.val();
        if (toVal && toVal < $from.val()) {
            $to.kvDatepicker('clearDates');
        }
    });

    $('#leave-requests-form').on('submit', function (e) {
        var f = $from.val(), t = $to.val();
        // Half-day leave is a single date — skip the From/To range check.
        if ($('#leave-duration').val() !== 'half' && f && t && t < f) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({icon: 'error', title: 'Invalid Date Range', text: 'To Date must be on or after From Date.'});
            } else {
                alert('To Date must be on or after From Date.');
            }
            return false;
        }
    });
});
JS, \yii\web\View::POS_END); ?>
<?php $this->registerJs(<<<JS
// Half Day reveals the Morning/Evening session picker, fixes No. of Days to 0.5,
// and hides To Date (a half-day leave is a single date).
function toggleLeaveDuration() {
    var half = jQuery('#leave-duration').val() === 'half';
    jQuery('#leave-session-wrap').css('display', half ? '' : 'none');
    jQuery('#leave-todate-wrap').css('display', half ? 'none' : '');
    if (half) {
        jQuery('#leave-days').val('0.5').prop('readonly', true);
    } else {
        jQuery('#leave-days').prop('readonly', false);
    }
}
jQuery('#leave-duration').on('change', toggleLeaveDuration);
toggleLeaveDuration();
JS, \yii\web\View::POS_READY); ?>
