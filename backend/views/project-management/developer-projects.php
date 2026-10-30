<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\widgets\LinkPager;

$this->title = 'My PM Projects';

$statusLabel = [1 => 'Active', 2 => 'Completed', 3 => 'On Hold', 4 => 'Cancelled'];
$statusBadge = [1 => 'success', 2 => 'primary', 3 => 'warning', 4 => 'danger'];

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

<div class="pm-developer-projects">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">My PM Projects</h5>
            <small class="text-muted"><?= $total ?> project<?= $total != 1 ? 's' : '' ?> assigned to you</small>
        </div>
        <?= Html::a('All Projects', ['all-project'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Project Title</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No projects assigned to you.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $p): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold small"><?= Html::encode($p->title ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($p->client->client_name ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($p->project_type ?? '—') ?></td>
                        <td>
                            <?php $st = (int)($p->project_status ?? 1); ?>
                            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>-subtle text-<?= $statusBadge[$st] ?? 'secondary' ?> border" style="font-size:.7rem">
                                <?= $statusLabel[$st] ?? 'Unknown' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:120px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
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
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
