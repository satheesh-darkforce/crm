<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Amc|\backend\models\AmcRenew $model */
/** @var \backend\models\Project|null $project */
/** @var bool $isRenew */
/** @var array $cancelUrl */

use backend\models\Amc;
use kartik\date\DatePicker;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$isRenew = $isRenew ?? false;
$estCost = (float)($project->total_estimation_cost ?? 0);
$in = fn(string $placeholder, array $extra = []) => $extra + ['class' => 'form-control form-control-sm', 'placeholder' => $placeholder];

$btnLabel = $isRenew
    ? ($model->isNewRecord ? 'Renew AMC' : 'Update Renewal')
    : ($model->isNewRecord ? 'Start AMC' : 'Update AMC');
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header ui-section">Project</div>
    <table class="table ui-detail">
        <tr><th scope="row">Project</th><td><?= Html::encode($project->title ?? '—') ?></td></tr>
        <tr><th scope="row">Client</th><td><?= Html::encode($project->client->client_name ?? '—') ?></td></tr>
        <tr><th scope="row">Estimation cost</th><td id="amc-est-cost" data-cost="<?= $estCost ?>"><?= $estCost > 0 ? '₹' . number_format($estCost, 2) : '<span class="text-muted">Not set — percentage charges cannot be calculated</span>' ?></td></tr>
    </table>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header ui-section"><?= $isRenew ? 'Renewal terms' : 'Contract terms' ?></div>
    <div class="card-body">
        <?php $form = ActiveForm::begin(['id' => $isRenew ? 'amc-renew-form' : 'amc-form', 'options' => ['class' => 'ui-form']]); ?>
        <?= $form->errorSummary($model, ['class' => 'alert alert-danger', 'header' => '<p>Please fix the following:</p>']) ?>
        <p class="ui-req-note">Fields marked <b>*</b> are required.</p>

        <div class="row g-3">
            <div class="col-md-4">
                <?= $form->field($model, 'start_date')->widget(DatePicker::class, [
                    'type' => DatePicker::TYPE_INPUT,
                    'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                ]) ?>
            </div>
            <div class="col-md-4">
                <?php if ($isRenew): ?>
                    <?= $form->field($model, 'months')->input('number', $in('e.g. 12', ['min' => 1, 'max' => 600, 'step' => 1])) ?>
                <?php else: ?>
                    <?= $form->field($model, 'warranty_period')->input('number', $in('e.g. 12', ['min' => 0, 'max' => 600, 'step' => 1]))
                        ->hint('Enter 0 for no warranty — the AMC starts immediately.') ?>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <?= $form->field($model, 'charge_type')->dropDownList([Amc::CHARGE_PERCENTAGE => 'Percentage of cost', Amc::CHARGE_AMOUNT => 'Fixed amount'], ['class' => 'form-select form-select-sm']) ?>
            </div>
            <div class="col-md-4 amc-pct-wrap">
                <?= $form->field($model, 'percentage', ['options' => ['class' => 'mb-3 required']])->input('number', $in('e.g. 15', ['min' => 0, 'max' => 100, 'step' => '0.01']))
                    ->hint('Works out to <span id="amc-pct-amount" class="fw-semibold">—</span>') ?>
            </div>
            <div class="col-md-4 amc-amt-wrap">
                <?= $form->field($model, 'amount', ['options' => ['class' => 'mb-3 required']])->input('number', $in('e.g. 25000', ['min' => 0, 'step' => '0.01']))->label('Amount (₹)') ?>
            </div>
            <div class="col-12">
                <?= $form->field($model, 'description')->textarea($in('Scope, inclusions or notes', ['rows' => 2, 'maxlength' => 2000])) ?>
            </div>
        </div>

        <div class="ui-form-actions mb-0">
            <?= Html::submitButton('<i class="fas fa-check me-1"></i>' . $btnLabel, ['class' => 'btn btn-primary btn-sm px-4']) ?>
            <?= Html::a('Cancel', $cancelUrl, ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
