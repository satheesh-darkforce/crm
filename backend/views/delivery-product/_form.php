<?php

/** @var yii\web\View $this */
/** @var backend\models\DeliveryProduct $model */

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$this->registerCssFile('@web/css/form-theme.css');
?>

<div class="app-form-page">
    <div class="app-form-shell" style="max-width: 760px;">
        <div class="app-form-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="app-form-title">
                    <?= $model->isNewRecord ? 'Add New Product' : 'Update Product' ?>
                </h4>
                <p class="app-form-subtitle text-muted">
                    <?= $model->isNewRecord
                        ? 'Create a product entry with item, HSN, and unit details.'
                        : 'Refresh the product information without changing its behavior.' ?>
                </p>
            </div>
            <?php $backUrl = Yii::$app->request->get('dc_id')
                ? Url::to(['/dc/update', 'id' => Yii::$app->request->get('dc_id')])
                : Url::to(['/delivery-product/index']); ?>
            <a class="btn btn-sm btn-secondary" href="<?= $backUrl ?>">Back</a>
        </div>

        <div class="app-form-card card">
            <div class="card-body">
                <?php $form = ActiveForm::begin([
                    'id' => 'delivery-product-form',
                    'fieldConfig' => [
                        'options' => ['class' => 'mb-3'],
                        'labelOptions' => ['class' => 'form-label'],
                        'inputOptions' => ['class' => 'form-control form-control-sm'],
                    ],
                ]); ?>

                <?= $form->field($model, 'item_name')->textInput(['placeholder' => 'Enter Product/Item Name']) ?>
                <?= $form->field($model, 'hsn_code')->textInput(['placeholder' => 'Enter HSN Code']) ?>
                <?= $form->field($model, 'units')->textInput(['placeholder' => 'e.g. PCS, NOS, MTR']) ?>

                <?php if ($dcId = Yii::$app->request->get('dc_id')): ?>
                    <input type="hidden" name="dc_id" value="<?= (int)$dcId ?>">
                <?php endif; ?>

                <div class="d-flex justify-content-end">
                    <?= Html::submitButton(
                        $model->isNewRecord ? 'Add Product' : 'Update Product',
                        ['class' => 'btn btn-success', 'id' => 'btn-submit-product']
                    ) ?>
                </div>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
</div>
