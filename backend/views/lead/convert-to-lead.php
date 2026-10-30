<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\User[] $users */
/** @var \backend\models\LeadMaster $prospect */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Convert Prospect to Lead';
?>
<div class="lead-convert" style="max-width:820px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Convert Prospect to Lead</h5>
            <small class="text-muted">Prospect: <?= Html::encode($prospect->getFullName()) ?> — <?= Html::encode($prospect->account_name) ?></small>
        </div>
        <?= Html::a('Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <?php if ($lead->hasErrors()): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($lead->getErrors() as $errs): foreach ($errs as $e): ?>
                    <li><?= Html::encode($e) ?></li>
                <?php endforeach; endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold py-2">Lead Information</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <?= $form->field($lead, 'client_name')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'company_name')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'contact_no')->label('Phone')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'email')->input('email', ['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'country')->label('City')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'industry')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-8">
                    <?= $form->field($lead, 'subject')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'What is this lead about?']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'priority')->dropDownList([
                        \backend\models\Lead::PRIORITY_HIGH   => 'High',
                        \backend\models\Lead::PRIORITY_MEDIUM => 'Medium',
                        \backend\models\Lead::PRIORITY_LOW    => 'Low',
                    ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select priority…']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'mode')->dropDownList([
                        \backend\models\Lead::MODE_ONLINE    => 'Online',
                        \backend\models\Lead::MODE_OFFLINE   => 'Offline',
                        \backend\models\Lead::MODE_REFERENCE => 'Reference',
                    ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select source…']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'assigned_to')->dropDownList(
                        ArrayHelper::map($users, 'user_id', 'username'),
                        ['class' => 'form-select form-select-sm', 'prompt' => 'Assign to…']
                    ) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($lead, 'lead_value')->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Next Follow-up Date</label>
                    <?= \kartik\date\DatePicker::widget([
                        'name'          => 'Lead[meeting_reported_date]',
                        'value'         => $lead->meeting_reported_date ? substr($lead->meeting_reported_date, 0, 10) : '',
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Next Follow-up Date'],
                    ]) ?>
                </div>
                <div class="col-12">
                    <?= $form->field($lead, 'description')->textarea(['rows' => 3, 'class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-12">
                    <label class="form-label small">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <?= Html::submitButton('Convert to Lead', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Cancel', ['lead-master'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
