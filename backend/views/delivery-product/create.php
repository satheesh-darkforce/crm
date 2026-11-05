<?php
/** @var yii\web\View $this */
/** @var backend\models\DeliveryProduct $model */

use yii\bootstrap5\Html;

$this->title = 'Add Product';
$dcId = (int)Yii::$app->request->get('dc_id', 0);
?>
<div class="ui-page delivery-product-create" style="max-width:760px">
    <div class="ui-head">
        <div>
            <h1>Add product</h1>
            <div class="ui-sub">Products can be picked on delivery challans.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $dcId ? ['/dc/update', 'id' => $dcId] : ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model]) ?>
</div>
