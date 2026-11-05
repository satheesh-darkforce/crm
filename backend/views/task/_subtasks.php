<?php
/**
 * Subtask list fragment (Yii1 getsubtasks; returned by TaskController::actionGetSubtasks).
 *
 * @var \yii\web\View $this
 * @var \backend\models\Task[] $subtasks
 */

use backend\models\Task;
use yii\bootstrap5\Html;
?>
<?php if (!$subtasks): ?>
    <div class="ui-modal-state">No subtasks.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table list-table ui-table mb-0">
        <thead class="table-light"><tr><th>#</th><th>Subtask</th><th>Assigned to</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($subtasks as $k => $sub): ?>
            <tr>
                <td class="muted"><?= $k + 1 ?></td>
                <td><?= Html::a(Html::encode($sub->task), ['/task/view', 'id' => $sub->id]) ?></td>
                <td class="small"><?= Html::encode($sub->assignedTo->username ?? '—') ?></td>
                <td><span class="ui-pill tone-<?= Task::statusTone((int)$sub->task_status) ?>"><?= Html::encode($sub->getStatusLabel()) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
