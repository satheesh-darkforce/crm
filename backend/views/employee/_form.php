<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Employee $employee */
/** @var \backend\models\User $user */
/** @var bool $isUpdate */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isUpdate = $isUpdate ?? false;
?>
<?php $form = ActiveForm::begin(['enableAjaxValidation' => false, 'enableClientValidation' => true]); ?>

<p class="text-muted small mb-3">Fields with <span class="text-danger">*</span> are required.</p>

<?php /* ── Error summary block (all validation errors at top) ── */ ?>
<?php
$allErrors = $employee->getErrors();
if (isset($user) && $user->hasErrors()) {
    $allErrors = array_merge($allErrors, $user->getErrors());
}
if (!empty($allErrors)): ?>
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <strong class="small">Please fix the following errors:</strong>
    <ul class="mb-0 small">
        <?php foreach ($allErrors as $fieldErrors): ?>
            <?php foreach ((array)$fieldErrors as $err): ?>
                <li><?= Html::encode($err) ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($isUpdate): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-light py-2"><strong class="small">Update Password <span class="text-muted fw-normal">(leave blank to keep current password)</span></strong></div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            $pwdErr = isset($user) ? $user->getFirstError('password') : null;
            ?>
            <div class="col-md-4">
                <label class="form-label">New Password</label>
                <input type="password" id="emp_password" name="password"
                       value=""
                       placeholder="At least 6 characters"
                       class="form-control form-control-sm <?= $pwdErr ? 'is-invalid' : '' ?>"
                       minlength="6" maxlength="50" autocomplete="new-password">
                <?php if ($pwdErr): ?>
                <div class="invalid-feedback"><?= Html::encode($pwdErr) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Repeat New Password</label>
                <input type="password" id="emp_repeat_password" name="repeat_password"
                       value=""
                       placeholder="Re-enter password"
                       class="form-control form-control-sm"
                       minlength="6" maxlength="50" autocomplete="new-password">
                <div id="emp_password_err" class="invalid-feedback">Passwords do not match.</div>
            </div>
        </div>
    </div>
</div>
<?php $this->registerJs(<<<JS
(function () {
    var form   = document.querySelector('form');
    var pwd    = document.getElementById('emp_password');
    var repeat = document.getElementById('emp_repeat_password');
    var errDiv = document.getElementById('emp_password_err');
    if (!form || !pwd || !repeat) return;

    function showError(msg, focusEl) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: msg, confirmButtonText: 'OK' })
                .then(function () { if (focusEl) focusEl.focus(); });
        } else {
            alert(msg);
            if (focusEl) focusEl.focus();
        }
    }
    function liveRepeat() {
        if (repeat.value && pwd.value !== repeat.value) {
            repeat.classList.add('is-invalid'); repeat.classList.remove('is-valid');
            errDiv.style.display = 'block';
        } else {
            repeat.classList.remove('is-invalid');
            if (repeat.value) repeat.classList.add('is-valid');
            errDiv.style.display = 'none';
        }
    }
    pwd.addEventListener('input', liveRepeat);
    repeat.addEventListener('input', liveRepeat);

    form.addEventListener('submit', function (e) {
        var pwdV = pwd.value;
        var repV = repeat.value;
        // Empty = keep current password — skip validation entirely.
        if (!pwdV && !repV) return;
        var fail = null, focusEl = null;
        if (pwdV.length < 6)        { fail = 'Password must be at least 6 characters.';  focusEl = pwd; }
        else if (!repV)             { fail = 'Please re-enter the new password.';        focusEl = repeat; }
        else if (pwdV !== repV)     { fail = 'Passwords do not match.';                   focusEl = repeat; }
        if (fail) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (focusEl) focusEl.classList.add('is-invalid');
            showError(fail, focusEl);
            return false;
        }
    }, true);
})();
JS); ?>
<?php endif; ?>

<?php if (!$isUpdate): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-light py-2"><strong class="small">Login Account</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            // Preserve user input on validation failure so the form does not
            // look like it "reset". Password is echoed back intentionally —
            // the only way to avoid re-typing after a server-side error.
            $usernameVal = (string) Yii::$app->request->post('username', '');
            $passwordVal = (string) Yii::$app->request->post('password', '');
            $repeatPwdVal = (string) Yii::$app->request->post('repeat_password', '');
            $usernameErr = $user->getFirstError('username');
            $pwdErr      = $user->getFirstError('password');
            ?>
            <div class="col-md-4">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" value="<?= Html::encode($usernameVal) ?>"
                       placeholder="Login username"
                       class="form-control form-control-sm <?= $usernameErr ? 'is-invalid' : '' ?>" maxlength="50" autocomplete="off">
                <?php if ($usernameErr): ?>
                <div class="invalid-feedback"><?= Html::encode($usernameErr) ?></div>
                <?php else: ?>
                <div class="form-text text-muted">Used to log in to the portal</div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" id="emp_password" name="password"
                       value="<?= Html::encode($passwordVal) ?>"
                       placeholder="At least 6 characters"
                       class="form-control form-control-sm <?= $pwdErr ? 'is-invalid' : '' ?>"
                       required minlength="6" maxlength="50" autocomplete="new-password">
                <?php if ($pwdErr): ?>
                <div class="invalid-feedback"><?= Html::encode($pwdErr) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Repeat Password <span class="text-danger">*</span></label>
                <input type="password" id="emp_repeat_password" name="repeat_password"
                       value="<?= Html::encode($repeatPwdVal) ?>"
                       placeholder="Re-enter password"
                       class="form-control form-control-sm"
                       required minlength="6" maxlength="50" autocomplete="new-password">
                <div id="emp_password_err" class="invalid-feedback">Passwords do not match.</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if (!$isUpdate): ?>
