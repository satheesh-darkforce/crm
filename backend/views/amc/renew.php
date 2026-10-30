<?php
/** @var \yii\web\View $this */
/** @var \backend\models\AmcRenew $model */
/** @var \backend\models\Amc $amc */
/** @var \backend\models\Project $project */

use yii\bootstrap5\Html;

$heading = $model->isNewRecord ? 'Renew AMC' : 'Edit Renewal';
$this->title = $heading;
?>
<div class="amc-renew">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= $heading ?></h5>
        <?= Html::a('Back', ['view', 'id' => $amc->id], ['class' => 'btn btn-secondary btn-sm']) ?>
    </div>

    <div class="alert alert-info py-2 small">
        Original warranty: <strong><?= (int)$amc->warranty_period ?> month(s)</strong>
        (<?= $amc->start_date ? date('d-m-Y', strtotime($amc->start_date)) : '—' ?>
        &rarr; <?= $amc->end_date ? date('d-m-Y', strtotime($amc->end_date)) : '—' ?>).
        Current end date: <strong><?= ($ce = $amc->getCurrentEndDate()) ? date('d-m-Y', strtotime($ce)) : '—' ?></strong>.
    </div>

    <?= $this->render('_form', ['model' => $model, 'project' => $project, 'isRenew' => true]) ?>
</div>
