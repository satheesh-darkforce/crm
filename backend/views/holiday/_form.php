<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Holiday $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Holiday' : 'Edit Holiday';
?>
<div class="holiday-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= $this->title ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:480px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(); ?>
            <div class="row g-3">
                <div class="col-12">
                    <?= $form->field($model, 'date')->input('date', ['class' => 'form-control form-control-sm']) ?>
                </div>
                <div class="col-12">
                    <?= $form->field($model, 'reason')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'e.g. Republic Day']) ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton($isNew ? 'Add Holiday' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
