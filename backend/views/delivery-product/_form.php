<?php
/** @var yii\web\View $this */
/** @var backend\models\DeliveryProduct $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$dcId = (int)Yii::$app->request->get('dc_id', 0);
?>
<?php $form = ActiveForm::begin(['id' => 'delivery-product-form', 'options' => ['class' => 'ui-form']]); ?>
<?= $form->errorSummary($model, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
<p class="ui-req-note">Fields marked <b>*</b> are required.</p>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Product</div>
    <div class="card-body row g-3">
        <div class="col-12"><?= $form->field($model, 'item_name')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'e.g. 32-channel IoT controller', 'autocomplete' => 'off']) ?></div>
        <div class="col-sm-6"><?= $form->field($model, 'hsn_code')->textInput(['class' => 'form-control form-control-sm mono js-digits', 'maxlength' => 8, 'inputmode' => 'numeric', 'placeholder' => 'e.g. 90328990'])
            ->hint('4–8 digits, printed on delivery challans.') ?></div>
        <div class="col-sm-6"><?= $form->field($model, 'units')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'Nos', 'list' => 'dp-units']) ?>
            <datalist id="dp-units"><option value="Nos"><option value="Pcs"><option value="Set"><option value="Mtr"><option value="Kg"></datalist></div>
    </div>
</div>

<?php if ($dcId): ?><input type="hidden" name="dc_id" value="<?= $dcId ?>"><?php endif; ?>

<div class="ui-form-actions">
    <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . ($model->isNewRecord ? 'Add product' : 'Save changes'), ['class' => 'btn btn-primary btn-sm px-4']) ?>
    <?= Html::a('Cancel', $dcId ? ['/dc/update', 'id' => $dcId] : ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>
<?php ActiveForm::end(); ?>
