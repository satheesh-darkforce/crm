<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'PM Quotations';

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

<div class="pm-quotation-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">PM Quotations</h5>
            <small class="text-muted"><?= $total ?> quotation<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ New PM Quotation', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Search -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm" placeholder="Client, project, or quotation ID…">
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Search</button>
                    <?php if (Yii::$app->request->get('search')): ?>
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
                        <th>Quotation ID</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Created</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No PM quotations found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $q): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $q->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($q->unique_id ?: '#' . $q->id) ?>
                            </a>
                        </td>
                        <td><?= Html::encode($q->client_name) ?></td>
                        <td><?= Html::encode($q->project_name) ?></td>
                        <td class="small text-muted"><?= Html::encode($q->email) ?></td>
                        <td class="small text-muted"><?= Html::encode($q->mobile ?: '—') ?></td>
                        <td class="small text-muted"><?= $q->created_date ? date('d M Y', strtotime($q->created_date)) : '—' ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $q->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $q->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $q->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-method' => 'post',
                                        'data-confirm' => 'Delete quotation ' . Html::encode($q->unique_id ?: $q->id) . '?',
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
