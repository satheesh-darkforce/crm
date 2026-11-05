<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $client */
/** @var \backend\models\User $user */
/** @var bool $isUpdate */
/** @var array|string|null $cancelUrl  Defaults to the client list (overridden by lead "Add To Client") */

use backend\assets\ClientAsset;
use backend\models\ClientModel;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

ClientAsset::register($this); // also rendered by Leads → Add To Client

$isUpdate  = $isUpdate ?? false;
$cancelUrl = $cancelUrl ?? ['/client/index'];

/** Input options: small control + placeholder; $extra wins (e.g. a class with JS hooks). */
$in = fn(string $placeholder, array $extra = []) => $extra + ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder];
// Stored value for the "only re-check what changed" client-side rules (see ClientModel::rules()).
$orig = fn(string $attr) => $client->isNewRecord ? [] : ['data-orig' => (string)$client->getOldAttribute($attr)];

$isIntl      = (int)$client->client_type === ClientModel::TYPE_INTERNATIONAL;
$phoneMax    = $isIntl ? 15 : 10;
$gstRequired = (int)$client->gst_registration_type === ClientModel::GST_REG_REGULAR;

$userErrors = isset($user) ? $user->getErrors() : [];
$allErrors  = array_merge($client->getErrors(), $userErrors);
?>
<?php $form = ActiveForm::begin(['id' => 'client-form', 'successCssClass' => '', 'options' => ['class' => 'ui-form']]); ?>

<?php if ($allErrors): ?>
<div class="alert alert-danger" role="alert">
    <p>Please fix the following:</p>
    <ul class="mb-0">
        <?php foreach ($allErrors as $fieldErrors): ?>
            <?php foreach ((array)$fieldErrors as $err): ?><li><?= Html::encode($err) ?></li><?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<p class="ui-req-note">Fields marked <b>*</b> are required.</p>

<?php if (!$isUpdate): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Portal login</div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <?php $usernameErr = $user->getFirstError('username'); ?>
            <div class="required">
                <label class="form-label" for="client-username">Username</label>
                <input type="text" id="client-username" name="username" class="form-control form-control-sm <?= $usernameErr ? 'is-invalid' : '' ?>"
                       value="<?= Html::encode(is_string(Yii::$app->request->post('username')) ? Yii::$app->request->post('username') : '') ?>"
                       maxlength="50" placeholder="e.g. acme.portal" autocomplete="off" spellcheck="false"
                       aria-describedby="client-username-help">
                <?php if ($usernameErr): ?><div class="invalid-feedback"><?= Html::encode($usernameErr) ?></div><?php endif; ?>
                <div class="form-text" id="client-username-help">3–50 characters: letters, numbers, . _ @ -</div>
            </div>
        </div>
        <div class="col-md-4">
            <?php $pwdErr = $user->getFirstError('password'); ?>
            <div class="required">
                <label class="form-label" for="client-password">Password</label>
                <div class="input-group input-group-sm has-validation">
                    <input type="password" name="password" id="client-password" autocomplete="new-password"
                           class="form-control form-control-sm <?= $pwdErr ? 'is-invalid' : '' ?>"
                           minlength="<?= ClientModel::PASSWORD_MIN ?>" maxlength="<?= ClientModel::PASSWORD_MAX ?>"
                           placeholder="At least <?= ClientModel::PASSWORD_MIN ?> characters">
                    <button type="button" class="btn btn-outline-secondary js-toggle-pwd" data-target="#client-password" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                    <?php if ($pwdErr): ?><div class="invalid-feedback"><?= Html::encode($pwdErr) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Client details</div>
    <div class="card-body row g-3">
        <div class="col-md-4"><?= $form->field($client, 'client_name')->textInput($in('e.g. John Mathew', ['maxlength' => 255])) ?></div>
        <div class="col-md-4"><?= $form->field($client, 'company_name')->textInput($in('e.g. Acme Pvt Ltd', ['maxlength' => 255])) ?></div>
        <div class="col-md-4"><?= $form->field($client, 'contact_person')->textInput($in('Point of contact', ['maxlength' => 255])) ?></div>
        <div class="col-md-4"><?= $form->field($client, 'email_id')->input('email', $in('name@company.com', ['maxlength' => 255]) + $orig('email_id'))->label('Email') ?></div>
        <div class="col-md-4">
            <?= $form->field($client, 'phone')->textInput($in($isIntl ? 'Up to 15 digits' : '10-digit number', [
                'class' => 'form-control form-control-sm js-client-phone', 'maxlength' => $phoneMax, 'inputmode' => 'numeric',
            ]) + $orig('phone')) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($client, 'alt_phone')->textInput($in($isIntl ? 'Up to 15 digits (optional)' : '10-digit number (optional)', [
                'class' => 'form-control form-control-sm js-client-phone', 'maxlength' => $phoneMax, 'inputmode' => 'numeric',
            ]) + $orig('alt_phone')) ?>
        </div>
        <div class="col-12"><?= $form->field($client, 'address')->textarea($in('Street, area, city, PIN', ['rows' => 2, 'maxlength' => 5000])) ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Tax</div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <?= $form->field($client, 'client_type')->dropDownList(ClientModel::typeOptions(), ['class' => 'form-select form-select-sm', 'prompt' => 'Select type…'])
                ->hint('GST numbers starting with 33 (Tamil Nadu) set Intra-State automatically.') ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($client, 'gst_registration_type')->dropDownList(ClientModel::registrationTypeOptions(), ['class' => 'form-select form-select-sm', 'prompt' => 'Select…'])
                ->label('GST Registration Type') ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($client, 'gst_no')
                ->label('GST No. <span class="text-danger fw-bold gst-required-mark"' . ($gstRequired ? '' : ' style="display:none"') . '>*</span>', ['encode' => false])
                ->textInput($in('e.g. 33ABCDE1234F1Z5', ['maxlength' => 15, 'autocomplete' => 'off', 'spellcheck' => 'false', 'style' => 'text-transform:uppercase']) + $orig('gst_no'))
                ->hint('Required for Regular registration.') ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($client, 'tax_exemption')->dropDownList([0 => 'No', 1 => 'Yes'], ['class' => 'form-select form-select-sm', 'id' => 'tax-exemption-select'] + $orig('tax_exemption'))
                ->label('Tax Exemption') ?>
        </div>
        <div class="col-md-8" id="tax-reason-wrap" style="<?= (int)$client->tax_exemption === 1 ? '' : 'display:none' ?>">
            <?= $form->field($client, 'tax_exemption_reason')->textarea($in('Reason for the exemption, e.g. SEZ unit', ['rows' => 2, 'maxlength' => 255])) ?>
        </div>
    </div>
</div>

<div class="ui-form-actions">
    <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . ($isUpdate ? 'Update Client' : 'Create Client'), ['class' => 'btn btn-primary btn-sm px-4']) ?>
    <?= Html::a('Cancel', $cancelUrl, ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>

<?php ActiveForm::end(); ?>
