<?php
use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var \backend\models\Invoice $invoice */
/** @var \backend\models\ClientModel|null $preClient */
/** @var string|null $nextNo */
/** @var int $invoiceCategoryParam */

$isProforma = ((int)$invoice->invoice_role === \backend\models\Invoice::ROLE_PROFORMA);
$this->title = $isProforma ? 'Create Proforma Invoice' : 'Create Invoice';

// Use the referer for the back button so the user lands wherever they came
// from (listing, client view, dashboard, etc.). Fall back to the matching
// listing page if no referer is available or it points back to this page.
$referer    = Yii::$app->request->referrer;
$selfUrl    = Url::current([], true);
$listUrl    = Url::to([$isProforma ? 'proforma' : 'index']);
$backUrl    = ($referer && strpos($referer, $selfUrl) === false) ? $referer : $listUrl;
?>
<div class="invoice-create">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">
            <strong>Create</strong> <span class="text-muted"><?= $isProforma ? 'Proforma Invoice' : 'Invoice' ?></span>
        </h4>
        <?= Html::a('<i class="bi bi-arrow-left"></i> Back', $backUrl, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <?= $this->render('_form', compact(
        'invoice', 'clients', 'projects', 'banks', 'nextNo', 'clientTypes',
        'preClient', 'currencies', 'hsnCodes', 'lutArn', 'invoiceCategoryParam'
    )) ?>
</div>
