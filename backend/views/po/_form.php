<?php

/** @var yii\web\View $this */
/** @var backend\models\PoDetail $model */
/** @var array $clientOptions */
/** @var array $companyOptions */
/** @var array $inchargeOptions */
/** @var bool $isUpdate */

use backend\models\PoDetail;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$isUpdate = $isUpdate ?? false;
// On edit, the user almost always lands here from the PO dashboard — send
// Back/Cancel back there. On create, keep the standalone-listing target.
$backUrl = $isUpdate ? ['/po-dashboard/index'] : ['/po/index'];
?>

<div class="ui-page po-form" style="max-width:1000px">
    <div class="ui-head">
        <div>
            <h1><?= $isUpdate ? 'Edit purchase order' : 'Add purchase order' ?></h1>
            <div class="ui-sub"><?= $isUpdate ? 'PO ' . Html::encode($model->getOldAttribute('po_number')) : 'Record a PO received from a client.' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $isUpdate ? ['view', 'id' => $model->id] : $backUrl, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">Purchase order</div>
        <div class="card-body">
<?php $form = ActiveForm::begin([
            'id' => 'po-form',
            'enableClientValidation' => true,
            'enableAjaxValidation'   => false,
            'options'   => ['enctype' => 'multipart/form-data', 'class' => 'row g-3 ui-form'],
            'requiredCssClass' => '', // stars are written explicitly (client is conditionally required)
            'fieldConfig' => [
                'options'      => ['class' => 'mb-2'],
                'labelOptions' => ['class' => 'form-label fw-semibold'],
                'inputOptions' => ['class' => 'form-control form-control-sm'],
                'errorOptions' => ['class' => 'invalid-feedback d-block'],
            ],
        ]);
        // quote_id is stored on the model when the PO came from a client
        // quotation. It is not user-editable, but we mirror it as a hidden
        // input so the conditional client-side validation rule for client_id
        // can read it (otherwise the JS check defaults to "standalone" and
        // wrongly demands a client_id on quotation-linked POs).
        echo Html::activeHiddenInput($model, 'quote_id');
        $isQuotationLinked = !empty($model->quote_id);
        $linkedClient      = $isQuotationLinked ? ($model->quote->client ?? null) : null;
        ?>

        <?php if ($isQuotationLinked): ?>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Client</label>
                <input type="text" class="form-control form-control-sm" value="<?= Html::encode($linkedClient->client_name ?? '—') ?>" readonly>
                <div class="form-text">Linked via Quotation <?= Html::encode($model->quote->quote_no ?? '') ?>.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Company Name</label>
                <input type="text" class="form-control form-control-sm" value="<?= Html::encode($linkedClient->company_name ?? '—') ?>" readonly>
            </div>
        <?php else: ?>
            <div class="col-md-6">
                <?= $form->field($model, 'client_id')
                    ->widget(Select2::class, [
                        'data' => $clientOptions,
                        'options' => [
                            'placeholder' => 'Select Client',
                            'id'          => 'podetail-client_id',
                        ],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width'      => '100%',
                        ],
                    ])
                    ->label('Client <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold" for="po-company-select">Company Name <span class="text-danger">*</span></label>
                <?= Select2::widget([
                    'name'    => 'po_company_select',
                    'data'    => $companyOptions,
                    'value'   => $model->client_id,
                    'options' => [
                        'placeholder' => 'Select Company',
                        'id'          => 'po-company-select',
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'width'      => '100%',
                    ],
                ]) ?>
                <div class="form-text">Pick either Client or Company — the other auto-fills.</div>
            </div>
        <?php endif; ?>

        <div class="col-md-4">
            <?= $form->field($model, 'date')
                ->widget(DatePicker::class, [
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select date'],
                    'pluginOptions' => [
                        'autoclose' => true,
                        'format'    => 'yyyy-mm-dd',
                        'todayHighlight' => true,
                    ],
                ])
                ->label('PO Date <span class="text-danger">*</span>', ['encode' => false]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_number')
                ->textInput(['maxlength' => 20, 'placeholder' => 'Enter PO Number'])
                ->label('PO Number <span class="text-danger">*</span>', ['encode' => false]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_cost')
                ->textInput([
                    'type' => 'number', 'step' => '0.01', 'min' => 0,
                    'placeholder' => 'Enter PO Cost',
                ])
                ->label('Value (₹) <span class="text-danger">*</span>', ['encode' => false]) ?>
        </div>

        <div class="col-12">
            <?= $form->field($model, 'particulars')
                ->textarea(['rows' => 3, 'maxlength' => 500, 'placeholder' => 'Detail of project / PO particulars'])
                ->label('Detail of Project <span class="text-danger">*</span>', ['encode' => false]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'delivery_date')
                ->widget(DatePicker::class, [
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select delivery date'],
                    'pluginOptions' => [
                        'autoclose' => true,
                        'format'    => 'yyyy-mm-dd',
                        'todayHighlight' => true,
                    ],
                ])
                ->label('Delivery Date') ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_received_by')
                ->textInput(['maxlength' => 50, 'placeholder' => 'Accountant name'])
                ->label('Accountant') ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_incharge')
                ->widget(Select2::class, [
                    'data' => $inchargeOptions,
                    'options' => ['placeholder' => 'Select PO In-charge', 'id' => 'podetail-po_incharge'],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ])
                ->label('PO In-charge') ?>
        </div>

        <div class="col-md-6">
            <?php
            // Cancelling a PO is a separate workflow (done from the dashboard
            // status-change modal) — the create/edit form only exposes the
            // forward-flow statuses. If the PO is *already* cancelled, keep
            // the option so the dropdown can render its current value.
            $statusOptions = PoDetail::statusOptions();
            if ((int) $model->po_status !== PoDetail::STATUS_CANCELLED) {
                unset($statusOptions[PoDetail::STATUS_CANCELLED]);
            }
            ?>
            <?= $form->field($model, 'po_status')
                ->dropDownList($statusOptions, ['class' => 'form-select form-select-sm'])
                ->label('Status') ?>
        </div>

        <div class="col-md-6">
            <?= $form->field($model, 'po_file')
                ->fileInput([
                    'accept'   => 'application/pdf,.pdf,image/*,.jpg,.jpeg,.png,.gif,.webp,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,.doc,.docx',
                    'required' => !$isUpdate,
                ])
                ->label('PO File ' . (!$isUpdate ? '<span class="text-danger">*</span>' : ''), ['encode' => false])
                ->hint($isUpdate && !empty($model->file)
                    ? 'Existing: ' . basename($model->file) . ' — upload to replace.'
                    : 'Allowed: PDF, JPG, PNG, GIF, WebP, DOC, DOCX. Max 10 MB.') ?>
        </div>

        <div class="col-12 ui-form-actions">
            <?= Html::submitButton('<i class="fas fa-save me-1"></i> ' . ($isUpdate ? 'Update PO' : 'Save PO'),
                ['class' => 'btn btn-primary btn-sm px-4']) ?>
            <?= Html::a('Cancel', $isUpdate ? ['view', 'id' => $model->id] : $backUrl, ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>

        <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
(function () {
    var \$client  = jQuery('#podetail-client_id');
    var \$company = jQuery('#po-company-select');
    if (!\$client.length || !\$company.length) return;

    // Both Select2s use client_id as the option value. When one changes,
    // mirror the value to the other so picking a client auto-fills the
    // company and vice-versa.
    var syncing = false;
    function sync(\$from, \$to) {
        if (syncing) return;
        syncing = true;
        var v = \$from.val() || '';
        if (\$to.val() !== v) {
            \$to.val(v).trigger('change.select2');
        }
        syncing = false;
    }
    \$client.on('change', function () { sync(\$client, \$company); });
    \$company.on('change', function () { sync(\$company, \$client); });

    // Block non-digit / non-decimal characters in PO cost.
    jQuery('#podetail-po_cost').on('keypress', function (e) {
        var c = e.which;
        if ((c < 48 || c > 57) && c !== 46) e.preventDefault();
    });

    // File-type guard before submit (PDF / image / Word).
    jQuery('#po-form').on('beforeSubmit', function () {
        var fi = jQuery('#podetail-po_file')[0];
        if (fi && fi.files && fi.files.length) {
            var f = fi.files[0];
            var ok = /\.(pdf|jpe?g|png|gif|webp|docx?)\$/i.test(f.name);
            if (!ok) {
                if (window.Swal) Swal.fire({icon:'error', title:'Invalid file', text:'Allowed file types: PDF, JPG, PNG, GIF, WebP, DOC, DOCX.'});
                return false;
            }
        }
        return true;
    });
})();
JS);
?>
