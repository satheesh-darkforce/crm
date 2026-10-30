<?php
/** @var \yii\web\View $this */
/** @var \backend\models\PmTask $task */
/** @var \backend\models\PmTask[] $subtasks */
/** @var \backend\models\TaskLogs[] $logs */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = $task->task;

$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'primary', 4 => 'success', 5 => 'dark'];
$statusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Completed', 5 => 'Closed'];
$priBadge    = [1 => 'danger', 2 => 'warning', 3 => 'info'];
$priLabel    = [1 => 'High', 2 => 'Medium', 3 => 'Low'];

$ts  = (int)$task->task_status;
$pri = (int)$task->priority;
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
</style>

<div class="pm-task-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><?= Html::encode($task->task) ?></h4>
            <span class="badge bg-<?= $statusBadge[$ts] ?? 'secondary' ?>"><?= $statusLabel[$ts] ?? '—' ?></span>
            <?php if (isset($priLabel[$pri])): ?>
                <span class="badge bg-<?= $priBadge[$pri] ?>-subtle text-<?= $priBadge[$pri] ?> border ms-1" style="font-size:.72rem">
                    <?= $priLabel[$pri] ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $task->id], ['class' => 'btn btn-warning btn-sm', 'encode' => false]) ?>
            <?= Html::a('Delete', ['delete', 'id' => $task->id], [
                'class' => 'btn btn-danger btn-sm',
                'data'  => ['confirm' => 'Delete this PM task?', 'method' => 'post'],
            ]) ?>
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Task details -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold border-bottom">Task Details</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th class="text-muted" style="width:45%">Project</th>
                            <td><?= Html::encode($task->project->title ?? '—') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Assigned To</th>
                            <td><?= Html::encode($task->assignedTo->username ?? '—') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Assigned By</th>
                            <td><?= Html::encode($task->assignedBy->username ?? '—') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Expected Hours</th>
                            <td><?= $task->getExpectedTimeFormatted() ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Total Logged</th>
                            <td><?= $task->getTotalTimeFormatted() ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Deadline</th>
                            <td><?= $task->expected_delivery_date ? date('d M Y', strtotime($task->expected_delivery_date)) : '—' ?></td>
                        </tr>
                        <?php if ($task->completed_date): ?>
                        <tr>
                            <th class="text-muted">Completed On</th>
                            <td><?= date('d M Y H:i', strtotime($task->completed_date)) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($task->description): ?>
                        <tr>
                            <th class="text-muted">Description</th>
                            <td><?= nl2br(Html::encode($task->description)) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Quick actions -->
                <div class="card-footer bg-white border-top d-flex gap-2 flex-wrap">
                    <?php if ($ts === 1): ?>
                        <?= Html::a('Accept Task', ['/pm-task/task-accept', 'taskid' => $task->id], ['class' => 'btn btn-info btn-sm']) ?>
                    <?php endif; ?>
                    <?php if ($ts !== 4 && $ts !== 5): ?>
                        <?= Html::a('Complete Task', ['/pm-task/complete-task', 'taskid' => $task->id], ['class' => 'btn btn-success btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sub-tasks -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold border-bottom">
                    Sub-Tasks (<?= count($subtasks) ?>)
                </div>
                <?php if (!empty($subtasks)): ?>
                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Sub Task</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Time</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subtasks as $i => $sub): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <a href="<?= Url::to(['/pm-task/view', 'id' => $sub->id]) ?>" class="text-decoration-none">
                                    <?= Html::encode($sub->task) ?>
                                </a>
                            </td>
                            <td class="small"><?= Html::encode($sub->assignedTo->username ?? '—') ?></td>
                            <td>
                                <?php $sts = (int)$sub->task_status; ?>
                                <span class="badge bg-<?= $statusBadge[$sts] ?? 'secondary' ?>" style="font-size:.72rem">
                                    <?= $statusLabel[$sts] ?? '—' ?>
                                </span>
                            </td>
                            <td class="small"><?= $sub->getTotalTimeFormatted() ?></td>
                            <td>
                                <?= Html::a('<i class="fas fa-eye"></i>', ['/pm-task/view', 'id' => $sub->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="card-body text-muted small text-center py-3">No sub-tasks.</div>
                <?php endif; ?>
            </div>

            <!-- Time logs -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold border-bottom">
                    Time Logs (<?= count($logs) ?>)
                </div>
                <?php if (!empty($logs)): ?>
                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $totalLogSecs = 0; ?>
                        <?php foreach ($logs as $i => $log): ?>
                            <?php $totalLogSecs += (int)$log->total_time; ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="small"><?= Html::encode($log->user->username ?? '—') ?></td>
                                <td class="small"><?= $log->start_time ? date('d M Y h:i A', strtotime($log->start_time)) : '—' ?></td>
                                <td class="small"><?= $log->end_time ? date('d M Y h:i A', strtotime($log->end_time)) : '<span class="text-success">Running</span>' ?></td>
                                <td class="small fw-semibold"><?= $log->getTotalTimeFormatted() ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-light">
                            <td colspan="4" class="text-end fw-semibold small">Total:</td>
                            <td class="fw-bold small">
                                <?= $totalLogSecs > 0 ? sprintf('%02d:%02d', intdiv($totalLogSecs, 3600), intdiv($totalLogSecs % 3600, 60)) : '—' ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="card-body text-muted small text-center py-3">No time logs recorded.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
