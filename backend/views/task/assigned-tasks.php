<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var array<int,int> $workedSecs  [task_id => worked seconds] aggregated from task_logs */

use kartik\date\DatePicker;
use kartik\select2\Select2;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Assigned Tasks';

$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'primary', 4 => 'warning', 5 => 'success', 6 => 'dark'];
$statusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Re Open', 5 => 'Completed', 6 => 'Re Assigned'];
$priBadge    = [1 => 'danger', 2 => 'warning', 3 => 'info'];
$priLabel    = [1 => 'High', 2 => 'Medium', 3 => 'Low'];

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

// Developer list for the Create Subtask modal.
// Yii1 parity: dropdown shows the developer's full name, keyed by user_id.
$developers = \backend\models\Developer::find()
    ->where(['status' => 1])
    ->orderBy('developer_name')
    ->all();
$devData = \yii\helpers\ArrayHelper::map($developers, 'user_id', 'developer_name');

?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
.row-overrun{background:#f1f1f1!important;color:#000}
.row-overrun:hover{background:#e8e8e8!important}
/* Select2 inside a hidden modal initializes at width:0 — force full width. */
#createSubtaskModal .select2-container{width:100%!important}
/* Cap the modal-Select2 dropdown so long developer lists scroll instead of
   stretching the modal full-height. */
#createSubtaskModal .select2-results__options{max-height:220px;overflow-y:auto}
</style>

<?php
// Raw script tag (not via registerJs) so the function is guaranteed to be
// on the window object before any dropdown-item's inline onclick can fire.
// Earlier attempts via registerJs / jQuery delegation never reached the
// click handler — see commit history.
$completeUrlInline = \yii\helpers\Url::to(['/task/complete-task']);
?>
<script>
window.completeAssignedTask = function (taskId) {
    if (!taskId) { return; }
    var url = <?= json_encode($completeUrlInline) ?>;
    var doRequest = function () {
        // Use fetch so this works even if jQuery happens not to be ready yet.
        var qs = url + (url.indexOf('?') === -1 ? '?' : '&') + 'taskid=' + encodeURIComponent(taskId);
        fetch(qs, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.status == 1) {
                    if (window.Swal) {
                        Swal.fire({icon:'success',title:'Done',text:res.msg,timer:1500,showConfirmButton:false}).then(function(){location.reload();});
                    } else {
                        location.reload();
                    }
                } else {
                    var msg = (res && res.msg) || 'Failed to complete task.';
                    if (window.Swal) { Swal.fire({icon:'error',title:'Cannot Complete',text:msg}); }
                    else { alert(msg); }
                }
            })
            .catch(function () {
                if (window.Swal) { Swal.fire({icon:'error',title:'Error',text:'Request failed. Please try again.'}); }
                else { alert('Request failed. Please try again.'); }
            });
    };
    if (window.Swal) {
        Swal.fire({
            icon: 'question',
            title: 'Complete Task?',
            text: 'Are you sure you want to mark this task as completed?',
            showCancelButton: true,
            confirmButtonText: 'Yes, complete it',
            cancelButtonText: 'Cancel'
        }).then(function (result) { if (result.isConfirmed) doRequest(); });
    } else {
        if (confirm('Are you sure you want to mark this task as completed?')) doRequest();
    }
};

// Create Subtask modal opener — defined inline (same reason as
// completeAssignedTask above) because jQuery click handlers registered via
// registerJs never fire from these dropdown-items.
window.openCreateSubtaskModal = function (taskId) {
    if (!taskId) { return; }
    document.getElementById('subtaskParentId').value = taskId;
    new bootstrap.Modal(document.getElementById('createSubtaskModal')).show();
};
</script>

