<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task[] $subtasks */

use yii\helpers\Html;
use yii\helpers\Url;

$statusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Re Open', 5 => 'Completed', 6 => 'Re Assigned'];
$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'primary', 4 => 'warning', 5 => 'success', 6 => 'dark'];

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02dh %02dm', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
?>
<div id="subtaskdiv">
    <h6 class="mb-2 fw-semibold">Sub Tasks
        <span class="badge bg-info ms-1"><?= count($subtasks) ?></span>
    </h6>
    <?php if (empty($subtasks)): ?>
        <p class="text-muted small mb-0">No subtasks found.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Task</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th class="text-center">Total Time</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($subtasks as $k => $sub):
                $ss       = (int)$sub->task_status;
                $overtime = $sub->total_time > 0 && $sub->expected_time > 0 && $sub->total_time > $sub->expected_time;
            ?>
            <tr class="<?= $ss === 4 ? 'table-success' : ($ss === 2 ? 'table-primary' : '') ?>">
                <td class="text-muted"><?= $k + 1 ?></td>
                <td>
                    <a href="<?= Url::to(['task/view', 'id' => $sub->id]) ?>"
                       class="text-decoration-none text-dark fw-semibold">
                        <?= Html::encode($sub->task) ?>
                    </a>
                </td>
                <td><?= Html::encode($sub->assignedTo->username ?? '—') ?></td>
                <td>
                    <span class="badge bg-<?= $statusBadge[$ss] ?? 'secondary' ?>">
                        <?= $statusLabel[$ss] ?? '—' ?>
                    </span>
                </td>
                <td class="text-center <?= $overtime ? 'text-danger fw-semibold' : '' ?>">
                    <?= $fmt((int)$sub->total_time) ?>
                </td>
                <td class="text-end">
                    <a href="<?= Url::to(['task/view', 'id' => $sub->id]) ?>"
                       class="btn btn-outline-secondary btn-sm py-0 px-1" style="font-size:.72rem">View</a>
                    <?php if ($ss < 4): ?>
                    <button type="button"
                            class="btn btn-outline-success btn-sm py-0 px-1 js-complete-subtask"
                            style="font-size:.72rem"
                            data-taskid="<?= $sub->id ?>">
                        Done
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

<script>
jQuery('.js-complete-subtask').on('click', function () {
    var taskId = jQuery(this).data('taskid');
    Swal.fire({
        icon: 'question',
        title: 'Complete Subtask?',
        text: 'Mark this subtask as completed?',
        showCancelButton: true,
        confirmButtonText: 'Yes, complete',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        jQuery.ajax({
            url: '<?= Url::to(['task/complete-task']) ?>',
            type: 'GET',
            data: {taskid: taskId},
            dataType: 'json',
            success: function (res) {
                if (res.status == 1) {
                    Swal.fire({icon:'success',title:'Done',text:res.msg,timer:1500,showConfirmButton:false}).then(function(){location.reload();});
                } else {
                    Swal.fire({icon:'error',title:'Cannot Complete',text:res.msg});
                }
            }
        });
    });
});
</script>
