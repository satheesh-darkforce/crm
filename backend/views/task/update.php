<?php
use yii\bootstrap5\Html;
$this->title = 'Edit Task: ' . $task->task;
?>
<div class="task-update">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('task', 'projects', 'developers')) ?>
</div>
