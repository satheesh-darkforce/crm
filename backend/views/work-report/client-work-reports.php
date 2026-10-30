<?php
/**
 * Client Work Reports — work reports filtered for the logged-in client.
 *
 * @var \yii\web\View                $this
 * @var \yii\data\ActiveDataProvider $dataProvider
 * @var \backend\models\Project[]     $projects
 * @var \backend\models\Developer[]   $developers
 * @var int                          $totalMins   sum of total_time (minutes) for current page query
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Work Reports';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

// Page-level total
$pageMins = 0;
foreach ($models as $wr) { $pageMins += (int)$wr->total_time; }
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="client-work-reports">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Daily Work Reports</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <?php if (!empty($projects)): ?>
                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p->id ?>" <?= Yii::$app->request->get('project_id') == $p->id ? 'selected' : '' ?>>
                                <?= Html::encode($p->title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (!empty($developers)): ?>
                <div class="col-md-3">
                    <select name="developer_id" class="form-select form-select-sm">
                        <option value="">All Developers</option>
                        <?php foreach ($developers as $d): ?>
                            <option value="<?= $d->id ?>" <?= Yii::$app->request->get('developer_id') == $d->id ? 'selected' : '' ?>>
                                <?= Html::encode($d->developer_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
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
                    <button class="btn btn-secondary btn-sm" type="submit" id="filter-btn">Filter</button>
                    <?php if (Yii::$app->request->get('from_date') || Yii::$app->request->get('to_date') || Yii::$app->request->get('project_id') || Yii::$app->request->get('developer_id')): ?>
                        <?= Html::a('Clear', ['client-work-reports'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
                <?php if ($pageMins > 0): ?>
                <div class="col-auto ms-auto">
                    <span class="badge bg-primary-subtle text-primary border" style="font-size:.82rem">
                        Page total: <?= $fmt($pageMins) ?> hrs
                    </span>
                </div>
                <?php endif; ?>
            </form>
<script>
(function(){var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}$f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});$t.on('changeDate change',v);$f.closest('form').on('submit',function(){return v();});}());
</script>
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
                        <th>Developer</th>
                        <th>Project</th>
                        <th>Task</th>
                        <th class="text-center">Time</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No work reports found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $wr): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="small text-muted">
                            <?= $wr->date ? date('d M Y', strtotime($wr->date)) : '—' ?>
                        </td>
                        <td class="fw-semibold small">
                            <?= Html::encode($wr->developer->developer_name ?? '—') ?>
                        </td>
                        <td class="small"><?= Html::encode($wr->project->title ?? '—') ?></td>
                        <td class="small" style="max-width:280px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                 title="<?= Html::encode($wr->task ?? '') ?>">
                                <?= Html::encode($wr->task ?? '—') ?>
                            </div>
                        </td>
                        <td class="text-center fw-semibold <?= $wr->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                            <?= $fmt((int)$wr->total_time) ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:120px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $wr->work_id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?>
            </small>
            <?= LinkPager::widget([
                'pagination'     => $pagination,
                'options'        => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»',
                'prevPageLabel'  => '‹', 'nextPageLabel'  => '›',
                'maxButtonCount' => 7,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