<?php $this->registerJs(<<<JS
(function () {
    var form     = document.querySelector('form');
    var pwd      = document.getElementById('emp_password');
    var repeat   = document.getElementById('emp_repeat_password');
    var errDiv   = document.getElementById('emp_password_err');
    if (!form || !pwd || !repeat) return;

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var NAME_RE  = /^[A-Za-z .'\-]{2,255}$/;

    function showError(msg, focusEl) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: msg, confirmButtonText: 'OK' })
                .then(function () { if (focusEl) focusEl.focus(); });
        } else {
            alert(msg);
            if (focusEl) focusEl.focus();
        }
    }

    function val(name) {
        var el = form.querySelector('[name="' + name + '"]');
        return el ? el.value.trim() : '';
    }
    function el(name) { return form.querySelector('[name="' + name + '"]'); }

    function liveRepeat() {
        if (repeat.value && pwd.value !== repeat.value) {
            repeat.classList.add('is-invalid');
            repeat.classList.remove('is-valid');
            errDiv.style.display = 'block';
        } else {
            repeat.classList.remove('is-invalid');
            if (repeat.value) repeat.classList.add('is-valid');
            errDiv.style.display = 'none';
        }
    }
    repeat.addEventListener('input', liveRepeat);
    pwd.addEventListener('input', liveRepeat);

    // Capture-phase: run BEFORE Yii2's ActiveForm submit handler so we can
    // fully cancel the request with preventDefault + stopImmediatePropagation.
    form.addEventListener('submit', function (e) {
        var pwdV    = pwd.value;
        var repV    = repeat.value;
        var nameV   = val('Employee[employee_name]');
        var desigV  = val('Employee[designation]');
        var mobileV = val('Employee[mobile]');
        var emailV  = val('Employee[email_id]');
        var addrV   = val('Employee[address]');

        var fail = null, focusEl = null;

        if (!pwdV)                              { fail = 'Password is required.';                       focusEl = pwd; }
        else if (pwdV.length < 6)               { fail = 'Password must be at least 6 characters.';    focusEl = pwd; }
        else if (!repV)                         { fail = 'Please re-enter the password.';              focusEl = repeat; }
        else if (pwdV !== repV)                 { fail = 'Passwords do not match.';                     focusEl = repeat; }
        else if (!nameV)                        { fail = 'Employee Name is required.';                  focusEl = el('Employee[employee_name]'); }
        else if (!NAME_RE.test(nameV))          { fail = 'Employee Name may contain only letters, spaces, dot, apostrophe and hyphen.'; focusEl = el('Employee[employee_name]'); }
        else if (!desigV)                       { fail = 'Designation is required.';                    focusEl = el('Employee[designation]'); }
        else if (!mobileV)                      { fail = 'Mobile number is required.';                  focusEl = el('Employee[mobile]'); }
        else if (!/^[0-9]{10}$/.test(mobileV))  { fail = 'Mobile must be exactly 10 digits (numbers only).'; focusEl = el('Employee[mobile]'); }
        else if (!emailV)                       { fail = 'Email is required.';                          focusEl = el('Employee[email_id]'); }
        else if (!EMAIL_RE.test(emailV))        { fail = 'Please enter a valid email address.';         focusEl = el('Employee[email_id]'); }
        else if (!addrV)                        { fail = 'Address is required.';                        focusEl = el('Employee[address]'); }

        if (fail) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (focusEl) focusEl.classList.add('is-invalid');
            showError(fail, focusEl);
            return false;
        }
    }, true);
})();
JS); ?>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-light py-2"><strong class="small">Employee Details</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <?= $form->field($employee, 'employee_name')->textInput([
                    'class'       => 'form-control form-control-sm',
                    'required'    => true,
                    'minlength'   => 2,
                    'maxlength'   => 255,
                    'placeholder' => 'Full name',
                    'pattern'     => "[A-Za-z .'-]{2,255}",
                    'title'       => 'Letters, spaces, dot, apostrophe and hyphen only',
                ]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($employee, 'designation')->textInput([
                    'class'       => 'form-control form-control-sm',
                    'required'    => true,
                    'minlength'   => 2,
                    'maxlength'   => 255,
                    'placeholder' => 'e.g. Senior Developer',
                ]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($employee, 'mobile')->input('tel', [
                    'class'       => 'form-control form-control-sm',
                    'required'    => true,
                    'maxlength'   => 10,
                    'minlength'   => 10,
                    'pattern'     => '[0-9]{10}',
                    'inputmode'   => 'numeric',
                    'placeholder' => '10-digit mobile',
                    'title'       => 'Mobile must be exactly 10 digits',
                    'oninput'     => "this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)",
                ]) ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($employee, 'email_id')->label('Email')->input('email', [
                    'class'       => 'form-control form-control-sm',
                    'required'    => true,
                    'maxlength'   => 255,
                    'placeholder' => 'name@example.com',
                ]) ?>
            </div>
            <div class="col-12">
                <?= $form->field($employee, 'address')->textarea([
                    'class'       => 'form-control form-control-sm',
                    'required'    => true,
                    'minlength'   => 5,
                    'maxlength'   => 255,
                    'rows'        => 3,
                    'placeholder' => 'Street, area, city',
                ]) ?>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <?= Html::submitButton($isUpdate ? 'Update Employee' : 'Create Employee', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>

<?php ActiveForm::end(); ?>
