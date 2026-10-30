<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Tasks $task */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\Developer[] $allDevelopers */
/** @var int[] $assignedDevIds */

$this->title = 'Update Task: ' . $task->task;
?>

<div class="tasks-update">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= $this->title ?></h5>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?= $this->render('_form', compact('task', 'projects', 'allDevelopers', 'assignedDevIds')) ?>
        </div>
    </div>
</div>
