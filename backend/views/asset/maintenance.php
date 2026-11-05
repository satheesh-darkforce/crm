<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Asset[] $assets */

use backend\models\AssetMaintenance;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Asset Maintenance';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>

<div class="ui-page asset-maintenance-index">
    <div class="ui-head">
        <div>
            <h1>Asset Maintenance</h1>
            <div class="ui-sub"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('+ Add Maintenance', ['add-maintenance'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Asset</label>
                    <select name="asset_id" class="form-select form-select-sm">
                        <option value="">All Assets</option>
                        <?php foreach ($assets as $a): ?>
                            <option value="<?= $a->id ?>" <?= Yii::$app->request->get('asset_id') == $a->id ? 'selected' : '' ?>><?= Html::encode($a->product_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Payment Type</label>
                    <select name="payment_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach (AssetMaintenance::paymentTypeLabels() as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= Yii::$app->request->get('payment_type') == $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto d-flex gap-1">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('asset_id') || Yii::$app->request->get('payment_type')): ?>
                        <?= Html::a('Clear', ['maintenance'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Asset</th>
                        <th>Service Agent</th>
                        <th>Contact</th>
                        <th>Payment Type</th>
                        <th>Cost</th>
                        <th>Attended By</th>
                        <th>Invoice Bill</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No maintenance records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $m): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <div class="fw-semibold small"><?= Html::encode($m->asset->product_name ?? '—') ?></div>
                            <small class="text-muted" style="font-family:monospace"><?= Html::encode($m->asset->barcode ?? '') ?></small>
                        </td>
                        <td class="small"><?= Html::encode($m->service_provider_name ?? $m->service_provider ?? '—') ?></td>
                        <td class="small text-muted"><?= Html::encode($m->sp_number ?? '—') ?></td>
                        <td class="small">
                            <?php $pt = (int)$m->payment_type; ?>
                            <span class="badge bg-<?= $pt === 2 ? 'warning text-dark' : 'success-subtle text-success border' ?>">
                                <?= AssetMaintenance::paymentTypeLabels()[$pt] ?? '—' ?>
                            </span>
                            <?php if ($pt === 2 && $m->payment_mode): ?>
                                <small class="text-muted d-block"><?= AssetMaintenance::paymentModeLabels()[$m->payment_mode] ?? '' ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= $pt === 2 ? '₹' . number_format((float)$m->service_cost, 2) : '<span class="text-muted">—</span>' ?></td>
                        <td class="small"><?= Html::encode($m->developer->developer_name ?? '—') ?></td>
                        <td class="small">
                            <?php if ($m->invoice_bill): ?>
                                <div class="d-flex align-items-center gap-1 bill-row" data-id="<?= $m->id ?>">
                                    <a href="<?= \backend\controllers\Controller::uploadUrl($m->invoice_bill) ?>" target="_blank" class="text-primary">View</a>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 delete-bill" data-id="<?= $m->id ?>" style="font-size:.65rem"><i class="fas fa-times"></i></button>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view-maintenance', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update-maintenance', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete-maintenance', 'id' => $m->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-confirm' => 'Delete this record?', 'data-method' => 'post',
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

<script>
document.querySelectorAll('.delete-bill').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (!confirm('Delete this invoice bill?')) return;
        var id = this.getAttribute('data-id');
        var row = this.closest('.bill-row');
        fetch('<?= Url::to(['/asset/delete-maintenance-bill']) ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: '<?= Yii::$app->request->csrfParam ?>=<?= Yii::$app->request->getCsrfToken() ?>&id=' + id
        }).then(r => r.json()).then(d => {
            if (d.success) { row.innerHTML = '<span class="text-muted">—</span>'; }
            else { Swal.fire({icon:'error', title:'Error', text: d.message || 'Delete failed.'}); }
        });
    });
});
</script>
