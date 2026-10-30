<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Amc|\backend\models\AmcRenew $model */
/** @var \backend\models\Project $project */
/** @var bool $isRenew */

use kartik\date\DatePicker;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\web\View;

$isRenew = $isRenew ?? false;
$estCost = (float)($project->total_estimation_cost ?? 0);

$ctId    = Html::getInputId($model, 'charge_type');
$pctId   = Html::getInputId($model, 'percentage');
$amtId   = Html::getInputId($model, 'amount');
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">

        <!-- Project / client context -->
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Project</label>
                <div class="fw-semibold"><?= Html::encode($project->title ?? '—') ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Client</label>
                <div class="fw-semibold"><?= Html::encode($project->client->client_name ?? '—') ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Total Estimation Cost</label>
                <div class="fw-semibold text-success" id="amc-est-cost" data-cost="<?= $estCost ?>">
                    <?= $estCost > 0 ? '₹' . number_format($estCost, 2) : '—' ?>
                </div>
            </div>
        </div>

        <hr>

        <?php $form = ActiveForm::begin([
            'id'                     => $isRenew ? 'amc-renew-form' : 'amc-form',
            'enableClientValidation' => true,
            'enableAjaxValidation'   => false,
            'validateOnSubmit'       => true,
        ]); ?>

        <?= $form->errorSummary($model, ['class' => 'alert alert-danger']) ?>

        <div class="row mb-3">
            <div class="col-md-4">
                <?= $form->field($model, 'start_date')->widget(DatePicker::class, [
                    'type'          => DatePicker::TYPE_INPUT,
                    'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Select date'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                ])->label(($isRenew ? 'Renewal Start Date' : 'Start Date') . ' <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>

            <div class="col-md-4">
                <?php if ($isRenew): ?>
                    <?= $form->field($model, 'months')->textInput([
                        'type' => 'number', 'min' => 1, 'max' => 600,
                        'class' => 'form-control form-control-sm', 'placeholder' => 'No. of months',
                    ])->label('No. of Months <span class="text-danger">*</span>', ['encode' => false]) ?>
                <?php else: ?>
                    <?= $form->field($model, 'warranty_period')->textInput([
                        'type' => 'number', 'min' => 0, 'max' => 600,
                        'class' => 'form-control form-control-sm', 'placeholder' => 'Warranty in months',
                    ])->label('Warranty Period (months) <span class="text-danger">*</span>', ['encode' => false]) ?>
                    <small class="text-muted">Enter <strong>0</strong> for no warranty — AMC starts immediately.</small>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <?= $form->field($model, 'charge_type')->dropDownList([
                    1 => 'Percentage', 2 => 'Amount',
                ], ['class' => 'form-select form-select-sm'])
                    ->label('Charge Type <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4 amc-pct-wrap">
                <?= $form->field($model, 'percentage')->textInput([
                    'type' => 'number', 'min' => 0, 'max' => 100, 'step' => '0.01',
                    'class' => 'form-control form-control-sm', 'placeholder' => 'e.g. 15',
                ])->label('Percentage (%) <span class="text-danger">*</span>', ['encode' => false]) ?>
                <small class="text-muted">Computed AMC amount: <span id="amc-pct-amount" class="fw-semibold">—</span></small>
            </div>
            <div class="col-md-4 amc-amt-wrap">
                <?= $form->field($model, 'amount')->textInput([
                    'type' => 'number', 'min' => 0, 'step' => '0.01',
                    'class' => 'form-control form-control-sm', 'placeholder' => 'Fixed amount',
                ])->label('Amount <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-8">
                <?= $form->field($model, 'description')->textarea([
                    'rows' => 2, 'class' => 'form-control form-control-sm', 'placeholder' => 'Description',
                ]) ?>
            </div>
        </div>

        <div class="mt-2">
            <?php
            $btnLabel = $isRenew
                ? ($model->isNewRecord ? 'Renew AMC' : 'Update Renewal')
                : ($model->isNewRecord ? 'Start AMC' : 'Update AMC');
            ?>
            <?= Html::submitButton($btnLabel, ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('Cancel', ['active'], ['class' => 'btn btn-light btn-sm']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<?php
$this->registerJs(<<<JS
(function(){
    var ct  = jQuery('#$ctId');
    var pct = jQuery('#$pctId');
    var cost = parseFloat(jQuery('#amc-est-cost').data('cost')) || 0;

    function toggle(){
        if (ct.val() === '2') {            // Amount
            jQuery('.amc-pct-wrap').hide();
            jQuery('.amc-amt-wrap').show();
        } else {                            // Percentage
            jQuery('.amc-amt-wrap').hide();
            jQuery('.amc-pct-wrap').show();
        }
    }
    function calc(){
        var p = parseFloat(pct.val());
        if (!isNaN(p) && cost > 0) {
            jQuery('#amc-pct-amount').text('₹' + (cost * p / 100).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        } else {
            jQuery('#amc-pct-amount').text('—');
        }
    }
    ct.on('change', toggle);
    pct.on('input', calc);
    toggle();
    calc();
})();
JS, View::POS_READY);
?>
