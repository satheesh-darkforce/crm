<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\ClientModel[] $clients */
/** @var int $grandTotalSecs */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$this->title = 'Daily Work Report';

// Per-row action visibility (matches Yii1: each button has its own checkAccess).
$canView   = UserAccess::checkAccess('work-report/view');
$canUpdate = UserAccess::checkAccess('work-report/update');
$canDelete = UserAccess::checkAccess('work-report/delete');

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$totalSecs = 0;
foreach ($models as $m) { $totalSecs += (int)$m->total_time; }
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<?php
$g = Yii::$app->request->get();
$hasFilter = !empty($g['developer_id']) || !empty($g['project_id']) || !empty($g['client_id'])
          || !empty($g['from_date']) || !empty($g['to_date']) || !empty($g['task']);

// Build export URL preserving the current filter set (Yii1 parity).
$exportUrl = Url::to(array_filter([
    '/work-report/export-daily-work-report',
    'from_date'    => $g['from_date']    ?? null,
    'to_date'      => $g['to_date']      ?? null,
    'developer_id' => $g['developer_id'] ?? null,
    'project_id'   => $g['project_id']   ?? null,
    'client_id'    => $g['client_id']    ?? null,
], fn($v) => $v !== null && $v !== ''));
?>
<div class="work-report-index">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Work Reports</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border" style="font-size:.82rem">
                Total Time: <?= $fmt((int) $grandTotalSecs) ?> hrs
            </span>
            <span class="badge bg-primary-subtle text-primary border" style="font-size:.82rem">
                Current Page Timing: <?= $fmt($totalSecs) ?> hrs
            </span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="toggle-search-btn"
                    data-bs-toggle="collapse" data-bs-target="#search-panel"
                    aria-expanded="<?= $hasFilter ? 'true' : 'false' ?>" aria-controls="search-panel">
                <i class="fas fa-filter me-1"></i> <span id="toggle-search-label"><?= $hasFilter ? 'Hide Search' : 'Search' ?></span>
            </button>
            <?php if (UserAccess::checkAccess('work-report/export-daily-work-report')): ?>
            <?= Html::a('<i class="fas fa-file-excel me-1"></i> Export to Excel', $exportUrl, ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if (UserAccess::checkAccess('work-report/create')): ?>
            <?= Html::a('Add Work Report', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="collapse <?= $hasFilter ? 'show' : '' ?>" id="search-panel">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3 px-3">
                <form method="get" class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm small mb-1">From Date</label>
                        <?= DatePicker::widget([
                            'name'          => 'from_date',
                            'value'         => Yii::$app->request->get('from_date'),
                            'type'          => DatePicker::TYPE_INPUT,
                            'options'       => ['id' => 'filter-from-date', 'placeholder' => 'From', 'class' => 'form-control form-control-sm'],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                        ]) ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm small mb-1">To Date</label>
                        <?= DatePicker::widget([
                            'name'          => 'to_date',
                            'value'         => Yii::$app->request->get('to_date'),
                            'type'          => DatePicker::TYPE_INPUT,
                            'options'       => ['id' => 'filter-to-date', 'placeholder' => 'To', 'class' => 'form-control form-control-sm'],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                        ]) ?>
                        <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm small mb-1">Developer</label>
                        <?= Select2::widget([
                            'name'          => 'developer_id',
                            'value'         => Yii::$app->request->get('developer_id'),
                            'data'          => ['' => 'All Developers'] + ArrayHelper::map($developers, 'id', 'developer_name'),
                            'options'       => ['placeholder' => 'All Developers'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm small mb-1">Project</label>
                        <?= Select2::widget([
                            'name'          => 'project_id',
                            'value'         => Yii::$app->request->get('project_id'),
                            'data'          => ['' => 'All Projects'] + ArrayHelper::map($projects, 'id', 'title'),
                            'options'       => ['placeholder' => 'All Projects'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm small mb-1">Client</label>
                        <?= Select2::widget([
                            'name'          => 'client_id',
                            'value'         => Yii::$app->request->get('client_id'),
                            'data'          => ['' => 'All Clients'] + ArrayHelper::map($clients, 'id', 'client_name'),
                            'options'       => ['placeholder' => 'All Clients'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm small mb-1">Task</label>
                        <input type="text" name="task" class="form-control form-control-sm"
                               value="<?= Html::encode((string) Yii::$app->request->get('task', '')) ?>"
                               placeholder="Search task description">
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button class="btn btn-primary btn-sm" id="filter-btn"><i class="fas fa-search me-1"></i>Filter</button>
                        <?php if ($hasFilter): ?>
                            <?= Html::a('<i class="fas fa-times me-1"></i>Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
(function(){
    var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');
    function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}
    function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}
    $f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});
    $t.on('changeDate change',v);
    $f.closest('form').on('submit',function(){return v();});

    var $panel = $('#search-panel'), $lbl = $('#toggle-search-label');
    $panel.on('show.bs.collapse', function(){ $lbl.text('Hide Search'); });
    $panel.on('hide.bs.collapse', function(){ $lbl.text('Search'); });
}());
</script>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Developer</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Task</th>
                        <th class="text-center">Time</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No work reports found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $wr): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <span class="fw-semibold"><?= Html::encode($wr->developer->developer_name ?? '—') ?></span>
                        </td>
                        <td class="text-muted small">
                            <?= $wr->date ? date('d M Y', strtotime($wr->date)) : '—' ?>
                        </td>
                        <td class="text-muted small"><?= Html::encode($wr->client->client_name ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($wr->project->title ?? '—') ?></td>
                        <td class="small" style="max-width:260px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= Html::encode($wr->task ?? '') ?>">
                                <?= Html::encode($wr->task ?? '—') ?>
                            </div>
                        </td>
                        <td class="text-center fw-semibold <?= $wr->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                            <?= $fmt((int)$wr->total_time) ?>
                        </td>
                        <td class="text-end">
                            <?php if ($canView || $canUpdate || $canDelete): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:140px">
                                    <?php if ($canView): ?>
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $wr->work_id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canUpdate): ?>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $wr->work_id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canDelete && ($canView || $canUpdate)): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $wr->work_id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-method' => 'post', 'data-confirm' => 'Delete this work report?',
                                    ]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
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
