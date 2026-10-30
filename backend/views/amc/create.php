<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Amc $model */
/** @var \backend\models\Project $project */

use yii\bootstrap5\Html;

$heading = $model->isNewRecord ? 'Start AMC' : 'Edit AMC';
$this->title = $heading;
?>
<div class="amc-create">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= $heading ?></h5>
        <?= Html::a('Back', $model->isNewRecord ? ['projects'] : ['view', 'id' => $model->id], ['class' => 'btn btn-secondary btn-sm']) ?>
    </div>
    <?= $this->render('_form', ['model' => $model, 'project' => $project, 'isRenew' => false]) ?>
</div>
