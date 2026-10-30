<?php
use yii\bootstrap5\Html;
$this->title = 'New PM Project';
?>
<div class="pm-project-create">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('model', 'clients', 'developers')) ?>
</div>
