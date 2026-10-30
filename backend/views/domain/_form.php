<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Domain $model */
/** @var \backend\models\ClientModel[] $clients */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use backend\models\Domain;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Domain' : 'Edit Domain: ' . $model->domain_name;
?>
<div class="domain-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['id' => 'domain-form']); ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <?= $form->field($model, 'client_id')->label('Client <span class="text-danger">*</span>', ['encode' => false])->widget(Select2::class, [
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'Select Client…', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'domain_name')->textInput([
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'e.g. example.com or http://example.com/',
                    ])->label('Domain Name <span class="text-danger">*</span>', ['encode' => false])
                    ?>
                    <small class="form-text text-muted">Accepts: <code>example.com</code>, <code>http://example.com/</code>, <code>https://www.example.com/</code></small>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'domain_status')->dropDownList(
                        Domain::domainStatusLabels(),
                        ['prompt' => 'Select Status…', 'class' => 'form-select form-select-sm']
                    )->label('Domain Status <span class="text-danger">*</span>', ['encode' => false]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'price')->textInput([
                        'class' => 'form-control form-control-sm',
                        'type' => 'number', 'step' => '0.01', 'min' => '0',
                        'placeholder' => '0.00',
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'date_of_purchase')->label('Date of Purchase <span class="text-danger">*</span>', ['encode' => false])->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'id' => 'domain-date_of_purchase'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => '0d', 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'expiry_date')->label('Expiry Date <span class="text-danger">*</span>', ['encode' => false])->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'id' => 'domain-expiry_date'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'service_provider')->label('Service Provider <span class="text-danger">*</span>', ['encode' => false])->textInput([
                        'class' => 'form-control form-control-sm',
                        'placeholder' => 'e.g. GoDaddy, Namecheap',
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'domain_username')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Registrar account username']) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'domain_password')->label('Domain Password <small class="text-muted">(stored as-is)</small>', ['encode' => false])->textInput([
                        'class' => 'form-control form-control-sm',
                        'id' => 'domain-password-field',
                        'type' => 'password',
                        'placeholder' => 'Registrar account password',
                    ]) ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="toggle-domain-pwd">
                        Show / Hide
                    </button>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'hosting_server')->dropDownList(
                        ['Maestro' => 'Maestro', 'Maestro Godaddy' => 'Maestro Godaddy', 'Client' => 'Client'],
                        ['prompt' => 'Select Hosting Server', 'class' => 'form-select form-select-sm']
                    ) ?>
                </div>
                <div class="col-md-12">
                    <?= $form->field($model, 'description')->textarea(['rows' => 3, 'class' => 'form-control form-control-sm', 'placeholder' => 'Additional notes about this domain']) ?>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? 'Add Domain' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
<?php
$todayJs = date('Y-m-d');
$this->registerJs(<<<JS
document.getElementById('toggle-domain-pwd').addEventListener('click', function () {
    var f = document.getElementById('domain-password-field');
    f.type = (f.type === 'password') ? 'text' : 'password';
    this.textContent = (f.type === 'password') ? 'Show / Hide' : 'Hide';
});

(function () {
    var \$purchase = jQuery('#domain-date_of_purchase');
    var \$expiry   = jQuery('#domain-expiry_date');
    var TODAY     = '$todayJs';

    function syncExpiryMin() {
        var v = \$purchase.val();
        if (v) {
            \$expiry.datepicker('setStartDate', v);
            if (\$expiry.val() && \$expiry.val() < v) {
                \$expiry.datepicker('setDate', v).val(v).trigger('change');
            }
        } else {
            \$expiry.datepicker('setStartDate', null);
        }
    }
    \$purchase.on('changeDate change', syncExpiryMin);
    syncExpiryMin();

    jQuery('#domain-form').on('submit', function (e) {
        var p = (\$purchase.val() || '').trim();
        var x = (\$expiry.val() || '').trim();
        var msg = null;
        if (p && p > TODAY)        { msg = 'Purchase date cannot be in the future.'; }
        else if (p && x && x < p)  { msg = 'Expiry date cannot be before the purchase date.'; }
        if (msg) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.Swal) Swal.fire({icon:'error', title:'Invalid Date', text: msg});
            else alert(msg);
            return false;
        }
    });
})();
JS);
?>
