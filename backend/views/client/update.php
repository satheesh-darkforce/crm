<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $client */

use backend\models\User;
use yii\bootstrap5\Html;

$this->title = 'Edit Client';
// Clients migrated from Yii1 often lack fields that are now required.
$missing = $client->hasErrors() ? [] : $client->missingRequiredFields();
?>
<div class="ui-page client-update" style="max-width:1100px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub"><?= Html::encode($client->client_name) ?> · changing the company name also renames its ledger.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $client->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?php if ($missing): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="fas fa-info-circle mt-1"></i>
        <div>This client's record is incomplete. Fill in <strong><?= Html::encode(implode(', ', $missing)) ?></strong> to save changes.</div>
    </div>
    <?php endif; ?>
    <?= $this->render('_form', ['client' => $client, 'user' => new User(), 'isUpdate' => true, 'cancelUrl' => ['view', 'id' => $client->id]]) ?>
</div>
