<?php
use yii\bootstrap5\Html;
$isProforma = ((int)$invoice->invoice_role === \backend\models\Invoice::ROLE_PROFORMA);
$label      = $isProforma ? 'Proforma' : 'Invoice';
$listAction = $isProforma ? 'proforma' : 'index';
$displayNo  = $invoice->invoice_no ?: ('#' . $invoice->id);

$this->title = 'Edit ' . $label . ' ' . $displayNo;
?>
<div class="invoice-update">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?php $nextNo = $invoice->invoice_no; ?>
    <?= $this->render('_form', compact(
        'invoice', 'clients', 'projects', 'banks', 'nextNo', 'clientTypes',
        'preClient', 'currencies', 'hsnCodes', 'lutArn', 'invoiceCategoryParam'
    )) ?>
</div>
