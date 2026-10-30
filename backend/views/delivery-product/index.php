<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $products */
/** @var array $data */
/** @var int|null $selected */

use common\components\UserAccess;
use kartik\select2\Select2;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'DC Products';

$totalCount = $products->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
.unit-chip{display:inline-block;padding:2px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-weight:600;font-size:.75rem;letter-spacing:.3px}
</style>

<div class="product-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">DC Products</h5>
            <small class="text-muted"><?= $totalCount ?> product<?= $totalCount != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?php if (UserAccess::checkAccess('delivery-product/create')): ?>
                <?= Html::a('+ Add Product', ['/delivery-product/create'], ['class' => 'btn btn-primary btn-sm']) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="post" action="<?= Url::to(['/delivery-product/index']) ?>" class="row g-2 align-items-end">
                <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Product Name</label>
                    <?= Select2::widget([
                        'name' => 'item_id',
                        'data' => $data,
                        'value' => $selected,
                        'options' => ['placeholder' => 'All products', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-auto d-flex gap-1">
                    <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                    <?php if ($selected): ?>
                        <a href="<?= Url::to(['/delivery-product/index']) ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div style="overflow-x:auto;overflow-y:visible">
            <?= GridView::widget([
                'dataProvider' => $products,
                'layout' => "{items}<div class='d-flex justify-content-between align-items-center px-3 py-2'><div class='d-none d-sm-block small text-muted'>{summary}</div>{pager}</div>",
                'tableOptions' => ['class' => 'table list-table mb-0'],
                'options' => ['tag' => 'div'],
                'emptyTextOptions' => ['class' => 'text-center text-muted py-5'],
                'emptyText' => 'No products found.',
                'pager' => [
                    'class' => LinkPager::class,
                    'maxButtonCount' => 3,
                    'options' => ['class' => 'pagination pagination-sm mb-0'],
                    'linkOptions' => ['class' => 'page-link'],
                    'disabledListItemSubTagOptions' => ['tag' => 'a', 'class' => 'page-link'],
                ],
                'columns' => [
                    [
                        'class' => 'yii\grid\SerialColumn',
                        'header' => '#',
                        'headerOptions' => ['style' => 'width:40px'],
                        'contentOptions' => ['class' => 'text-muted'],
                    ],
                    [
                        'label' => 'Product Name',
                        'format' => 'raw',
                        'value' => fn($m) => '<span class="fw-semibold">' . Html::encode($m->item_name ?: '—') . '</span>',
                    ],
                    [
                        'label' => 'HSN Code',
                        'headerOptions' => ['style' => 'width:140px'],
                        'value' => fn($m) => $m->hsn_code ?: '—',
                        'contentOptions' => ['class' => 'small text-muted'],
                    ],
                    [
                        'label' => 'Units',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width:110px'],
                        'value' => fn($m) => $m->units
                            ? '<span class="unit-chip">' . Html::encode($m->units) . '</span>'
                            : '<span class="text-muted">—</span>',
                    ],
                    [
                        'label' => 'Created',
                        'headerOptions' => ['style' => 'width:130px'],
                        'value' => fn($m) => $m->created_at ? date('d M Y', strtotime($m->created_at)) : '—',
                        'contentOptions' => ['class' => 'small text-muted'],
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'header' => 'Actions',
                        'headerOptions' => ['class' => 'text-end', 'style' => 'width:130px'],
                        'contentOptions' => ['class' => 'text-end'],
                        'template' => '{update} {delete}',
                        'buttons' => [
                            'update' => function ($url, $model) {
                                if (!UserAccess::checkAccess('delivery-product/update')) return '';
                                return Html::a('<i class="fas fa-pen"></i>', ['/delivery-product/update', 'id' => $model->id], [
                                    'class' => 'btn btn-outline-primary act-btn me-1',
                                    'encode' => false,
                                    'title' => 'Edit',
                                ]);
                            },
                            'delete' => function ($url, $model) {
                                if (!UserAccess::checkAccess('delivery-product/delete')) return '';
                                return Html::a('<i class="fas fa-trash"></i>', '#', [
                                    'class' => 'btn btn-outline-danger act-btn btn-delete-product',
                                    'data-id' => $model->id,
                                    'data-name' => $model->item_name,
                                    'encode' => false,
                                    'title' => 'Delete',
                                ]);
                            },
                        ],
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>

<?php
$deleteUrl = Url::to(['/delivery-product/delete']);
$this->registerJs(<<<JS
$(document).on('click', '.btn-delete-product', function (e) {
    e.preventDefault();
    var id   = $(this).data('id');
    var name = $(this).data('name') || 'this product';
    if (!window.Swal) {
        if (confirm('Delete "' + name + '"?')) {
            location.href = '$deleteUrl?id=' + id;
        }
        return;
    }
    Swal.fire({
        title: 'Delete product?',
        html: 'Are you sure you want to delete <strong>' + $('<div>').text(name).html() + '</strong>?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: '$deleteUrl',
            type: 'POST',
            data: { id: id, _csrf: (window.yii ? yii.getCsrfToken() : '') },
            dataType: 'json',
            success: function (r) {
                if (r && r.success) {
                    Swal.fire({title:'Deleted', text: r.message || 'Product deleted.', icon:'success', timer: 1500, showConfirmButton:false})
                        .then(function(){ location.reload(); });
                } else {
                    Swal.fire({title:'Error', text: (r && r.message) || 'Could not delete.', icon:'error'});
                }
            },
            error: function () {
                Swal.fire({title:'Error', text: 'Could not delete.', icon:'error'});
            }
        });
    });
});
JS);
?>
