<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Project[] $projects */
/** @var bool $isDeveloper */
/** @var int $activeTaskId Task ID with an open timer (0 if none) */

use kartik\date\DatePicker;
use kartik\select2\Select2;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'My Tasks';

$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'primary', 4 => 'warning', 5 => 'success', 6 => 'dark'];
$statusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Re Open', 5 => 'Completed', 6 => 'Re Assigned'];
$filterStatusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Re Open']; // completed not listed

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

// Subtask counts (batch query to avoid N+1)
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

// Developer list for Re-Assign / Create Subtask modals.
// Yii1 parity: dropdown shows the developer's full name, keyed by user_id.
$developers = \backend\models\Developer::find()
    ->where(['status' => 1])
    ->orderBy('developer_name')
    ->all();
$devData = ArrayHelper::map($developers, 'user_id', 'developer_name');
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
.task-nav .nav-link{color:#495057;font-size:.85rem;padding:.35rem .9rem;border-radius:6px}
.task-nav .nav-link:hover{background:#e9ecef;color:#212529}
.task-nav .nav-link.active{background:#0d6efd;color:#fff;font-weight:600}
</style>

<div class="task-index">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
    </div>

    <!-- Nav links -->
    <ul class="nav task-nav mb-3 bg-white border rounded px-2 py-1 shadow-sm" style="gap:4px">
        <li class="nav-item">
            <?= Html::a('My Tasks', ['index'], ['class' => 'nav-link active']) ?>
        </li>
        <li class="nav-item">
            <?= Html::a('Completed Task List', ['completed-task-list'], ['class' => 'nav-link']) ?>
        </li>
    </ul>

    <!-- Filter (Yii1 parity: project, task name, status, assigned-to/by, created-by, expected/created date) -->
    <?php
    $devOptions = $devData;
    $hasAnyFilter = Yii::$app->request->get('task') || Yii::$app->request->get('project_id')
        || Yii::$app->request->get('task_status') || Yii::$app->request->get('assigned_to')
        || Yii::$app->request->get('assigned_by') || Yii::$app->request->get('created_by')
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
                    <label class="form-label small mb-1">Status</label>
                    <?= Select2::widget([
                        'name'          => 'task_status',
                        'value'         => Yii::$app->request->get('task_status'),
                        'data'          => $filterStatusLabel,
                        'options'       => ['placeholder' => 'All Statuses'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
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
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:160px">Project</th>
                        <th style="width:220px">Task Name</th>
                        <th class="text-center">Sub Tasks</th>
                        <th class="text-center">Expected Time</th>
                        <th class="text-center">Total Time</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Created By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-5">No tasks found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $task):
                        $st       = (int)$task->task_status;
                        $subCount = $subtaskCounts[$task->id] ?? 0;
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="text-truncate" style="max-width:160px" title="<?= Html::encode($task->project->title ?? '') ?>"><?= Html::encode($task->project->title ?? '—') ?></td>
                        <td class="text-truncate" style="max-width:220px" title="<?= Html::encode($task->task) ?>">
                            <?php if (!(int)$task->is_read): ?>
                                <span class="badge bg-danger me-1" title="Unread">New</span>
                            <?php endif; ?>
                            <?= Html::encode($task->task) ?>
                        </td>
                        <td class="text-center">
                            <?php if ($subCount > 0): ?>
                                <span class="badge bg-info"><?= $subCount ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-muted small"><?= $fmt((int)$task->expected_time) ?></td>
                        <td class="text-center fw-semibold <?= (int)$task->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                            <?= $fmt((int)$task->total_time) ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>">
                                <?= $statusLabel[$st] ?? '—' ?>
                            </span>
                        </td>
                        <td><?= Html::encode($task->assignedTo->username ?? '—') ?></td>
                        <td><?= Html::encode($task->createdBy->username ?? '—') ?></td>
                        <td class="text-center">
                            <?php $isRunning = $isDeveloper && $activeTaskId > 0 && $activeTaskId === (int)$task->id; ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions"
                                        style="padding:.2rem .45rem;font-size:.78rem;line-height:1.3">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:180px">
                                    <li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['view', 'id' => $task->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-clock-rotate-left me-2"></i>Task History', ['task-history', 'id' => $task->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>

                                    <?php if ($isDeveloper && (int)$task->assigned_to === (int)Yii::$app->user->id): ?>
                                        <?php if ($isRunning && UserAccess::checkAccess('task/task-stop')): ?>
                                        <li><button type="button" class="dropdown-item text-warning js-task-stop-btn" data-task-id="<?= $task->id ?>"><i class="fas fa-pause me-2"></i>Stop</button></li>
                                        <?php elseif ($st === 3 && UserAccess::checkAccess('task/task-start')): ?>
                                        <li><button type="button" class="dropdown-item text-primary js-task-start-btn" data-task-id="<?= $task->id ?>"><i class="fas fa-play me-2"></i>Resume</button></li>
                                        <?php elseif (($st === 2 || $st === 4) && UserAccess::checkAccess('task/task-start')): ?>
                                        <li><button type="button" class="dropdown-item text-primary js-task-start-btn" data-task-id="<?= $task->id ?>"><i class="fas fa-play me-2"></i>Start</button></li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($st !== 5 && !$isRunning && UserAccess::checkAccess('task/complete-task')): ?>
                                    <li><button type="button" class="dropdown-item text-success js-complete-btn" data-task-id="<?= $task->id ?>"><i class="fas fa-check me-2"></i>Complete</button></li>
                                    <?php endif; ?>

                                    <li><button type="button" class="dropdown-item js-task-logs-btn" data-task-id="<?= $task->id ?>" data-bs-toggle="modal" data-bs-target="#taskLogsModal"><i class="fas fa-list me-2"></i>Task Logs</button></li>

                                    <li><button type="button" class="dropdown-item js-create-subtask" data-taskid="<?= $task->id ?>"><i class="fas fa-plus me-2"></i>Subtask</button></li>

                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-warning js-reassign" href="#" data-taskid="<?= $task->id ?>"><i class="fas fa-rotate me-2"></i>Re-Assign</a></li>
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
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Full Re-Assign Modal -->
<div class="modal fade" id="reassignModal" tabindex="-1" aria-labelledby="reassignModalLabel" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reassignModalLabel">Re-Assign Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= Url::to(['full-reassign-task']) ?>">
                <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                <input type="hidden" name="task_id" id="reassignTaskId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Assign To <span class="text-danger">*</span></label>
                        <?= Select2::widget([
                            'name'          => 'assigned_to',
                            'data'          => $devData,
                            'options'       => ['placeholder' => '— Select Developer —', 'required' => true, 'id' => 'reassignAssignedTo'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Re-Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Subtask Modal -->
<div class="modal fade" id="createSubtaskModal" tabindex="-1" aria-labelledby="createSubtaskModalLabel" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSubtaskModalLabel">Create SubTask</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= Url::to(['reassign-task']) ?>">
                <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                <input type="hidden" name="task_id" id="subtaskParentId" value="">
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Task <span class="text-danger">*</span></label>
                            <input type="text" name="task" class="form-control form-control-sm" placeholder="Enter task name" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Assigned To <span class="text-danger">*</span></label>
                            <?= Select2::widget([
                                'name'          => 'assigned_to',
                                'data'          => $devData,
                                'options'       => ['placeholder' => '— Select Developer —', 'required' => true, 'id' => 'subtask_assigned_to'],
                                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                            ]) ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select form-select-sm" required>
                                <option value="">Select Priority</option>
                                <option value="1">High</option>
                                <option value="2">Medium</option>
                                <option value="3">Low</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Assign Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select form-select-sm" required>
                                <option value="">Select Type</option>
                                <option value="1">Full</option>
                                <option value="2">Partial</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Expected Delivery Date <span class="text-danger">*</span></label>
                            <?= DatePicker::widget([
                                'name'          => 'expect_date',
                                'type'          => DatePicker::TYPE_INPUT,
                                'options'       => ['placeholder' => 'yyyy-mm-dd', 'class' => 'form-control form-control-sm', 'required' => true],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true, 'startDate' => date('Y-m-d')],
                            ]) ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Hour Of Work Required <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="number" id="subtask-expect-hours" min="0" value="0"
                                       class="form-control form-control-sm" style="width:70px" placeholder="h">
                                <input type="number" id="subtask-expect-minutes" min="0" max="59" value="0"
                                       class="form-control form-control-sm" style="width:70px" placeholder="m">
                            </div>
                            <input type="hidden" name="expect_time" id="subtask-expect-time" value="">
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control form-control-sm" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary btn-sm">Create</button>
                </div>
            </form>
        </div>
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
$startUrl    = Url::to(['/task/task-start']);
$stopUrl     = Url::to(['/task/task-stop']);
$completeUrl = Url::to(['/task/complete-task']);
$logsUrl     = Url::to(['/task/task-logs']);
$csrfParam   = Yii::$app->request->csrfParam;
$csrfToken   = Yii::$app->request->csrfToken;
$activeTaskIdJs = (int)($activeTaskId ?? 0);

$js = <<<JS
var _activeTaskId = {$activeTaskIdJs};

// Complete Task
jQuery('.js-complete-btn').on('click', function () {
    var btn = jQuery(this);
    var taskId = btn.data('task-id');
    if (!taskId) { Swal.fire({icon:'error',title:'Error',text:'Missing task ID'}); return; }
    Swal.fire({
        icon: 'question',
        title: 'Complete Task?',
        text: 'Are you sure you want to mark this task as completed?',
        showCancelButton: true,
        confirmButtonText: 'Yes, complete it',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        btn.prop('disabled', true).text('Completing…');
        jQuery.ajax({
            url: '{$completeUrl}',
            type: 'GET',
            data: {taskid: taskId},
            dataType: 'json',
            success: function (res) {
                if (res.status == 1) {
                    Swal.fire({icon:'success',title:'Done',text:res.msg,timer:1500,showConfirmButton:false}).then(function(){location.reload();});
                } else {
                    Swal.fire({icon:'error',title:'Cannot Complete',text:res.msg || 'Failed to complete task.'});
                    btn.prop('disabled', false).text('Complete');
                }
            },
            error: function (xhr) {
                Swal.fire({icon:'error',title:'Error',text:'Request failed (HTTP ' + xhr.status + '). Please try again.'});
                btn.prop('disabled', false).text('Complete');
            }
        });
    });
});

// Start / Resume Task
jQuery('.js-task-start-btn').on('click', function () {
    var btn = jQuery(this);
    var taskId = btn.data('task-id');
    var label = btn.text().trim();
    if (!taskId) { Swal.fire({icon:'error',title:'Error',text:'Missing task ID'}); return; }
    // Block if another task is already in progress (different task id).
    if (typeof _activeTaskId !== 'undefined' && _activeTaskId > 0 && _activeTaskId !== parseInt(taskId, 10)) {
        Swal.fire({
            icon: 'warning',
            title: 'Task already in progress',
            text: 'You have already started a task. Please stop the existing task before starting a new one.'
        });
        return;
    }
    Swal.fire({
        icon: 'question',
        title: label + ' Task?',
        text: 'Are you sure you want to ' + label.toLowerCase() + ' this task?',
        showCancelButton: true,
        confirmButtonText: 'Yes, ' + label.toLowerCase(),
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        var postData = {taskid: taskId, description: ''};
        postData['{$csrfParam}'] = '{$csrfToken}';
        btn.prop('disabled', true).text('Starting…');
        jQuery.ajax({
            url: '{$startUrl}',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function (res) {
                if (res.status == 1) {
                    Swal.fire({icon:'success',title:'Started',text:res.msg,timer:1500,showConfirmButton:false}).then(function(){location.reload();});
                } else {
                    Swal.fire({icon:'error',title:'Error',text:res.msg || 'Failed to start task.'});
                    btn.prop('disabled', false).text(label);
                }
            },
            error: function (xhr) {
                Swal.fire({icon:'error',title:'Error',text:'Request failed (HTTP ' + xhr.status + '). Please try again.'});
                btn.prop('disabled', false).text(label);
            }
        });
    });
});

// Stop Task
jQuery('.js-task-stop-btn').on('click', function () {
    var btn = jQuery(this);
    var taskId = btn.data('task-id');
    if (!taskId) { Swal.fire({icon:'error',title:'Error',text:'Missing task ID'}); return; }
    Swal.fire({
        icon: 'question',
        title: 'Stop Task?',
        text: 'Are you sure you want to stop this task?',
        showCancelButton: true,
        confirmButtonText: 'Yes, stop',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        var postData = {taskid: taskId};
        postData['{$csrfParam}'] = '{$csrfToken}';
        btn.prop('disabled', true).text('Stopping…');
        jQuery.ajax({
            url: '{$stopUrl}',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function (res) {
                if (res.status == 1) {
                    Swal.fire({icon:'success',title:'Stopped',text:res.msg,timer:1500,showConfirmButton:false}).then(function(){location.reload();});
                } else {
                    Swal.fire({icon:'error',title:'Error',text:res.msg || 'Failed to stop task.'});
                    btn.prop('disabled', false).text('Stop');
                }
            },
            error: function (xhr) {
                Swal.fire({icon:'error',title:'Error',text:'Request failed (HTTP ' + xhr.status + '). Please try again.'});
                btn.prop('disabled', false).text('Stop');
            }
        });
    });
});

// Task Logs modal
jQuery('#taskLogsModal').on('show.bs.modal', function (event) {
    var taskId = jQuery(event.relatedTarget).data('task-id');
    var body = jQuery('#taskLogsBody');
    body.html('<div class="text-center text-muted py-4">Loading…</div>');
    if (!taskId) return;
    jQuery.ajax({
        url: '{$logsUrl}',
        type: 'GET',
        data: {id: taskId},
        success: function (html) { body.html(html); },
        error: function () { body.html('<p class="text-danger text-center py-3">Failed to load logs.</p>'); }
    });
});

// Re-Assign trigger
jQuery('.js-reassign').on('click', function(e) {
    e.preventDefault();
    jQuery('#reassignTaskId').val(jQuery(this).data('taskid'));
    new bootstrap.Modal(document.getElementById('reassignModal')).show();
});

// Create Subtask trigger
jQuery('.js-create-subtask').on('click', function(e) {
    e.preventDefault();
    jQuery('#subtaskParentId').val(jQuery(this).data('taskid'));
    new bootstrap.Modal(document.getElementById('createSubtaskModal')).show();
});

// Sub Task: validate Hour Of Work Required and pack as HH:MM before submit.
jQuery('#createSubtaskModal form').on('submit', function (e) {
    var h = parseInt(jQuery('#subtask-expect-hours').val(), 10) || 0;
    var m = parseInt(jQuery('#subtask-expect-minutes').val(), 10) || 0;
    if (h <= 0 && m <= 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Invalid Hours',
            text: 'Hour Of Work Required must be at least 1 minute.'
        });
        jQuery('#subtask-expect-hours').focus();
        return false;
    }
    if (m < 0 || m > 59) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Invalid Minutes', text: 'Minutes must be between 0 and 59.' });
        jQuery('#subtask-expect-minutes').focus();
        return false;
    }
    jQuery('#subtask-expect-time').val(h + ':' + (m < 10 ? '0' + m : m));
});
JS;

$this->registerJs($js, \yii\web\View::POS_READY);
?>
