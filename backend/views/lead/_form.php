<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var array $users  user_id => username */
/** @var array|string $cancelUrl */
/** @var array<int,string>|null $categories  product category id => name */

use backend\components\LeadFiles;
use backend\controllers\Controller;
use backend\models\Lead;
use common\components\UserAccess;
use backend\components\FormPickers;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isNew      = $lead->isNewRecord;
$simple     = \backend\components\Crm::simple(); // Green Divine mode: IT-sales fields hidden
$categories = $categories ?? \backend\models\ProductCategory::options();
$cancelUrl  = $cancelUrl ?? ['index'];
$today      = date('Y-m-d');
$datePlugin = ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true];

/** Text input options: small control + placeholder (+ extras). */
$in = fn(string $placeholder, array $extra = []) => ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder] + $extra;
$s2 = fn(string $placeholder, array $extra = []) => [
    'options' => ['placeholder' => $placeholder] + $extra,
    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
    'size' => Select2::SMALL,
];
$dateInput = fn(array $plugin) => [
    'type' => DatePicker::TYPE_INPUT,
    'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
    'pluginOptions' => $datePlugin + $plugin,
];
?>
<?php $form = ActiveForm::begin(['id' => 'lead-form', 'options' => ['enctype' => 'multipart/form-data', 'class' => 'ui-form']]); ?>

