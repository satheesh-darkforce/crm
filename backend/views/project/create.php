<?php
use yii\bootstrap5\Html;
$this->title = 'New Project';
?>
<div class="project-create">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('project', 'clients', 'developers')) ?>
</div>
