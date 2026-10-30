<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Proposals';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$statusLabels = \backend\models\Proposal::statusLabels();
$statusColors = [
    1 => 'secondary', 2 => 'success', 3 => 'danger',
    4 => 'warning',   5 => 'info',    6 => 'primary', 7 => 'dark',
];
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="proposal-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Proposals</h5>
            <small class="text-muted"><?= $total ?> proposal<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Proposal', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Search / Filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm" placeholder="Search title…">
                </div>
                <div class="col-md-3">
                    <select name="client_id" class="form-select form-select-sm">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c->id ?>" <?= Yii::$app->request->get('client_id') == $c->id ? 'selected' : '' ?>>
                                <?= Html::encode($c->client_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Search</button>
                    <?php if (Yii::$app->request->get('search') || Yii::$app->request->get('client_id')): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Title</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Attachment</th>
                        <th>Created</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No proposals found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $p): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold"><?= Html::encode($p->title) ?></td>
                        <td class="small"><?= Html::encode($p->client->client_name ?? '—') ?></td>
                        <td class="small text-muted"><?= Html::encode($p->project->title ?? '—') ?></td>
                        <td>
                            <span class="badge bg-<?= $statusColors[$p->proposal_status] ?? 'secondary' ?>">
                                <?= Html::encode($statusLabels[$p->proposal_status] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($p->attachment): ?>
                                <a href="<?= Yii::$app->request->baseUrl . $p->attachment ?>" target="_blank" rel="noreferrer noopener" class="small">
                                    <i class="fa fa-file-pdf-o"></i> View
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= $p->created_date ? date('d M Y', strtotime($p->created_date)) : '—' ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete', 'id' => $p->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-confirm' => 'Delete this proposal?', 'data-method' => 'post',
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
        <div class="card-footer bg-white border-top-0 py-2">
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
