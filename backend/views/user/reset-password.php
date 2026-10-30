<?php
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
$this->title = 'Reset Password — ' . $model->username;
?>
<div class="user-reset-password" style="max-width:400px;">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?php $form = ActiveForm::begin(['action' => ['reset-password', 'id' => $model->user_id]]); ?>
    <div class="mb-3">
        <label class="form-label">New Password <span class="text-danger">*</span></label>
        <input type="password" name="new_password" class="form-control form-control-sm" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
        <input type="password" name="confirm_password" class="form-control form-control-sm" required>
    </div>
    <div class="d-flex gap-2">
        <?= Html::submitButton('Reset Password', ['class' => 'btn btn-warning']) ?>
        <?= Html::a('Cancel', ['view', 'id' => $model->user_id], ['class' => 'btn btn-secondary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
