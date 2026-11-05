<?php
/** @var yii\web\View $this */
/** @var backend\models\Product $model */
/** @var array<int,string> $categories */

use yii\bootstrap5\Html;

$this->title = 'Edit: ' . $model->item_name;
?>
<div class="ui-page product-update" style="max-width:1180px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub">Changes apply to new quotations and invoices only</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model, 'categories' => $categories]) ?>
</div>
