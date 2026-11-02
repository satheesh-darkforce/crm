<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\ClientModel $client */
/** @var \backend\models\User $user */

use yii\bootstrap5\Html;

$this->title = 'Add To Client';
?>
<div class="lead-add-to-client">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['converted'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="alert alert-info py-2 small">
        Creating a client login from converted lead <strong><?= Html::encode($lead->client_name) ?></strong>.
        Details are pre-filled from the lead — complete the remaining fields.
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?= $this->render('@backend/views/client/_form', ['client' => $client, 'user' => $user, 'cancelUrl' => ['converted']]) ?>
        </div>
    </div>
</div>
