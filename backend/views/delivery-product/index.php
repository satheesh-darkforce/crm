<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $q */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Delivery Products';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$can = [
    'create' => UserAccess::checkAccess('delivery-product/create'),
    'update' => UserAccess::checkAccess('delivery-product/update'),
    'delete' => UserAccess::checkAccess('delivery-product/delete'),
];
?>
<div class="ui-page delivery-product-index">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Delivery products',
        'sub'     => number_format($total) . ' product' . ($total == 1 ? '' : 's') . ' available on delivery challans',
        'actions' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add product', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : '',
        'route'   => 'index',
        'filters' => [['type' => 'text', 'name' => 'q', 'label' => 'Name or HSN', 'placeholder' => 'Search products', 'col' => 'col-lg-4 col-md-8']],
    ]) ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Product</th><th>HSN code</th><th>Units</th><th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="5" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $q !== ''
                            ? ['icon' => 'search', 'title' => 'No products match "' . $q . '"', 'action' => Html::a('Clear search', ['index'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'box', 'title' => 'No products yet', 'text' => 'Add the items you deliver so they can be picked on challans.',
                               'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add product', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $p): ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title"><?= Html::encode($p->item_name) ?></td>
                        <td class="mono" data-label="HSN code"><?= Html::encode($p->hsn_code) ?></td>
                        <td data-label="Units"><?= Html::encode($p->units) ?></td>
                        <td class="cell-actions text-end">
                            <?php if ($can['update'] || $can['delete']): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?= Html::encode($p->item_name) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <?php if ($can['update']): ?><li><?= Html::a('<i class="fas fa-pen fa-fw me-1"></i>Edit', ['update', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['delete']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-trash fa-fw me-1"></i>Remove', ['delete', 'id' => $p->id], ['class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data' => ['method' => 'post', 'confirm' => 'Remove "' . $p->item_name . '"? It can no longer be picked on new challans.']]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'products']) ?>
    </div>
</div>
