<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Quotation $model */
/** @var \backend\models\QuotationDetail $detail */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;
?>

<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold border-bottom">Quotation Info</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <?= $form->field($model, 'client_name')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Client / company name']) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'email')->input('email', ['class' => 'form-control form-control-sm', 'placeholder' => 'name@example.com']) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'mobile')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => '10-digit mobile number']) ?>
            </div>
            <div class="col-md-8">
                <?= $form->field($model, 'project_name')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Project / quotation title']) ?>
            </div>
        </div>
    </div>
</div>

<?php if ($model->isNewRecord): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold border-bottom">Initial Quote Version</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <?= $form->field($detail, 'quote_amount')->label('Quote Amount')
                    ->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($detail, 'currency_id')->label('Currency')
                    ->widget(Select2::class, [
                        'data'          => ArrayHelper::map(
                            \backend\models\Currency::find()->where(['status' => 1])->all(),
                            'id', 'code'
                        ),
                        'options'       => ['placeholder' => 'Select currency…'],
                        'pluginOptions' => ['allowClear' => true],
                    ]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($detail, 'proposal_send_date')->label('Proposal Send Date')
                    ->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
            </div>
            <div class="col-12">
                <label class="form-label">Attachments</label>
                <input type="file" name="QuotationDetail[attachmentFiles][]"
                       class="form-control form-control-sm" multiple
                       accept=".pdf,.doc,.docx,.ppt,.pptx">
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="d-flex gap-2 mt-3">
    <?= Html::submitButton($model->isNewRecord ? 'Create PM Quotation' : 'Update PM Quotation', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>

<?php ActiveForm::end(); ?>
