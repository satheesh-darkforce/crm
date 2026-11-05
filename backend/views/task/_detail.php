<?php
/**
 * Task details, subtasks and time logs — shared by the task page and Task History.
 *
 * @var \yii\web\View $this
 * @var \backend\models\Task $task
 * @var \backend\models\Task[] $subtasks
 * @var \backend\models\TaskLogs[] $taskLogs
 * @var array<int,int> $worked  task id => seconds logged (parent includes subtasks)
 */

use backend\components\TaskAccess;
use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$st     = (int)$task->task_status;
$d      = fn($v, $fmt = 'd M Y') => $v ? date($fmt, strtotime($v)) : '—';
$late   = $st !== Task::STATUS_COMPLETED && $task->expected_delivery_date && substr($task->expected_delivery_date, 0, 10) < date('Y-m-d');
$secs   = $worked[(int)$task->id] ?? 0;
$over   = $secs > 0 && (int)$task->expected_time > 0 && $secs > (int)$task->expected_time;
$manage = TaskAccess::canManageWork($task) && $st !== Task::STATUS_COMPLETED;
?>
<div class="ui-tiles">
    <div class="ui-tile"><div class="t-label">Status</div><div class="t-value"><span class="ui-pill lg tone-<?= Task::statusTone($st) ?>"><?= Html::encode($task->getStatusLabel()) ?></span></div>
        <div class="t-sub"><?= $st === Task::STATUS_COMPLETED ? 'Completed ' . $d($task->completed_date) : Html::encode(Task::priorityOptions()[(int)$task->priority] ?? '') . ' priority' ?></div></div>
    <div class="ui-tile"><div class="t-label">Deadline</div><div class="t-value <?= $late ? 'is-late' : '' ?>"><?= $d($task->expected_delivery_date) ?></div>
        <div class="t-sub"><?= $late ? 'Past the deadline' : 'Created ' . $d($task->created_date) ?></div></div>
    <div class="ui-tile"><div class="t-label">Worked / estimate</div><div class="t-value num <?= $over ? 'is-over' : '' ?>"><?= Task::hm($secs) ?> <span class="text-muted fw-normal">/ <?= Task::hm((int)$task->expected_time) ?></span></div>
        <div class="t-sub"><?= $subtasks ? 'Includes subtasks' : ($over ? 'Over the estimate' : 'From time logs') ?></div></div>
    <div class="ui-tile"><div class="t-label">Assigned to</div><div class="t-value"><?= Html::encode($task->assignedTo->username ?? '—') ?></div>
        <div class="t-sub">by <?= Html::encode($task->assignedBy->username ?? $task->createdBy->username ?? '—') ?> · <?= Html::encode($task->getAssignTypeLabel()) ?></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Details</div>
    <table class="table ui-detail">
        <tr><th scope="row">Project</th><td><?= Html::encode($task->project->title ?? '—') ?></td></tr>
        <tr><th scope="row">Client</th><td><?= Html::encode($task->client->client_name ?? '—') ?></td></tr>
        <tr><th scope="row">Type</th><td><?= (int)$task->parent_id
            ? 'Subtask of ' . (UserAccess::checkAccess('task/view') && $task->parent ? Html::a(Html::encode($task->parent->task), ['view', 'id' => $task->parent_id]) : 'task #' . (int)$task->parent_id)
            : 'Main task' ?></td></tr>
        <tr><th scope="row">Created by</th><td><?= Html::encode($task->createdBy->username ?? '—') ?> · <?= $d($task->created_date, 'd M Y, h:i a') ?></td></tr>
        <tr><th scope="row">Description</th><td><?= trim((string)$task->description) !== '' ? nl2br(Html::encode($task->description)) : '—' ?></td></tr>
    </table>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">
        <span>Subtasks<?= $subtasks ? ' (' . count($subtasks) . ')' : '' ?></span>
        <?php if ($manage && !(int)$task->parent_id && UserAccess::checkAccess('task/reassign-task')): ?>
            <button type="button" class="btn btn-outline-primary btn-sm" data-subtask="<?= (int)$task->id ?>" data-name="<?= Html::encode($task->task) ?>"><i class="fas fa-plus me-1"></i>Add subtask</button>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table list-table ui-table stack mb-0">
            <thead class="table-light">
            <tr><th style="width:40px">#</th><th>Subtask</th><th>Assigned to</th><th>Deadline</th><th class="text-center">Worked</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if (!$subtasks): ?>
                <tr class="ui-empty-row"><td colspan="6" class="ui-empty-cell">
                    <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'sitemap', 'title' => 'No subtasks', 'small' => true]) ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($subtasks as $k => $sub): ?>
                <?php $ss = (int)$sub->task_status; $sw = $worked[(int)$sub->id] ?? 0; ?>
                <tr>
                    <td class="cell-num muted"><?= $k + 1 ?></td>
                    <td class="cell-title wrap"><?= Html::a(Html::encode($sub->task), ['view', 'id' => $sub->id], ['class' => 'text-reset text-decoration-none']) ?></td>
                    <td class="small" data-label="Assigned to"><?= Html::encode($sub->assignedTo->username ?? '—') ?></td>
                    <td class="small nowrap" data-label="Deadline"><?= $d($sub->expected_delivery_date) ?></td>
                    <td class="text-center num <?= $sw > (int)$sub->expected_time && (int)$sub->expected_time > 0 ? 'is-over' : '' ?>" data-label="Worked"><?= Task::hm($sw) ?> <span class="muted">/ <?= Task::hm((int)$sub->expected_time) ?></span></td>
                    <td data-label="Status"><span class="ui-pill tone-<?= Task::statusTone($ss) ?>"><?= Html::encode($sub->getStatusLabel()) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header ui-section"><span>Time logs<?= $taskLogs ? ' (' . count($taskLogs) . ')' : '' ?></span></div>
    <?= $this->render('_task-logs', ['logs' => $taskLogs]) ?>
</div>
