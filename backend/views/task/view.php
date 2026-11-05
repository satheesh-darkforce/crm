<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task $task */
/** @var \backend\models\Task[] $subtasks */
/** @var \backend\models\TaskLogs[] $taskLogs */
/** @var array<int,int> $worked */
/** @var array $developers  user_id => name */

use backend\components\TaskAccess;
use backend\models\Task;
use backend\models\TaskLogs;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'View Task';

$st       = (int)$task->task_status;
$running  = TaskLogs::find()->where(['task_id' => $task->id, 'user_id' => Yii::$app->user->id, 'status' => 1, 'end_time' => null])->exists();
$assignee = TaskAccess::isAssignee($task);
$manage   = TaskAccess::canManageWork($task);
$edit     = TaskAccess::canEdit($task);
$done     = $st === Task::STATUS_COMPLETED;
$name     = (string)$task->task;
?>
<div class="ui-page task-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($name) ?></h1>
            <div class="ui-sub">#<?= (int)$task->id ?> · <?= Html::encode($task->project->title ?? 'No project') ?><?php if ($running): ?> · <span class="timer-dot"></span>timer running<?php endif; ?></div>
        </div>
        <div class="ui-actions">
            <?php if ($assignee && $st === Task::STATUS_PENDING && UserAccess::checkAccess('task/task-accept')): ?>
                <?= Html::a('<i class="fas fa-check me-1"></i>Accept', ['task-accept'], ['class' => 'btn btn-primary btn-sm', 'encode' => false,
                    'data' => ['method' => 'post', 'params' => ['taskid' => $task->id]]]) ?>
            <?php endif; ?>
            <?php if ($assignee && $running && UserAccess::checkAccess('task/task-stop')): ?>
                <button type="button" class="btn btn-primary btn-sm" data-task-stop="<?= (int)$task->id ?>"><i class="fas fa-stop me-1"></i>Stop timer</button>
            <?php elseif ($assignee && in_array($st, [Task::STATUS_ACCEPTED, Task::STATUS_ONGOING, Task::STATUS_REOPEN], true) && UserAccess::checkAccess('task/task-start')): ?>
                <button type="button" class="btn btn-primary btn-sm" data-task-start="<?= (int)$task->id ?>" data-name="<?= Html::encode($name) ?>"
                        data-label="<?= $st === Task::STATUS_ONGOING ? 'Resume' : 'Start' ?>"><i class="fas fa-play me-1"></i><?= $st === Task::STATUS_ONGOING ? 'Resume timer' : 'Start timer' ?></button>
            <?php endif; ?>
            <?php if ($manage && !$done && !$running && $st !== Task::STATUS_PENDING && UserAccess::checkAccess('task/complete-task')): ?>
                <button type="button" class="btn btn-outline-primary btn-sm" data-task-complete="<?= (int)$task->id ?>" data-name="<?= Html::encode($name) ?>"><i class="fas fa-flag-checkered me-1"></i>Complete</button>
            <?php endif; ?>
            <?php if (($manage && !$done && UserAccess::checkAccess('task/full-reassign-task')) || ($edit && UserAccess::checkAccess('task/update')) || ($edit && UserAccess::checkAccess('task/delete'))): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More</button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <?php if ($edit && !$done && UserAccess::checkAccess('task/update')): ?>
                        <li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['update', 'id' => $task->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                    <?php endif; ?>
                    <?php if ($manage && !$done && !$running && UserAccess::checkAccess('task/full-reassign-task')): ?>
                        <li><a href="#" class="dropdown-item" data-reassign="<?= (int)$task->id ?>" data-name="<?= Html::encode($name) ?>"><i class="fas fa-user-edit me-2"></i>Re-assign</a></li>
                    <?php endif; ?>
                    <?php if ($edit && UserAccess::checkAccess('task/delete')): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><?= Html::a('<i class="fas fa-trash me-2"></i>Delete', ['delete', 'id' => $task->id], ['class' => 'dropdown-item text-danger', 'encode' => false,
                            'data' => ['method' => 'post', 'confirm' => 'Delete the task "' . $name . '"? It is removed from every task list.']]) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $done ? ['completed-task-list'] : ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <?= $this->render('_detail', compact('task', 'subtasks', 'taskLogs', 'worked')) ?>
    <?= $this->render('_modals', ['developers' => $developers, 'returnTo' => 'index']) ?>
</div>
