<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadFollowers $model */
/** @var \backend\models\Lead $lead */
/** @var array $users */

use yii\bootstrap5\Html;

$this->title = 'Update Follow Up';
?>
<div class="lead-followers-update" style="max-width:1000px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?> <small class="text-muted fw-normal">— <?= Html::encode($lead->client_name) ?></small></h5>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <?= $this->render('_form', ['model' => $model, 'lead' => $lead, 'users' => $users]) ?>
</div>
