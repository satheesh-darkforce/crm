<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\ClientModel $client */
/** @var \backend\models\User $user */

use yii\bootstrap5\Html;

$this->title = 'Add To Client';
?>
<div class="ui-page lead-add-to-client">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub">From converted lead <?= Html::encode($lead->client_name) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['converted'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="fas fa-info-circle mt-1"></i>
        <div>Creates a client login for this lead. Details are pre-filled from the lead — complete the remaining fields.</div>
    </div>
    <?= $this->render('@backend/views/client/_form', ['client' => $client, 'user' => $user, 'cancelUrl' => ['converted']]) ?>
</div>