<div class="task-assigned-list">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Tasks Assigned By Me</h5>
            <small class="text-muted"><?= $total ?> task<?= $total != 1 ? 's' : '' ?> assigned</small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('All Tasks', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= DatePicker::widget([
                        'name'          => 'expected_date',
                        'value'         => Yii::$app->request->get('expected_date'),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'Deadline…', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <?= DatePicker::widget([
                        'name'          => 'date_time',
                        'value'         => Yii::$app->request->get('date_time'),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'Created date…', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('expected_date') || Yii::$app->request->get('date_time')): ?>
                        <?= Html::a('Clear', ['assigned-tasks'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Assigned To</th>
                        <th class="text-center">Exp. Time</th>
                        <th class="text-center">Total Time</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>Created</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-5">No tasks found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $task):
                        $st      = (int)$task->task_status;
                        $overdue = $st < 5 && $task->expected_delivery_date && strtotime($task->expected_delivery_date) < time();
                        $worked  = (int)($workedSecs[$task->id] ?? 0);
                        $overrun = $worked > 0 && (int)$task->expected_time > 0 && $worked > (int)$task->expected_time;
                    ?>
                    <tr class="<?= $overrun ? 'row-overrun' : '' ?>" <?= $overrun ? 'title="This task exceeded its expected time"' : '' ?>>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $task->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($task->task) ?>
                            </a>
                        </td>
                        <td class="text-muted small"><?= Html::encode($task->project->title ?? '—') ?></td>
                        <td><?= Html::encode($task->assignedTo->username ?? '—') ?></td>
                        <td class="text-center text-muted small"><?= $fmt((int)$task->expected_time) ?></td>
                        <td class="text-center fw-semibold <?= $worked > 0 ? ($overrun ? 'text-danger' : 'text-primary') : 'text-muted' ?>">
                            <?= $fmt($worked) ?>
                            <?= $overrun ? ' <span class="badge bg-danger ms-1" style="font-size:.65rem">Overrun</span>' : '' ?>
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
                        <td class="text-muted small">
                            <?= $task->created_date ? date('d M Y', strtotime($task->created_date)) : '—' ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <?php if (UserAccess::checkAccess('task/view')): ?>
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $task->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php // Edit intentionally omitted — Yii1 admin.php template for assignedtasks
                                          // is {view_detail} {sub_task} {complete} {tasklogs} {reassign}, no edit. ?>
                                    <?php if (UserAccess::checkAccess('task/task-history')): ?>
                                    <li><?= Html::a('<i class="fas fa-clipboard"></i> Sub Tasks', ['task-history', 'id' => $task->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('task/task-logs')): ?>
                                    <li>
                                        <a class="dropdown-item js-task-logs-btn" href="#"
                                           data-task-id="<?= $task->id ?>"
                                           data-bs-toggle="modal"
                                           data-bs-target="#taskLogsModal"><i class="fas fa-clock"></i> Task Logs</a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ((int)$task->task_status !== 5 && (int)$task->task_status !== 3 && UserAccess::checkAccess('task/complete-task')): ?>
                                    <li>
                                        <a class="dropdown-item text-success" href="javascript:void(0);"
                                           onclick="window.completeAssignedTask(<?= (int)$task->id ?>); return false;">
                                            <i class="fas fa-check"></i> Complete
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php // Yii1 parity (protected/views/task/assignedtasks.php): the only
                                          // re-assignment action on this list is "Create Subtask" — Full Re-Assign
                                          // lives on the My-New-Tasks view, not here. Always visible regardless of
                                          // task_status (Yii1 button has no `visible` constraint). ?>
                                    <?php if (UserAccess::checkAccess('task/reassign-task')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="javascript:void(0);"
                                           onclick="window.openCreateSubtaskModal(<?= (int)$task->id ?>); return false;">
                                            &#43; Create Subtask
                                        </a>
                                    </li>
                                    <?php endif; ?>
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

<!-- Create Subtask Modal -->
<div class="modal fade" id="createSubtaskModal" tabindex="-1" aria-labelledby="createSubtaskModalLabel" aria-hidden="true">
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
                                'pluginOptions' => [
                                    'allowClear'     => true,
                                    'width'          => '100%',
                                    // Render dropdown inside the modal so Bootstrap 5 doesn't
                                    // steal focus from the search input.
                                    'dropdownParent' => new \yii\web\JsExpression("jQuery('#createSubtaskModal')"),
                                ],
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
$logsUrl     = Url::to(['/task/task-logs']);
$completeUrl = Url::to(['/task/complete-task']);
$this->registerJs(<<<JS
// Select2 inside a hidden Bootstrap modal initializes with width:0 because the
// modal is display:none at document.ready. When the modal is actually shown,
// force the Select2 container to recompute width.
jQuery(document).on('shown.bs.modal', '#createSubtaskModal', function () {
    jQuery(this).find('.select2-container').css('width', '100%');
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

// window.completeAssignedTask is defined in the raw <script> tag near the
// top of this view so it is available before any dropdown-item is clicked.
JS, \yii\web\View::POS_READY);
?>
