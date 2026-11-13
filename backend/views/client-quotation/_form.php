<?php

/** @var yii\web\View $this */
/** @var backend\models\ClientQuotation $model */
/** @var backend\models\ClientQuotationFile $fileModel */
/** @var array $clientOptions */
/** @var array $userOptions */
/** @var int   $quotenum */
/** @var bool  $showFile */
/** @var bool  $requireOfficialContactBeforeCreate */
/** @var array $lines       quotation line items (LineItems rows) */
/** @var string[] $lineErrors */

use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;

// Delivery quote_for labels: 1=IT, 2=IOT.
$quoteForOptions = [1 => 'IT', 2 => 'IOT'];

$showFile                           ??= true;
$quotenum                           ??= 0;
$isChildQuotation                   = !empty($model->parent_id);
$requireOfficialContactBeforeCreate ??= false;
$simple = \backend\components\Crm::simple(); // Green Divine mode: IT fields hidden, items required
if ($simple) {
    $requireOfficialContactBeforeCreate = false;
    $model->quote_for = $model->quote_for ?: 1;
    $model->deployment_type = $model->deployment_type ?: \backend\models\ClientQuotation::DEPLOYMENT;
}

$indexUrl = Url::to(['index']);
?>

<style>
#clientquotation-address { height: 100px; }
.quote-by-field-wrap    { display: flex; gap: 10px; align-items: flex-start; }
.quote-by-field-wrap .field-clientquotation-alternate_quote_by { flex: 1; margin-bottom: 0; }
.quote-by-action-btn    { margin-top: 25px; white-space: nowrap; }
.quote-by-error         { color: #dc3545; font-size: 0.875rem; margin-top: 6px; min-height: 20px; }
</style>

<div class="form-wrapper">
    <?php $form = ActiveForm::begin([
        'id' => 'quotation-form',
        'options' => ['enctype' => 'multipart/form-data'],
        'enableAjaxValidation' => true,
        'fieldConfig' => [
            'options' => ['class' => 'form-group mb-3 custom-form'],
            'inputOptions' => ['class' => 'form-control form-control-sm'],
            'labelOptions' => ['class' => 'form-label'],
        ],
    ]); ?>

        <div class="section-block">
            <div class="section-header">
                <div class="header-icon"><i class="fas fa-user"></i></div>
                <h4>Client &amp; Project</h4>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <?= $form->field($model, 'client_id')->label($simple ? 'Client' : 'Company Name')->widget(Select2::class, [
                        'data' => $clientOptions,
                        'options' => ['placeholder' => 'Select Company Name...'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-4">
                    <?= $form->field($model, 'project_name')->label('Project Name')->textInput(['maxlength' => true, 'placeholder' => 'Project Name']) ?>
                </div>
                <div class="col-12 col-md-4">
                    <?= $form->field($model, 'address')->label('Address')->textarea(['maxlength' => true, 'placeholder' => 'Address']) ?>
                </div>
                <div class="col-12 d-none">
                    <?= $form->field($model, 'alter_reason')->textarea([
                        'rows' => 3,
                        'maxlength' => true,
                        'placeholder' => $isChildQuotation ? 'Enter alter reason' : 'Alter Reason (Optional for main quotation)',
                    ])->label($isChildQuotation ? null : 'Alter Reason') ?>
                </div>
            </div>
        </div>

        <input type="hidden" value="<?= (int) $quotenum ?>" id="quotenum">

        <div class="section-block">
            <div class="section-header">
                <div class="header-icon" style="background:#8b5cf6;"><i class="fas fa-file-invoice"></i></div>
                <h4>Quotation Details</h4>
            </div>
            <div class="row g-3">
                <?php if ($simple): ?>
                    <?= Html::activeHiddenInput($model, 'quote_for') ?>
                    <?= Html::activeHiddenInput($model, 'deployment_type', ['id' => 'clientquotation-deployment_type']) ?>
                <?php else: ?>
                <div class="col-12 col-md-4">
                    <?= $form->field($model, 'quote_for')->dropDownList($quoteForOptions, ['prompt' => 'Select Quote For']) ?>
                </div>
                <div class="col-12 col-md-4">
                    <?= $form->field($model, 'deployment_type', ['options' => ['class' => 'form-group mb-3 custom-form required']])
                        ->dropDownList(\backend\models\ClientQuotation::deploymentTypeOptions()) ?>
                </div>
                <?php endif; ?>
                <div class="col-12 col-md-4">
                    <?= $form->field($model, 'quote_no')->textInput(['maxlength' => true, 'placeholder' => 'Quotation Number', 'readonly' => true]) ?>
                </div>
                <div class="col-12 col-md-6<?= $simple ? ' d-none' : '' ?>">
                    <div class="quote-by-field-wrap">
                        <?= $form->field($model, 'alternate_quote_by')->label('Additional Quote By')->widget(Select2::class, [
                            'data' => $userOptions,
                            'options' => ['placeholder' => 'Select Additional Quote By...'],
                            'pluginOptions' => [
                                'allowClear' => true,
                                'width' => '100%',
                                'ajax' => [
                                    'url' => Url::to(['additional-quote-by-users']),
                                    'dataType' => 'json',
                                    'delay' => 250,
                                    'data' => new JsExpression('function(p){return{term:p.term||""};}'),
                                    'processResults' => new JsExpression('function(d){return d;}'),
                                    'cache' => true,
                                ],
                                'minimumInputLength' => 0,
                            ],
                        ]) ?>
                        <button type="button" class="btn btn-outline-primary quote-by-action-btn" id="open-add-quote-by-modal">
                            Add Quote By
                        </button>
                    </div>
                    <div class="quote-by-error" id="alternate-quote-by-help"></div>
                </div>
                <div class="col-12 col-md-4 d-none">
                    <?= $form->field($model, 'version')->textInput(['maxlength' => true, 'placeholder' => 'version']) ?>
                </div>
                <div class="col-12 col-md-4 d-none">
                    <?php $model->quote_date = $model->quote_date ?: date('Y-m-d'); ?>
                    <?= $form->field($model, 'quote_date')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control d-none', 'readonly' => true],
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true],
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="section-block">
            <div class="section-header">
                <div class="header-icon" style="background:#7366ff;"><i class="fas fa-list-ul"></i></div>
                <h4>Items</h4>
            </div>
            <div class="row g-3 mb-2">
                <div class="col-12 col-md-4">
                    <?= $form->field($model, 'tax_mode')->dropDownList(\backend\components\LineItems::taxModeOptions(), ['id' => 'quote-tax-mode', 'class' => 'form-select form-select-sm'])
                        ->hint('Set from the client; change it if the site is in another state.') ?>
                </div>
                <div class="col-12 col-md-4">
                    <?= $form->field($model, 'valid_until')->widget(DatePicker::class, [
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true, 'startDate' => date('Y-m-d')],
                    ])->hint('Usually 15–30 days.') ?>
                </div>
            </div>
            <?= $this->render('@backend/views/_ui/_line_items', [
                'name'         => 'QuoteItems',
                'lines'        => $lines ?? [],
                'taxMode'      => (int)($model->tax_mode ?: \backend\components\LineItems::TAX_INTRA),
                'taxModeInput' => '#quote-tax-mode',
                'totalTarget'  => '#clientquotation-estimation_cost',
                'errors'       => $lineErrors ?? [],
            ]) ?>
            <div class="row g-3 mt-1">
                <div class="col-12 col-md-6">
                    <?= $form->field($model, 'terms')->textarea(['rows' => 3, 'class' => 'form-control form-control-sm', 'placeholder' => 'One term per line'])->label('Terms & Conditions (one per line)') ?>
                </div>
                <div class="col-12 col-md-6">
                    <?= $form->field($model, 'notes')->textarea(['rows' => 3, 'class' => 'form-control form-control-sm', 'placeholder' => 'One note per line'])->label('Notes (one per line)') ?>
                </div>
            </div>
            <?php if (!$simple): ?><p class="small text-muted mb-0"><i class="fas fa-info-circle me-1"></i>With items added, the estimation cost below is the quote total and fills in automatically. Without items, enter it by hand and attach the quotation PDF as before.</p><?php endif; ?>
        </div>

        <div class="section-block">
            <div class="section-header">
                <div class="header-icon" style="background:#10b981;"><i class="fas fa-coins"></i></div>
                <h4>Financials &amp; Attachment</h4>
            </div>
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-4<?= $simple ? ' d-none' : '' ?>">
                    <?= $form->field($model, 'estimation_cost', [
                            'options' => ['class' => 'form-group mb-3 custom-form required', 'id' => 'estimation-cost-field'],
                        ])->textInput(['type' => 'number', 'step' => '1', 'min' => '1', 'max' => '999999999', 'maxlength' => true, 'placeholder' => 'Estimated Cost', 'inputmode' => 'numeric', 'pattern' => '[0-9]*']) ?>
                    <small id="estimation-cost-optional-hint" class="text-muted d-none">Optional for a Trial quotation. Leave blank to omit it from the proposal.</small>
                </div>
                <?php if ($showFile): ?>
                    <div class="col-12 col-md-5">
                        <?= $form->field($fileModel, 'file_path', ['enableClientValidation' => !$simple, 'enableAjaxValidation' => false])->fileInput([
                            'accept' => 'application/pdf,.pdf',
                            'class' => 'form-control form-control-sm',
                        ])->label($simple ? 'Attachment (optional PDF — drawings, brochure)' : 'Quotation File (PDF only)') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <button type="button" id="preview-doc" class="btn btn-info mt-1 mb-4" style="display:none;">Preview</button>

        <div class="action-footer shadow-sm">
            <p class="text-muted mb-0 small">Review the file and quote details before saving the quotation record.</p>
            <div class="d-flex gap-2 flex-wrap">
                <?= Html::a('Cancel', ['index'], [
                    'class'   => 'btn btn-outline-secondary',
                    'onclick' => 'event.preventDefault(); window.location.replace(this.href); return false;',
                ]) ?>
                <?= Html::submitButton('<i class="fas fa-save me-2"></i> Save Quotation', ['class' => 'btn btn-success', 'encode' => false]) ?>
            </div>
        </div>

    <?php ActiveForm::end(); ?>
</div>

<!-- Add Quote By modal (creates / updates a developer's official contact) -->
<div class="modal fade quotation-form" id="addQuoteByModal" tabindex="-1" aria-labelledby="addQuoteByModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content quotation-form">
            <div class="modal-header">
                <h5 class="modal-title" id="addQuoteByModalLabel">Add Quote By</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body quotation-form">
                <div class="mb-3">
                    <label class="form-label required" for="quote-by-user-id">Quote By User</label>
                    <?= Select2::widget([
                        'name' => 'quote_by_user_id',
                        'value' => '',
                        'theme' => Select2::THEME_KRAJEE_BS5,
                        'data' => [],
                        'options' => ['id' => 'quote-by-user-id', 'placeholder' => 'Select Quote By User...'],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width' => '100%',
                            'dropdownParent' => '#addQuoteByModal',
                            'ajax' => [
                                'url' => Url::to(['quote-by-users']),
                                'dataType' => 'json',
                                'delay' => 250,
                                'data' => new JsExpression('function(p){return{term:p.term||""};}'),
                                'processResults' => new JsExpression('function(d){return d;}'),
                                'cache' => true,
                            ],
                        ],
                    ]) ?>
                    <div class="quote-by-error" id="quote-by-user-id-error"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="quote-by-official-email">Official Email ID</label>
                    <input type="email" id="quote-by-official-email" class="form-control" placeholder="name@company.com">
                    <div class="quote-by-error" id="quote-by-official-email-error"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label required" for="quote-by-official-mobile">Official Phone Number</label>
                    <input type="text" id="quote-by-official-mobile" class="form-control" maxlength="10" inputmode="numeric" placeholder="Enter official phone number">
                    <div class="quote-by-error" id="quote-by-official-mobile-error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="save-quote-by-details">Save</button>
            </div>
        </div>
    </div>
</div>

<?php
$getaddress                         = Url::to(['get-client-address']);
$previewUrl                         = Url::to(['preview-pdf']);
$quoteByUsersUrl                    = Url::to(['quote-by-users']);
$developerOfficialContactUrl        = Url::to(['developer-official-contact']);
$currentDeveloperOfficialContactUrl = Url::to(['current-developer-official-contact']);
$saveDeveloperOfficialContactUrl    = Url::to(['save-developer-official-contact']);

$quotePrefixesJson = \yii\helpers\Json::htmlEncode(\backend\controllers\ClientQuotationController::quotePrefixes()); // quote_for => number prefix

$this->registerJs(<<<JS
$(function () {
    var quotenum = parseInt($('#quotenum').val(), 10) || 0;
    var form = $('#quotation-form');
    var addQuoteByModalElement = document.getElementById('addQuoteByModal');
    var addQuoteByModal = addQuoteByModalElement ? new bootstrap.Modal(addQuoteByModalElement) : null;
    var quoteByUserSelect = $('#quote-by-user-id');
    var alternateQuoteByField = $('#clientquotation-alternate_quote_by');
    var saveQuoteByButton = $('#save-quote-by-details');
    var requireOfficialContactBeforeCreate = JSON.parse('{$requireOfficialContactBeforeCreate}' || 'false');
    var forceCurrentUserUpdate = false;
    var forcedCurrentUserId = null;

    function clearQuoteByErrors() {
        $('#quote-by-user-id-error').text('');
        $('#quote-by-official-email-error').text('');
        $('#quote-by-official-mobile-error').text('');
        $('#alternate-quote-by-help').text('');
    }
    function setQuoteByError(fieldId, message) {
        $('#' + fieldId + '-error').text(message || '');
    }

    function loadClientAddress(clientId) {
        if (!clientId) { $('#clientquotation-address').val('').trigger('change'); return; }
        $.ajax({
            url: '$getaddress', type: 'POST', data: { id: clientId },
            success: function (r) { $('#clientquotation-address').val(r.address || '').trigger('change'); },
            error: function () {
                if (window.Swal) Swal.fire({title:'Error', text:'Error fetching address', icon:'error'});
            }
        });
    }

    function updateQuoteNumber() {
        if (quotenum <= 0) return;
        var val = $('#clientquotation-quote_for').val();
        var padded = String(quotenum).padStart(3, '0');
        var today = new Date();
        var m = today.getMonth() + 1, y = today.getFullYear();
        var startY = (m >= 4) ? y : y - 1;
        var endY   = (m >= 4) ? y + 1 : y;
        var fy = String(startY).slice(-2) + '-' + String(endY).slice(-2);
        var prefixes = {$quotePrefixesJson};
        var qn = prefixes[val] ? prefixes[val] + '/' + fy + '/' + padded : '';
        $('#clientquotation-quote_no').val(qn);
    }

    function injectAdditionalQuoteByOption(option) {
        if (!option || !option.id) return;
        var id = String(option.id);
        var existing = alternateQuoteByField.find('option[value="' + id + '"]');
        if (!existing.length) {
            alternateQuoteByField.append(new Option(option.text, id, true, true));
        } else {
            existing.text(option.text).prop('selected', true);
        }
        alternateQuoteByField.val(id).trigger('change');
    }

    function lockQuoteByModal(locked) {
        if (!addQuoteByModalElement) return;
        $(addQuoteByModalElement).find('[data-bs-dismiss="modal"], .btn-close').prop('disabled', locked);
    }

    function prefillQuoteByModal(option, developer, locked) {
        if (!option || !option.id) return;
        var id = String(option.id);
        var existing = quoteByUserSelect.find('option[value="' + id + '"]');
        if (!existing.length) {
            quoteByUserSelect.append(new Option(option.text, id, true, true));
        } else {
            existing.text(option.text).prop('selected', true);
        }
        quoteByUserSelect.val(id).trigger('change');
        $('#quote-by-official-email').val((developer && developer.official_email_id) || '');
        $('#quote-by-official-mobile').val((developer && developer.official_mobile_no) || '');
        quoteByUserSelect.prop('disabled', locked);
        lockQuoteByModal(locked);
    }

    function enforceCurrentUserOfficialContact() {
        $.ajax({
            url: '$currentDeveloperOfficialContactUrl',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (!response.success || !response.requires_update) return;
                forceCurrentUserUpdate = true;
                forcedCurrentUserId = String(response.user.id);
                if (!window.Swal) return;
                Swal.fire({
                    title: 'Official Contact Required',
                    text: 'Please add official phone number and email to add quotation.',
                    icon: 'warning',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showCancelButton: true,
                    showCloseButton: true,
                    confirmButtonText: 'Click to Add',
                    cancelButtonText: 'Cancel',
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        // Cancel / X / dismiss — always go to the listing page.
                        window.location.replace('$indexUrl');
                        return;
                    }
                    clearQuoteByErrors();
                    prefillQuoteByModal(response.user, response.developer, true);
                    if (addQuoteByModal) addQuoteByModal.show();
                });
            }
        });
    }

    // Modal Select2 setup (AJAX-search all developers except current user)
    quoteByUserSelect.select2({
        width: '100%',
        dropdownParent: $('#addQuoteByModal'),
        placeholder: 'Select Quote By User...',
        allowClear: true,
        ajax: {
            url: '$quoteByUsersUrl',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { term: params.term || '' }; },
            processResults: function (data) { return data; },
            cache: true,
        },
    });

    // Block modal close while we force the user to add their own contact
    if (addQuoteByModalElement) {
        $(addQuoteByModalElement).on('hide.bs.modal', function (event) {
            if (forceCurrentUserUpdate) event.preventDefault();
        });
    }

    // When a user is chosen in the modal, prefill their existing developer contact
    quoteByUserSelect.on('change', function () {
        clearQuoteByErrors();
        var userId = $(this).val();
        if (!userId) {
            $('#quote-by-official-email').val('');
            $('#quote-by-official-mobile').val('');
            return;
        }
        if (forceCurrentUserUpdate && forcedCurrentUserId !== null && String(userId) === String(forcedCurrentUserId)) {
            return;
        }
        $.ajax({
            url: '$developerOfficialContactUrl',
            type: 'GET',
            dataType: 'json',
            data: { userId: userId },
            success: function (response) {
                if (!response.success) {
                    $('#quote-by-official-email').val('');
                    $('#quote-by-official-mobile').val('');
                    setQuoteByError('quote-by-user-id', response.message || 'Unable to load user details.');
                    return;
                }
                $('#quote-by-official-email').val(response.developer.official_email_id || '');
                $('#quote-by-official-mobile').val(response.developer.official_mobile_no || '');
            },
            error: function () {
                setQuoteByError('quote-by-user-id', 'Unable to load user details.');
            }
        });
    });

    // Digits-only mobile
    $('#quote-by-official-mobile').on('input', function () {
        var digits = ($(this).val() || '').replace(/\D/g, '').slice(0, 10);
        $(this).val(digits);
    });

    // Open modal
    $('#open-add-quote-by-modal').on('click', function () {
        clearQuoteByErrors();
        forceCurrentUserUpdate = false;
        forcedCurrentUserId = null;
        quoteByUserSelect.val(null).trigger('change');
        quoteByUserSelect.prop('disabled', false);
        $('#quote-by-official-email').val('');
        $('#quote-by-official-mobile').val('');
        lockQuoteByModal(false);
        if (addQuoteByModal) addQuoteByModal.show();
    });

    // Save modal — append to alternate_quote_by Select2
    saveQuoteByButton.on('click', function () {
        clearQuoteByErrors();
        var payload = {
            user_id: quoteByUserSelect.val(),
            official_email_id: $.trim($('#quote-by-official-email').val()),
            official_mobile_no: $.trim($('#quote-by-official-mobile').val()),
            allow_current_user: forceCurrentUserUpdate ? 1 : 0,
        };
        var hasError = false;
        if (!payload.user_id)               { setQuoteByError('quote-by-user-id', 'Please select a user.'); hasError = true; }
        if (!payload.official_email_id)     { setQuoteByError('quote-by-official-email', 'Official email ID cannot be blank.'); hasError = true; }
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.official_email_id)) {
            setQuoteByError('quote-by-official-email', 'Enter a valid official email address.'); hasError = true;
        }
        if (!payload.official_mobile_no)    { setQuoteByError('quote-by-official-mobile', 'Official phone number cannot be blank.'); hasError = true; }
        else if (!/^\d{10}$/.test(payload.official_mobile_no)) {
            setQuoteByError('quote-by-official-mobile', 'Official phone number must be exactly 10 digits.'); hasError = true;
        }
        if (hasError) return;

        saveQuoteByButton.prop('disabled', true);
        $.ajax({
            url: '$saveDeveloperOfficialContactUrl',
            type: 'POST', dataType: 'json', data: payload,
            success: function (r) {
                if (!r.success) {
                    if (r.errors) {
                        setQuoteByError('quote-by-user-id',          r.errors.user_id            || '');
                        setQuoteByError('quote-by-official-email',   r.errors.official_email_id  || '');
                        setQuoteByError('quote-by-official-mobile',  r.errors.official_mobile_no || '');
                    }
                    return;
                }
                if (!forceCurrentUserUpdate) injectAdditionalQuoteByOption(r.option);
                var wasForced = forceCurrentUserUpdate;
                forceCurrentUserUpdate = false;
                forcedCurrentUserId = null;
                quoteByUserSelect.prop('disabled', false);
                lockQuoteByModal(false);
                if (addQuoteByModal) addQuoteByModal.hide();
                if (wasForced && window.Swal) {
                    Swal.fire({title:'Success', text:'Official phone number and email added successfully.', icon:'success', confirmButtonText:'Okay'});
                }
            },
            error: function () {
                if (window.Swal) Swal.fire({title:'Error', text:'Unable to save official contact details.', icon:'error'});
            },
            complete: function () { saveQuoteByButton.prop('disabled', false); }
        });
    });

    // Client address + quote number
    $('#clientquotation-client_id').on('change', function () {
        loadClientAddress($(this).val());
    });
    $('#clientquotation-quote_for').on('change', updateQuoteNumber);

    // Preview button — hidden until a quotation file is picked, then validates required fields
    $('#preview-doc').hide();
    $('#clientquotationfile-file_path').on('change', function () {
        var f = this.files && this.files[0];
        if (!f) { $('#preview-doc').hide(); return; }
        var name = (f.name || '').toLowerCase();
        var isPdf = (f.type === 'application/pdf') || name.slice(-4) === '.pdf';
        if (!isPdf) {
            if (window.Swal) Swal.fire({icon:'error', title:'Invalid File', text:'Only PDF files are allowed.'});
            else if (window.Swal) Swal.fire({icon: 'error', title: 'Invalid File', text: 'Only PDF files are allowed.'});
            else alert('Only PDF files are allowed.');
            this.value = '';
            $('#preview-doc').hide();
            return;
        }
        $('#preview-doc').show();
    });

    // Estimation cost — block non-digit keystrokes (no minus, no decimal, no exponent).
    $('#clientquotation-estimation_cost').on('keypress', function (e) {
        var c = e.which;
        if (c < 48 || c > 57) { e.preventDefault(); }
    }).on('input', function () {
        this.value = this.value.replace(/\D+/g, '');
    }).on('paste', function (e) {
        var pasted = (e.originalEvent || e).clipboardData.getData('text');
        if (!/^\d+$/.test(pasted)) e.preventDefault();
    });

    // Trial quotations may omit the estimation cost.
    function isTrial() {
        return String($('#clientquotation-deployment_type').val()) === '2';
    }

    // Show/hide the required asterisk + helper text as the deployment type changes.
    function syncEstimationCostRequirement() {
        var trial = isTrial();
        $('#estimation-cost-field').toggleClass('required', !trial);
        $('#estimation-cost-optional-hint').toggleClass('d-none', !trial);
    }
    $('#clientquotation-deployment_type').on('change', syncEstimationCostRequirement);
    syncEstimationCostRequirement();

    // Block submit if estimation cost is invalid. For a Trial a blank cost is
    // allowed; any value that IS entered must still be a positive whole number.
    form.on('submit', function (e) {
        var costEl = $('#clientquotation-estimation_cost');
        var cost   = (costEl.val() || '').trim();
        if (isTrial() && cost === '') {
            return; // Trial without a cost — let it through.
        }
        if (!/^\d+$/.test(cost) || parseInt(cost, 10) <= 0) {
            e.preventDefault();
            e.stopImmediatePropagation();
            var msg = 'Estimation cost must be a positive whole number greater than 0.';
            if (window.Swal) Swal.fire({icon:'error', title:'Invalid Input', text: msg});
            else alert(msg);
            costEl.focus();
            return false;
        }
    });

    $('#preview-doc').on('click', function (e) {
        e.preventDefault();

        var client    = $('#clientquotation-client_id').val();
        var quoteNo   = $('#clientquotation-quote_no').val();
        var project   = $('#clientquotation-project_name').val();
        var quoteFor  = $('#clientquotation-quote_for').val();
        var cost      = $('#clientquotation-estimation_cost').val();
        var date      = $('#clientquotation-quote_date').val();

        var costRequired = !isTrial();
        if (!client || !quoteNo || !project || !quoteFor || !date || (costRequired && !cost)) {
            if (window.Swal) Swal.fire({title:'Missing Fields', text:'Please fill all the details before preview', icon:'warning'});
            else alert('Please fill all the details before preview');
            return false;
        }
        // Validate the cost format only when a value is present.
        if (cost && (!/^\d+$/.test(cost) || parseInt(cost, 10) <= 0)) {
            if (window.Swal) Swal.fire({icon:'error', title:'Invalid Input', text:'Estimation cost must be a positive number greater than 0.'});
            else alert('Estimation cost must be a positive number greater than 0.');
            return false;
        }

        // Native form submit to the preview URL in a new tab — same as delivery.
        var formElement = form[0];
        var previousAction = form.attr('action');
        var previousTarget = form.attr('target');
        var previousMethod = form.attr('method');

        form.attr('action', '$previewUrl');
        form.attr('target', '_blank');
        form.attr('method', 'post');
        formElement.submit();

        window.setTimeout(function () {
            form.attr('action', previousAction || '');
            if (previousTarget) form.attr('target', previousTarget); else form.removeAttr('target');
            form.attr('method', previousMethod || 'post');
        }, 0);
    });

    loadClientAddress($('#clientquotation-client_id').val());
    updateQuoteNumber();

    if (requireOfficialContactBeforeCreate) enforceCurrentUserOfficialContact();
});
JS);
?>
