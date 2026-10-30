<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $client */

use yii\bootstrap5\Html;

$this->title = 'Edit Client: ' . $client->client_name;
?>
<div class="client-update">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <div class="alert alert-info py-2 small">
        <strong>NOTE:</strong> Ledger name will be updated if <strong>Company Name</strong> is changed.
    </div>
    <?= $this->render('_form', ['client' => $client, 'user' => new \backend\models\User(), 'isUpdate' => true]) ?>
</div>
