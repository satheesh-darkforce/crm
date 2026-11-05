<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Domain $domain */
/** @var \backend\models\DomainRenewalHistory $model */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use kartik\date\DatePicker;

$this->title = 'Domain Renewal: ' . $domain->domain_name;

$renewedDefault        = $model->renewed_date         ?: date('Y-m-d');
$expiryDefault         = $model->expiry_date          ?: date('Y-m-d', strtotime('+1 year'));
$amountReceivedDefault = $model->amount_received_date ?: date('Y-m-d');

$modeOptions = [
    ''  => '-Select-',
    1   => 'Cash',
    2   => 'Cheque',
    3   => 'Online Transfer',
    4   => 'Others',
];
?>
<div class="ui-page domain-renewal-form">
    <div class="ui-head">
        <div>
            <h1>Record Renewal &mdash; <?= Html::encode($domain->domain_name) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['view', 'id' => $domain->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2" style="font-size:.875rem">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">Client</dt>
                        <dd class="col-sm-7 fw-semibold"><?= Html::encode($domain->client->client_name ?? '—') ?></dd>
                        <dt class="col-sm-5 text-muted">Current Expiry</dt>
                        <dd class="col-sm-7 <?= ($domain->expiry_date && $domain->expiry_date < date('Y-m-d')) ? 'text-danger fw-semibold' : '' ?>">
                            <?= $domain->expiry_date ? date('d M Y', strtotime($domain->expiry_date)) : '—' ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p class="help-block text-muted small mb-3">Fields with <span class="text-danger">*</span> are required.</p>

            <?php $form = ActiveForm::begin([
                'id' => 'domain-renewal-history-form',
                'options' => ['enctype' => 'multipart/form-data'],
                'enableClientValidation' => true,
                'enableAjaxValidation' => false,
            ]); ?>

            <?= $form->errorSummary($model) ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <?= $form->field($model, 'renewed_date')->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'value'         => $renewedDefault,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select Renewal Date', 'value' => $renewedDefault],
                        'pluginOptions' => [
                            'autoclose' => true,
                            'format'    => 'yyyy-mm-dd',
                            'endDate'   => '0d',
                        ],
                    ])->label('Renewed Date <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>

                <div class="col-md-4">
                    <?= $form->field($model, 'expiry_date')->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'value'         => $expiryDefault,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select Expiry Date', 'value' => $expiryDefault],
                        'pluginOptions' => [
                            'autoclose' => true,
                            'format'    => 'yyyy-mm-dd',
                            'startDate' => '0d',
                        ],
                    ])->label('Expiry Date <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>

                <div class="col-md-4">
                    <?= $form->field($model, 'amount_received_date')->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'value'         => $amountReceivedDefault,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select Amount Received Date', 'value' => $amountReceivedDefault],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>

                <div class="col-md-4">
                    <?= $form->field($model, 'amount_received')->textInput([
                        'class' => 'form-control form-control-sm',
                        'maxlength' => 8,
                        'placeholder' => 'e.g. 1500.00',
                    ]) ?>
                </div>

                <div class="col-md-4">
                    <?= $form->field($model, 'amount_received_by')->textInput([
                        'class' => 'form-control form-control-sm',
                        'maxlength' => 100,
                        'placeholder' => 'Received by (name)',
                    ]) ?>
                </div>

                <div class="col-md-4">
                    <?= $form->field($model, 'mode_of_payment')->dropDownList($modeOptions, [
                        'class' => 'form-select form-select-sm',
                        'prompt' => '-Select-',
                    ])->label('Mode Of Payment') ?>
                </div>

                <div class="col-md-4">
                    <?= $form->field($model, 'invoice_number')->textInput([
                        'class' => 'form-control form-control-sm',
                        'maxlength' => 200,
                        'placeholder' => 'e.g. INV-2025-001',
                    ]) ?>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label class="form-label">Invoice Attachment</label>
                        <?php if (!empty($model->invoice_attachment)): ?>
                            <div class="mb-2">
                                <?= Html::img(Yii::$app->request->baseUrl . $model->invoice_attachment, ['alt' => 'No Image Found', 'style' => 'max-width:240px; max-height:80px']) ?>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="img" class="form-control form-control-sm" accept=".jpg,.jpeg,.gif,.png,.pdf,.doc,.docx">
                        <div class="form-text small">Allowed: jpg, jpeg, gif, png, pdf, doc, docx. Max 2 MB.</div>
                    </div>
                </div>

                <div class="col-md-4 d-flex align-items-center">
                    <div class="form-check mt-2">
                        <input type="hidden" name="DomainRenewalHistory[with_gst]" value="0">
                        <input type="checkbox"
                               class="form-check-input"
                               id="drh-with-gst"
                               name="DomainRenewalHistory[with_gst]"
                               value="1"
                               <?= !empty($model->with_gst) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="drh-with-gst">With GST</label>
                    </div>
                </div>

                <div class="col-12">
                    <?= $form->field($model, 'notes')->textarea([
                        'rows' => 6,
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'Any notes about this renewal...',
                    ]) ?>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm px-4 submit_btn']) ?>
                <?= Html::a('Cancel', ['view', 'id' => $domain->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$js = <<<JS
jQuery(function(\$){
    \$('input[name="img"]').on('change', function(){
        var f = this.files && this.files[0];
        if (f && f.size > 2 * 1024 * 1024) {
            Swal.fire({ icon: 'warning', title: 'File too large', text: 'File size must be smaller than 2 MB.' });
            \$(this).val('');
        }
    });

    var \$form    = \$('#domain-renewal-history-form');
    var \$renewed = \$form.find('input[name="DomainRenewalHistory[renewed_date]"]');
    var \$expiry  = \$form.find('input[name="DomainRenewalHistory[expiry_date]"]');

    // When renewed_date changes, move expiry's minimum forward so the user
    // cannot pick an expiry earlier than the renewal date.
    \$renewed.on('change', function(){
        var v = \$(this).val();
        if (v) {
            \$expiry.kvDatepicker('setStartDate', v);
            if (\$expiry.val() && new Date(\$expiry.val()) < new Date(v)) {
                \$expiry.val('').kvDatepicker('update');
            }
        } else {
            \$expiry.kvDatepicker('setStartDate', false);
        }
    });

    // On page load enforce the same constraint in case renewed_date has a value.
    if (\$renewed.val()) {
        \$expiry.kvDatepicker('setStartDate', \$renewed.val());
    }

    // Mirror compareDateRange server-side validator so the form never submits
    // when expiry < renewed — no page refresh on that error.
    var renewedAttrId = 'domainrenewalhistory-renewed_date';
    \$form.on('afterValidate', function (event, messages, errorAttributes) {
        var renewed = \$renewed.val();
        var expiry  = \$expiry.val();
        if (renewed && expiry && new Date(expiry) < new Date(renewed)) {
            var msg = 'The expired date can not be less/before renewed date.';
            messages[renewedAttrId] = messages[renewedAttrId] || [];
            if (messages[renewedAttrId].indexOf(msg) === -1) {
                messages[renewedAttrId].push(msg);
            }
            var already = false;
            for (var i = 0; i < errorAttributes.length; i++) {
                if (errorAttributes[i].id === renewedAttrId) { already = true; break; }
            }
            if (!already) {
                var cfg = \$form.data('yiiActiveForm');
                if (cfg && cfg.attributes) {
                    for (var j = 0; j < cfg.attributes.length; j++) {
                        if (cfg.attributes[j].id === renewedAttrId) {
                            errorAttributes.push(cfg.attributes[j]);
                            break;
                        }
                    }
                }
            }
        }
    });
});
JS;
$this->registerJs($js, \yii\web\View::POS_READY);
?>
