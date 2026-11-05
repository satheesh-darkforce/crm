<?php

/** @var yii\web\View $this */
/** @var backend\models\PoDetail $model */
/** @var backend\models\ClientQuotation $rootQuote */
/** @var backend\models\ClientQuotation $latestQuote */
/** @var int $returnId */
/** @var array $inchargeOptions */

use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Add Purchase Order — ' . $rootQuote->quote_no;
?>
<style>
.po-wrap   { padding:0; }
.po-card   { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:22px 24px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04); }
.po-head   { padding-bottom:14px; margin-bottom:18px; border-bottom:2px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
.po-head h4{ margin:0; font-weight:700; color:#1e293b; }
.po-help   { font-size:.78rem; color:#64748b; }
.required-star { color:#dc2626; }
</style>

<div class="ui-page po-wrap" style="max-width:1000px">
    <div class="ui-head">
        <div>
            <h1>Add purchase order</h1>
            <div class="ui-sub">Record the client's PO against this quotation.</div>
        </div>
        <div class="ui-actions">
            <a href="<?= Url::to(['view', 'id' => $returnId]) ?>" class="btn btn-outline-secondary btn-sm" onclick="event.preventDefault(); window.location.replace(this.href); return false;"><i class="fas fa-arrow-left me-1"></i>Back to quotation</a>
        </div>
    </div>
    <div class="po-card">
<?php $form = ActiveForm::begin([
            'id' => 'po-form',
            'enableClientValidation' => true,
            'enableAjaxValidation'   => false,
            'options'   => ['enctype' => 'multipart/form-data', 'class' => 'row g-3'],
            'fieldConfig' => [
                'options'      => ['class' => 'mb-2'],
                'labelOptions' => ['class' => 'form-label fw-semibold'],
                'inputOptions' => ['class' => 'form-control form-control-sm'],
                'errorOptions' => ['class' => 'invalid-feedback d-block'],
            ],
        ]); ?>

        <div class="col-md-4">
            <label class="form-label fw-semibold">Quote No <span class="required-star">*</span></label>
            <input type="text" class="form-control form-control-sm" value="<?= Html::encode($latestQuote->quote_no) ?>" readonly>
            <div class="po-help">Latest quotation number under this parent.</div>
        </div>

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
                ->label('Date <span class="required-star">*</span>', ['encode' => false]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_number')
                ->textInput([
                    'maxlength'   => 20,
                    'placeholder' => 'Enter PO Number',
                ])
                ->label('PO Number <span class="required-star">*</span>', ['encode' => false]) ?>
        </div>

        <div class="col-12">
            <?= $form->field($model, 'particulars')
                ->textarea(['rows' => 3, 'maxlength' => 500, 'placeholder' => 'PO particulars / scope of work'])
                ->label('Particulars <span class="required-star">*</span>', ['encode' => false]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'given_cost')
                ->textInput([
                    'readonly'    => true,
                    'placeholder' => 'Given Cost',
                ])
                ->label('Given Cost <span class="required-star">*</span>', ['encode' => false])
                ->hint('Auto-filled from the latest quotation cost.') ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_cost')
                ->textInput([
                    'type'        => 'number',
                    'step'        => '0.01',
                    'min'         => 0,
                    'placeholder' => 'Enter PO Cost',
                ])
                ->label('PO Cost <span class="required-star">*</span>', ['encode' => false]) ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_file')
                ->fileInput([
                    'accept'   => 'application/pdf,.pdf,image/*,.jpg,.jpeg,.png,.gif,.webp,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,.doc,.docx',
                    'required' => true,
                ])
                ->label('PO File <span class="required-star">*</span>', ['encode' => false])
                ->hint('Allowed: PDF, JPG, PNG, GIF, WebP, DOC, DOCX. Max 10 MB.') ?>
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
                ->label('PO Delivery Date') ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_received_by')
                ->textInput([
                    'maxlength'   => 50,
                    'placeholder' => 'Accountant name',
                ])
                ->label('Accountant') ?>
        </div>

        <div class="col-md-4">
            <?= $form->field($model, 'po_incharge')
                ->widget(Select2::class, [
                    'data' => $inchargeOptions,
                    'options' => [
                        'placeholder' => 'Select PO In-charge',
                        'id' => 'podetail-po_incharge',
                    ],
                    'pluginOptions' => [
                        'allowClear' => true,
                        'width'      => '100%',
                    ],
                ])
                ->label('PO In-charge') ?>
        </div>

        <div class="col-12" id="reason-wrap" style="display:none;">
            <?= $form->field($model, 'reason')
                ->textarea(['rows' => 3, 'maxlength' => 500, 'placeholder' => 'Reason for cost reduction'])
                ->label('Reason <span class="required-star">*</span>', ['encode' => false])
                ->hint('Required when PO cost is less than the given cost.') ?>
        </div>

        <div class="col-12 d-flex gap-2">
            <?= Html::submitButton('<i class="fas fa-save me-1"></i> Save PO', [
                'class' => 'btn btn-primary btn-sm',
            ]) ?>
            <a href="<?= Url::to(['view', 'id' => $returnId]) ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<?php
$this->registerJs(<<<JS
(function () {
    var \$po     = jQuery('#podetail-po_cost');
    var \$given  = jQuery('#podetail-given_cost');
    var \$reasonWrap = jQuery('#reason-wrap');
    var \$reason = jQuery('#podetail-reason');

    function toggleReason() {
        var po = parseFloat(\$po.val() || '0');
        var gc = parseFloat(\$given.val() || '0');
        if (!isNaN(po) && !isNaN(gc) && po < gc && po > 0) {
            \$reasonWrap.show();
        } else {
            \$reasonWrap.hide();
            \$reason.val('');
        }
    }

    \$po.on('input change', toggleReason);
    \$given.on('input change', toggleReason);
    toggleReason();

    // Client-side file-type check before submit (defence-in-depth).
    jQuery('#po-form').on('beforeSubmit', function () {
        var fi = jQuery('#podetail-po_file')[0];
        if (fi && fi.files && fi.files.length) {
            var f = fi.files[0];
            var ok = /\.(pdf|jpe?g|png|gif|webp|docx?)\$/i.test(f.name);
            if (!ok) {
                if (window.Swal) { Swal.fire({icon:'error', title:'Invalid file', text:'Allowed file types: PDF, JPG, PNG, GIF, WebP, DOC, DOCX.'}); }
                return false;
            }
        }
        return true;
    });
})();
JS);
?>
