<?php
/**
 * @var \yii\web\View $this
 * @var \backend\models\InwardInvoice $model
 * @var \backend\models\Vendor $vendor
 * @var array $lines, $expenses, $ledgerGroups, $paymentTypes, $paymentLedgers
 */

use yii\bootstrap5\Html;

$this->title = 'Add Inward Invoice';
?>
<div class="ui-page inward-invoice-create">
    <div class="ui-head">
        <div>
            <h1>Add inward invoice</h1>
            <div class="ui-sub">Enter the bill exactly as the vendor issued it.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('Change vendor', ['create'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', compact('model', 'vendor', 'lines', 'expenses', 'ledgerGroups', 'paymentTypes', 'paymentLedgers')) ?>
</div>
