<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var bool $checkedInToday */
/** @var \backend\models\Attendance|null $todayRecord */
/** @var int|null $devId */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'My Attendance';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<?php \yii\widgets\Pjax::begin(['id' => 'my-attendance-pjax', 'timeout' => 5000]); ?>
<div class="my-attendance">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">My Attendance</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?php /*
              Check In / Check Out buttons hidden per user request.
              Re-enable by replacing the `if (false)` below with `if (!empty($devId))`.
              Backing controller actions (attendance/check-in, attendance/check-out)
              are still in place — only the UI entry point on this page is disabled.
            */ ?>
            <?php if (false && !empty($devId)): ?>
                <?php if (!$checkedInToday): ?>
                    <?= Html::a('Check In', ['check-in'], [
                        'class' => 'btn btn-success btn-sm',
                        'data-confirm' => 'Are you sure you want to check in now?',
                    ]) ?>
                <?php elseif ($todayRecord && !$todayRecord->out_time): ?>
                    <?= Html::a('Check Out', ['check-out'], [
                        'class' => 'btn btn-danger btn-sm',
                        'data-confirm' => 'Are you sure you want to check out now?',
                    ]) ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php /*
      Today's check-in summary alert hidden per user request — re-enable by
      replacing `if (false)` with the original `if ($checkedInToday && $todayRecord)`.
    */ ?>
    <?php if (false && $checkedInToday && $todayRecord): ?>
    <div class="alert alert-info py-2 mb-3">
        <strong>Today's check-in:</strong>
        <?= date('h:i A', strtotime($todayRecord->in_time)) ?>
        <?php if ($todayRecord->out_time): ?>
            &mdash; <strong>Check-out:</strong> <?= date('h:i A', strtotime($todayRecord->out_time)) ?>
        <?php else: ?>
            &mdash; <span class="badge bg-warning text-dark">Still in</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <?= \kartik\date\DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => Yii::$app->request->get('from_date', ''),
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                        'options'       => ['id' => 'filter-from-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'From'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= \kartik\date\DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => Yii::$app->request->get('to_date', ''),
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                        'options'       => ['id' => 'filter-to-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'To'],
                    ]) ?>
                    <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" id="filter-btn">Filter</button>
                    <?php if (Yii::$app->request->get('from_date') || Yii::$app->request->get('to_date')): ?>
                        <?= Html::a('Clear', ['my-attendance'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
<?php
$this->registerJs(<<<'JS'
(function(){
    var $f=jQuery('#filter-from-date'),$t=jQuery('#filter-to-date'),$e=jQuery('#to-date-error'),$b=jQuery('#filter-btn');
    function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}
    function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}
    $f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});
    $t.on('changeDate change',v);
    $f.closest('form').on('submit',function(){return v();});
}());
JS, \yii\web\View::POS_READY);
?>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Date</th>
                        <th>In Time</th>
                        <th>Out Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-5">No attendance records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $att): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="text-muted small"><?= $att->in_time ? date('d-m-Y', strtotime($att->in_time)) : '-' ?></td>
                        <td class="small"><?= $att->in_time ? date('h:i A', strtotime($att->in_time)) : '-' ?></td>
                        <td class="small"><?= $att->out_time ? date('h:i A', strtotime($att->out_time)) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php \yii\widgets\Pjax::end(); ?>
