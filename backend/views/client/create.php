<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $client */
/** @var \backend\models\User $user */

use yii\bootstrap5\Html;

$this->title = 'Add Client';
?>
<div class="client-create">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <div class="alert alert-info py-2 small">
        <strong>NOTE:</strong> A Ledger will be created in the name of <strong>Company Name</strong>.
    </div>
    <?= $this->render('_form', ['client' => $client, 'user' => $user]) ?>
</div>
