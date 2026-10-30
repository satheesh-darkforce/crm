<?php
/** @var \yii\web\View $this */
/** @var \backend\models\AdditionalWorks $model */
/** @var array $developers */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Additional Work' : 'Edit Additional Work';
?>
<div class="additional-works-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
    </div>

    <div class="card border-0 shadow-sm" style="max-width:560px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(); ?>

            <?= $form->field($model, 'developer_id')->widget(Select2::class, [
                'data'          => $developers,
                'options'       => ['placeholder' => 'Select Employee', 'class' => 'form-select form-select-sm'],
                'pluginOptions' => ['allowClear' => true],
            ]) ?>

            <?= $form->field($model, 'date')->widget(DatePicker::class, [
                'type'          => DatePicker::TYPE_INPUT,
                'options'       => ['placeholder' => 'YYYY-MM-DD', 'class' => 'form-control form-control-sm'],
                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
            ]) ?>

            <?= $form->field($model, 'hours')->textInput(['type' => 'number', 'min' => 0, 'max' => 24, 'class' => 'form-control form-control-sm', 'placeholder' => 'Hours worked (0–24)']) ?>

            <?= $form->field($model, 'description')->textarea(['rows' => 4, 'class' => 'form-control form-control-sm', 'placeholder' => 'Describe the additional work performed']) ?>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? 'Save' : 'Update', ['class' => 'btn btn-primary btn-sm']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
