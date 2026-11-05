<?php
/** @var yii\web\View $this */
/** @var backend\models\DeliveryProduct $model */

use yii\bootstrap5\Html;

$this->title = 'Edit Product';
?>
<div class="ui-page delivery-product-update" style="max-width:760px">
    <div class="ui-head">
        <div>
            <h1>Edit product</h1>
            <div class="ui-sub"><?= Html::encode($model->getOldAttribute('item_name')) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model]) ?>
</div>
