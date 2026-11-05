<?php
/** @var yii\web\View $this */
/** @var backend\models\Product $model */
/** @var array<int,string> $categories */

use yii\bootstrap5\Html;

$this->title = 'Add product';
?>
<div class="ui-page product-create" style="max-width:1180px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub">Add a product or service to the catalogue</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model, 'categories' => $categories]) ?>
</div>
