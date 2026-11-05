<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Holiday $model */
/** @var \backend\models\Developer[] $developers */

use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Edit Holiday';

$req = '<span class="text-danger">*</span>';

// Decode stored employee IDs for pre-selection
$selectedEmployees = [];
if (!empty($model->employee_id)) {
    $decoded = json_decode($model->employee_id, true);
    if (is_array($decoded)) {
        $selectedEmployees = $decoded;
    }
}
?>
<div class="ui-page holiday-update">
    <div class="ui-head">
        <div>
            <h1><?= $this->title ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:600px">
        <div class="card-body">
            <?php $form = ActiveForm::begin(); ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Date <?= $req ?></label>
                    <?= DatePicker::widget([
                        'model'         => $model,
                        'attribute'     => 'date',
                        'type'          => DatePicker::TYPE_INPUT,
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true],
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select date'],
                    ]) ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Reason <?= $req ?></label>
                    <?= Html::activeTextInput($model, 'reason', ['class' => 'form-control form-control-sm', 'maxlength' => 255]) ?>
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label small fw-semibold mb-0">Employee</label>
                        <a href="javascript:void(0)" id="holiday-emp-clear" class="small text-decoration-none">Clear all</a>
                    </div>
                    <?= Select2::widget([
                        'model'         => $model,
                        'attribute'     => 'employee_id',
                        'data'          => ArrayHelper::map($developers, 'user_id', 'developer_name'),
                        'value'         => $selectedEmployees,
                        'options'       => ['id' => 'holiday-employee-id', 'placeholder' => 'Select Employee', 'multiple' => true],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
            </div>

            <?php if ($model->hasErrors()): ?>
            <div class="alert alert-danger py-2 mt-2 mb-0">
                <?php foreach ($model->getFirstErrors() as $err): ?>
                    <div class="small"><?= Html::encode($err) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton('Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
// Registered via registerJs so it runs at POS_READY — after jQuery & Select2 are loaded.
// Select2 is jQuery-driven: clearing requires .val(null).trigger('change').
$this->registerJs("
    $('#holiday-emp-clear').on('click', function () {
        $('#holiday-employee-id').val(null).trigger('change');
    });
");
?>
