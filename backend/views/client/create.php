<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $client */
/** @var \backend\models\User $user */

use yii\bootstrap5\Html;

$this->title = 'Add Client';
?>
<div class="ui-page client-create" style="max-width:1100px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub">Creates the client, its portal login and a ledger named after the company.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['client' => $client, 'user' => $user]) ?>
</div>
