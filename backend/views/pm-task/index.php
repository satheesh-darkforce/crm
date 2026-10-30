<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Project[] $projects */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'PM Tasks';

$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'primary', 4 => 'success', 5 => 'dark'];
$statusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Completed', 5 => 'Closed'];
$priBadge    = [1 => 'danger', 2 => 'warning', 3 => 'info'];
$priLabel    = [1 => 'High', 2 => 'Medium', 3 => 'Low'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="pm-task-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">PM Tasks</h5>
            <small class="text-muted"><?= $total ?> task<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ New PM Task', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="task" value="<?= Html::encode(Yii::$app->request->get('task')) ?>"
                           class="form-control form-control-sm" placeholder="Task name…">
                </div>
                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All PM Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p->id ?>" <?= Yii::$app->request->get('project_id') == $p->id ? 'selected' : '' ?>>
                                <?= Html::encode($p->title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="task_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusLabel as $v => $l): ?>
                            <option value="<?= $v ?>" <?= Yii::$app->request->get('task_status') == $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('task') || Yii::$app->request->get('task_status') || Yii::$app->request->get('project_id')): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Task</th>
                        <th>Project</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No PM tasks found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $task): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $task->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($task->task) ?>
                            </a>
                        </td>
                        <td class="small text-muted"><?= Html::encode($task->project->title ?? '—') ?></td>
                        <td class="small"><?= Html::encode($task->assignedTo->username ?? '—') ?></td>
                        <td>
                            <?php $pri = (int)$task->priority; ?>
                            <?php if (isset($priLabel[$pri])): ?>
                                <span class="badge bg-<?= $priBadge[$pri] ?>-subtle text-<?= $priBadge[$pri] ?> border" style="font-size:.72rem">
                                    <?= $priLabel[$pri] ?>
                                </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= $task->expected_delivery_date ? date('d M Y', strtotime($task->expected_delivery_date)) : '—' ?>
                        </td>
                        <td>
                            <?php $ts = (int)$task->task_status; ?>
                            <span class="badge bg-<?= $statusBadge[$ts] ?? 'secondary' ?>">
                                <?= $statusLabel[$ts] ?? '—' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $task->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $task->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $task->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-method' => 'post',
                                        'data-confirm' => 'Delete task "' . Html::encode($task->task) . '"?',
                                    ]) ?></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?>
            </small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
