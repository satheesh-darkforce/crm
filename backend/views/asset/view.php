<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Asset $model */
/** @var \backend\models\AssetHandover[] $handovers */
/** @var \backend\models\AssetMaintenance[] $maintenances */
/** @var \backend\models\AssetUpload[] $uploads */

use backend\models\AssetUpload;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Asset: ' . $model->product_name;
$today = date('Y-m-d');

// Group uploads by type
$uploadsByType = [];
foreach ($uploads as $u) {
    $uploadsByType[$u->upload_type][] = $u;
}
$uploadTypeLabels = AssetUpload::uploadTypeLabels();
?>
<div class="asset-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($model->product_name) ?></h5>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-2" style="font-size:.875rem">
                        <div class="col-md-4"><span class="text-muted d-block">Product Name</span><strong><?= Html::encode($model->product_name ?? '—') ?></strong></div>
                        <div class="col-md-4"><span class="text-muted d-block">Model</span><?= Html::encode($model->model ?? '—') ?></div>
                        <div class="col-md-4"><span class="text-muted d-block">Barcode</span><code><?= Html::encode($model->barcode ?? '—') ?></code></div>
                        <div class="col-md-4"><span class="text-muted d-block">Brand</span><?= Html::encode($model->brand->brand_name ?? '—') ?></div>
                        <div class="col-md-4"><span class="text-muted d-block">Category</span><?= Html::encode($model->category->name ?? '—') ?></div>
                        <div class="col-md-4"><span class="text-muted d-block">Sub Category</span><?= Html::encode($model->subcategory->name ?? '—') ?></div>
                        <div class="col-md-4"><span class="text-muted d-block">Location</span><?= Html::encode($model->location->loc ?? '—') ?></div>
                        <div class="col-md-4"><span class="text-muted d-block">Vendor</span><?= Html::encode($model->vendor->company_name ?? '—') ?></div>
                        <div class="col-md-4"><span class="text-muted d-block">Bill Amount</span><?= $model->bill ? '₹' . number_format((float)$model->bill, 2) : '—' ?></div>
                        <div class="col-md-4"><span class="text-muted d-block">Date of Purchase</span><?= $model->date_of_purchase ? date('d M Y', strtotime($model->date_of_purchase)) : '—' ?></div>
                        <?php if ($model->date_of_expire): ?>
                        <div class="col-md-4"><span class="text-muted d-block">Expire Date</span>
                            <span class="<?= $model->date_of_expire < $today ? 'text-danger fw-semibold' : '' ?>"><?= date('d M Y', strtotime($model->date_of_expire)) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($model->service_contact): ?>
                        <div class="col-md-4"><span class="text-muted d-block">Service Contact</span><?= Html::encode($model->service_contact) ?></div>
                        <?php endif; ?>
                        <?php $tagNames = $model->getTagNames(); if ($tagNames): ?>
                        <div class="col-md-8"><span class="text-muted d-block">Tags</span><?= Html::encode($tagNames) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2 text-muted small text-uppercase">Warranty</h6>
                    <?php if ($model->warranty): ?>
                        <?php $wExpired = $model->warranty_end_date && $model->warranty_end_date < $today; ?>
                        <span class="badge bg-<?= $wExpired ? 'danger' : 'success' ?> mb-2"><?= $wExpired ? 'Expired' : 'Active' ?></span>
                        <div style="font-size:.875rem">
                            <div><span class="text-muted">Start:</span> <?= $model->warranty_start_date ? date('d M Y', strtotime($model->warranty_start_date)) : '—' ?></div>
                            <div><span class="text-muted">End:</span> <?= $model->warranty_end_date ? date('d M Y', strtotime($model->warranty_end_date)) : '—' ?></div>
                        </div>
                    <?php else: ?><span class="text-muted small">No Warranty</span><?php endif; ?>

                    <hr>
                    <h6 class="fw-semibold mb-2 text-muted small text-uppercase">AMC</h6>
                    <?php if ($model->amc): ?>
                        <?php $amcExpired = $model->amc_end_date && $model->amc_end_date < $today; ?>
                        <span class="badge bg-<?= $amcExpired ? 'danger' : 'info' ?> mb-2"><?= $amcExpired ? 'Expired' : 'Active' ?></span>
                        <div style="font-size:.875rem">
                            <div><span class="text-muted">Start:</span> <?= $model->amc_start_date ? date('d M Y', strtotime($model->amc_start_date)) : '—' ?></div>
                            <div><span class="text-muted">End:</span> <?= $model->amc_end_date ? date('d M Y', strtotime($model->amc_end_date)) : '—' ?></div>
                        </div>
                    <?php else: ?><span class="text-muted small">No AMC</span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($model->description): ?>
    <div class="alert alert-light border mb-3 small"><?= nl2br(Html::encode($model->description)) ?></div>
    <?php endif; ?>

    <!-- Documents & Uploads -->
    <?php if (!empty($uploads)): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold py-2">Documents &amp; Files</div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($uploadTypeLabels as $typeId => $typeLabel): ?>
                    <?php if (!empty($uploadsByType[$typeId])): ?>
                    <div class="col-md-3">
                        <h6 class="small fw-semibold text-muted text-uppercase"><?= $typeLabel ?></h6>
                        <?php foreach ($uploadsByType[$typeId] as $u): ?>
                            <div class="d-flex align-items-center gap-1 mb-1 upload-row" data-id="<?= $u->id ?>">
                                <?php $fileUrl = \backend\controllers\Controller::uploadUrl($u->path); ?>
                                <?php if ($typeId === 4 && in_array(strtolower($u->type), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                    <a href="<?= $fileUrl ?>" target="_blank"><img src="<?= $fileUrl ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px" class="border"></a>
                                <?php endif; ?>
                                <a href="<?= $fileUrl ?>" target="_blank" class="small text-truncate" style="max-width:140px"><?= Html::encode(basename($u->path)) ?></a>
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 delete-upload" data-id="<?= $u->id ?>" style="font-size:.7rem"><i class="fas fa-times"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Handover History -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold py-2 d-flex justify-content-between align-items-center">
            <span>Handover History</span>
            <?= Html::a('+ Add Handover', ['add-handover', 'asset_id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Developer</th><th>Issued Date</th><th>Return Date</th><th>Condition</th><th>Location</th></tr>
                </thead>
                <tbody>
                <?php if (empty($handovers)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No handover history.</td></tr>
                <?php else: ?>
                    <?php $condLabels = \backend\models\AssetHandover::conditionLabels(); ?>
                    <?php foreach ($handovers as $i => $h): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="fw-semibold small"><?= Html::encode($h->developer->developer_name ?? '—') ?></td>
                        <td class="small"><?= $h->in_date ? date('d M Y', strtotime($h->in_date)) : '—' ?></td>
                        <td class="small"><?= $h->out_date ? date('d M Y', strtotime($h->out_date)) : '<span class="badge bg-warning text-dark">In Use</span>' ?></td>
                        <td class="small"><?= $h->asset_condition ? ($condLabels[$h->asset_condition] ?? '—') : '—' ?></td>
                        <td class="small text-muted"><?= Html::encode($h->asset_location ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Maintenance History -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold py-2 d-flex justify-content-between align-items-center">
            <span>Maintenance History</span>
            <?= Html::a('+ Add Maintenance', ['add-maintenance', 'asset_id' => $model->id], ['class' => 'btn btn-outline-warning btn-sm']) ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Service Provider</th><th>Contact</th><th>Cost</th><th>Payment</th><th>Attended By</th><th>Bill</th></tr>
                </thead>
                <tbody>
                <?php if (empty($maintenances)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No maintenance history.</td></tr>
                <?php else: ?>
                    <?php foreach ($maintenances as $i => $m): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="small"><?= Html::encode($m->service_provider_name ?? $m->service_provider ?? '—') ?></td>
                        <td class="small"><?= Html::encode($m->sp_number ?? '—') ?></td>
                        <td class="small"><?= (int)$m->payment_type === 2 ? '₹' . number_format((float)$m->service_cost, 2) : '<span class="text-muted">Free</span>' ?></td>
                        <td class="small"><?= \backend\models\AssetMaintenance::paymentTypeLabels()[$m->payment_type] ?? '—' ?></td>
                        <td class="small"><?= Html::encode($m->developer->developer_name ?? '—') ?></td>
                        <td class="small">
                            <?php if ($m->invoice_bill): ?>
                                <a href="<?= \backend\controllers\Controller::uploadUrl($m->invoice_bill) ?>" target="_blank" class="text-primary">View Bill</a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.delete-upload').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (!confirm('Delete this file?')) return;
        var id = this.getAttribute('data-id');
        var row = this.closest('.upload-row');
        fetch('<?= Url::to(['/asset/delete-upload']) ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: '<?= Yii::$app->request->csrfParam ?>=<?= Yii::$app->request->getCsrfToken() ?>&id=' + id
        }).then(r => r.json()).then(d => {
            if (d.success) { row.remove(); }
            else { Swal.fire({icon:'error', title:'Error', text: d.message || 'Delete failed.'}); }
        });
    });
});
</script>
