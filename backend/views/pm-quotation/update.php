<?php
use yii\bootstrap5\Html;
$this->title = 'Edit PM Quotation: ' . Html::encode($model->unique_id ?: '#' . $model->id);
?>
<div class="pm-quotation-update">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('model', 'detail')) ?>
</div>
