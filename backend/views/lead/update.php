<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var array $users */

use yii\bootstrap5\Html;

$this->title = 'Update Lead';
?>
<div class="ui-page lead-update">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub"><?= Html::encode($lead->client_name) ?><?= $lead->company_name ? ' · ' . Html::encode($lead->company_name) : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['lead' => $lead, 'users' => $users, 'cancelUrl' => ['view', 'id' => $lead->id]]) ?>
</div>
