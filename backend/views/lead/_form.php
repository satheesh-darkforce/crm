<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\User[] $users */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;
?>

<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

<div class="row g-3">
    <!-- Lead info -->
    <div class="col-md-4">
        <?= $form->field($lead, 'client_name')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Contact person name']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'company_name')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Company / organization']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'contact_no')->label('Phone')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Phone number']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'email')->input('email', ['class' => 'form-control form-control-sm', 'placeholder' => 'name@example.com']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'country')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Country']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'industry')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'e.g. Manufacturing']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'offering_service')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Service offered']) ?>
    </div>
    <div class="col-md-8">
        <?= $form->field($lead, 'subject')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Lead subject / title']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'priority')->dropDownList([
            \backend\models\Lead::PRIORITY_HIGH   => 'High',
            \backend\models\Lead::PRIORITY_MEDIUM => 'Medium',
            \backend\models\Lead::PRIORITY_LOW    => 'Low',
        ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select…']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'mode')->dropDownList([
            \backend\models\Lead::MODE_ONLINE    => 'Online',
            \backend\models\Lead::MODE_OFFLINE   => 'Offline',
            \backend\models\Lead::MODE_REFERENCE => 'Reference',
        ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select source…']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'assigned_to')->widget(Select2::class, [
            'data'          => ArrayHelper::map($users, 'user_id', 'username'),
            'options'       => ['placeholder' => 'Assign to…'],
            'pluginOptions' => ['allowClear' => true],
        ]) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'lead_value')->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm', 'placeholder' => 'Potential value']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'meeting_attended_date')->label('Meeting Date')->widget(DatePicker::class, [
            'type'          => DatePicker::TYPE_INPUT,
            'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
        ]) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($lead, 'meeting_reported_date')->label('Next Follow-up Date')->widget(DatePicker::class, [
            'type'          => DatePicker::TYPE_INPUT,
            'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
        ]) ?>
    </div>
    <div class="col-12">
        <?= $form->field($lead, 'description')->textarea(['rows' => 3, 'class' => 'form-control form-control-sm', 'placeholder' => 'Notes about this lead']) ?>
    </div>
    <div class="col-12">
        <label class="form-label">Attachments</label>
        <input type="file" name="attachments[]" class="form-control form-control-sm" multiple
               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <?= Html::submitButton($lead->isNewRecord ? 'Create Lead' : 'Update Lead', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>

<?php ActiveForm::end(); ?>
