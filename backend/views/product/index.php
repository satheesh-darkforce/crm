<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array<int,string> $categories */
/** @var array $counts */
/** @var array $filters */

use backend\models\Product;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Products';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$sort       = $dataProvider->getSort();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$searching  = $filters['q'] !== '' || $filters['category_id'] || $filters['item_type'] || $filters['is_active'] !== '';
$can = [
    'create'     => UserAccess::checkAccess('product/create'),
    'update'     => UserAccess::checkAccess('product/update'),
    'delete'     => UserAccess::checkAccess('product/delete'),
    'categories' => UserAccess::checkAccess('product/categories'),
];
$addBtn = $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add product', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : '';
$actions = ($can['categories'] ? Html::a('<i class="fas fa-tags me-1"></i>Categories', ['categories'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) : '') . $addBtn;
?>
<div class="ui-page product-index">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Products & services',
        'sub'     => number_format($total) . ' item' . ($total == 1 ? '' : 's'),
        'actions' => $actions,
        'route'   => 'index',
        'filters' => [
            ['type' => 'text', 'name' => 'q', 'label' => 'Name, code or HSN', 'placeholder' => 'Search the catalogue', 'col' => 'col-lg-4 col-md-6'],
            ['type' => 'select', 'name' => 'category_id', 'label' => 'Category', 'data' => ['-1' => 'No category'] + $categories, 'placeholder' => 'All categories', 'col' => 'col-lg-3 col-md-6'],
            ['type' => 'dropdown', 'name' => 'item_type', 'label' => 'Type', 'data' => Product::typeOptions(), 'placeholder' => 'Products & services', 'col' => 'col-lg-2 col-md-6'],
            ['type' => 'dropdown', 'name' => 'is_active', 'label' => 'Status', 'data' => ['1' => 'Active', '0' => 'Inactive'], 'placeholder' => 'Any status', 'col' => 'col-lg-2 col-md-6'],
        ],
    ]) ?>

    <?php if ($counts && !$searching): ?>
    <div class="ui-tiles">
        <div class="ui-tile is-accent"><div class="t-label">In catalogue</div><div class="t-value num"><?= number_format($counts['total'] ?? 0) ?></div><div class="t-sub"><?= number_format($counts['active'] ?? 0) ?> active</div></div>
        <div class="ui-tile"><div class="t-label">Products</div><div class="t-value num"><?= number_format(($counts['total'] ?? 0) - ($counts['services'] ?? 0)) ?></div><div class="t-sub">Supplied goods</div></div>
        <div class="ui-tile"><div class="t-label">Services</div><div class="t-value num"><?= number_format($counts['services'] ?? 0) ?></div><div class="t-sub">Installation, maintenance</div></div>
        <div class="ui-tile"><div class="t-label">Categories</div><div class="t-value num"><?= number_format(count($categories)) ?></div>
            <div class="t-sub"><?= ($counts['uncategorised'] ?? 0) ? Html::a(number_format($counts['uncategorised']) . ' without a category', ['index', 'category_id' => -1]) : 'All items categorised' ?></div></div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th><?= $sort->link('item_name') ?></th>
                    <th><?= $sort->link('product_code') ?></th>
                    <th>Category</th>
                    <th>HSN / SAC</th>
                    <th class="text-end"><?= $sort->link('sale_price') ?></th>
                    <th class="text-end">GST</th>
                    <th>Status</th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="9" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $searching
                            ? ['icon' => 'search', 'title' => 'No items match your search', 'action' => Html::a('Clear search', ['index'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'box', 'title' => 'No products yet', 'text' => 'Add the products and services you quote, such as artificial grass per sq.ft or installation work.', 'action' => $addBtn ?: null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $p): /** @var Product $p */
                    $off = $p->getDiscountPercent(); ?>
                    <tr class="<?= (int)$p->is_active ? '' : 'is-inactive' ?>">
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title">
                            <div class="p-name">
                                <?php if ($p->image): ?>
                                    <img class="p-thumb" src="<?= Html::encode(Yii::getAlias('@web') . $p->image) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="p-thumb" aria-hidden="true"><i class="fas <?= $p->isService() ? 'fa-tools' : 'fa-leaf' ?>"></i></span>
                                <?php endif; ?>
                                <div>
                                    <?= Html::a(Html::encode($p->item_name), ['view', 'id' => $p->id], ['class' => 'p-title text-decoration-none']) ?>
                                    <div class="p-sub"><?= $p->getTypeName() ?><?= $p->units ? ' · per ' . Html::encode($p->units) : '' ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="mono" data-label="Code"><?= Html::encode($p->product_code ?: '—') ?></td>
                        <td data-label="Category"><?= Html::encode($p->category->name ?? '—') ?></td>
                        <td class="mono" data-label="HSN / SAC"><?= Html::encode($p->hsn_code ?: '—') ?></td>
                        <td class="text-end num" data-label="Price">
                            <span class="cell-stack">
                                <?php if ($p->sale_price !== null): ?>
                                    ₹<?= Yii::$app->formatter->asDecimal((float)$p->sale_price, 2) ?>
                                    <?php if ($off): ?><span class="p-off"><?= $off ?>% off</span><?php endif; ?>
                                <?php endif; ?>
                                <?php if ((int)$p->price_on_request): ?><span class="ui-pill tone-warning">On request</span><?php endif; ?>
                            </span>
                        </td>
                        <td class="text-end num" data-label="GST"><?= $p->gst_rate !== null ? (float)$p->gst_rate . '%' : '—' ?></td>
                        <td data-label="Status"><span class="ui-pill <?= (int)$p->is_active ? 'tone-success' : '' ?>"><?= (int)$p->is_active ? 'Active' : 'Inactive' ?></span></td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?= Html::encode($p->item_name) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><?= Html::a('<i class="fas fa-eye fa-fw me-1"></i>View', ['view', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php if ($can['update']): ?>
                                        <li><?= Html::a('<i class="fas fa-pen fa-fw me-1"></i>Edit', ['update', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                        <li><?= Html::a('<i class="fas fa-power-off fa-fw me-1"></i>' . ((int)$p->is_active ? 'Mark inactive' : 'Mark active'), ['toggle-active', 'id' => $p->id],
                                            ['class' => 'dropdown-item', 'encode' => false, 'data' => ['method' => 'post', 'params' => ['back' => 'index']]]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($can['delete']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-trash fa-fw me-1"></i>Remove', ['delete', 'id' => $p->id], ['class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data' => ['method' => 'post', 'confirm' => 'Remove "' . $p->item_name . '" from the catalogue? Documents that already use it keep their copy.']]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'items']) ?>
    </div>
</div>
