<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadMaster $model */

use yii\bootstrap5\Html;

$this->title = 'Lead Master';
?>
<div class="lead-master-view" style="max-width:640px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($model->getFullName()) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm">
        <?= $this->render('_lead-master-detail', ['model' => $model]) ?>
    </div>
</div>
