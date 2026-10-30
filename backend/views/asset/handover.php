<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer[] $developers */

use backend\models\Asset;
use yii\bootstrap5\Html;
use yii\widgets\LinkPager;

$this->title = 'Asset Handover';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$req        = Yii::$app->request;
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="asset-handover-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Asset Handover</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Handover', ['add-handover'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Employee</label>
                    <select name="developer_id" class="form-select form-select-sm">
                        <option value="">All Employees</option>
                        <?php foreach ($developers as $d): ?>
                            <option value="<?= $d->id ?>" <?= $req->get('developer_id') == $d->id ? 'selected' : '' ?>><?= Html::encode($d->developer_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto d-flex gap-1">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if ($req->get('developer_id')): ?>
                        <?= Html::a('Clear', ['handover'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th style="width:40px">Sno</th>
                        <th>Employee</th>
                        <th>Asset Id</th>
                        <th style="width:120px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-5">No handover records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $h): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td><?= Html::encode($h->developer->developer_name ?? '—') ?></td>
                        <td>
                            <?php
                            $assetIds = $h->getDecodedAssetIds();
                            if (!empty($assetIds)):
                                $handoverAssets = Asset::find()->where(['id' => $assetIds, 'status' => 1])->all();
                                $barcodes = [];
                                foreach ($handoverAssets as $ha) {
                                    if (!empty($ha->barcode)) {
                                        $barcodes[] = Html::encode($ha->barcode);
                                    }
                                }
                                echo !empty($barcodes) ? implode(', ', $barcodes) . '...' : '—';
                            else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= Html::a('Assets Details', ['handover-history', 'dev_id' => $h->dev_id], ['class' => 'btn btn-outline-primary btn-sm act-btn']) ?>
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
