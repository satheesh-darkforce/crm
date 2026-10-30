<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
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
$priBadge    = [1 => 'danger', 2 => 'warning', 3 => 'info'];
$priLabel    = [1 => 'High', 2 => 'Medium', 3 => 'Low'];

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

// Developer list for Create Subtask modal
$developers = \backend\models\User::find()
    ->where(['status' => 1])
    ->andWhere(['!=', 'usertype', \backend\models\User::TYPE_CLIENT])
    ->orderBy('username')
    ->all();
$devData = ArrayHelper::map($developers, 'user_id', 'username');
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.list-table tr.row-started td:first-child{border-left:3px solid #0d6efd}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="task-my-list">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">My Tasks</h5>
            <small class="text-muted"><?= $total ?> task<?= $total != 1 ? 's' : '' ?> assigned to you</small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('New Tasks', ['my-new-task-list'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
            <?= Html::a('All Tasks', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
    </div>

    <!-- Date filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= DatePicker::widget([
                        'name'          => 'expected_date',
                        'value'         => Yii::$app->request->get('expected_date'),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'Filter by deadline…', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('expected_date')): ?>
                        <?= Html::a('Clear', ['my-task-list'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Task</th>
                        <th>Project</th>
                        <th>Assigned By</th>
                        <th class="text-center">Exp. Time</th>
                        <th class="text-center">Total Time</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-5">No tasks found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $task):
                        $st      = (int)$task->task_status;
                        $overdue = $st < 5 && $task->expected_delivery_date && strtotime($task->expected_delivery_date) < time();
                        $isRunning = $activeTaskId > 0 && $activeTaskId === (int)$task->id;
                    ?>
                    <tr class="<?= $isRunning ? 'row-started' : '' ?>">
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $task->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($task->task) ?>
                            </a>
                        </td>
                        <td class="text-muted small"><?= Html::encode($task->project->title ?? '—') ?></td>
                        <td><?= Html::encode($task->assignedBy->username ?? '—') ?></td>
                        <td class="text-center text-muted small"><?= $fmt((int)$task->expected_time) ?></td>
                        <td class="text-center fw-semibold <?= $task->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                            <?= $fmt((int)$task->total_time) ?>
                        </td>
                        <td>
                            <?php $pri = (int)$task->priority; ?>
                            <?php if ($pri && isset($priLabel[$pri])): ?>
                                <span class="badge bg-<?= $priBadge[$pri] ?>-subtle text-<?= $priBadge[$pri] ?> border" style="font-size:.72rem">
                                    <?= $priLabel[$pri] ?>
                                </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>">
                                <?= $statusLabel[$st] ?? '—' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($task->expected_delivery_date): ?>
                                <span class="<?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?> small">
                                    <?= date('d M Y', strtotime($task->expected_delivery_date)) ?>
                                    <?= $overdue ? '<span class="badge bg-danger ms-1">Overdue</span>' : '' ?>
                                </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?= Html::a('View', ['view', 'id' => $task->id], ['class' => 'btn btn-info btn-sm act-btn']) ?>
                                <?php if ($isRunning && UserAccess::checkAccess('task/task-stop')): ?>
                                    <button type="button" class="btn btn-warning btn-sm act-btn js-task-stop" data-taskid="<?= $task->id ?>">Stop</button>
                                <?php elseif ($st === 3 && UserAccess::checkAccess('task/task-start')): ?>
                                    <button type="button" class="btn btn-primary btn-sm act-btn js-task-start" data-taskid="<?= $task->id ?>">Resume</button>
                                <?php elseif (($st === 2 || $st === 4) && UserAccess::checkAccess('task/task-start')): ?>
                                    <button type="button" class="btn btn-primary btn-sm act-btn js-task-start" data-taskid="<?= $task->id ?>">Start</button>
                                <?php endif; ?>
                                <?php if (!$isRunning && $st < 5 && UserAccess::checkAccess('task/complete-task')): ?>
                                    <button type="button" class="btn btn-success btn-sm act-btn js-task-complete" data-taskid="<?= $task->id ?>">Complete</button>
                                <?php endif; ?>
                                <?= Html::a('Sub Tasks', ['task-history', 'id' => $task->id], ['class' => 'btn btn-outline-secondary btn-sm act-btn']) ?>
                                <button type="button"
                                        class="btn btn-secondary btn-sm act-btn js-task-logs-btn"
                                        data-task-id="<?= $task->id ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#taskLogsModal">Task Logs</button>
                                <button type="button"
                                        class="btn btn-outline-primary btn-sm act-btn js-create-subtask"
                                        data-taskid="<?= $task->id ?>"
                                        title="Create Subtask">&#43; Subtask</button>
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
                'pagination'    => $pagination,
                'options'       => ['class' => 'pagination mb-0'],
                'firstPageLabel'=> '«', 'lastPageLabel' => '»',
                'prevPageLabel' => '‹', 'nextPageLabel' => '›',
                'maxButtonCount'=> 7,
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
                            <input type="text" name="expect_time" class="form-control form-control-sm" placeholder="HH:MM" required>
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

<?php
$startUrl    = Url::to(['/task/task-start']);
$stopUrl     = Url::to(['/task/task-stop']);
$completeUrl = Url::to(['/task/complete-task']);
$csrfParam   = Yii::$app->request->csrfParam;
$csrfToken   = Yii::$app->request->csrfToken;

$activeTaskIdJs = (int)$activeTaskId;
$js = <<<JS
var _activeTaskId = {$activeTaskIdJs};

// Start / Resume Task
jQuery('.js-task-start').on('click', function () {
    var btn = jQuery(this);
    var taskId = btn.data('taskid');
    var label = btn.text().trim();
    if (!taskId) { Swal.fire({icon:'error',title:'Error',text:'Missing task ID'}); return; }
    // Block if another task is already in progress (different task id).
    if (_activeTaskId > 0 && _activeTaskId !== parseInt(taskId, 10)) {
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
jQuery('.js-task-stop').on('click', function () {
    var btn = jQuery(this);
    var taskId = btn.data('taskid');
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

// Complete Task
jQuery('.js-task-complete').on('click', function () {
    var btn = jQuery(this);
    var taskId = btn.data('taskid');
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

// Create Subtask trigger
jQuery('.js-create-subtask').on('click', function(e) {
    e.preventDefault();
    jQuery('#subtaskParentId').val(jQuery(this).data('taskid'));
    new bootstrap.Modal(document.getElementById('createSubtaskModal')).show();
});
JS;

$this->registerJs($js, \yii\web\View::POS_READY);
?>
