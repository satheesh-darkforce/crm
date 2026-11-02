<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $client */
/** @var \backend\models\User $user */
/** @var bool $isUpdate */
/** @var array|string|null $cancelUrl  Defaults to the client list (overridden by lead "Add To Client") */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isUpdate  = $isUpdate ?? false;
$cancelUrl = $cancelUrl ?? ['/client/index'];
?>

<?php $form = ActiveForm::begin(['successCssClass' => '']); ?>

<p class="text-muted small mb-3">Fields with <span class="text-danger">*</span> are required.</p>

<?php /* ── Error summary block ── */ ?>
<?php
$allErrors = $client->getErrors();
if (isset($user) && $user->hasErrors()) {
    $allErrors = array_merge($allErrors, $user->getErrors());
}
if (!empty($allErrors)): ?>
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <strong class="small">Please fix the following errors:</strong>
    <ul class="mb-0 small">
        <?php foreach ($allErrors as $fieldErrors): ?>
            <?php foreach ((array)$fieldErrors as $err): ?>
                <li><?= Html::encode($err) ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <?php if (!$isUpdate): ?>
    <div class="col-12">
        <div class="card border-secondary mb-2">
            <div class="card-header bg-light py-2"><strong>Login Account</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <?php $usernameErr = isset($user) ? $user->getFirstError('username') : null; ?>
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control form-control-sm <?= $usernameErr ? 'is-invalid' : '' ?>"
                               value="<?= Html::encode(Yii::$app->request->post('username', '')) ?>"
                               maxlength="250" placeholder="Enter username">
                        <?php if ($usernameErr): ?>
                        <div class="invalid-feedback"><?= Html::encode($usernameErr) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <?php $pwdErr = isset($user) ? $user->getFirstError('password') : null; ?>
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="password" name="password" id="client-password"
                                   class="form-control form-control-sm <?= $pwdErr ? 'is-invalid' : '' ?>" maxlength="128" placeholder="Enter password">
                            <button type="button" class="btn btn-outline-secondary js-toggle-pwd" data-target="#client-password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <?php if ($pwdErr): ?>
                        <div class="invalid-feedback d-block"><?= Html::encode($pwdErr) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-md-4">
        <?= $form->field($client, 'client_name')->label('Client Name <span class="text-danger">*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Enter client name']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($client, 'company_name')->label('Company Name <span class="text-danger">*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Enter company name']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($client, 'contact_person')->label('Contact Person <span class="text-danger">*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Enter contact person']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($client, 'email_id')->label('Email Id <span class="text-danger">*</span>', ['encode' => false])->input('email', ['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'Enter email address']) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($client, 'gst_registration_type')->label('GST Registration Type <span class="text-danger">*</span>', ['encode' => false])->dropDownList([
            \backend\models\ClientModel::GST_REG_REGULAR  => 'Regular',
            \backend\models\ClientModel::GST_REG_CONSUMER => 'Consumer',
        ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select…']) ?>
    </div>
    <div class="col-md-4">
        <?php
            // Asterisk visibility is toggled in JS based on gst_registration_type.
            $gstRequired = ((int)$client->gst_registration_type === \backend\models\ClientModel::GST_REG_REGULAR);
        ?>
        <?= $form->field($client, 'gst_no')->label('GST No. <span class="text-danger gst-required-mark"' . ($gstRequired ? '' : ' style="display:none"') . '>*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'e.g. 33ABCDE1234F1Z5', 'maxlength' => 15]) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($client, 'client_type')->label('Client Type <span class="text-danger">*</span>', ['encode' => false])->dropDownList([
            \backend\models\ClientModel::TYPE_INTERSTATE    => 'Inter-State',
            \backend\models\ClientModel::TYPE_INTRASTATE    => 'Intra-State',
            \backend\models\ClientModel::TYPE_INTERNATIONAL => 'International',
        ], ['class' => 'form-select form-select-sm', 'prompt' => 'Select type…']) ?>
    </div>
    <?php
        // Phone length is client_type-aware: International allows up to 15
        // digits, Inter/Intra-State require exactly 10. JS below re-applies
        // this when client_type changes.
        $isIntl       = ((int)$client->client_type === \backend\models\ClientModel::TYPE_INTERNATIONAL);
        $phoneMax     = $isIntl ? 15 : 10;
        $phoneHint    = $isIntl ? 'Enter up to 15-digit phone number' : 'Enter 10-digit phone number';
        $altPhoneHint = $isIntl ? 'Enter alternate phone (up to 15 digits)' : 'Enter alternate phone number';
    ?>
    <div class="col-md-4">
        <?= $form->field($client, 'phone')->label('Phone <span class="text-danger">*</span>', ['encode' => false])->textInput([
            'class'       => 'form-control form-control-sm js-client-phone',
            'maxlength'   => $phoneMax,
            'placeholder' => $phoneHint,
            'inputmode'   => 'numeric',
            'oninput'     => "this.value=this.value.replace(/[^0-9]/g,'')",
        ]) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($client, 'alt_phone')->label('Alt Phone')->textInput([
            'class'       => 'form-control form-control-sm js-client-phone',
            'maxlength'   => $phoneMax,
            'placeholder' => $altPhoneHint,
            'inputmode'   => 'numeric',
            'oninput'     => "this.value=this.value.replace(/[^0-9]/g,'')",
        ]) ?>
    </div>
    <div class="col-md-4">
        <?= $form->field($client, 'tax_exemption')->label('Tax Exemption <span class="text-danger">*</span>', ['encode' => false])->dropDownList([0 => 'No', 1 => 'Yes'], [
            'class' => 'form-select form-select-sm',
            'id'    => 'tax-exemption-select',
        ]) ?>
    </div>
    <div class="col-md-8" id="tax-reason-wrap" style="<?= $client->tax_exemption == 1 ? '' : 'display:none' ?>">
        <?= $form->field($client, 'tax_exemption_reason')->textarea([
            'rows'        => 2,
            'class'       => 'form-control form-control-sm',
            'placeholder' => 'Reason for tax exemption…',
        ])->label('Tax Exemption Reason') ?>
    </div>
    <div class="col-12">
        <?= $form->field($client, 'address')->label('Address <span class="text-danger">*</span>', ['encode' => false])->textarea(['rows' => 2, 'class' => 'form-control form-control-sm', 'placeholder' => 'Enter full address']) ?>
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <?= Html::submitButton($isUpdate ? 'Update Client' : 'Create Client', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancel', $cancelUrl, [
        'class' => 'btn btn-secondary',
        'onclick' => 'event.preventDefault(); window.location.replace(this.href); return false;',
    ]) ?>
