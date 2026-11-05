<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task $task */
/** @var array $projects */
/** @var array $developers */

use yii\bootstrap5\Html;

$this->title = 'Edit Task';
?>
<div class="ui-page task-update" style="max-width:1100px">
    <div class="ui-head">
        <div>
            <h1>Edit task</h1>
            <div class="ui-sub">#<?= (int)$task->id ?> · <?= Html::encode($task->getOldAttribute('task') ?: $task->task) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $task->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', compact('task', 'projects', 'developers')) ?>
</div>
