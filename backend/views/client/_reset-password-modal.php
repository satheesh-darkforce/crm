<?php
/**
 * Reset-password modal (client list and client view). Opened by a trigger with
 * data-bs-target="#resetModal" data-url data-name data-return; see clients.js.
 *
 * @var \yii\web\View $this
 */

use backend\models\ClientModel;
use yii\bootstrap5\Html;
?>
<div class="modal fade" id="resetModal" tabindex="-1" aria-labelledby="resetModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="resetForm" action="" novalidate>
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <?= Html::hiddenInput('return', 'index') ?>
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold" id="resetModalTitle">Reset portal password</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Client: <strong id="resetClientName"></strong></p>
                    <label class="form-label" for="reset-new-password">New password</label>
                    <input type="password" id="reset-new-password" name="new_password" class="form-control form-control-sm"
                           minlength="<?= ClientModel::PASSWORD_MIN ?>" maxlength="<?= ClientModel::PASSWORD_MAX ?>"
                           autocomplete="new-password" placeholder="At least <?= ClientModel::PASSWORD_MIN ?> characters" required>
                    <div class="invalid-feedback">Password must be <?= ClientModel::PASSWORD_MIN ?>–<?= ClientModel::PASSWORD_MAX ?> characters.</div>
                    <div class="form-text">The client will be asked to change it at their next login.</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
