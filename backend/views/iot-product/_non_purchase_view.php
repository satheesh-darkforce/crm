<?php
/** @var \yii\web\View $this */
/** @var \backend\models\IotNonPurchase $model */
/** @var \backend\models\IotNonPurchaseProducts[] $items */

use yii\bootstrap5\Html;

$this->title = 'Non-Purchase Entry #' . $model->id;
?>
<div class="ui-page non-purchase-view">
    <div class="ui-head">
        <div>
            <h1>Non-Purchase Entry #<?= $model->id ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update-non-purchase', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['non-purchased'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <dl class="row mb-0" style="font-size:.875rem">
                        <dt class="col-sm-4 text-muted">Project</dt>
                        <dd class="col-sm-8 fw-semibold"><?= Html::encode($model->project->title ?? '—') ?></dd>
                        <dt class="col-sm-4 text-muted">Created</dt>
                        <dd class="col-sm-8"><?= $model->created_at ? date('d M Y', strtotime($model->created_at)) : '—' ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold py-2">Products Required</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Product Name</th><th class="text-center">Quantity</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><?= Html::encode($item->product->product_name ?? 'ID:'.$item->product_id) ?></td>
                        <td class="text-center fw-semibold"><?= $item->quantity ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="3" class="text-muted text-center py-3">No products listed.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