<?= $form->errorSummary($lead, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
<p class="ui-req-note">Fields marked <b>*</b> are required.</p>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Client</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'client_name')->textInput($in('e.g. John Mathew', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'company_name')->textInput($in('e.g. Acme Pvt Ltd', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'email')->input('email', $in('name@company.com', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_no', ['options' => ['class' => 'mb-3' . ($simple && $isNew ? ' required' : '')]])->textInput($in('e.g. +91 98765 43210', ['maxlength' => 25, 'inputmode' => 'tel'])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_no2')->textInput($in('Alternate number', ['maxlength' => 25, 'inputmode' => 'tel'])) ?></div>
        <?php if (!$simple): ?>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_person_name')->textInput($in('Point of contact', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_person_mobile')->textInput($in('e.g. 98765 43210', ['maxlength' => 25, 'inputmode' => 'tel'])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'contact_person_email')->input('email', $in('contact@company.com', ['maxlength' => 255])) ?></div>
        <?php endif; ?>
        <div class="col-lg-9 col-md-6"><?= $form->field($lead, 'address')->textarea($in('Street, area, city, PIN', ['rows' => 2])) ?></div>
        <div class="col-12">
            <div id="lead-dup-alert" class="alert alert-warning lead-dup-alert mb-0 d-none" role="status" aria-live="polite" data-id="<?= $isNew ? '' : (int)$lead->id ?>"></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Enquiry</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'lead_source', ['options' => ['class' => 'mb-3' . ($isNew ? ' required' : '')]])->widget(Select2::class, ['data' => Lead::sourceOptions()] + $s2('Where did the lead come from?')) ?></div>
        <div class="col-lg-3 col-md-6" id="source-detail-wrap">
            <?= $form->field($lead, 'source_detail')->textInput($in('Referred by / campaign', ['maxlength' => 100]))
                ->hint('Required for Reference and Other.') ?>
        </div>
        <div class="col-lg-6 col-md-12">
            <?= $form->field($lead, 'interestIds')->widget(Select2::class, [
                'data'          => $categories,
                'options'       => ['placeholder' => $categories ? 'Products the client asked about' : 'Add product categories first', 'multiple' => true],
                'pluginOptions' => ['allowClear' => true, 'width' => '100%', 'closeOnSelect' => false],
                'size'          => Select2::SMALL,
            ]) ?>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="lead-site_area"><?= $lead->getAttributeLabel('site_area') ?></label>
            <div class="input-group input-group-sm">
                <?= Html::activeTextInput($lead, 'site_area', ['class' => 'form-control form-control-sm num' . ($lead->hasErrors('site_area') ? ' is-invalid' : ''), 'inputmode' => 'decimal', 'placeholder' => 'e.g. 450']) ?>
                <?= Html::activeDropDownList($lead, 'area_unit', array_combine(Lead::AREA_UNITS, Lead::AREA_UNITS), ['class' => 'form-select form-select-sm', 'style' => 'max-width:90px', 'aria-label' => 'Area unit']) ?>
            </div>
            <?php if ($err = $lead->getFirstError('site_area') ?: $lead->getFirstError('area_unit')): ?><div class="invalid-feedback d-block"><?= Html::encode($err) ?></div><?php endif; ?>
        </div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'expected_install_date')->widget(DatePicker::class, $dateInput(['startDate' => $today])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'priority')->dropDownList(Lead::priorityOptions(), ['class' => 'form-select form-select-sm']) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'country')->textInput($in('e.g. Bengaluru', ['maxlength' => 255])) ?></div>
        <div class="col-12"><?= $form->field($lead, 'description')->textarea($in('What the client wants: product, site (balcony, terrace, wall…), size, budget, timeline', ['rows' => 3])) ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Pipeline</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'title')->textInput($in('e.g. Terrace garden — Koramangala', ['maxlength' => 255])) ?></div>
        <?php if ($simple): ?>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'subject')->textInput($in('Filled in from the products if left blank', ['maxlength' => 255])) ?></div>
        <?php else: ?>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'subject')->textInput($in('e.g. Balcony grass enquiry', ['maxlength' => 255])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'current_stage')->widget(Select2::class, ['data' => Lead::stageOptions()] + $s2('Select the stage')) ?></div>
        <?php endif; ?>
        <?php if ($isNew): ?>
        <div class="col-lg-3 col-md-6">
            <label class="form-label" for="lead-status-display">Lead Status</label>
            <input type="text" id="lead-status-display" class="form-control form-control-sm" value="Open" disabled>
        </div>
        <?php endif; ?>
        <div class="col-lg-3 col-md-6">
            <?= $form->field($lead, 'lead_value', ['options' => ['class' => 'mb-1']])->input('number', $in('0.00', ['min' => 0, 'max' => 99999999.99, 'step' => '0.01'])) ?>
            <button type="button" class="btn btn-link btn-sm p-0 js-lead-estimate"><i class="fas fa-calculator me-1"></i>Estimate from products &amp; area</button>
            <div id="lead-estimate" class="lead-estimate d-none" aria-live="polite"></div>
        </div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'expected_budget')->input('number', $in('0.00', ['min' => 0, 'max' => 99999999.99, 'step' => '0.01'])) ?></div>
        <?php if (!$simple): ?>
        <div class="col-12">
            <details class="ui-more"<?= ($lead->industry || $lead->offering_service || $lead->hasErrors('industry') || $lead->hasErrors('offering_service')) ? ' open' : '' ?>>
                <summary class="small text-muted">More details (industry, offering)</summary>
                <div class="row g-3 mt-1">
                    <div class="col-md-6"><?= $form->field($lead, 'industry')->textarea($in('e.g. Residential, Hospitality, Office', ['rows' => 2, 'maxlength' => 255])) ?></div>
                    <div class="col-md-6"><?= $form->field($lead, 'offering_service')->textarea($in('e.g. Supply and installation', ['rows' => 2, 'maxlength' => 255])) ?></div>
                </div>
            </details>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Team &amp; Follow Up</div>
    <div class="card-body row g-3">
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'assigned_to')->widget(Select2::class, ['data' => $users] + $s2('Select employee'))->label('Sales person') ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'site_visit_by')->widget(Select2::class, ['data' => $users] + $s2('Who visits the site')) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'visit_date')->widget(DatePicker::class, $dateInput([])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'visit_time')->widget(TimePicker::class, FormPickers::time()) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'quotation_by')->widget(Select2::class, ['data' => $users] + $s2('Who prepares the quote')) ?></div>
        <?php if (!$simple): ?>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'mode')->widget(Select2::class, ['data' => Lead::modeOptions()] + $s2('Select the mode', ['data-other-mode' => '#other-mode-wrap'])) ?></div>
        <div class="col-lg-3 col-md-6" id="other-mode-wrap" style="<?= (int)$lead->mode === Lead::MODE_OTHER ? '' : 'display:none' ?>">
            <?= $form->field($lead, 'other_mode', ['options' => ['class' => 'mb-3 required']])->textInput($in('e.g. Google Meet', ['maxlength' => 255])) ?>
        </div>
        <div class="w-100 d-none d-lg-block"></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'attended_date')->widget(DatePicker::class, $dateInput(['endDate' => $today])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'attended_time')->widget(TimePicker::class, FormPickers::time()) ?></div>
        <?php endif; /* mode + meeting attended */ ?>
        <?php if ($isNew): ?>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'nextfollowup_date')->widget(DatePicker::class, $dateInput(['startDate' => $today])) ?></div>
        <div class="col-lg-3 col-md-6"><?= $form->field($lead, 'nextfollowup_time')->widget(TimePicker::class, FormPickers::time()) ?></div>
        <?php else: ?>
        <div class="col-lg-6 col-md-12">
            <label class="form-label" for="lead-next-display">Next Follow Up Date &amp; Time</label>
            <input type="text" id="lead-next-display" class="form-control form-control-sm" value="<?= Lead::formatDateTime($lead->meeting_reported_date) ?>" disabled>
            <div class="form-text">Changes when a follow-up is added.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Attachments</div>
    <div class="card-body">
        <label class="visually-hidden" for="lead-attachments">Attachments</label>
        <input type="file" id="lead-attachments" name="attachments[]" class="form-control form-control-sm" multiple accept="<?= LeadFiles::acceptAttribute() ?>">
        <div class="form-text">PDF, Office documents, images, CSV, TXT or ZIP — up to 20 MB each.</div>
        <?php if (!$isNew && $lead->attachments): ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php foreach ($lead->attachments as $att): ?>
                <?php $name = basename((string)$att->attachment_path); ?>
                <span class="ui-file">
                    <a href="<?= Html::encode(Controller::uploadUrl((string)$att->attachment_path)) ?>" target="_blank" rel="noopener" class="text-reset text-decoration-none d-inline-flex align-items-center gap-2" title="<?= Html::encode($name) ?>">
                        <i class="fas fa-paperclip"></i><span><?= Html::encode($name) ?></span>
                    </a>
                    <?php if (UserAccess::checkAccess('lead/delete-attachment')): ?>
                        <a href="#" class="js-delete-attachment" data-id="<?= $att->id ?>" data-name="<?= Html::encode($name) ?>" title="Delete" aria-label="Delete <?= Html::encode($name) ?>"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="ui-form-actions">
    <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . ($isNew ? 'Save Lead' : 'Update Lead'), ['class' => 'btn btn-primary btn-sm px-4']) ?>
    <?= Html::a('Cancel', $cancelUrl, ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>

<?php ActiveForm::end(); ?>
