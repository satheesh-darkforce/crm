<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Project $project */
/** @var \backend\models\ClientModel[] $clients */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[] $existingPhases */

use yii\bootstrap5\Html;

$this->title = 'Edit Project';
?>
<div class="ui-page project-update" style="max-width:1200px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub">#<?= (int)$project->id ?> · <?= Html::encode($project->getOldAttribute('title') ?: $project->title) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $project->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', compact('project', 'clients', 'developers', 'existingPhases')) ?>
</div>
