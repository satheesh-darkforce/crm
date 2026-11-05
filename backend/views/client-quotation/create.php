<?php

/** @var yii\web\View $this */
/** @var backend\models\ClientQuotation $model */
/** @var backend\models\ClientQuotationFile $fileModel */
/** @var array $clientOptions */
/** @var array $userOptions */
/** @var int   $quotenum */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Add Quotation';

$backUrl = Url::to(['index']);
?>

<div class="ui-page client-quotation-form">
<div class="ui-head">
    <div>
        <h1>Add quotation</h1>
        <div class="ui-sub">Quote a client; the number is assigned when you save.</div>
    </div>
    <div class="ui-actions"><?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $backUrl, [
        'class'   => 'btn btn-outline-secondary btn-sm', 'encode' => false,
        'onclick' => 'event.preventDefault(); window.location.replace(this.href); return false;',
    ]) ?></div>
</div>

<?= $this->render('_form', [
    'model' => $model,
    'fileModel' => $fileModel,
    'clientOptions' => $clientOptions,
    'userOptions' => $userOptions,
    'showFile' => true,
    'quotenum' => $quotenum,
    'requireOfficialContactBeforeCreate' => true,
]) ?>
</div>
