<?php
use yii\bootstrap5\Html;
use yii\helpers\Url;

/** @var \backend\models\Invoice $invoice */
/** @var \backend\models\ClientModel|null $preClient */
/** @var string|null $nextNo */
/** @var int $invoiceCategoryParam */

$isProforma = ((int)$invoice->invoice_role === \backend\models\Invoice::ROLE_PROFORMA);
$this->title = $isProforma ? 'Create Proforma Invoice' : 'Create Invoice';

// Back goes wherever the user came from (listing, client page, dashboard …), else to the listing.
$referer = Yii::$app->request->referrer;
$selfUrl = Url::current([], true);
$listUrl = Url::to([$isProforma ? 'proforma' : 'index']);
$backUrl = ($referer && strpos($referer, $selfUrl) === false) ? $referer : $listUrl;
?>
<div class="ui-page invoice-create">
    <div class="ui-head">
        <div>
            <h1><?= $isProforma ? 'New proforma invoice' : 'New tax invoice' ?></h1>
            <div class="ui-sub"><?= Html::encode($preClient->company_name ?? 'No client selected') ?> · totals and GST are worked out for you</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $backUrl, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', compact(
        'invoice', 'clients', 'projects', 'banks', 'nextNo', 'clientTypes',
        'preClient', 'currencies', 'hsnCodes', 'lutArn', 'invoiceCategoryParam'
    ) + ['useLines' => $useLines ?? false, 'lines' => $lines ?? [], 'lineErrors' => $lineErrors ?? []]) ?>
</div>
