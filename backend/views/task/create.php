<?php
use yii\bootstrap5\Html;
$this->title = 'New Task';
?>
<div class="task-create">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('task', 'projects', 'developers')) ?>
</div>
