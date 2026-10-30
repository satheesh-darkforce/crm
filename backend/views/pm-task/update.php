<?php
use yii\bootstrap5\Html;
$this->title = 'Edit PM Task: ' . Html::encode($task->task);
?>
<div class="pm-task-update">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('task', 'projects', 'developers')) ?>
</div>
