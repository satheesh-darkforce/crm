<?php
/** @var \yii\web\View $this */
/** @var \backend\models\TaskLogs[] $logs */

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02dh %02dm', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
$totalSecs = 0;
foreach ($logs as $log) {
    $totalSecs += (int)$log->total_time;
}
?>
<?php if (empty($logs)): ?>
    <p class="text-muted text-center py-4 mb-0">No log entries found for this task.</p>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm mb-0" style="font-size:.82rem">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>User</th>
                <th>Start</th>
                <th>End</th>
                <th class="text-end">Duration</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $k => $log): ?>
        <tr>
            <td class="text-muted"><?= $k + 1 ?></td>
            <td><?= \yii\helpers\Html::encode($log->user->username ?? '—') ?></td>
            <td class="text-muted small">
                <?= $log->start_time ? date('d M Y H:i', strtotime($log->start_time)) : '—' ?>
            </td>
            <td class="text-muted small">
                <?= $log->end_time ? date('d M Y H:i', strtotime($log->end_time)) : '<em class="text-warning">In progress</em>' ?>
            </td>
            <td class="text-end fw-semibold"><?= $fmt((int)$log->total_time) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="4" class="text-end fw-semibold">Total:</td>
                <td class="text-end fw-bold text-primary"><?= $fmt($totalSecs) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
