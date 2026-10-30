<?php
/**
 * Vendor picker before creating a Purchase Invoice.
 * Mirrors Yii1 "Add Purchased Products" flow (vendors/admin?iotpurchase=1).
 *
 * @var \yii\web\View $this
 * @var \yii\data\ActiveDataProvider $dataProvider
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Add Purchased Products';

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
.vendor-actions{display:inline-flex;gap:.6rem;align-items:center;justify-content:flex-end}
.act-icon{font-size:1.05rem;line-height:1;text-decoration:none;padding:2px;border:0;background:transparent;display:inline-block;transition:transform .12s ease, opacity .12s ease}
.act-icon:hover{transform:scale(1.15);text-decoration:none}
.act-icon.act-add{color:#0d6efd}
.act-icon.act-edit{color:#198754}
.act-icon.act-del{color:#dc3545}
</style>

<div class="iot-pick-vendor">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Add Purchased Products</h5>
            <small class="text-muted">Select a vendor to create a new purchase invoice</small>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back to Purchased', ['purchased'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm" placeholder="Search by company or GST…">
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm">Search</button>
                    <?php if (Yii::$app->request->get('search')): ?>
                        <?= Html::a('Reset', ['pick-vendor'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>GST No</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th class="text-end" style="width:180px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No vendors found.</td></tr>
                <?php else: foreach ($models as $i => $v): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold"><?= Html::encode($v->company_name) ?></td>
                        <td class="text-muted small font-monospace"><?= Html::encode($v->gst_no ?: '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($v->phone_no ?? ($v->phone ?? '—')) ?></td>
                        <td class="text-muted small"><?= Html::encode($v->email ?? '—') ?></td>
                        <td class="text-end">
                            <div class="vendor-actions">
                                <?php if ((int)$v->vendor_type > 0): ?>
                                    <?= Html::a('<i class="fa fa-plus-circle"></i>',
                                        ['create-purchase', 'vendor_id' => $v->id],
                                        [
                                            'class'  => 'act-icon act-add',
                                            'title'  => 'Add Inward Invoice',
                                            'encode' => false,
                                        ]) ?>
                                <?php else: ?>
                                    <a href="#"
                                       class="act-icon act-add vendor-type-missing"
                                       title="Vendor Type not yet set."
                                       style="opacity:.3; cursor:not-allowed;">
                                        <i class="fa fa-plus-circle"></i>
                                    </a>
                                <?php endif; ?>

                                <?= Html::a('<i class="fa fa-pencil"></i>',
                                    ['/vendor/update', 'id' => $v->id],
                                    [
                                        'class'  => 'act-icon act-edit',
                                        'title'  => 'Edit Vendor',
                                        'encode' => false,
                                    ]) ?>

                                <?= Html::a('<i class="fa fa-trash"></i>',
                                    ['/vendor/delete', 'id' => $v->id],
                                    [
                                        'class'        => 'act-icon act-del',
                                        'title'        => 'Delete Vendor',
                                        'encode'       => false,
                                        'data-method'  => 'post',
                                        'data-confirm' => 'Delete ' . Html::encode($v->company_name) . '?',
                                    ]) ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
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

<?php
$this->registerJs(<<<'JS'
document.querySelectorAll('.vendor-type-missing').forEach(function (btn) {
    btn.addEventListener('click', function () {
        Swal.fire({
            icon: 'warning',
            title: 'Vendor Type not set',
            text: 'Vendor Type not yet set. You cannot add invoice to this vendor until it is updated. Please update vendor type and try again.'
        });
    });
});
JS, \yii\web\View::POS_READY);
?>
