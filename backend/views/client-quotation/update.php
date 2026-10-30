<?php

/** @var yii\web\View $this */
/** @var backend\models\ClientQuotation $model */
/** @var backend\models\ClientQuotationFile $fileModel */
/** @var array $clientOptions */
/** @var array $userOptions */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Update Quotation';
?>

<div class="d-flex justify-content-between mt-4 mb-2">
    <div><h3>Update Quotation</h3></div>
    <div class="d-flex gap-2">
        <?= Html::a('View Details', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-primary']) ?>
        <?= Html::a('Back', Url::to(['index']), [
            'class'   => 'btn btn-secondary',
            'onclick' => 'event.preventDefault(); window.location.replace(this.href); return false;',
        ]) ?>
    </div>
</div>

<?= $this->render('_form', [
    'model' => $model,
    'fileModel' => $fileModel,
    'clientOptions' => $clientOptions,
    'userOptions' => $userOptions,
    'showFile' => false,
]) ?>
