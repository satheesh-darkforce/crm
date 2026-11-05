<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Task $mainTask */
/** @var \backend\models\Task[] $subtasks */
/** @var \backend\models\TaskLogs[] $taskLogs */
/** @var array<int,int> $worked */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Task History';
?>
<div class="ui-page task-history">
    <div class="ui-head">
        <div>
            <h1>Task history</h1>
            <div class="ui-sub"><?= Html::encode($mainTask->task) ?> · subtasks and time logs</div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('task/view')): ?>
                <?= Html::a('<i class="fas fa-eye me-1"></i>Open task', ['view', 'id' => $mainTask->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_detail', ['task' => $mainTask, 'subtasks' => $subtasks, 'taskLogs' => $taskLogs, 'worked' => $worked]) ?>
</div>
