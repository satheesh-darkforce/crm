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

<div class="ui-page client-quotation-form">
<div class="ui-head">
    <div>
        <h1>Edit quotation</h1>
        <div class="ui-sub"><?= Html::encode($model->getOldAttribute('quote_no')) ?> · <?= Html::encode($model->getOldAttribute('project_name')) ?></div>
    </div>
    <div class="ui-actions">
        <?= Html::a('View details', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', Url::to(['index']), [
            'class'   => 'btn btn-outline-secondary btn-sm', 'encode' => false,
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
</div>
