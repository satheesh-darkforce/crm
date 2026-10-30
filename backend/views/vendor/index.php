<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var int $iotPurchase */

use common\components\UserAccess;
use backend\models\Vendor;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\widgets\LinkPager;

$this->title = 'Vendors';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$typeColors = [1 => 'primary', 2 => 'success', 3 => 'info'];
$iotPurchase = $iotPurchase ?? 0;

// Check invoice create access — matches Yii1 logic
$chkInvoiceAccess = ($iotPurchase == 1 && UserAccess::checkAccess('iot-product/create-purchase-invoice'))
    || ($iotPurchase == 0 && UserAccess::checkAccess('inward-invoice/create'));
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="vendor-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Vendors</h5>
            <small class="text-muted"><?= $total ?> vendor<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Vendor', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Search / Filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search"
                           value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm"
                           placeholder="Search company name or GST number…">
                </div>
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'vendor_type',
                        'value'         => Yii::$app->request->get('vendor_type'),
                        'data'          => Vendor::typeLabels(),
                        'options'       => ['placeholder' => 'All Types', 'class' => 'form-select form-select-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Search</button>
                    <?php if (Yii::$app->request->get('search') || Yii::$app->request->get('vendor_type')): ?>
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
                        <th>Company Name</th>
                        <th>Type</th>
                        <th>GST No</th>
                        <th>Address</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No vendors found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $v): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= \yii\helpers\Url::to(['view', 'id' => $v->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($v->company_name) ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-<?= $typeColors[$v->vendor_type] ?? 'secondary' ?>">
                                <?= Html::encode($v->getTypeLabel()) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= Html::encode($v->gst_no ?: '—') ?></td>
                        <td class="small text-muted"><?= Html::encode(
                            strlen($v->address) > 60 ? substr($v->address, 0, 60) . '…' : ($v->address ?: '—')
                        ) ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:180px">
                                    <?php if ($chkInvoiceAccess): ?>
                                        <?php if ($v->vendor_type > 0): ?>
                                        <li><?= Html::a('<i class="fa fa-plus-circle"></i> Add Inward Invoice',
                                            $iotPurchase == 1
                                                ? ['/iot-product/create-purchase-invoice', 'vendor_id' => $v->id]
                                                : ['/inward-invoice/create', 'vendor_id' => $v->id],
                                            ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                        <?php else: ?>
                                        <li><a href="javascript:void(0)" class="dropdown-item" style="opacity:0.4"
                                               onclick="alert('Vendor Type not yet set. You can not add invoice to this vendor until it is updated. Please update vendor type and try again')">
                                            <i class="fa fa-plus-circle"></i> Add Inward Invoice
                                        </a></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $v->id],
                                        ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete', 'id' => $v->id], [
                                        'class'        => 'dropdown-item text-danger',
                                        'encode'       => false,
                                        'data-confirm' => 'Delete this vendor?',
                                        'data-method'  => 'post',
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
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?>
                of <?= $total ?>
            </small>
            <?= LinkPager::widget([
                'pagination'     => $pagination,
                'options'        => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»',
                'prevPageLabel'  => '‹', 'nextPageLabel'  => '›',
                'maxButtonCount' => 7,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
