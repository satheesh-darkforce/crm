<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadFollowers $model */
/** @var \backend\models\Lead $lead */
/** @var array $users  user_id => username */

use backend\components\LeadFiles;
use backend\models\Lead;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$in = fn(string $placeholder, array $extra = []) => ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder] + $extra;
$s2 = fn(string $placeholder) => [
    'options' => ['placeholder' => $placeholder],
    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
    'size' => Select2::SMALL,
];
?>
<?= $this->render('@backend/views/lead/_form-css') ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php $form = ActiveForm::begin(['id' => 'lead-followers-form', 'options' => ['enctype' => 'multipart/form-data', 'class' => 'lead-form-scope']]); ?>
        <?= $form->errorSummary($model, ['class' => 'alert alert-danger py-2 small']) ?>
        <p class="text-muted small mb-3">Fields marked <span class="text-danger fw-bold">*</span> are required.</p>
        <div class="row g-3">
            <div class="col-lg-6"><?= $form->field($model, 'subject')->textInput($in('e.g. Proposal discussion', ['maxlength' => 255])) ?></div>
            <div class="col-lg-3 col-md-6">
                <?= $form->field($model, 'follow_up_date')->widget(DatePicker::class, [
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true]
                        + ($model->isNewRecord ? ['startDate' => date('Y-m-d')] : []),
                ]) ?>
            </div>
            <div class="col-lg-3 col-md-6"><?= $form->field($model, 'follow_up_time')->input('time', $in('HH:MM')) ?></div>
            <div class="col-lg-4 col-md-6"><?= $form->field($model, 'assigned_to')->widget(Select2::class, ['data' => $users] + $s2('Select employee')) ?></div>
            <div class="col-lg-4 col-md-6"><?= $form->field($model, 'mode')->widget(Select2::class, ['data' => Lead::modeOptions()] + $s2('Select the mode')) ?></div>
            <div class="col-lg-4 col-md-6" id="fu-other-mode-wrap" style="<?= (int)$model->mode === Lead::MODE_OTHER ? '' : 'display:none' ?>">
                <?= $form->field($model, 'other_mode', ['options' => ['class' => 'mb-3 required']])->textInput($in('e.g. Google Meet', ['maxlength' => 255])) ?>
            </div>
            <div class="col-12"><?= $form->field($model, 'description')->textarea($in('What was discussed / what needs to happen next…', ['rows' => 4])) ?></div>
            <?php if ($model->isNewRecord): ?>
            <div class="col-12">
                <label class="form-label">Attachments</label>
                <input type="file" name="attachments[]" class="form-control form-control-sm" multiple accept="<?= LeadFiles::acceptAttribute() ?>">
                <div class="form-text">PDF, Office documents, images, CSV, TXT or ZIP — up to 20 MB each.</div>
            </div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 mt-3">
            <?= Html::submitButton($model->isNewRecord ? 'Submit' : 'Update', ['class' => 'btn btn-primary btn-sm px-4']) ?>
            <?= Html::a('Cancel', ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
<?php
$this->registerJs(<<<JS
jQuery('#leadfollowers-mode').on('change', function () {
    var other = jQuery(this).val() == '5';
    jQuery('#fu-other-mode-wrap').toggle(other);
    if (!other) { jQuery('#leadfollowers-other_mode').val(''); jQuery('#lead-followers-form').yiiActiveForm('validateAttribute', 'leadfollowers-other_mode'); }
});
JS);
