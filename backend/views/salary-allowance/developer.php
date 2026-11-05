<?php
/**
 * Per-developer allowance & deduction overrides — mirrors Yii1 allowance_deduction.php
 *
 * @var \yii\web\View $this
 * @var \backend\models\Developer $developer
 * @var \backend\models\SalaryAllowance[] $allComponents
 * @var \backend\models\DeveloperAllowanceDeduction[] $overrides  indexed by allowance_deduction_id
 * @var \yii\data\ActiveDataProvider $historyProvider
 */

use yii\bootstrap5\Html;
use yii\grid\GridView;
use yii\helpers\Url;

$this->title = 'Allowances & Deductions — ' . $developer->developer_name;

$allowances = array_values(array_filter($allComponents, fn($c) => $c->type == 1));
$deductions  = array_values(array_filter($allComponents, fn($c) => $c->type == 2));

// Pre-calculate current gross from active overrides
$currentGross = 0;
foreach ($allowances as $comp) {
    if (isset($overrides[$comp->id])) {
        $currentGross += (float)$overrides[$comp->id]->value;
    }
}
?>
<style>
.sal-card .card-header{font-size:.82rem;font-weight:600;background:#f8f9fa;padding:8px 14px}
.sal-card .form-label{font-size:.82rem;margin-bottom:2px;color:#495057}
.sal-card input[type=number]{font-size:.85rem;padding:4px 8px}
.hist-table th{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600}
.hist-table td{font-size:.82rem;vertical-align:middle}
</style>

<div class="ui-page salary-developer">

    <!-- Header -->
    <div class="ui-head">
        <div>
            <h1>Allowances &amp; Deductions</h1>
            <div class="ui-sub"><?= Html::encode($developer->developer_name) ?>
                &nbsp;|&nbsp; <?= Html::encode($developer->designation ?? '') ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['/developer/index'], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <!-- Flash -->
    <form method="post" action="<?= Url::to(['/salary-allowance/developer', 'id' => $developer->id]) ?>">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>

        <div class="row g-3">

            <!-- Allowances -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm sal-card h-100">
                    <div class="card-header">Allowances</div>
                    <div class="card-body">
                        <?php if (empty($allowances)): ?>
                            <p class="text-muted small">No allowances defined. <a href="<?= Url::to(['/salary-allowance/create-allowance']) ?>">Add one</a>.</p>
                        <?php else: ?>
                            <?php foreach ($allowances as $comp): ?>
                            <div class="mb-2">
                                <label class="form-label"><?= Html::encode($comp->title) ?></label>
                                <input type="number" step="any" min="0"
                                       name="components[<?= $comp->id ?>]"
                                       class="form-control form-control-sm allow-input"
                                       value="<?= isset($overrides[$comp->id]) ? (float)$overrides[$comp->id]->value : 0 ?>"
                                       onkeyup="calcSalary()" onblur="calcSalary()">
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Deductions -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm sal-card h-100">
                    <div class="card-header">Deductions</div>
                    <div class="card-body">
                        <?php if (empty($deductions)): ?>
                            <p class="text-muted small">No deductions defined. <a href="<?= Url::to(['/salary-allowance/create-deduction']) ?>">Add one</a>.</p>
                        <?php else: ?>
                            <?php foreach ($deductions as $comp): ?>
                            <div class="mb-2">
                                <label class="form-label">
                                    <?= Html::encode($comp->title) ?>
                                    <span class="text-muted">(<?= $comp->type2 == 1 ? 'in %' : 'in &#8377;' ?>)</span>
                                </label>
                                <input type="number" step="any" min="0"
                                       name="components[<?= $comp->id ?>]"
                                       class="form-control form-control-sm ded-input"
                                       value="<?= isset($overrides[$comp->id]) ? (float)$overrides[$comp->id]->value : (float)($comp->default_value ?? 0) ?>"
                                       onkeyup="calcSalary()" onblur="calcSalary()">
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Gross Salary -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm sal-card">
                    <div class="card-header">Gross Salary (CTC)</div>
                    <div class="card-body">
                        <label class="form-label">Total Allowances</label>
                        <div class="input-group input-group-sm mb-3">
                            <span class="input-group-text">&#8377;</span>
                            <input type="text" id="grossDisplay" class="form-control fw-bold text-success"
                                   readonly value="<?= number_format($currentGross, 2) ?>">
                        </div>
                        <div class="alert alert-info py-2 small mb-3">
                            Gross salary is auto-calculated as the <strong>sum of all allowances</strong>.
                            It will be saved to the employee record on submit.
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-check"></i> Save Changes
                        </button>
                        <?= Html::a('Cancel', ['/developer/index'], ['class' => 'btn btn-outline-secondary w-100 mt-2']) ?>
                    </div>
                </div>
            </div>

        </div>
    </form>

    <!-- History Grid -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-light py-2 fw-semibold small">Change History</div>
        <div class="table-responsive">
            <?= GridView::widget([
                'dataProvider' => $historyProvider,
                'layout' => "{items}\n{pager}",
                'tableOptions' => ['class' => 'table table-sm mb-0 hist-table'],
                'pager' => [
                    'class' => \yii\widgets\LinkPager::class,
                    'options' => ['class' => 'pagination pagination-sm justify-content-center mt-3'],
                    'linkOptions' => ['class' => 'page-link'],
                ],
                'emptyText' => '<div class="text-muted small p-3">No change history available.</div>',
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],
                    [
                        'label' => 'Component',
                        'value' => function ($model) {
                            return $model->allowanceDeduction->title ?? '—';
                        },
                    ],
                    [
                        'label' => 'Type',
                        'format' => 'raw',
                        'value' => function ($model) {
                            if (!$model->allowanceDeduction) return '';
                            $color = $model->allowanceDeduction->type == 1 ? 'info' : 'warning';
                            $label = $model->allowanceDeduction->type == 1 ? 'Allowance' : 'Deduction';
                            return '<span class="badge bg-' . $color . ' text-white" style="font-size:.72rem">' . $label . '</span>';
                        },
                    ],
                    [
                        'label' => 'Value',
                        'contentOptions' => ['class' => 'text-end'],
                        'headerOptions' => ['class' => 'text-end'],
                        'value' => function ($model) {
                            return number_format((float)$model->value, 2);
                        },
                    ],
                ],
            ]) ?>
        </div>
    </div>

</div>

<script>
function calcSalary() {
    var total = 0;
    document.querySelectorAll('.allow-input').forEach(function(el) {
        var v = parseFloat(el.value);
        if (!isNaN(v) && v > 0) total += v;
    });
    document.getElementById('grossDisplay').value = total.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
calcSalary();
</script>
