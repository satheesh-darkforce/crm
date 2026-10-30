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

<div class="d-flex justify-content-between mt-4 mb-2">
    <div><h3>Add Quotation</h3></div>
    <div><?= Html::a('Back', $backUrl, [
        'class'   => 'btn btn-secondary',
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
