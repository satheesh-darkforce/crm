<?php
/** @var yii\web\View $this */
/** @var backend\models\Product $model */

use backend\models\Product;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = $model->item_name;
$f       = Yii::$app->formatter;
$money   = fn($v) => $v === null || $v === '' ? '—' : '₹' . $f->asDecimal((float)$v, 2);
$off     = $model->getDiscountPercent();
$withTax = $model->getPriceWithTax();
$used    = $model->getChallanUseCount();
$active  = (int)$model->is_active === 1;
$audit   = function (?string $at, ?int $by) use ($f) {
    if (!$at) {
        return '—';
    }
    $user = $by ? \backend\models\User::findOne($by) : null;
    return $f->asDatetime($at, 'php:d M Y, h:i A') . ($user ? ' · ' . Html::encode($user->username) : '');
};
?>
<div class="ui-page product-view" style="max-width:1180px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->item_name) ?>
                <span class="ui-pill <?= $active ? 'tone-success' : '' ?> align-middle"><?= $active ? 'Active' : 'Inactive' ?></span>
            </h1>
            <div class="ui-sub">
                <?= $model->getTypeName() ?><?= $model->category ? ' · ' . Html::encode($model->category->name) : '' ?><?= $model->product_code ? ' · ' . Html::encode($model->product_code) : '' ?>
            </div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('product/update')): ?>
                <?= Html::a('<i class="fas fa-power-off me-1"></i>' . ($active ? 'Mark inactive' : 'Mark active'), ['toggle-active', 'id' => $model->id],
                    ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false, 'data' => ['method' => 'post']]) ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="ui-tiles">
        <div class="ui-tile is-accent">
            <div class="t-label">Unit price</div>
            <div class="t-value num"><?= $model->sale_price !== null ? $money($model->sale_price) : 'On request' ?></div>
            <div class="t-sub"><?= $model->units ? 'per ' . Html::encode($model->units) : '' ?><?= $off ? ' · ' . $off . '% off MRP' : '' ?></div>
        </div>
        <div class="ui-tile">
            <div class="t-label">With GST</div>
            <div class="t-value num"><?= $withTax !== null ? $money($withTax) : '—' ?></div>
            <div class="t-sub">GST <?= $model->gst_rate !== null ? (float)$model->gst_rate . '%' : 'not set' ?></div>
        </div>
        <div class="ui-tile">
            <div class="t-label">MRP / list price</div>
            <div class="t-value num"><?= $money($model->mrp) ?></div>
            <div class="t-sub"><?= (int)$model->price_on_request ? 'Price on request' : 'Fixed rate' ?></div>
        </div>
        <div class="ui-tile">
            <div class="t-label"><?= $model->isService() ? 'SAC code' : 'HSN code' ?></div>
            <div class="t-value mono"><?= Html::encode($model->hsn_code ?: '—') ?></div>
            <div class="t-sub">Used on <?= $used ?> delivery challan line<?= $used === 1 ? '' : 's' ?></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header ui-section">Description</div>
                <div class="card-body">
                    <?php if ($model->short_description): ?><p class="fw-semibold mb-2"><?= Html::encode($model->short_description) ?></p><?php endif; ?>
                    <?php if ($model->description): ?>
                        <div class="text-body"><?= nl2br(Html::encode($model->description)) ?></div>
                    <?php elseif (!$model->short_description): ?>
                        <p class="text-muted mb-0">No description yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header ui-section">Features</div>
                        <div class="card-body">
                            <?php if ($model->featureList): ?>
                                <ul class="p-features"><?php foreach ($model->featureList as $ft): ?><li><?= Html::encode($ft) ?></li><?php endforeach; ?></ul>
                            <?php else: ?><p class="text-muted mb-0">No features listed.</p><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header ui-section">Specifications</div>
                        <?php if ($model->specList): ?>
                            <table class="table ui-detail mb-0">
                                <?php foreach ($model->specList as $s): ?>
                                    <tr><th scope="row"><?= Html::encode($s['name']) ?></th><td><?= Html::encode($s['value']) ?></td></tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?><div class="card-body"><p class="text-muted mb-0">No specifications listed.</p></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header ui-section">Image</div>
                <div class="card-body text-center">
                    <?php if ($model->image): ?>
                        <img class="p-image" src="<?= Html::encode(Yii::getAlias('@web') . $model->image) ?>" alt="<?= Html::encode($model->item_name) ?>">
                    <?php else: ?>
                        <div class="p-image-empty"><i class="fas fa-image fa-2x"></i><span>No image</span></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header ui-section">Record</div>
                <table class="table ui-detail mb-0">
                    <tr><th scope="row">Sold by</th><td><?= Html::encode($model->units ?: '—') ?><?php $grp = Product::unitGroupOf($model->units); if ($grp): ?> <span class="text-muted small">· <?= Html::encode(Product::UNIT_GROUPS[$grp]['label']) ?></span><?php endif; ?></td></tr>
                    <tr><th scope="row">Added</th><td><?= $audit($model->created_at, $model->created_by) ?></td></tr>
                    <tr><th scope="row">Last updated</th><td><?= $audit($model->updated_at, $model->updated_by) ?></td></tr>
                </table>
            </div>
            <?php if (UserAccess::checkAccess('product/delete')): ?>
                <div class="mt-3 text-end">
                    <?= Html::a('<i class="fas fa-trash me-1"></i>Remove from catalogue', ['delete', 'id' => $model->id], ['class' => 'btn btn-link btn-sm text-danger', 'encode' => false,
                        'data' => ['method' => 'post', 'confirm' => 'Remove "' . $model->item_name . '" from the catalogue? Documents that already use it keep their copy.']]) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
