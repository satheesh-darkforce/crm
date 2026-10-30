<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\ClientModel $client */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Convert Lead to Client';
?>
<div class="lead-convert-client" style="max-width:720px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Convert Lead to Client</h5>
            <small class="text-muted">Lead: <?= Html::encode($lead->client_name) ?> — <?= Html::encode($lead->subject) ?></small>
        </div>
        <?= Html::a('Back to Lead', ['view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <?php if ($client->hasErrors()): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($client->getErrors() as $errs): foreach ($errs as $e): ?>
                    <li><?= Html::encode($e) ?></li>
                <?php endforeach; endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(); ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold py-2">Client Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <?= $form->field($client, 'client_name')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($client, 'company_name')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($client, 'phone')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($client, 'alt_phone')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($client, 'email_id')->input('email', ['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($client, 'contact_person')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($client, 'gst_no')->label('GST No.')->textInput(['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($client, 'client_type')->dropDownList([
                        \backend\models\ClientModel::TYPE_INTERSTATE    => 'InterState',
                        \backend\models\ClientModel::TYPE_INTRASTATE    => 'IntraState',
                        \backend\models\ClientModel::TYPE_INTERNATIONAL => 'International',
                    ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select type…']) ?>
                </div>
                <div class="col-12">
                    <?= $form->field($client, 'address')->textarea(['rows' => 3, 'class' => 'form-control form-control-sm']) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <?= Html::submitButton('Convert to Client', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Cancel', ['view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
