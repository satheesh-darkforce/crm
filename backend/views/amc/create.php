<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Amc $model */
/** @var \backend\models\Project $project */

use yii\bootstrap5\Html;

$heading     = $model->isNewRecord ? 'Start AMC' : 'Edit AMC';
$this->title = $heading;
$backUrl     = $model->isNewRecord ? ['/project/view', 'id' => $project->id] : ['view', 'id' => $model->id];
?>
<div class="ui-page amc-create" style="max-width:960px">
    <div class="ui-head">
        <div>
            <h1><?= $heading ?></h1>
            <div class="ui-sub"><?= Html::encode($project->title ?? '') ?><?= $model->isNewRecord ? ' · start date defaults to the live date' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $backUrl, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model, 'project' => $project, 'isRenew' => false, 'cancelUrl' => $backUrl]) ?>
</div>
