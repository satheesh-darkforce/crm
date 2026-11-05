<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\LeadMaster $prospect */
/** @var array $users */

use yii\bootstrap5\Html;

$this->title = 'Convert to Lead';
?>
<div class="ui-page lead-convert">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub"><?= Html::encode($prospect->getFullName()) ?> · <?= Html::encode($prospect->account_name) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="fas fa-info-circle mt-1"></i>
        <div>Details are pre-filled from the prospect. Once the lead is saved, the prospect is removed from Lead Master.</div>
    </div>
    <?= $this->render('_form', ['lead' => $lead, 'users' => $users, 'cancelUrl' => ['lead-master']]) ?>
</div>
