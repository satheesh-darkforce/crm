<?php
/** @var \yii\web\View $this */
/** @var \backend\models\AmcRenew $model */
/** @var \backend\models\Amc $amc */
/** @var \backend\models\Project $project */

use yii\bootstrap5\Html;

$heading     = $model->isNewRecord ? 'Renew AMC' : 'Edit Renewal';
$this->title = $heading;
$d           = fn($v) => $v ? date('d M Y', strtotime($v)) : '—';
$currentEnd  = $amc->getCurrentEndDate();
?>
<div class="ui-page amc-renew" style="max-width:960px">
    <div class="ui-head">
        <div>
            <h1><?= $heading ?></h1>
            <div class="ui-sub"><?= Html::encode($project->title ?? '') ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $amc->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="fas fa-info-circle mt-1"></i>
        <div>
            Warranty: <strong><?= (int)$amc->warranty_period ?> month(s)</strong> (<?= $d($amc->start_date) ?> → <?= $d($amc->end_date) ?>).
            Current period ends <strong><?= $d($currentEnd) ?></strong><?= $model->isNewRecord ? ' — the renewal starts the next day by default.' : '.' ?>
        </div>
    </div>

    <?= $this->render('_form', ['model' => $model, 'project' => $project, 'isRenew' => true, 'cancelUrl' => ['view', 'id' => $amc->id]]) ?>
</div>
