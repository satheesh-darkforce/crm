<?php
/**
 * "Mark returned" and "Cancel DC" modals shared by the DC list and the quotation dashboard's
 * DC list. Opened by [data-dc-return] / [data-dc-cancel] (delivery.js).
 *
 * @var yii\web\View $this
 */

use common\components\UserAccess;
use kartik\date\DatePicker;
use yii\helpers\Url;
?>
<?php if (UserAccess::checkAccess('dc/save-return')): ?>
<div class="modal fade" id="returnDcModal" tabindex="-1" aria-labelledby="returnDcTitle" aria-hidden="true" data-url="<?= Url::to(['/dc/save-return']) ?>">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content js-dc-return-form" novalidate>
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="returnDcTitle">Mark DC <span class="js-dc-no"></span> returned</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body ui-form">
                <input type="hidden" name="id">
                <div class="mb-3 required">
                    <label class="form-label" for="return_dc_date">Returned on</label>
                    <?= DatePicker::widget([
                        'id' => 'return_dc_date', 'name' => 'date', 'value' => date('Y-m-d'), 'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'autocomplete' => 'off', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true, 'endDate' => date('Y-m-d')],
                    ]) ?>
                    <div class="form-text">Between the DC date and today.</div>
                </div>
                <div>
                    <label class="form-label" for="return_dc_desc">Remarks</label>
                    <textarea id="return_dc_desc" name="description" class="form-control form-control-sm" rows="3" maxlength="5000" placeholder="Condition, who returned it…"></textarea>
                </div>
                <div class="invalid-feedback d-block js-dc-error"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Save return</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (UserAccess::checkAccess('dc/cancel')): ?>
<div class="modal fade" id="cancelDcModal" tabindex="-1" aria-labelledby="cancelDcTitle" aria-hidden="true" data-url="<?= Url::to(['/dc/cancel']) ?>">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content js-dc-cancel-form" novalidate>
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="cancelDcTitle">Cancel DC <span class="js-dc-no"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body ui-form">
                <input type="hidden" name="id">
                <div class="required">
                    <label class="form-label" for="cancel_dc_reason">Reason</label>
                    <textarea id="cancel_dc_reason" name="reason" class="form-control form-control-sm" rows="3" maxlength="2000" placeholder="Why is this DC being cancelled?"></textarea>
                    <div class="form-text">A cancelled DC stays in the list for reference and cannot be reactivated.</div>
                </div>
                <div class="invalid-feedback d-block js-dc-error"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Keep DC</button>
                <button type="submit" class="btn btn-danger btn-sm">Cancel DC</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
