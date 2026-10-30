<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task $task */
/** @var \backend\models\Task[] $subtasks */
/** @var \backend\models\TaskLogs[] $taskLogs */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'View Task';

$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'primary', 4 => 'warning', 5 => 'success', 6 => 'dark'];
$statusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Re Open', 5 => 'Completed', 6 => 'Re Assigned'];
$priBadge    = [1 => 'danger', 2 => 'warning', 3 => 'success'];
$priLabel    = [1 => 'High', 2 => 'Medium', 3 => 'Low'];

$expSecs = (int)$task->expected_time;
$expHr   = floor($expSecs / 3600);
$expMin  = intdiv($expSecs % 3600, 60);
$expFmt  = $expSecs > 0 ? "{$expHr} hr {$expMin} m" : '—';

$fmtHHMM = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$st = (int)$task->task_status;
?>
<style>
.info-table td{padding:.35rem .5rem;font-size:.875rem;vertical-align:top}
.info-table td:first-child{font-weight:600;color:#6c757d;width:180px;white-space:nowrap}
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="task-view">
    <?php $isCompleted = ($st === 5); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">View Task</h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', $isCompleted ? ['completed-task-list'] : ['index'], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <!-- Info card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <!-- Left column -->
                <div class="col-md-6">
                    <table class="table table-borderless info-table mb-0">
                        <tr>
                            <td>Project</td>
                            <td><?= Html::encode($task->project->title ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td>Client</td>
                            <td><?= Html::encode($task->client->client_name ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td>Date</td>
                            <td><?= $task->created_date ? date('d-m-Y', strtotime($task->created_date)) : '—' ?></td>
                        </tr>
                        <tr>
                            <td>Task Type</td>
                            <td><?= (int)$task->parent_id === 0 ? 'Main Task' : 'Sub Task' ?></td>
                        </tr>
                        <tr>
                            <td>Hour Of Work Required</td>
                            <td><?= $expFmt ?></td>
                        </tr>
                        <tr>
                            <td>Expected Delivery Date</td>
                            <td><?= $task->expected_delivery_date ? date('d-m-Y', strtotime($task->expected_delivery_date)) : '—' ?></td>
                        </tr>
                        <tr>
                            <td>Task Status</td>
                            <td>
                                <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>">
                                    <?= $statusLabel[$st] ?? '—' ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Completed Date</td>
                            <td><?= $task->completed_date ? date('d-m-Y', strtotime($task->completed_date)) : '—' ?></td>
                        </tr>
                    </table>
                </div>
                <!-- Right column -->
                <div class="col-md-6">
                    <table class="table table-borderless info-table mb-0">
                        <tr>
                            <td>Total Time</td>
                            <td class="fw-semibold <?= (int)$task->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                                <?= $fmtHHMM((int)$task->total_time) ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Created By</td>
                            <td><?= Html::encode($task->createdBy->username ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td>Description</td>
                            <td><?= nl2br(Html::encode($task->description ?? '—')) ?></td>
                        </tr>
                        <tr>
                            <td>Priority</td>
                            <td>
                                <?php $pri = (int)$task->priority; ?>
                                <?php if ($pri && isset($priLabel[$pri])): ?>
                                    <span class="badge bg-<?= $priBadge[$pri] ?>">
                                        <?= $priLabel[$pri] ?>
                                    </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Assigned To</td>
                            <td><?= Html::encode($task->assignedTo->username ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td>Assigned Type</td>
                            <td><?= (int)$task->assign_type === 1 ? 'Full' : 'Partial' ?></td>
                        </tr>
                        <tr>
                            <td>Created Date</td>
                            <td><?= $task->created_date ? date('d-m-Y H:i', strtotime($task->created_date)) : '—' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sub Tasks card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Sub Tasks</span>
            <?php if (!$isCompleted): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSubtaskModal">
                Create SubTask
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($subtasks)): ?>
                <p class="text-muted text-center py-4 mb-0">No Results Found</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table list-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Task Name</th>
                            <th class="text-center">Expected Time</th>
                            <th>Expected Delivery Date</th>
                            <th>Task Status</th>
                            <th class="text-center">Total Time</th>
                            <th>Created By</th>
                            <th>Assigned By</th>
                            <th>Assigned To</th>
                            <th>Created Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subtasks as $k => $sub): ?>
                        <?php $ss = (int)$sub->task_status; ?>
                        <tr>
                            <td class="text-muted"><?= $k + 1 ?></td>
                            <td><?= Html::encode($sub->task) ?></td>
                            <td class="text-center text-muted small"><?= $fmtHHMM((int)$sub->expected_time) ?></td>
                            <td><?= $sub->expected_delivery_date ? date('d-m-Y', strtotime($sub->expected_delivery_date)) : '—' ?></td>
                            <td>
                                <span class="badge bg-<?= $statusBadge[$ss] ?? 'secondary' ?>">
                                    <?= $statusLabel[$ss] ?? '—' ?>
                                </span>
                            </td>
                            <td class="text-center fw-semibold <?= (int)$sub->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                                <?= $fmtHHMM((int)$sub->total_time) ?>
                            </td>
                            <td><?= Html::encode($sub->createdBy->username ?? '—') ?></td>
                            <td><?= Html::encode($sub->assignedBy->username ?? '—') ?></td>
                            <td><?= Html::encode($sub->assignedTo->username ?? '—') ?></td>
                            <td class="small text-muted"><?= $sub->created_date ? date('d-m-Y', strtotime($sub->created_date)) : '—' ?></td>
                            <td>
                                <?= Html::a('View', ['view', 'id' => $sub->id], ['class' => 'btn btn-info btn-sm act-btn']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Task Logs card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">Task Logs</div>
        <div class="card-body p-0">
            <?php if (empty($taskLogs)): ?>
                <p class="text-muted text-center py-4 mb-0">No log entries found.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table list-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>User</th>
                            <th>Start</th>
                            <th>End</th>
                            <th class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($taskLogs as $k => $log): ?>
                        <tr>
                            <td class="text-muted"><?= $k + 1 ?></td>
                            <td><?= Html::encode($log->user->username ?? '—') ?></td>
                            <td class="small text-muted">
                                <?= $log->start_time ? date('d-m-Y H:i', strtotime($log->start_time)) : '—' ?>
                            </td>
                            <td class="small text-muted">
                                <?= $log->end_time ? date('d-m-Y H:i', strtotime($log->end_time)) : '<em class="text-warning">In progress</em>' ?>
                            </td>
                            <td class="text-center fw-semibold"><?= $fmtHHMM((int)$log->total_time) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php if (!$isCompleted): ?>
<!-- Create SubTask Modal -->
<div class="modal fade" id="createSubtaskModal" tabindex="-1" aria-labelledby="createSubtaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="createSubtaskModalLabel">Create SubTask</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= Url::to(['reassign-task']) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <input type="hidden" name="task_id" value="<?= $task->id ?>">
                <input type="hidden" name="expect_time" id="subtask-expect-time" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Task <span class="text-danger">*</span></label>
                            <input type="text" name="task" class="form-control form-control-sm" required placeholder="Task name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Assigned To <span class="text-danger">*</span></label>
                            <select name="assigned_to" class="form-select form-select-sm" required>
                                <option value="">Select Developer</option>
                                <?php foreach ($developers as $dev): ?>
                                    <option value="<?= $dev->user_id ?>"><?= Html::encode($dev->developer_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Priority</label>
                            <select name="priority" class="form-select form-select-sm">
                                <option value="1">High</option>
                                <option value="2">Medium</option>
                                <option value="3">Low</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Assign Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select form-select-sm" required>
                                <option value="1">Full</option>
                                <option value="2">Partial</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Expected Delivery Date <span class="text-danger">*</span></label>
                            <?= \kartik\date\DatePicker::widget([
                                'name'          => 'expect_date',
                                'value'         => '',
                                'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                                'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true, 'startDate' => date('Y-m-d')],
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Expected Delivery Date', 'required' => true],
                            ]) ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Hour Of Work Required <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2">
                                <input type="number" id="subtask-expect-hours" min="0" value="0"
                                       class="form-control form-control-sm" style="width:70px" placeholder="h">
                                <input type="number" id="subtask-expect-minutes" min="0" max="59" value="0"
                                       class="form-control form-control-sm" style="width:70px" placeholder="m">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm">Description</label>
                            <textarea name="description" class="form-control form-control-sm" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="submit" class="btn btn-primary btn-sm" id="subtask-submit-btn">Create</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<'JS'
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
JS, \yii\web\View::POS_READY);
?>
<?php endif; ?>
