<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Lead Master (Prospects)';

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

<div class="lead-master-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Lead Master — Prospects</h5>
            <small class="text-muted"><?= $total ?> prospect<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('Upload CSV', ['upload-lead'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('Active Leads', ['index'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm" placeholder="Name / Company / Email / Phone…">
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('search')): ?>
                        <?= Html::a('Clear', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Name</th>
                        <th>Account / Company</th>
                        <th>Designation</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Added</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No prospects found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $prospect): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold"><?= Html::encode($prospect->getFullName() ?: '—') ?></td>
                        <td><?= Html::encode($prospect->account_name ?: '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($prospect->designation ?: '—') ?></td>
                        <td class="small"><?= Html::encode($prospect->email_id ?: '—') ?></td>
                        <td class="small"><?= Html::encode($prospect->phone_number ?: '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($prospect->city ?: '—') ?></td>
                        <td class="text-muted small">
                            <?= $prospect->created_at ? date('d M Y', strtotime($prospect->created_at)) : '—' ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:165px">
                                    <li><?= Html::a('<i class="fas fa-caret-right"></i> Convert to Lead', ['convert-to-lead', 'id' => $prospect->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
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
