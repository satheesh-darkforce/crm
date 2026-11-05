<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task $task */
/** @var array $projects */
/** @var array $developers */

use yii\bootstrap5\Html;

$this->title = 'New Task';
?>
<div class="ui-page task-create" style="max-width:1100px">
    <div class="ui-head">
        <div>
            <h1>New task</h1>
            <div class="ui-sub">The assignee accepts it before starting work.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['assigned-tasks'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', compact('task', 'projects', 'developers')) ?>
</div>
