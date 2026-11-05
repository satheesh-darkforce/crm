<?php
/** @var \yii\web\View $this */
/** @var string $listType  index | my-task-list | my-new-task-list | assigned-tasks | completed-task-list */
/** @var string $title */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var array $f  filters */
/** @var array<int,int> $workedSecs   task id => seconds logged (incl. subtasks) */
/** @var array<int,int> $subtaskCounts */
/** @var int $activeTaskId  task with my running timer, 0 if none */
/** @var array $projects    id => title */
/** @var array $developers  user_id => name */

use backend\components\TaskAccess;
use backend\models\Task;
use common\components\UserAccess;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = $title;

$tasks       = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination ? $pagination->getOffset() : 0;
$activeCount = count(array_filter($f, fn($v) => $v !== ''));
$hasFilters  = $activeCount > 0;
$today       = date('Y-m-d');
$isNew       = $listType === 'my-new-task-list';
$isDone      = $listType === 'completed-task-list';
$showOwner   = in_array($listType, ['index', 'assigned-tasks', 'completed-task-list'], true);

$can = [
    'create'   => UserAccess::checkAccess('task/create'),
    'view'     => UserAccess::checkAccess('task/view'),
    'history'  => UserAccess::checkAccess('task/task-history'),
    'logs'     => UserAccess::checkAccess('task/task-logs'),
    'start'    => UserAccess::checkAccess('task/task-start'),
    'stop'     => UserAccess::checkAccess('task/task-stop'),
    'complete' => UserAccess::checkAccess('task/complete-task'),
    'accept'   => UserAccess::checkAccess('task/task-accept'),
    'subtask'  => UserAccess::checkAccess('task/reassign-task'),
    'reassign' => UserAccess::checkAccess('task/full-reassign-task'),
    'update'   => UserAccess::checkAccess('task/update'),
    'delete'   => UserAccess::checkAccess('task/delete'),
];

$tabs = array_filter([
    'index'               => TaskAccess::isManager() ? 'Open tasks' : 'My tasks',
    'my-new-task-list'    => 'New',
    'assigned-tasks'      => 'Assigned by me',
    'completed-task-list' => 'Completed',
], fn($k) => UserAccess::checkAccess('task/' . $k), ARRAY_FILTER_USE_KEY);
$tabKey = $listType === 'my-task-list' ? 'index' : $listType;

$emptyText = [
    'index'               => TaskAccess::isManager() ? 'Open tasks across the team appear here.' : 'Tasks you have accepted appear here. New ones wait under "New".',
    'my-task-list'        => 'Tasks you have accepted appear here.',
    'my-new-task-list'    => 'Tasks assigned to you appear here until you accept them.',
    'assigned-tasks'      => 'Tasks you assign to others appear here.',
    'completed-task-list' => 'Finished tasks appear here.',
][$listType] ?? '';

