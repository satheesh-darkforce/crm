<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var array $developerOptions */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$this->title = 'Attendance';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$fmtDuration = function(?string $inTime, ?string $outTime): string {
    if (!$inTime || !$outTime) return '—';
    $secs = strtotime($outTime) - strtotime($inTime);
    if ($secs <= 0) return '—';
    return sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
};
?>

<?php \yii\widgets\Pjax::begin(['id' => 'attendance-pjax', 'timeout' => 5000]); ?>
<div class="ui-page attendance-index">
    <div class="ui-head">
        <div>
            <h1>Attendance</h1>
            <div class="ui-sub"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <?php
                $req      = Yii::$app->request;
                $teamF    = (string) $req->get('team', '');
                $branchF  = (string) $req->get('branch', '');
                $inIpF    = (string) $req->get('in_ip', '');
                $outIpF   = (string) $req->get('out_ip', '');
                $teams    = \backend\models\Developer::$teams    ?? [1 => 'Team A', 2 => 'Team B'];
                $branches = \backend\models\Developer::$branches ?? [1 => 'Coimbatore', 2 => 'Chennai'];
                $hasFilter = $req->get('developer_id') || $req->get('from_date') || $req->get('to_date')
                    || $teamF !== '' || $branchF !== '' || $inIpF !== '' || $outIpF !== '';
            ?>
            <form method="get" id="att-filter-form" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => $req->get('developer_id'),
                        'data'          => $developerOptions,
                        'options'       => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $req->get('from_date'),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-from-date', 'placeholder' => 'From', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $req->get('to_date'),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-to-date', 'placeholder' => 'To', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                    <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                </div>
                <div class="col-md-2">
                    <select name="team" class="form-select form-select-sm">
                        <option value="">Select Team</option>
                        <?php foreach ($teams as $tv => $tl): ?>
                            <option value="<?= $tv ?>" <?= (string)$teamF === (string)$tv ? 'selected' : '' ?>><?= Html::encode($tl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="branch" class="form-select form-select-sm">
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $bv => $bl): ?>
                            <option value="<?= $bv ?>" <?= (string)$branchF === (string)$bv ? 'selected' : '' ?>><?= Html::encode($bl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" name="in_ip"  class="form-control form-control-sm" placeholder="Check In IP"  value="<?= Html::encode($inIpF) ?>">
                </div>
                <div class="col-md-2">
                    <input type="text" name="out_ip" class="form-control form-control-sm" placeholder="Check Out IP" value="<?= Html::encode($outIpF) ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" id="filter-btn">Filter</button>
                    <?php if ($hasFilter): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
<?php $this->registerJs(<<<'JS'
(function(){var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}$f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});$t.on('changeDate change',v);$f.closest('form').on('submit',function(){return v();});}());
JS, \yii\web\View::POS_END); ?>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Developer</th>
                        <th>Date</th>
                        <th>In Time</th>
                        <th>Out Time</th>
                        <th class="text-center">Duration</th>
                        <th>In IP</th>
                        <th>Out IP</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No attendance records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $att):
                        $duration = $fmtDuration($att->in_time, $att->out_time);
                        $noOut    = !$att->out_time;
                    ?>
                    <tr class="<?= $noOut ? 'table-warning' : '' ?>">
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <span class="fw-semibold"><?= Html::encode($att->developer->developer_name ?? '—') ?></span>
                        </td>
                        <td class="text-muted small">
                            <?= $att->in_time ? date('d M Y', strtotime($att->in_time)) : '—' ?>
                        </td>
                        <td class="small">
                            <?= $att->in_time ? '<span class="text-success fw-semibold">' . date('h:i A', strtotime($att->in_time)) . '</span>' : '—' ?>
                        </td>
                        <td class="small">
                            <?php if ($att->out_time): ?>
                                <span class="text-danger fw-semibold"><?= date('h:i A', strtotime($att->out_time)) ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark" style="font-size:.7rem">Still in</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-semibold <?= $duration !== '—' ? 'text-primary' : 'text-muted' ?>">
                            <?= $duration ?>
                        </td>
                        <td class="text-muted" style="font-size:.75rem;font-family:monospace">
                            <?= Html::encode($att->in_ip ?? '—') ?>
                        </td>
                        <td class="text-muted" style="font-size:.75rem;font-family:monospace">
                            <?= Html::encode($att->out_ip ?? '—') ?>
                        </td>
                        <td class="text-end">
                            <?= Html::a('<i class="fas fa-eye"></i>', ['view', 'id' => $att->id], [
                                'class' => 'btn btn-outline-secondary act-btn', 'encode' => false,
                                'title' => 'View',
                            ]) ?>
                        </td>
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
<?php
$this->registerJs(<<<'JS'
(function () {
    var form = document.getElementById('att-filter-form');
    if (!form) { return; }
    var $form = jQuery(form);
    var timer = null;
    function go() { form.submit(); }
    function debounced() { clearTimeout(timer); timer = setTimeout(go, 500); }

    // Select2 employee dropdown — submit only on a real user pick/clear
    // (binding plain 'change' would re-fire on init when a value is preselected).
    $form.find('select[name="developer_id"]').on('select2:select select2:unselect select2:clear', go);
    // Native dropdowns submit immediately on change.
    $form.find('select[name="team"], select[name="branch"]').on('change', go);
    // Free-text IP filters submit after the user pauses typing (debounced).
    $form.find('input[type="text"]').on('input', debounced);
    // Date pickers submit after a short pause (lets range validation settle).
    $form.find('#filter-from-date, #filter-to-date').on('changeDate', debounced);
})();
JS, \yii\web\View::POS_READY);
?>
