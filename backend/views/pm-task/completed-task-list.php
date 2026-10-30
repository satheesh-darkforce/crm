<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Completed PM Tasks';

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

<div class="pm-completed-tasks">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Completed PM Tasks</h5>
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
                        <th>Assigned To</th>
                        <th>Completed On</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No completed tasks found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $task): ?>
                    <tr class="text-muted">
                        <td><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $task->id]) ?>" class="text-decoration-none">
                                <?= Html::encode($task->task) ?>
                            </a>
                        </td>
                        <td class="small"><?= Html::encode($task->project->title ?? '—') ?></td>
                        <td class="small"><?= Html::encode($task->assignedTo->username ?? '—') ?></td>
                        <td class="small"><?= $task->completed_date ? date('d M Y H:i', strtotime($task->completed_date)) : '—' ?></td>
                        <td class="text-end">
                            <?= Html::a('View', ['view', 'id' => $task->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
