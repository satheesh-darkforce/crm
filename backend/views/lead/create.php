<?php
use yii\bootstrap5\Html;
$this->title = 'Add Lead';
?>
<div class="lead-create">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('lead', 'users')) ?>
</div>
