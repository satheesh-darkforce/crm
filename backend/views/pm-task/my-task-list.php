<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'My PM Tasks';

$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'primary', 4 => 'success', 5 => 'dark'];
$statusLabel = [1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Completed', 5 => 'Closed'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="pm-my-task-list">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">My PM Tasks</h5>
            <small class="text-muted"><?= $total ?> task<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('All PM Tasks', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Task</th>
                        <th>Project</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No tasks assigned to you.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $task): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $task->id]) ?>" class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($task->task) ?>
                            </a>
                        </td>
                        <td class="small text-muted"><?= Html::encode($task->project->title ?? '—') ?></td>
                        <td class="small text-muted"><?= $task->expected_delivery_date ? date('d M Y', strtotime($task->expected_delivery_date)) : '—' ?></td>
                        <td>
                            <?php $ts = (int)$task->task_status; ?>
                            <span class="badge bg-<?= $statusBadge[$ts] ?? 'secondary' ?>"><?= $statusLabel[$ts] ?? '—' ?></span>
                        </td>
                        <td class="text-end">
                            <?= Html::a('View', ['view', 'id' => $task->id], ['class' => 'btn btn-outline-primary btn-sm']) ?>
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
