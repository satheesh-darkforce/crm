<?php
/** @var \yii\web\View $this */
/** @var \backend\models\BankDetail $model */

use yii\bootstrap5\Html;

$this->title = 'Add Bank Account';
?>
<div class="ui-page bank-detail-create" style="max-width:860px">
    <div class="ui-head">
        <div>
            <h1>Add bank account</h1>
            <div class="ui-sub">Accounts appear under "Payment bank info" on invoices.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model]) ?>
</div>
