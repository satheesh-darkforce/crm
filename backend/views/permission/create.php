<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeaveRequests $model */
/** @var \backend\models\Developer[] $developers */
/** @var bool $isAdmin */
/** @var \backend\models\Developer|null $selfDev */
/** @var int $cap */

use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$this->title = 'Request Permission';
?>
<div class="permission-create">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">Request Permission</h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="alert alert-info py-2">
        <i class="fas fa-circle-info"></i> Each employee may take up to <strong><?= (int)$cap ?> hour(s)</strong> of permission per month
        (e.g. one 2-hour permission or two 1-hour permissions).
        
    </div>

    <div class="card border-0 shadow-sm">
        <form method="post" action="<?= Url::to(['create']) ?>">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
            <div class="card-body">
                <div class="row g-3">
                    <?php if ($isAdmin): ?>
                    <div class="col-md-4">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <?= Select2::widget([
                            'name'          => 'developer_id',
                            'value'         => $model->developer_id,
                            'data'          => ArrayHelper::map($developers, 'id', 'developer_name'),
                            'options'       => ['placeholder' => 'Select employee'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                    </div>
                    <?php else: ?>
                    <div class="col-md-4">
                        <label class="form-label">Employee</label>
                        <input type="text" class="form-control form-control-sm" value="<?= Html::encode($selfDev->developer_name ?? '') ?>" readonly>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-4">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <?= DatePicker::widget([
                            'name'          => 'permission_date',
                            'type'          => DatePicker::TYPE_INPUT,
                            'value'         => $model->from_date ?: date('Y-m-d'),
                            'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                        ]) ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Hours <span class="text-danger">*</span></label>
                        <select name="permission_hours" id="perm-hours" class="form-select form-select-sm">
                            <option value="1" <?= (float)$model->permission_hours === 1.0 ? 'selected' : '' ?>>1 hour</option>
                            <option value="2" <?= (float)$model->permission_hours === 2.0 ? 'selected' : '' ?>>2 hours</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <input type="text" name="reason" class="form-control form-control-sm" maxlength="2000"
                               value="<?= Html::encode($model->reason) ?>" placeholder="Reason for permission">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control form-control-sm" maxlength="5000"
                               value="<?= Html::encode($model->description) ?>" placeholder="Optional details">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent text-end py-3">
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php
// Surface validation errors (notably the monthly permission-hour cap) as a
// blocking SweetAlert popup instead of an inline box.
if ($model->hasErrors()):
    $errHtml = implode('<br>', array_map(static fn($e) => Html::encode($e), $model->getFirstErrors()));
    $this->registerJs(
        'Swal.fire({icon:"error", title:"Cannot submit request", html:' . json_encode($errHtml) . '});',
        \yii\web\View::POS_READY
    );
endif;
?>
