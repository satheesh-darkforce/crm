<?php
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;
$this->title = 'Generate Payslip';
?>
<div class="payslip-create">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>

    <?php $form = ActiveForm::begin(); ?>
    <div class="row g-3">
        <div class="col-md-4">
            <?= $form->field($payslip, 'developer_id')->widget(Select2::class, [
                'data'          => ArrayHelper::map($developers, 'id', 'developer_name'),
                'options'       => ['placeholder' => 'Select developer…'],
                'pluginOptions' => ['allowClear' => true],
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($payslip, 'month_year')->label('Month')->widget(DatePicker::class, [
                'type'          => DatePicker::TYPE_INPUT,
                'options'       => ['placeholder' => 'Select Month', 'class' => 'form-control form-control-sm', 'autocomplete' => 'off'],
                'pluginOptions' => ['format' => 'yyyy-mm', 'startView' => 'months', 'minViewMode' => 'months', 'autoclose' => true],
            ]) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'no_of_working_days')->input('number', ['class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'gross_salary')->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'leave_allocated')->input('number', ['step' => '0.5', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'leave_taken')->input('number', ['step' => '0.5', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'leave_lop')->label('Leave LOP')->input('number', ['step' => '0.5', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'late_permission')->input('number', ['step' => '0.5', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'late_lop')->label('Late LOP')->input('number', ['step' => '0.5', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'additional_working')->input('number', ['step' => '0.5', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <?= $form->field($payslip, 'total_lop')->label('Total LOP')->input('number', ['step' => '0.5', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-md-2">
            <label class="form-label">Net Salary
                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 ms-1" id="calc-salary" title="Auto-calculate from Gross &amp; LOP" style="font-size:.7rem">Calc</button>
            </label>
            <?= $form->field($payslip, 'actual_salary')->label(false)->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm']) ?>
        </div>
        <div class="col-12">
            <?= $form->field($payslip, 'remarks')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'Optional notes for this payslip']) ?>
        </div>

        <!-- Earnings / Deductions -->
        <div class="col-12">
            <label class="form-label fw-bold">Earnings &amp; Deductions</label>
            <table class="table table-sm table-bordered" id="payslip-items">
                <thead class="table-light">
                    <tr><th>Label</th><th>Type</th><th>Amount (₹)</th><th></th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="items[0][label]" class="form-control form-control-sm" placeholder="e.g. Basic Salary"></td>
                        <td>
                            <select name="items[0][item_type]" class="form-select form-select-sm">
                                <option value="1">Earning</option>
                                <option value="2">Deduction</option>
                            </select>
                        </td>
                        <td><input type="number" name="items[0][amount]" step="0.01" class="form-control form-control-sm"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">×</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="add-item">+ Add Row</button>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <?= Html::submitButton('Generate', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(<<<JS
let idx = 1;
document.getElementById('add-item').addEventListener('click', function() {
    const tbody = document.querySelector('#payslip-items tbody');
    const row = tbody.querySelector('tr').cloneNode(true);
    row.querySelectorAll('[name]').forEach(el => { el.name = el.name.replace('[0]', '[' + idx + ']'); });
    row.querySelector('input[type=text]').value = '';
    row.querySelector('input[type=number]').value = '';
    tbody.appendChild(row);
    idx++;
});
document.querySelector('#payslip-items').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row')) e.target.closest('tr').remove();
});

// Auto-calculate net salary from gross + LOP
document.getElementById('calc-salary').addEventListener('click', function() {
    const gross   = parseFloat(document.getElementById('payslip-gross_salary')?.value || document.querySelector('[id$="gross_salary"]')?.value || 0);
    const lop     = parseFloat(document.getElementById('payslip-total_lop')?.value || document.querySelector('[id$="total_lop"]')?.value || 0);
    const working = parseFloat(document.getElementById('payslip-no_of_working_days')?.value || document.querySelector('[id$="no_of_working_days"]')?.value || 26) || 26;
    if (gross <= 0) { Swal.fire({icon:'warning', title:'Required', text:'Enter Gross Salary first.'}); return; }
    const net = Math.max(0, Math.round((gross - (gross / working * lop)) * 100) / 100);
    const netField = document.getElementById('payslip-actual_salary') || document.querySelector('[id$="actual_salary"]');
    if (netField) netField.value = net.toFixed(2);
});
JS);
?>
