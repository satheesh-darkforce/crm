<?php
/** @var \yii\web\View $this */
/** @var \backend\models\DailyAttendance $model */

use yii\bootstrap5\Html;
use yii\helpers\Url;

// Match Yii1 condition: form is shown ONLY for developers (usertype=2) AND only
// when no remarks exist yet. Anyone else — or anyone re-opening an already-filled
// remark — sees a read-only view.
/** @var \backend\models\User $user */
$user       = Yii::$app->user->identity;
$canEdit    = $user && $user->isDeveloper() && empty($model->remarks);
$hasRemarks = $model->remarks !== null && $model->remarks !== '';
?>
<div class="update-remarks p-3">
    <p class="text-muted small mb-3">
        <strong>Date:</strong> <?= Html::encode(date('d M Y', strtotime($model->attendance_date))) ?>
    </p>

    <?php if ($canEdit): ?>
        <form method="post" action="<?= Url::to(['update-remarks', 'id' => $model->id]) ?>" enctype="multipart/form-data" id="remarks-form">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>

            <div class="mb-3">
                <label for="remarks" class="form-label fw-semibold">Remarks <span class="text-danger">*</span></label>
                <textarea id="remarks" name="remarks" rows="4"
                          class="form-control form-control-sm" required
                          placeholder="Describe the reason for this attendance status…"></textarea>
            </div>

            <div class="mb-3">
                <label for="remarks_attachment" class="form-label fw-semibold">
                    Attachment <span class="text-muted">(optional, image only, max 5 MB)</span>
                </label>
                <input type="file" id="remarks_attachment" name="remarks_attachment"
                       class="form-control form-control-sm"
                       accept="image/jpeg,image/png,image/gif">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>

        <?php
        // Client-side 5MB file-size guard, mirrors Yii1 CMultiFileUpload afterFileSelect callback.
        $this->registerJs(<<<JS
jQuery('#remarks-form input[name="remarks_attachment"]').on('change', function () {
    var f = this.files && this.files[0];
    if (f && f.size > 5 * 1024 * 1024) {
        Swal.fire({icon:'warning', title:'File too large', text:'File size must be smaller than 5 MB'});
        jQuery(this).val('');
    }
});

// Submit the remark via AJAX so the Monthly Attendance page (and its active
// filter) is NOT reloaded. On success the modal closes and the clicked cell is
// flagged as having a remark, without a full page refresh.
jQuery('#remarks-form').on('submit', function (e) {
    e.preventDefault();
    var form = this;
    var submitBtn = jQuery(form).find('button[type="submit"]').prop('disabled', true);
    jQuery.ajax({
        url: form.action,
        type: 'POST',
        data: new FormData(form),
        processData: false,
        contentType: false,
        dataType: 'json',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function (res) {
            var modalEl = document.getElementById('remarksModal');
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (res && res.success) {
                if (inst) { inst.hide(); }
                if (window.__remarksCellId) {
                    jQuery('.remarks-btn[data-id="' + window.__remarksCellId + '"]').attr('data-has-remarks', '1');
                }
                Swal.fire({ icon:'success', title:'Saved', text: res.msg || 'Remarks updated', timer:1500, showConfirmButton:false });
            } else {
                submitBtn.prop('disabled', false);
                Swal.fire({ icon:'error', title:'Error', text: (res && res.msg) || 'Failed to save remarks.' });
            }
        },
        error: function () {
            submitBtn.prop('disabled', false);
            Swal.fire({ icon:'error', title:'Error', text:'Something went wrong. Please try again.' });
        }
    });
});
JS, \yii\web\View::POS_READY);
        ?>
    <?php elseif ($hasRemarks): ?>
        <div class="mb-3">
            <label class="form-label fw-semibold">Remarks</label>
            <div class="p-2 bg-light rounded small"><?= nl2br(Html::encode($model->remarks)) ?></div>
        </div>
        <?php if ($model->remarks_attachment): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold d-block">Attachment</label>
                <a href="<?= Yii::$app->request->baseUrl . Html::encode($model->remarks_attachment) ?>" target="_blank" rel="noreferrer noopener" class="d-inline-block" style="max-width:100%">
                    <img src="<?= Yii::$app->request->baseUrl . Html::encode($model->remarks_attachment) ?>"
                         alt="attachment"
                         style="max-width:100%;max-height:200px;height:auto;width:auto;display:block;border-radius:4px;border:1px solid #dee2e6;object-fit:contain">
                </a>
                <div class="mt-1"><small class="text-muted">Click to open full size in a new tab.</small></div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted mb-0">No remarks have been added yet.</p>
    <?php endif; ?>
</div>
