<?php
/** @var \yii\web\View $this */
/** @var string|null $error */

use yii\bootstrap5\Html;

$this->title = 'Change Password';
?>
<div class="row justify-content-center mt-4">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold">Change Password</h5>
            </div>
            <div class="card-body">                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= Html::encode($error) ?></div>
                <?php endif; ?>

                <form method="post" action="" id="change-password-form" novalidate>
                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" id="current_password" class="form-control form-control-sm" autofocus>
                        <div class="invalid-feedback" id="err_current">Current password is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" id="new_password" class="form-control form-control-sm" minlength="6">
                        <div class="invalid-feedback" id="err_new">New password must be at least 6 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-sm">
                        <div class="invalid-feedback" id="err_confirm">Passwords do not match.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-4">Update Password</button>
                        <?= Html::a('Cancel', ['/site/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
document.getElementById('change-password-form').addEventListener('submit', function(e) {
    var cur = document.getElementById('current_password');
    var np  = document.getElementById('new_password');
    var cp  = document.getElementById('confirm_password');
    var valid = true;

    // Reset
    [cur, np, cp].forEach(function(el) { el.classList.remove('is-invalid'); });

    if (!cur.value.trim()) {
        cur.classList.add('is-invalid');
        document.getElementById('err_current').textContent = 'Current password is required.';
        valid = false;
    }
    if (!np.value || np.value.length < 6) {
        np.classList.add('is-invalid');
        document.getElementById('err_new').textContent = 'New password must be at least 6 characters.';
        valid = false;
    }
    if (!cp.value || np.value !== cp.value) {
        cp.classList.add('is-invalid');
        document.getElementById('err_confirm').textContent = cp.value ? 'Passwords do not match.' : 'Please confirm your new password.';
        valid = false;
    }

    if (!valid) {
        e.preventDefault();
    }
});

// Clear errors on input
['current_password', 'new_password', 'confirm_password'].forEach(function(id) {
    document.getElementById(id).addEventListener('input', function() {
        this.classList.remove('is-invalid');
    });
});
JS);
?>
