<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer $developer */
/** @var \backend\models\User $user */
/** @var bool $isUpdate */
/** @var string $suggestedEmpCode */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use kartik\date\DatePicker;
use backend\models\Developer;

$isUpdate = $isUpdate ?? false;
?>

<?php $form = ActiveForm::begin([
    'id'                     => 'developer-form',
    'options'                => ['enctype' => 'multipart/form-data'],
    'enableClientValidation' => true,
    'enableAjaxValidation'   => true,
    'successCssClass'        => '',
]); ?>

<p class="text-muted small mb-2">Fields with <span class="text-danger">*</span> are required.</p>

<div class="alert alert-info py-2 mb-3">
    <strong>NOTE:</strong>
    <?= $isUpdate ? 'Ledger name will be updated if <strong>Employee Name</strong> changes.' : 'A Ledger will be Created in the name of <strong>Employee Name</strong>.' ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-3">

            <!-- Row 1: Name, DOB, Branch, Mobile -->
            <div class="col-md-3">
                <?= $form->field($developer, 'developer_name')->label('Employee Name <span class="text-danger">*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'Full name']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'dob')->widget(DatePicker::class, [
                    'type'          => DatePicker::TYPE_INPUT,
                    'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => date('Y-m-d')],
                ]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'branch')->label('Branch <span class="text-danger">*</span>', ['encode' => false])->widget(\kartik\select2\Select2::class, ['data' => Developer::$branches, 'size' => \kartik\select2\Select2::SMALL, 'options' => ['placeholder' => 'Select Branch'], 'pluginOptions' => ['allowClear' => true, 'width' => '100%']]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'mobile')->label('Mobile <span class="text-danger">*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 20, 'placeholder' => '10-digit mobile']) ?>
            </div>

            <!-- Row 2: Alt Phone, Email, Official Email, Qualification -->
            <div class="col-md-3">
                <?= $form->field($developer, 'official_mobile_no')->label('Alt Phone Number')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 20, 'placeholder' => 'Alternate phone']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'email_id')->label('Email')->input('email', ['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'name@example.com']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'official_email_id')->label('Official Email')->input('email', ['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'name@greendivine.in']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'qualification')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'e.g. B.E. Computer Science']) ?>
            </div>

            <!-- Row 3: Username, Password, Repeat Password (create only) -->
            <?php if (!$isUpdate): ?>
            <div class="col-md-3">
                <label class="form-label">User Name <span class="text-danger">*</span></label>
                <input type="text" name="username"
                       value="<?= Html::encode(Yii::$app->request->post('username', '')) ?>"
                       placeholder="Login username"
                       class="form-control form-control-sm" required maxlength="50" autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" id="dev-password"
                       placeholder="At least 6 characters"
                       class="form-control form-control-sm" required minlength="6" autocomplete="new-password">
            </div>
            <div class="col-md-3">
                <label class="form-label">Repeat Password <span class="text-danger">*</span></label>
                <input type="password" name="repeatpassword" id="dev-repeatpassword"
                       placeholder="Re-enter password"
                       class="form-control form-control-sm" required minlength="6" autocomplete="new-password">
            </div>
            <?php else: ?>
            <!-- Edit-only: optional password change. Leave blank to keep current password. -->
            <div class="col-md-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" id="dev-password"
                       placeholder="Leave blank to keep current"
                       class="form-control form-control-sm" minlength="6" autocomplete="new-password">
            </div>
            <div class="col-md-3">
                <label class="form-label">Repeat New Password</label>
                <input type="password" name="repeatpassword" id="dev-repeatpassword"
                       placeholder="Re-enter new password"
                       class="form-control form-control-sm" minlength="6" autocomplete="new-password">
            </div>
            <?php endif; ?>

            <!-- Employment Type, Department, Designation -->
            <div class="col-md-3">
                <?= $form->field($developer, 'employment_type')->widget(\kartik\select2\Select2::class, ['data' => Developer::$employmentTypes, 'size' => \kartik\select2\Select2::SMALL, 'options' => ['placeholder' => 'Select Employment Type'], 'pluginOptions' => ['allowClear' => true, 'width' => '100%']]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'department')->label('Department <span class="text-danger">*</span>', ['encode' => false])->widget(\kartik\select2\Select2::class, ['data' => Developer::$departments, 'size' => \kartik\select2\Select2::SMALL, 'options' => ['placeholder' => 'Select Department'], 'pluginOptions' => ['allowClear' => true, 'width' => '100%']]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'designation')->label('Designation <span class="text-danger">*</span>', ['encode' => false])->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'e.g. Sales Executive']) ?>
            </div>

            <!-- Emp Code, Team, Date of Join -->
            <div class="col-md-3">
                <?= $form->field($developer, 'emp_code')
                        ->label('Emp Code')
                        ->textInput([
                            'class'       => 'form-control form-control-sm',
                            'maxlength'   => 20,
                            'placeholder' => 'e.g. ' . \backend\models\Developer::formatEmpCode(1),
                        ])
                        ->hint($isUpdate ? false : 'Auto-generated — edit if you need a different code.') ?>
                <?php if (!$isUpdate): ?>
                    <?php /* The code we offered. The controller compares the submitted value
                             against this: if it's untouched but got taken while this form was
                             open, it silently rolls to the next free code instead of failing. */ ?>
                    <?= Html::hiddenInput('emp_code_auto', $suggestedEmpCode ?? '') ?>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'team')->label('Team <span class="text-danger">*</span>', ['encode' => false])->widget(\kartik\select2\Select2::class, ['data' => Developer::$teams, 'size' => \kartik\select2\Select2::SMALL, 'options' => ['placeholder' => 'Select Team'], 'pluginOptions' => ['allowClear' => true, 'width' => '100%']]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'doj')->label('Date of Join')->widget(DatePicker::class, [
                    'type'          => DatePicker::TYPE_INPUT,
                    'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                ]) ?>
            </div>

            <?php if ($isUpdate): ?>
            <div class="col-md-3">
                <?= $form->field($developer, 'dor')->label('Date of Resignation')->widget(DatePicker::class, [
                    'type'          => DatePicker::TYPE_INPUT,
                    'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                ]) ?>
            </div>
            <?php endif; ?>

            <!-- Account Number, IFSC, Bank, Bank Branch -->
            <div class="col-md-3">
                <?= $form->field($developer, 'account_number')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'Bank account number']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'ifsc_code')->label('IFSC Code')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'e.g. HDFC0000123']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'bank')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'Bank name']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'bank_branch')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'Branch name']) ?>
            </div>

            <!-- Gross Salary, CL Backup, CL Allotted, Hiring Mode, ESI, PF, UAN, PAN -->
            <div class="col-md-3">
                <?= $form->field($developer, 'gross_salary')->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm', 'placeholder' => '0.00']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'cl_backup')->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm', 'placeholder' => 'Carry-forward leaves']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'cl_allotted')->label('CL Allotted (per month)')->input('number', ['step' => '0.01', 'class' => 'form-control form-control-sm', 'placeholder' => 'Leaves per month']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'hiring_mode')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'e.g. Referral, Campus, Job portal']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'esi_details')->label('ESI Number')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'ESI number']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'pf_details')->label('PF Number')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'PF number']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'uan_details')->label('UAN Number')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'UAN number']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($developer, 'pan')->label('PAN Number')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 50, 'placeholder' => 'e.g. ABCDE1234F']) ?>
            </div>

            <!-- Profile Photo -->
            <div class="col-md-3">
                <?= $form->field($developer, 'profile_photo')->label('Profile Photo')->fileInput(['accept' => 'image/jpeg,image/png,image/gif,image/webp']) ?>
                <?php if ($isUpdate && $developer->profile_photo): ?>
                    <small class="text-muted">Current: <?= Html::encode($developer->profile_photo) ?></small>
                <?php endif; ?>
            </div>

            <!-- Address (full width) -->
            <div class="col-md-3">
                <?= $form->field($developer, 'address')->label('Address <span class="text-danger">*</span>', ['encode' => false])->textarea(['rows' => 4, 'class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'Street, area, city, state, pincode']) ?>
            </div>

        </div>
    </div>
</div>

<!-- Salary Management (requirement #1) -->
<?php
// Each component renders a "Applicable?" select + an amount input that is shown
// only when the select is "Applicable". $statusOpts drives the select.
$statusOpts = [0 => 'Not Applicable', 1 => 'Applicable'];
// PF / ESI / Professional Tax are per-employee Applicable toggles (their amounts
// are computed from the Manage Deductions settings). Incentive / Allowance carry
// a per-employee amount.
$salaryComponents = [
    'pf'               => ['status' => 'pf_status',               'label' => 'PF',               'toggleOnly' => true],
    'esi'              => ['status' => 'esi_status',              'label' => 'ESI',              'toggleOnly' => true],
    'professional_tax' => ['status' => 'professional_tax_status', 'label' => 'Professional Tax', 'toggleOnly' => true],
    'incentive'        => ['status' => 'incentive_status',        'label' => 'Incentive',        'amounts' => ['incentive_amount' => 'Incentive Amount (₹)']],
    'allowance'        => ['status' => 'allowance_applicable',    'label' => 'Allowance',        'amounts' => ['allowance_amount' => 'Allowance Amount (₹)']],
];
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">Salary Management</div>
    <div class="card-body">
        <div class="row g-3">

            <!-- Master switch: only "salary applicable" employees appear on Create Payslip -->
            <div class="col-md-4">
                <?= $form->field($developer, 'is_salary_applicable')->dropDownList(
                    $statusOpts,
                    ['class' => 'form-select form-select-sm']
                ) ?>
            </div>
            <div class="col-md-8 d-flex align-items-center">
                <small class="text-muted">Only employees marked <strong>Salary Applicable = Applicable</strong> are listed on the Create Payslip screen.</small>
            </div>

            <?php foreach ($salaryComponents as $c):
                $toggleOnly = !empty($c['toggleOnly']);
                $shown      = (int)$developer->{$c['status']} === 1;
            ?>
            <div class="col-md-6">
                <div class="row g-2 align-items-start">
                    <div class="col-6">
                        <?= $form->field($developer, $c['status'])->label($c['label'] . ' Applicable')->dropDownList(
                            $statusOpts,
                            ['class' => 'form-select form-select-sm salary-status', 'data-target' => $c['status']]
                        ) ?>
                    </div>
                    <?php if (!$toggleOnly): ?>
                    <?php foreach ($c['amounts'] as $field => $label): ?>
                    <div class="col-6 salary-amount-wrap" data-group="<?= $c['status'] ?>"
                         style="<?= $shown ? '' : 'display:none' ?>">
                        <?= $form->field($developer, $field)->label($label)->input('number', [
                            'step' => '0.01', 'min' => 0, 'class' => 'form-control form-control-sm', 'placeholder' => '0.00',
                        ]) ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <?= Html::submitButton($isUpdate ? 'Update Employee' : 'Submit', ['class' => 'btn btn-primary submit_btn']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>

<?php ActiveForm::end(); ?>

<?php $this->registerJs(<<<JS
// Show a component's amount field(s) only when its "Applicable" select = Applicable (1).
// PF/ESI have two wraps (employee + employer) sharing the same data-group.
jQuery('.salary-status').each(function () {
    var sel = this;
    function sync() {
        var target = sel.getAttribute('data-target');
        jQuery('.salary-amount-wrap[data-group="' + target + '"]')
            .css('display', sel.value === '1' ? '' : 'none');
    }
    jQuery(sel).on('change', sync);
    sync();
});
JS, \yii\web\View::POS_READY); ?>

<?php if (!$isUpdate): ?>
<script>
document.getElementById('developer-form').addEventListener('submit', function(e) {
    var pwd    = document.getElementById('dev-password').value;
    var repeat = document.getElementById('dev-repeatpassword').value;
    if (pwd !== repeat) {
        e.preventDefault();
        Swal.fire({icon:'error', title:'Error', text:'Passwords do not match.'});
        document.getElementById('dev-repeatpassword').focus();
    }
});
</script>
<?php else: ?>
<script>
document.getElementById('developer-form').addEventListener('submit', function(e) {
    var pwdEl    = document.getElementById('dev-password');
    var repeatEl = document.getElementById('dev-repeatpassword');
    if (!pwdEl || !repeatEl) return;
    var pwd    = pwdEl.value;
    var repeat = repeatEl.value;
    // Empty = keep current password (skip validation)
    if (!pwd && !repeat) return;
    if (pwd.length < 6) {
        e.preventDefault();
        Swal.fire({icon:'error', title:'Error', text:'Password must be at least 6 characters.'});
        pwdEl.focus();
        return;
    }
    if (pwd !== repeat) {
        e.preventDefault();
        Swal.fire({icon:'error', title:'Error', text:'Passwords do not match.'});
        repeatEl.focus();
    }
});
</script>
<?php endif; ?>
