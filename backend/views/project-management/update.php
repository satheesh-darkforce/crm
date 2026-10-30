<?php
use yii\bootstrap5\Html;
$this->title = 'Edit PM Project: ' . Html::encode($model->title);
?>
<div class="pm-project-update">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('model', 'clients', 'developers', 'phases')) ?>
</div>
