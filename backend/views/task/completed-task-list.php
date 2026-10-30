<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Project[] $projects */
/** @var array<int,int> $workedSecs  [task_id => worked seconds] aggregated from task_logs */

use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Completed Tasks';

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

// Batch subtask counts
$taskIds = array_column(array_map(fn($m) => ['id' => $m->id], $models), 'id');
$subtaskCounts = [];
if (!empty($taskIds)) {
    $rows = Yii::$app->db->createCommand(
        'SELECT parent_id, COUNT(*) AS cnt FROM task WHERE parent_id IN (' . implode(',', $taskIds) . ') AND status=1 GROUP BY parent_id'
    )->queryAll();
    foreach ($rows as $r) {
        $subtaskCounts[(int)$r['parent_id']] = (int)$r['cnt'];
    }
}
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.row-overrun{background:#f1f1f1!important;color:#000}
.row-overrun:hover{background:#e8e8e8!important}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
.task-nav .nav-link{color:#495057;font-size:.85rem;padding:.35rem .9rem;border-radius:6px}
.task-nav .nav-link:hover{background:#e9ecef;color:#212529}
.task-nav .nav-link.active{background:#0d6efd;color:#fff;font-weight:600}
</style>

<div class="task-completed-list">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 fw-semibold">Completed Tasks</h5>
    </div>

    <!-- Nav links -->
    <ul class="nav task-nav mb-3 bg-white border rounded px-2 py-1 shadow-sm" style="gap:4px">
        <li class="nav-item">
            <?= Html::a('Tasks', ['index'], ['class' => 'nav-link']) ?>
        </li>
        <li class="nav-item">
            <?= Html::a('Completed Task List', ['completed-task-list'], ['class' => 'nav-link active']) ?>
        </li>
    </ul>

    <!-- Filter (Yii1 parity: project, task name, assigned to/by, created by, expected/created date) -->
    <?php
    $devOptions = ArrayHelper::map($developers ?? [], 'user_id', 'developer_name');
    $hasAnyFilter = Yii::$app->request->get('task') || Yii::$app->request->get('project_id')
        || Yii::$app->request->get('assigned_to') || Yii::$app->request->get('assigned_by')
        || Yii::$app->request->get('created_by')
        || Yii::$app->request->get('expected_date') || Yii::$app->request->get('date_time');
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Project</label>
                    <?= Select2::widget([
                        'name'          => 'project_id',
                        'value'         => Yii::$app->request->get('project_id'),
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'All Projects'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Task Name</label>
                    <input type="text" name="task" value="<?= Html::encode(Yii::$app->request->get('task', '')) ?>"
                           class="form-control form-control-sm" placeholder="Search task name…">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Assigned To</label>
                    <?= Select2::widget([
                        'name'          => 'assigned_to',
                        'value'         => Yii::$app->request->get('assigned_to'),
                        'data'          => $devOptions,
                        'options'       => ['placeholder' => 'Anyone'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Assigned By</label>
                    <?= Select2::widget([
                        'name'          => 'assigned_by',
                        'value'         => Yii::$app->request->get('assigned_by'),
                        'data'          => $devOptions,
                        'options'       => ['placeholder' => 'Anyone'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Created By</label>
                    <?= Select2::widget([
                        'name'          => 'created_by',
                        'value'         => Yii::$app->request->get('created_by'),
                        'data'          => $devOptions,
                        'options'       => ['placeholder' => 'Anyone'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Expected Date</label>
                    <input type="date" name="expected_date" value="<?= Html::encode(Yii::$app->request->get('expected_date', '')) ?>"
                           class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Created Date</label>
                    <input type="date" name="date_time" value="<?= Html::encode(Yii::$app->request->get('date_time', '')) ?>"
                           class="form-control form-control-sm">
                </div>
                <div class="col-12 d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <?php if ($hasAnyFilter): ?>
                        <?= Html::a('Clear', ['completed-task-list'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:160px">Project Name</th>
                        <th style="width:220px">Task Name</th>
                        <th class="text-center">Sub Tasks</th>
                        <th class="text-center">Task Worked Time</th>
                        <th>Assigned To</th>
                        <th>Created By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No completed tasks found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $task):
                        $worked  = (int)($workedSecs[$task->id] ?? 0);
                        $overrun = $worked > 0 && (int)$task->expected_time > 0 && $worked > (int)$task->expected_time;
                    ?>
                    <tr class="<?= $overrun ? 'row-overrun' : '' ?>" <?= $overrun ? 'title="This task exceeded its expected time"' : '' ?>>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="text-truncate" style="max-width:160px" title="<?= Html::encode($task->project->title ?? '') ?>"><?= Html::encode($task->project->title ?? '—') ?></td>
                        <td class="text-truncate" style="max-width:220px" title="<?= Html::encode($task->task) ?>"><?= Html::encode($task->task) ?></td>
                        <td class="text-center">
                            <?php $cnt = $subtaskCounts[$task->id] ?? 0; ?>
                            <?= $cnt > 0 ? '<span class="badge bg-info">' . $cnt . '</span>' : '<span class="text-muted">0</span>' ?>
                        </td>
                        <td class="text-center fw-semibold <?= $worked > 0 ? ($overrun ? 'text-danger' : 'text-primary') : 'text-muted' ?>">
                            <?= $fmt($worked) ?>
                            <?= $overrun ? ' <span class="badge bg-danger ms-1" style="font-size:.65rem">Overrun</span>' : '' ?>
                        </td>
                        <td><?= Html::encode($task->assignedTo->username ?? '—') ?></td>
                        <td><?= Html::encode($task->createdBy->username ?? '—') ?></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?= Html::a('View', ['view', 'id' => $task->id], ['class' => 'btn btn-info btn-sm act-btn']) ?>
                                <?= Html::a('Task History', ['task-history', 'id' => $task->id], ['class' => 'btn btn-secondary btn-sm act-btn']) ?>
                                <button type="button"
                                        class="btn btn-dark btn-sm act-btn js-task-logs-btn"
                                        data-task-id="<?= $task->id ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#taskLogsModal">
                                    Task Logs
                                </button>
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

<!-- Task Logs Modal -->
<div class="modal fade" id="taskLogsModal" tabindex="-1" aria-labelledby="taskLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="taskLogsModalLabel">Task Logs</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="taskLogsBody">
                <div class="text-center text-muted py-4">Loading&hellip;</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$logsUrl = Url::to(['/task/task-logs']);
$this->registerJs(<<<JS
jQuery('#taskLogsModal').on('show.bs.modal', function (event) {
    var taskId = jQuery(event.relatedTarget).data('task-id');
    var body = jQuery('#taskLogsBody');
    body.html('<div class="text-center text-muted py-4">Loading&hellip;</div>');
    if (!taskId) return;
    jQuery.ajax({
        url: '{$logsUrl}',
        type: 'GET',
        data: {id: taskId},
        success: function (html) { body.html(html); },
        error: function () { body.html('<p class="text-danger text-center py-3">Failed to load logs.</p>'); }
    });
});
JS, \yii\web\View::POS_READY);
?>
