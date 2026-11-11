<?php
use yii\bootstrap5\Html;

/** @var \backend\models\Invoice $invoice */

$isProforma = ((int)$invoice->invoice_role === \backend\models\Invoice::ROLE_PROFORMA);
$label      = $isProforma ? 'proforma' : 'invoice';
$isDraft    = (int)$invoice->status === -1;
$displayNo  = $invoice->invoice_no ?: ('draft #' . $invoice->id);

$this->title = 'Edit ' . ucfirst($label) . ' ' . $displayNo;
$nextNo = $invoice->invoice_no;
?>
<div class="ui-page invoice-update">
    <div class="ui-head">
        <div>
            <h1>Edit <?= $label ?> <span class="mono"><?= Html::encode($displayNo) ?></span></h1>
            <div class="ui-sub"><?= Html::encode($invoice->client->company_name ?? '') ?><?= $isDraft ? ' · not saved yet — preview, then "Save and download"' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $isDraft
                ? [$isProforma ? 'preview-proforma' : 'preview-invoice', 'id' => $invoice->id]
                : [$isProforma ? 'proforma' : 'index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', compact(
        'invoice', 'clients', 'projects', 'banks', 'nextNo', 'clientTypes',
        'preClient', 'currencies', 'hsnCodes', 'lutArn', 'invoiceCategoryParam'
    ) + ['useLines' => $useLines ?? false, 'lines' => $lines ?? [], 'lineErrors' => $lineErrors ?? []]) ?>
</div>
