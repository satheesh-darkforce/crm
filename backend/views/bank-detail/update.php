<?php
/** @var \yii\web\View $this */
/** @var \backend\models\BankDetail $model */

use yii\bootstrap5\Html;

$this->title = 'Edit Bank Account';
$count = $model->getInvoiceCount();
?>
<div class="ui-page bank-detail-update" style="max-width:860px">
    <div class="ui-head">
        <div>
            <h1>Edit bank account</h1>
            <div class="ui-sub"><?= Html::encode($model->getOldAttribute('bank_name')) ?> · <?= Html::encode($model->getOldAttribute('branch')) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?php if ($count): ?>
        <div class="alert alert-info small">This account is printed on <?= number_format($count) ?> invoice<?= $count == 1 ? '' : 's' ?>; changes show on those invoices too.</div>
    <?php endif; ?>
    <?= $this->render('_form', ['model' => $model]) ?>
</div>