$dateFilter = fn(string $name, string $label) => '<label class="form-label small mb-1" for="tf-' . $name . '">' . $label . '</label>' . DatePicker::widget([
    'name' => $name, 'value' => $f[$name], 'type' => DatePicker::TYPE_INPUT,
    'options' => ['id' => 'tf-' . $name, 'class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true, 'clearBtn' => true],
]);
$s2 = fn(string $name, array $data, string $ph, string $label) => '<label class="form-label small mb-1" for="tf-' . $name . '">' . $label . '</label>' . Select2::widget([
    'name' => $name, 'value' => $f[$name] !== '' ? $f[$name] : null, 'data' => $data,
    'options' => ['placeholder' => $ph, 'id' => 'tf-' . $name], 'size' => Select2::SMALL,
    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
]);
?>
<div class="ui-page task-list">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($title) ?></h1>
            <div class="ui-sub"><?= number_format($total) ?> task<?= $total == 1 ? '' : 's' ?><?= $hasFilters ? ' matching your search' : '' ?></div>
        </div>
        <div class="ui-actions">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#task-search"
                    aria-expanded="<?= $hasFilters ? 'true' : 'false' ?>" aria-controls="task-search">
                <i class="fas fa-search me-1"></i>Search<?php if ($hasFilters): ?> <span class="ui-count ms-1"><?= $activeCount ?></span><?php endif; ?>
            </button>
            <?php if ($can['create']): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>New Task', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($tabs) > 1): ?>
    <nav class="ui-tabs" aria-label="Task lists">
        <?php foreach ($tabs as $route => $label): ?>
            <?= Html::a($label, [$route], ['class' => $route === $tabKey ? 'active' : '', 'aria-current' => $route === $tabKey ? 'page' : null]) ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($activeTaskId && $listType !== 'completed-task-list'): ?>
    <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center">
        <span><span class="timer-dot"></span>Your timer is running on
            <?= $can['view'] ? Html::a('task #' . $activeTaskId, ['view', 'id' => $activeTaskId]) : 'task #' . $activeTaskId ?>.</span>
        <?php if ($can['stop']): ?>
            <button type="button" class="btn btn-outline-primary btn-sm ms-auto" data-task-stop="<?= $activeTaskId ?>"><i class="fas fa-stop me-1"></i>Stop timer</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="task-search">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="<?= Url::to([$listType]) ?>" class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small mb-1" for="tf-task">Task name</label>
                        <input type="text" id="tf-task" name="task" value="<?= Html::encode($f['task']) ?>" class="form-control form-control-sm" maxlength="100" placeholder="Search task name">
                    </div>
                    <div class="col-lg-3 col-md-6"><?= $s2('project_id', $projects, 'All projects', 'Project') ?></div>
                    <?php if (!$isNew && !$isDone): ?>
                        <div class="col-lg-2 col-md-4 col-6">
                            <label class="form-label small mb-1" for="tf-task_status">Status</label>
                            <?= Html::dropDownList('task_status', $f['task_status'], array_diff_key(Task::statusOptions(), [Task::STATUS_COMPLETED => 1]),
                                ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'tf-task_status']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($showOwner): ?>
                        <div class="col-lg-2 col-md-4 col-6"><?= $s2('assigned_to', $developers, 'Anyone', 'Assigned to') ?></div>
                        <div class="col-lg-2 col-md-4 col-6"><?= $s2('assigned_by', $developers, 'Anyone', 'Assigned by') ?></div>
                        <div class="col-lg-2 col-md-4 col-6"><?= $s2('created_by', $developers, 'Anyone', 'Created by') ?></div>
                    <?php endif; ?>
                    <div class="col-lg-2 col-md-4 col-6"><?= $dateFilter('expected_date', 'Deadline') ?></div>
                    <div class="col-lg-2 col-md-4 col-6"><?= $dateFilter('date_time', 'Created on') ?></div>
                    <div class="col-lg-2 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                        <?= Html::a('Reset', [$listType], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Task</th>
                    <th class="text-center">Estimate</th>
                    <?php if (!$isNew): ?><th class="text-center">Worked</th><?php endif; ?>
                    <th>Deadline</th>
                    <?php if ($isNew): ?><th>Priority</th><?php else: ?><th>Status</th><?php endif; ?>
                    <th><?= $isNew ? 'Assigned by' : ($showOwner ? 'People' : 'Assigned by') ?></th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$tasks): ?>
                    <tr class="ui-empty-row"><td colspan="8" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilters
                            ? ['icon' => 'search', 'title' => 'No tasks match your search', 'text' => 'Try fewer or different search terms.',
                               'action' => Html::a('Clear search', [$listType], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => $isDone ? 'check-circle' : 'tasks', 'title' => $isNew ? 'No new tasks' : 'No tasks here yet', 'text' => $emptyText,
                               'action' => $can['create'] && in_array($listType, ['assigned-tasks', 'index'], true)
                                   ? Html::a('<i class="fas fa-plus me-1"></i>New Task', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($tasks as $i => $task): /** @var Task $task */ ?>
                    <?php
                    $id       = (int)$task->id;
                    $st       = (int)$task->task_status;
                    $worked   = $workedSecs[$id] ?? 0;
                    $subs     = $subtaskCounts[$id] ?? 0;
                    $running  = $activeTaskId === $id;
                    $late     = $st !== Task::STATUS_COMPLETED && $task->expected_delivery_date && substr($task->expected_delivery_date, 0, 10) < $today;
                    $over     = $worked > 0 && (int)$task->expected_time > 0 && $worked > (int)$task->expected_time;
                    $assignee = TaskAccess::isAssignee($task);
                    $manage   = TaskAccess::canManageWork($task);
                    $edit     = TaskAccess::canEdit($task);
                    $name     = (string)$task->task;
                    ?>
                    <tr class="<?= $running ? 'is-running' : '' ?>">
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap">
                            <?php if ($running): ?><span class="timer-dot" title="Timer running"></span><?php endif; ?>
                            <?php if ($isNew && (int)$task->is_read === 1): ?><span class="ui-pill plain tone-accent me-1">New</span><?php endif; ?>
                            <?= $can['view'] ? Html::a(Html::encode($name), ['view', 'id' => $id], ['class' => 'text-reset text-decoration-none']) : Html::encode($name) ?>
                            <div class="sub">
                                <?= Html::encode($task->project->title ?? 'No project') ?>
                                <?php if ((int)$task->parent_id): ?> · subtask<?php endif; ?>
                                <?php if ($subs): ?> · <?= $subs ?> subtask<?= $subs === 1 ? '' : 's' ?><?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center num" data-label="Estimate"><?= Task::hm((int)$task->expected_time) ?></td>
                        <?php if (!$isNew): ?>
                            <td class="text-center num <?= $over ? 'is-over' : ($worked ? 'fw-semibold' : 'muted') ?>" data-label="Worked" title="<?= $over ? 'More than the estimate' : '' ?>"><?= Task::hm($worked) ?></td>
                        <?php endif; ?>
                        <td class="small nowrap <?= $late ? 'is-late' : '' ?>" data-label="Deadline">
                            <?= $task->expected_delivery_date ? date('d M Y', strtotime($task->expected_delivery_date)) : '—' ?>
                            <?php if ($late): ?><span class="ui-pill plain tone-danger ms-1">Late</span><?php endif; ?>
                        </td>
                        <?php if ($isNew): ?>
                            <td data-label="Priority"><span class="ui-pill plain tone-<?= Task::priorityTone((int)$task->priority) ?>"><?= Html::encode(Task::priorityOptions()[(int)$task->priority] ?? '—') ?></span></td>
                        <?php else: ?>
                            <td data-label="Status"><span class="ui-pill tone-<?= Task::statusTone($st) ?>"><?= Html::encode($task->getStatusLabel()) ?></span></td>
                        <?php endif; ?>
                        <td class="small" data-label="<?= $isNew || !$showOwner ? 'Assigned by' : 'People' ?>">
                            <?php if ($isNew || !$showOwner): ?>
                                <?= Html::encode($task->assignedBy->username ?? '—') ?>
                            <?php else: ?>
                                <div><span class="muted">To</span> <?= Html::encode($task->assignedTo->username ?? '—') ?></div>
                                <div><span class="muted">By</span> <?= Html::encode($task->assignedBy->username ?? $task->createdBy->username ?? '—') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                        data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions for <?= Html::encode($name) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:190px">
                                    <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['view', 'id' => $id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($isNew && $assignee && $can['accept']): ?>
                                        <li><?= Html::a('<i class="fas fa-check me-2"></i>Accept', ['task-accept'], [
                                            'class' => 'dropdown-item text-success', 'encode' => false,
                                            'data' => ['method' => 'post', 'params' => ['taskid' => $id]],
                                        ]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($assignee && $running && $can['stop']): ?>
                                        <li><button type="button" class="dropdown-item" data-task-stop="<?= $id ?>"><i class="fas fa-stop me-2"></i>Stop timer</button></li>
                                    <?php elseif ($assignee && !$running && in_array($st, [Task::STATUS_ACCEPTED, Task::STATUS_ONGOING, Task::STATUS_REOPEN], true) && $can['start']): ?>
                                        <li><button type="button" class="dropdown-item" data-task-start="<?= $id ?>" data-name="<?= Html::encode($name) ?>"
                                                    data-label="<?= $st === Task::STATUS_ONGOING ? 'Resume' : 'Start' ?>"><i class="fas fa-play me-2"></i><?= $st === Task::STATUS_ONGOING ? 'Resume timer' : 'Start timer' ?></button></li>
                                    <?php endif; ?>
                                    <?php if ($manage && !$running && $st !== Task::STATUS_COMPLETED && $st !== Task::STATUS_PENDING && $can['complete']): ?>
                                        <li><button type="button" class="dropdown-item" data-task-complete="<?= $id ?>" data-name="<?= Html::encode($name) ?>"><i class="fas fa-flag-checkered me-2"></i>Complete</button></li>
                                    <?php endif; ?>
                                    <?php if ($can['logs']): ?>
                                        <li><a href="#" class="dropdown-item" data-ui-modal="#taskLogsModal" data-url="<?= Url::to(['task-logs', 'id' => $id]) ?>"><i class="fas fa-list me-2"></i>Time logs</a></li>
                                    <?php endif; ?>
                                    <?php if ($can['history']): ?>
                                        <li><?= Html::a('<i class="fas fa-sitemap me-2"></i>Subtasks &amp; history', ['task-history', 'id' => $id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($manage && !$isDone && ($can['subtask'] || $can['reassign'])): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if ($can['subtask'] && !(int)$task->parent_id): ?>
                                            <li><a href="#" class="dropdown-item" data-subtask="<?= $id ?>" data-name="<?= Html::encode($name) ?>"><i class="fas fa-plus me-2"></i>Add subtask</a></li>
                                        <?php endif; ?>
                                        <?php if ($can['reassign'] && !$running): ?>
                                            <li><a href="#" class="dropdown-item" data-reassign="<?= $id ?>" data-name="<?= Html::encode($name) ?>"><i class="fas fa-user-edit me-2"></i>Re-assign</a></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($edit && !$isDone && $can['update']): ?>
                                        <li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['update', 'id' => $id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($edit && $can['delete']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-trash me-2"></i>Delete', ['delete', 'id' => $id], [
                                            'class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data' => ['method' => 'post', 'confirm' => 'Delete the task "' . $name . '"? It is removed from every task list.'],
                                        ]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($tasks)]) ?>
    </div>

    <?= $this->render('_modals', ['developers' => $developers, 'returnTo' => $listType]) ?>
</div>
