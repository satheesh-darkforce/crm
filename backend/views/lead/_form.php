<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var array $users  user_id => username */
/** @var array|string $cancelUrl */

use backend\components\LeadFiles;
use backend\controllers\Controller;
use backend\models\Lead;
use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isNew      = $lead->isNewRecord;
$cancelUrl  = $cancelUrl ?? ['index'];
$today      = date('Y-m-d');
$datePlugin = ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true];

/** Text input options: small control + placeholder (+ extras). */
$in = fn(string $placeholder, array $extra = []) => ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder] + $extra;
$s2 = fn(string $placeholder) => [
    'options' => ['placeholder' => $placeholder],
    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
    'size' => Select2::SMALL,
];
?>
<?= $this->render('_form-css') ?>

<?php $form = ActiveForm::begin(['id' => 'lead-form', 'options' => ['enctype' => 'multipart/form-data', 'class' => 'lead-form-scope']]); ?>

<?= $form->errorSummary($lead, ['class' => 'alert alert-danger py-2 small']) ?>
<p class="text-muted small mb-3">Fields marked <span class="text-danger fw-bold">*</span> are required.</p>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Client</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'client_name')->textInput($in('e.g. John Mathew', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'company_name')->textInput($in('e.g. Acme Pvt Ltd', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'email')->input('email', $in('name@company.com', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_no')->textInput($in('e.g. +91 98765 43210', ['maxlength' => 25, 'inputmode' => 'tel'])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_no2')->textInput($in('Alternate number', ['maxlength' => 25, 'inputmode' => 'tel'])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'country')->textInput($in('e.g. Chennai', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_person_name')->textInput($in('Point of contact', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_person_mobile')->textInput($in('e.g. 98765 43210', ['maxlength' => 25, 'inputmode' => 'tel'])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_person_email')->input('email', $in('contact@company.com', ['maxlength' => 255])) ?></div>
        <div class="col-lg-9 col-md-6"><?= $form->field($lead, 'address')->textarea($in('Street, area, city, PIN', ['rows' => 2])) ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Lead</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'title')->textInput($in('e.g. E-commerce website', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'subject')->textInput($in('e.g. Requirement discussion', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'priority')->dropDownList(Lead::priorityOptions(), ['class' => 'form-select form-select-sm']) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'current_stage')->widget(Select2::class, ['data' => Lead::stageOptions()] + $s2('Select the stage')) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'lead_value')->input('number', $in('0.00', ['min' => 0, 'max' => 99999999.99, 'step' => '0.01'])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'expected_budget')->input('number', $in('0.00', ['min' => 0, 'max' => 99999999.99, 'step' => '0.01'])) ?></div>
        <?php if ($isNew): ?>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">Lead Status</label>
            <input type="text" class="form-control form-control-sm" value="Open" disabled>
        </div>
        <?php endif; ?>
        <div class="col-md-6"><?= $form->field($lead, 'industry')->textarea($in('e.g. Manufacturing, Healthcare', ['rows' => 2, 'maxlength' => 255])) ?></div>
        <div class="col-md-6"><?= $form->field($lead, 'offering_service')->textarea($in('e.g. Website, Mobile app, ERP', ['rows' => 2, 'maxlength' => 255])) ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Meeting &amp; Follow Up</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6">
            <?= $form->field($lead, 'attended_date')->widget(DatePicker::class, [
                'type' => DatePicker::TYPE_INPUT,
                'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                'pluginOptions' => $datePlugin + ['endDate' => $today],
            ]) ?>
        </div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'attended_time')->input('time', $in('HH:MM')) ?></div>
        <?php if ($isNew): ?>
        <div class="col-lg-3 col-md-6">
            <?= $form->field($lead, 'nextfollowup_date')->widget(DatePicker::class, [
                'type' => DatePicker::TYPE_INPUT,
                'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                'pluginOptions' => $datePlugin + ['startDate' => $today],
            ]) ?>
        </div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'nextfollowup_time')->input('time', $in('HH:MM')) ?></div>
        <?php else: ?>
        <div class="col-lg-6 col-md-12">
            <label class="form-label">Next Follow Up Date &amp; Time</label>
            <input type="text" class="form-control form-control-sm" value="<?= Lead::formatDateTime($lead->meeting_reported_date) ?>" disabled>
            <div class="form-text">Changes when a follow-up is added.</div>
        </div>
        <?php endif; ?>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'assigned_to')->widget(Select2::class, ['data' => $users] + $s2('Select employee')) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'mode')->widget(Select2::class, ['data' => Lead::modeOptions()] + $s2('Select the mode')) ?></div>
        <div class="col-lg-3 col-md-6" id="other-mode-wrap" style="<?= (int)$lead->mode === Lead::MODE_OTHER ? '' : 'display:none' ?>">
            <?= $form->field($lead, 'other_mode', ['options' => ['class' => 'mb-3 required']])->textInput($in('e.g. Google Meet', ['maxlength' => 255])) ?>
        </div>
        <?php if ($isNew): ?>
        <div class="col-12"><?= $form->field($lead, 'description')->textarea($in('Meeting notes, requirements discussed…', ['rows' => 4])) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Attachments</div>
    <div class="card-body">
        <input type="file" name="attachments[]" class="form-control form-control-sm" multiple accept="<?= LeadFiles::acceptAttribute() ?>">
        <div class="form-text">PDF, Office documents, images, CSV, TXT or ZIP — up to 20 MB each.</div>
        <?php if (!$isNew && $lead->attachments): ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php foreach ($lead->attachments as $att): ?>
            <div class="border rounded px-2 py-1 small d-flex align-items-center gap-2">
                <a href="<?= Html::encode(Controller::uploadUrl($att->attachment_path)) ?>" target="_blank" rel="noopener">
                    <i class="fas fa-paperclip me-1"></i><?= Html::encode(basename($att->attachment_path)) ?>
                </a>
                <?php if (UserAccess::checkAccess('lead/delete-attachment')): ?>
                <a href="#" class="text-danger js-delete-attachment" data-id="<?= $att->id ?>" title="Delete"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <?= Html::submitButton($isNew ? 'Submit' : 'Update', ['class' => 'btn btn-primary btn-sm px-4']) ?>
    <?= Html::a('Cancel', $cancelUrl, ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>

<?php ActiveForm::end(); ?>

<?php
$this->registerJs(<<<JS
jQuery('#lead-mode').on('change', function () {
    var other = jQuery(this).val() == '5';
    jQuery('#other-mode-wrap').toggle(other);
    if (!other) { jQuery('#lead-other_mode').val(''); jQuery('#lead-form').yiiActiveForm('validateAttribute', 'lead-other_mode'); }
});
JS);
echo $this->render('_status-js');
