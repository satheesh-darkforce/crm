<?php
/** @var yii\web\View $this */
/** @var backend\models\ProductCategory[] $categories */
/** @var array<int,int> $counts  category_id => live products */
/** @var backend\models\ProductCategory $model  the category being added / edited */

use common\components\UserAccess;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Product categories';
$editing  = !$model->isNewRecord;
$canAdd   = UserAccess::checkAccess('product/categories');
?>
<div class="ui-page product-categories" style="max-width:1180px">
    <div class="ui-head">
        <div>
            <h1>Product categories</h1>
            <div class="ui-sub"><?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?> · group the catalogue the same way as the website</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Products', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table list-table ui-table stack mb-0">
                        <thead class="table-light">
                        <tr><th style="width:40px">#</th><th>Category</th><th class="text-end">Products</th><th class="text-end">Order</th><th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$categories): ?>
                            <tr class="ui-empty-row"><td colspan="5" class="ui-empty-cell">
                                <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'tags', 'title' => 'No categories yet',
                                    'text' => 'Add categories such as Artificial Grass, Vertical Gardens or Artificial Plants.']) ?>
                            </td></tr>
                        <?php endif; ?>
                        <?php foreach ($categories as $i => $c): $n = $counts[$c->id] ?? 0; ?>
                            <tr class="<?= $editing && $model->id === $c->id ? 'table-active' : '' ?>">
                                <td class="cell-num muted"><?= $i + 1 ?></td>
                                <td class="cell-title">
                                    <div class="fw-semibold"><?= Html::encode($c->name) ?></div>
                                    <?php if ($c->description): ?><div class="small text-muted"><?= Html::encode($c->description) ?></div><?php endif; ?>
                                </td>
                                <td class="text-end num" data-label="Products">
                                    <?= $n ? Html::a(number_format($n), ['index', 'category_id' => $c->id]) : '0' ?>
                                </td>
                                <td class="text-end num" data-label="Order"><?= (int)$c->sort_order ?></td>
                                <td class="cell-actions text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?= Html::encode($c->name) ?>">&#8942;</button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><?= Html::a('<i class="fas fa-pen fa-fw me-1"></i>Edit', ['categories', 'edit' => $c->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                            <?php if (UserAccess::checkAccess('product/create')): ?>
                                                <li><?= Html::a('<i class="fas fa-plus fa-fw me-1"></i>Add product here', ['create', 'category_id' => $c->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><?= $n
                                                ? '<span class="dropdown-item disabled small">Remove — move its ' . $n . ' product' . ($n === 1 ? '' : 's') . ' first</span>'
                                                : Html::a('<i class="fas fa-trash fa-fw me-1"></i>Remove', ['category-delete', 'id' => $c->id], ['class' => 'dropdown-item text-danger', 'encode' => false,
                                                    'data' => ['method' => 'post', 'confirm' => 'Remove the category "' . $c->name . '"?']]) ?></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if ($canAdd): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header ui-section"><?= $editing ? 'Edit category' : 'Add category' ?></div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin(['action' => ['category-save'], 'options' => ['class' => 'ui-form'], 'id' => 'category-form']); ?>
                    <?php if ($editing): ?><?= Html::hiddenInput('id', $model->id) ?><?php endif; ?>
                    <?= $form->field($model, 'name')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 80, 'placeholder' => 'e.g. Artificial Grass', 'autofocus' => $editing])
                        ->label('Category name <span class="text-danger">*</span>', ['encode' => false]) ?>
                    <?= $form->field($model, 'description')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'Optional, one line']) ?>
                    <?= $form->field($model, 'sort_order')->input('number', ['class' => 'form-control form-control-sm', 'min' => 0, 'max' => 9999])
                        ->hint('Lower numbers are listed first.') ?>
                    <div class="d-flex gap-2">
                        <?= Html::submitButton($editing ? 'Save changes' : 'Add category', ['class' => 'btn btn-primary btn-sm']) ?>
                        <?php if ($editing): ?><?= Html::a('Cancel', ['categories'], ['class' => 'btn btn-outline-secondary btn-sm']) ?><?php endif; ?>
                    </div>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
