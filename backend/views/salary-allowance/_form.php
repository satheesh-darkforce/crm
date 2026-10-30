<?php
/** @var \yii\web\View $this */
/** @var \backend\models\SalaryAllowance $model */

use backend\models\SalaryAllowance;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isNew     = $model->isNewRecord;
$isAllow   = $model->type == 1;
$typeLabel = $isAllow ? 'Allowance' : 'Deduction';
$this->title = $isNew ? "Add $typeLabel" : "Edit $typeLabel";

// PF/ESI rows carry an employee + employer rate + wage ceiling. Everything else
// (earnings %, flat amounts like PT) uses the single Default Value.
$isStatutory = in_array($model->code, [SalaryAllowance::CODE_PF, SalaryAllowance::CODE_ESI], true);
$isPf  = $model->code === SalaryAllowance::CODE_PF;
$isEsi = $model->code === SalaryAllowance::CODE_ESI;
$compName = $isPf ? 'PF' : ($isEsi ? 'ESI' : 'PF / ESI');
// Admin charge is a PF-only concept — hide it for ESI.
$showAdmin = !$isEsi;
$statCol   = $showAdmin ? 'col-md-3' : 'col-md-4';
$ceilingHint = $isPf
    ? 'PF wage cap — contribution is capped at this amount.'
    : ($isEsi
        ? 'ESI eligibility limit — no ESI above this Pay After LOP.'
        : 'PF cap / ESI eligibility limit.');
$req = '<span class="text-danger">*</span>';
?>
<style>
.salary-allowance-form .stat-row .form-label{white-space:nowrap;font-size:.8rem}
.salary-allowance-form .stat-row .form-text{min-height:2.2em}
</style>
<div class="salary-allowance-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?>
            <?php if ($model->code): ?>
                <span class="badge bg-light text-secondary border ms-1"><?= Html::encode($model->code) ?></span>
            <?php endif; ?>
        </h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', [$isAllow ? 'allowances' : 'deductions'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:640px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['id' => 'salary-allowance-form']); ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <?= $form->field($model, 'title')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'e.g. House Rent Allowance'])->label("Title $req") ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'type2')->dropDownList(
                        [1 => 'Percentage (%)', 2 => 'Fixed Amount'],
                        ['class' => 'form-select form-select-sm', 'prompt' => '— Select —']
                    )->label("Value Type $req") ?>
                </div>

                <div class="col-md-6 <?= $isStatutory ? 'd-none' : '' ?>" id="fld-default-value">
                    <?= $form->field($model, 'default_value')->textInput([
                        'class' => 'form-control form-control-sm',
                        'placeholder' => '0.00',
                    ])->label(($model->type2 == 1 ? 'Value (%)' : 'Amount (₹)') . " $req") ?>
                    <div class="form-text">
                        <?= $model->type2 == 1 ? 'Percentage of the Calculated-On base.' : 'Flat amount (e.g. Professional Tax).' ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <?= $form->field($model, 'calc_basis')->dropDownList(
                        [
                            SalaryAllowance::BASIS_PAY_AFTER_LOP => 'Pay After LOP',
                            SalaryAllowance::BASIS_BASIC_PLUS_DA => 'Basic + DA',
                        ],
                        ['class' => 'form-select form-select-sm', 'prompt' => '— Not applicable —']
                    )->label('Calculated On')->hint('Base that a percentage applies on (earnings + PF/ESI).') ?>
                </div>
            </div>

            <!-- Statutory contribution split — PF / ESI only -->
            <div class="border rounded p-3 mt-2 <?= $isStatutory ? '' : 'd-none' ?>" id="statutory-block" style="background:#f8f9ff">
                <div class="fw-semibold small text-muted mb-2 text-uppercase" style="letter-spacing:.04em">
                    <?= Html::encode($compName) ?> contribution
                </div>
                <div class="row g-3 stat-row">
                    <div class="<?= $statCol ?>">
                        <?= $form->field($model, 'employee_value')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'e.g. 12'])->label('Employee %')->hint('Employee side, max 100%.') ?>
                    </div>
                    <div class="<?= $statCol ?>">
                        <?= $form->field($model, 'employer_value')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'e.g. 12'])->label('Employer %')->hint('Employer side, max 100%.') ?>
                    </div>
                    <?php if ($showAdmin): ?>
                    <div class="<?= $statCol ?>">
                        <?= $form->field($model, 'admin_charge')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => 'e.g. 1'])->label('Admin %')->hint('PF admin charge, deducted from net.') ?>
                    </div>
                    <?php endif; ?>
                    <div class="<?= $statCol ?>">
                        <?= $form->field($model, 'wage_ceiling')->textInput(['class' => 'form-control form-control-sm', 'placeholder' => $isEsi ? 'e.g. 21000' : 'e.g. 15000'])->label('Ceiling (₹)')->hint($ceilingHint) ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? "Add $typeLabel" : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', [$isAllow ? 'allowances' : 'deductions'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
<?php
$this->registerJs(<<<JS
// Show the statutory (employee/employer/ceiling) block only when a rate is used,
// so the plain earnings/flat-amount rows stay uncluttered.
// Using employee/employer rates makes the row statutory (PF/ESI): reveal the
// split block and hide the single Value field, which no longer applies.
jQuery('#salaryallowance-employee_value, #salaryallowance-employer_value').on('input', function() {
    if (jQuery(this).val()) {
        jQuery('#statutory-block').removeClass('d-none');
        jQuery('#fld-default-value').addClass('d-none');
    }
});
JS, \yii\web\View::POS_READY);
