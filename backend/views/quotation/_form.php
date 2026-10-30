<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Quotation $model */
/** @var \backend\models\QuotationDetail $detail */
/** @var \backend\models\Currency[] $currencies */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Quotation' : 'Edit Quotation';
?>
<div class="quotation-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= $this->title ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <p class="text-muted small mb-3">Fields with <span class="text-danger">*</span> are required.</p>

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light py-2"><strong class="small">Client & Project Info</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <?= $form->field($model, 'client_name')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 100, 'placeholder' => 'Enter client name'])->label('Client Name <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'project_name')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 100, 'placeholder' => 'Enter project name'])->label('Project Name <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'email')->input('email', ['class' => 'form-control form-control-sm', 'maxlength' => 100, 'placeholder' => 'Enter email address'])->label('Email <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'mobile')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 15, 'placeholder' => 'Enter mobile number']) ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isNew): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light py-2"><strong class="small">Initial Quote</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <?= $form->field($detail, 'currency_id')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($currencies, 'id', 'title'),
                        'options'       => ['placeholder' => '— Select Currency —'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Currency <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($detail, 'quote_amount')->textInput(['class' => 'form-control form-control-sm', 'type' => 'number', 'min' => 1, 'step' => '0.01', 'placeholder' => '0.00'])->label('Quote Amount <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($detail, 'proposal_send_date')->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'readonly' => true],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => date('Y-m-d')],
                    ])->label('Proposal Send Date <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>

                <!-- Multi-file upload (matches Yii1 CMultiFileUpload behaviour) -->
                <div class="col-md-8">
                    <label class="form-label">Attachments <small class="text-muted">(PDF, DOC, DOCX, PPT, PPTX — max 20MB each, up to 20 files)</small></label>
                    <input type="file" id="mf-picker" class="form-control form-control-sm"
                           accept=".pdf,.doc,.docx,.ppt,.pptx">
                    <!-- Hidden real input that holds the DataTransfer files for submission -->
                    <input type="file" id="mf-real" name="QuotationDetail[attachmentFiles][]" multiple style="display:none">
                    <div id="mf-list" class="mt-2"></div>
                    <div id="mf-error" class="text-danger small mt-1" style="display:none"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <?= Html::submitButton($isNew ? 'Create Quotation' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php if ($isNew): ?>
<style>
.mf-item{display:inline-flex;align-items:center;gap:6px;background:#f0f4f8;border:1px solid #d0d7de;border-radius:4px;padding:3px 8px;margin:3px 4px 3px 0;font-size:.8rem}
.mf-item .mf-name{max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mf-item .mf-size{color:#6c757d;font-size:.72rem}
.mf-remove{background:none;border:none;color:#dc3545;font-weight:bold;cursor:pointer;padding:0 2px;font-size:1rem;line-height:1}
.mf-remove:hover{color:#a71d2a}
</style>
<script>
(function(){
    var allowedExt = ['pdf','doc','docx','ppt','pptx'];
    var maxSize    = 20 * 1024 * 1024; // 20 MB
    var maxFiles   = 20;
    var files      = []; // {file: File, name: string}

    var picker  = document.getElementById('mf-picker');
    var realInp = document.getElementById('mf-real');
    var listEl  = document.getElementById('mf-list');
    var errEl   = document.getElementById('mf-error');

    function showError(msg) {
        errEl.textContent = msg;
        errEl.style.display = '';
        setTimeout(function(){ errEl.style.display = 'none'; }, 4000);
    }

    function getExt(name) {
        return name.split('.').pop().toLowerCase();
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function syncReal() {
        var dt = new DataTransfer();
        files.forEach(function(f){ dt.items.add(f.file); });
        realInp.files = dt.files;
    }

    function render() {
        listEl.innerHTML = '';
        files.forEach(function(f, idx) {
            var item = document.createElement('span');
            item.className = 'mf-item';
            item.innerHTML = '<span class="mf-name" title="' + f.name + '">' + f.name + '</span>'
                + '<span class="mf-size">(' + formatSize(f.file.size) + ')</span>'
                + '<button type="button" class="mf-remove" data-idx="' + idx + '" title="Remove">&times;</button>';
            listEl.appendChild(item);
        });
        syncReal();
    }

    picker.addEventListener('change', function() {
        var selected = this.files;
        for (var i = 0; i < selected.length; i++) {
            var file = selected[i];
            var ext = getExt(file.name);

            // type check
            if (allowedExt.indexOf(ext) === -1) {
                showError('File type not allowed: "' + file.name + '". Accepted: PDF, DOC, DOCX, PPT, PPTX.');
                continue;
            }
            // size check
            if (file.size > maxSize) {
                showError('File size must be smaller than 20 MB: "' + file.name + '"');
                continue;
            }
            // duplicate check
            var isDup = files.some(function(f){ return f.name === file.name && f.file.size === file.size; });
            if (isDup) {
                showError('Already selected: "' + file.name + '"');
                continue;
            }
            // max count
            if (files.length >= maxFiles) {
                showError('Maximum ' + maxFiles + ' files allowed.');
                break;
            }
            files.push({file: file, name: file.name});
        }
        this.value = ''; // reset picker so same file can be re-selected later if removed
        render();
    });

    listEl.addEventListener('click', function(e) {
        var btn = e.target.closest('.mf-remove');
        if (!btn) return;
        var idx = parseInt(btn.getAttribute('data-idx'));
        files.splice(idx, 1);
        render();
    });
})();
</script>
<?php endif; ?>
