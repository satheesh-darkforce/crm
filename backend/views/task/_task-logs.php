<?php
/**
 * Time logs table — Time logs modal and the task page.
 *
 * @var \yii\web\View $this
 * @var \backend\models\TaskLogs[] $logs
 */

use backend\models\Task;
use yii\bootstrap5\Html;

$total = array_sum(array_map(fn($l) => (int)$l->total_time, $logs));
$d     = fn($v) => $v ? date('d M Y, h:i a', strtotime($v)) : '—';
?>
<?php if (!$logs): ?>
    <div class="ui-modal-state"><i class="fas fa-clock me-1"></i>No time logged on this task yet.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table list-table ui-table stack mb-0">
        <thead class="table-light">
        <tr><th style="width:40px">#</th><th>Who</th><th>Started</th><th>Stopped</th><th class="text-end">Duration</th></tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $k => $log): ?>
            <tr class="<?= $log->end_time ? '' : 'is-running' ?>">
                <td class="cell-num muted"><?= $k + 1 ?></td>
                <td class="cell-title"><?= Html::encode($log->user->username ?? '—') ?></td>
                <td class="small nowrap" data-label="Started"><?= $d($log->start_time) ?></td>
                <td class="small nowrap" data-label="Stopped"><?= $log->end_time ? $d($log->end_time) : '<span class="timer-dot"></span>Running' ?></td>
                <td class="text-end num fw-semibold" data-label="Duration"><?= $log->end_time ? Task::hm((int)$log->total_time) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" class="text-end">Total</td><td class="text-end num"><?= Task::hm($total) ?></td></tr></tfoot>
    </table>
</div>
<?php endif; ?>