</div>

<?php ActiveForm::end();
$this->registerJs(<<<JS
$('#tax-exemption-select').on('change', function() {
    $('#tax-reason-wrap').toggle(\$(this).val() === '1');
});
$('.js-toggle-pwd').on('click', function() {
    var input = $($(this).data('target'));
    var icon = $(this).find('i');
    if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
        input.attr('type', 'password');
        icon.removeClass('bi-eye-slash').addClass('bi-eye');
    }
});

// GST Registration Type → toggle GST No. requiredness on the client side.
// Server-side validation enforces the same rule via the conditional 'when' /
// 'whenClient' validators in ClientModel::rules().
\$('#clientmodel-gst_registration_type').on('change', function() {
    var isRegular = \$(this).val() === '1';
    \$('.gst-required-mark').toggle(isRegular);
});

// Client Type → phone length swaps. International (3) allows up to 15
// digits; Inter/Intra-State enforce exactly 10. Trim overflow on switch so
// a long international number can't sneak past the 10-digit rule after the
// user changes type. Mirrors the conditional 'when' rules in ClientModel.
function _applyPhoneLengthFor(typeVal) {
    var isIntl = String(typeVal) === '3';
    var max = isIntl ? 15 : 10;
    var phoneHint = isIntl ? 'Enter up to 15-digit phone number' : 'Enter 10-digit phone number';
    var altHint   = isIntl ? 'Enter alternate phone (up to 15 digits)' : 'Enter alternate phone number';
    \$('#clientmodel-phone').attr('maxlength', max).attr('placeholder', phoneHint);
    \$('#clientmodel-alt_phone').attr('maxlength', max).attr('placeholder', altHint);
    \$('.js-client-phone').each(function () {
        var v = (this.value || '').replace(/[^0-9]/g, '');
        if (v.length > max) v = v.substring(0, max);
        this.value = v;
    });
}
\$('#clientmodel-client_type').on('change', function () {
    _applyPhoneLengthFor(\$(this).val());
});

// GST No. → first 2 chars are the state code. State code 33 = Tamil Nadu,
// which is intra-state for this business. Any other code clears the
// auto-selection so the user can pick the correct type themselves.
\$('#clientmodel-gst_no').on('input blur', function() {
    var val = (\$(this).val() || '').trim().toUpperCase();
    \$(this).val(val); // normalize to uppercase as the user types
    var stateCode = val.substring(0, 2);
    var \$type = \$('#clientmodel-client_type');
    if (stateCode === '33') {
        \$type.val('2').trigger('change'); // 2 = Intra-State
    }
    // Other codes intentionally do NOT auto-clear or auto-pick — the user
    // chooses Inter-State or International themselves.
});
JS
); ?>
