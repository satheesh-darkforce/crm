<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task $mainTask */
/** @var \backend\models\Task[] $subtasks */
/** @var \backend\models\TaskLogs[] $taskLogs */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Task History';

$statusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Re Open', 5 => 'Completed', 6 => 'Re Assigned'];
$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'primary', 4 => 'warning', 5 => 'success', 6 => 'dark'];

$fmtHHMM = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$st = (int)$mainTask->task_status;
?>
<style>
.info-table td{padding:.35rem .5rem;font-size:.875rem;vertical-align:top}
.info-table td:first-child{font-weight:600;color:#6c757d;width:200px;white-space:nowrap}
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="task-history">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">Task History</h5>
    </div>

    <!-- Info card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <!-- Left -->
                <div class="col-md-6">
                    <table class="table table-borderless info-table mb-0">
                        <tr>
                            <td>Task Name</td>
                            <td><?= Html::encode($mainTask->task) ?></td>
                        </tr>
                        <tr>
                            <td>Project Name</td>
                            <td><?= Html::encode($mainTask->project->title ?? '—') ?></td>
                        </tr>
                        <tr>
                            <td>Description</td>
                            <td><?= nl2br(Html::encode($mainTask->description ?? '—')) ?></td>
                        </tr>
                        <tr>
                            <td>Expected Delivery Time</td>
                            <td><?= $fmtHHMM((int)$mainTask->expected_time) ?></td>
                        </tr>
                        <tr>
                            <td>Created By</td>
                            <td><?= Html::encode($mainTask->createdBy->username ?? '—') ?></td>
                        </tr>
                    </table>
                </div>
                <!-- Right -->
                <div class="col-md-6">
                    <table class="table table-borderless info-table mb-0">
                        <tr>
                            <td>Expected Delivery Date</td>
                            <td><?= $mainTask->expected_delivery_date ? date('d-m-Y', strtotime($mainTask->expected_delivery_date)) : '—' ?></td>
                        </tr>
                        <tr>
                            <td>Status</td>
                            <td>
                                <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>">
                                    <?= $statusLabel[$st] ?? '—' ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Total Time</td>
                            <td class="fw-semibold <?= (int)$mainTask->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                                <?= $fmtHHMM((int)$mainTask->total_time) ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Sub Tasks card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            Sub Tasks <span class="badge bg-info ms-1"><?= count($subtasks) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($subtasks)): ?>
                <p class="text-muted text-center py-4 mb-0">No subtasks.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table list-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Task</th>
                            <th>SubTask</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th class="text-center">Total Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subtasks as $k => $sub):
                        $ss = (int)$sub->task_status;
                    ?>
                    <tr>
                        <td class="text-muted"><?= $k + 1 ?></td>
                        <td><?= Html::encode($sub->task) ?></td>
                        <td class="text-muted small"><?= Html::encode($mainTask->task) ?></td>
                        <td><?= Html::encode($sub->assignedTo->username ?? '—') ?></td>
                        <td>
                            <span class="badge bg-<?= $statusBadge[$ss] ?? 'secondary' ?>">
                                <?= $statusLabel[$ss] ?? '—' ?>
                            </span>
                        </td>
                        <td class="text-center fw-semibold <?= (int)$sub->total_time > 0 ? 'text-primary' : 'text-muted' ?>">
                            <?= $fmtHHMM((int)$sub->total_time) ?>
                        </td>
                        <td>
                            <?= Html::a('View', ['view', 'id' => $sub->id], ['class' => 'btn btn-info btn-sm act-btn']) ?>
                            <?php if ($ss !== 5 && $ss !== 3): ?>
                                <button type="button"
                                        class="btn btn-success btn-sm act-btn js-subtask-complete"
                                        data-taskid="<?= $sub->id ?>">
                                    Complete
                                </button>
                            <?php endif; ?>
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

<?php
$completeUrl = Url::to(['/task/complete-task']);
$js = <<<JS
jQuery('.js-subtask-complete').on('click', function () {
    var btn = jQuery(this);
    var taskId = btn.data('taskid');
    if (!taskId) { Swal.fire({icon:'error',title:'Error',text:'Missing task ID'}); return; }
    Swal.fire({
        icon: 'question',
        title: 'Complete Task?',
        text: 'Are you sure you want to mark this subtask as completed?',
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
JS;
$this->registerJs($js, \yii\web\View::POS_READY);
?>
