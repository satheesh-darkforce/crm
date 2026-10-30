<?php
/**
 * Edit payslip allowances / deductions — mirrors Yii1 update.php
 *
 * @var \yii\web\View              $this
 * @var \backend\models\Payslip     $model
 * @var \backend\models\Developer   $developer
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$devName  = $model->name ?? ($developer->developer_name ?? '—');
$monthStr = $model->month_year ? date('F Y', strtotime($model->month_year)) : '—';

$this->title = 'Update Payslip';

// Separate details
$allowances = [];
$deductions = [];
foreach ($model->details as $d) {
    if ($d->type == 1) { $allowances[] = $d; }
    else               { $deductions[] = $d; }
}

$gross    = (float)$model->gross_salary;
$totalLop = (float)$model->total_lop;
$daysInMonth = $model->month_year ? ((int)date('t', strtotime($model->month_year)) ?: 30) : 30; // real calendar days
$lopAmt   = ($gross > 0 && $totalLop > 0) ? round(($gross / $daysInMonth) * $totalLop, 2) : 0;
?>
<style>
.upd-table th { font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:#6c757d; font-weight:600; }
.upd-table td { vertical-align:middle; font-size:.875rem; }
.upd-table input { width:110px; }
.salary-cell { font-weight:700; color:#198754; }
</style>

<div class="payslip-update">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Update Payslip</h5>
            <small class="text-muted"><?= Html::encode($devName) ?> &nbsp;|&nbsp; <?= Html::encode($monthStr) ?></small>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['view', 'id' => $model->id], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <form method="post" action="<?= Url::to(['payslip/update', 'id' => $model->id]) ?>">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>

        <div class="row g-3">

            <!-- Employee Info (read-only) -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2 fw-semibold small">Employee Details</div>
                    <div class="card-body">
                        <div class="row g-2 small">
                            <div class="col-6"><strong>Name:</strong> <?= Html::encode($model->name ?? '—') ?></div>
                            <div class="col-6"><strong>Emp Code:</strong> <?= Html::encode($model->emp_id ?? '—') ?></div>
                            <div class="col-6"><strong>Designation:</strong> <?= Html::encode($model->designation ?? '—') ?></div>
                            <div class="col-6"><strong>Month:</strong> <?= Html::encode($monthStr) ?></div>
                            <div class="col-6"><strong>Working Days:</strong> <?= $model->no_of_working_days ?? 30 ?></div>
                            <div class="col-6"><strong>Gross Salary:</strong> ₹<?= number_format($gross, 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Details (read-only) -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2 fw-semibold small">Bank Details</div>
                    <div class="card-body">
                        <div class="row g-2 small">
                            <div class="col-6"><strong>Bank:</strong> <?= Html::encode($developer->bank ?? '—') ?></div>
                            <div class="col-6"><strong>Branch:</strong> <?= Html::encode($developer->bank_branch ?? '—') ?></div>
                            <div class="col-12"><strong>Account No:</strong> <?= Html::encode($developer->account_number ?? '—') ?></div>
                            <div class="col-12"><strong>IFSC:</strong> <?= Html::encode($developer->ifsc_code ?? '—') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOP / Extra Working -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2 fw-semibold small">Leave / LOP</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Leave Taken</label>
                                <input type="text" class="form-control form-control-sm" value="<?= $model->leave_taken ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Leave LOP</label>
                                <input type="text" class="form-control form-control-sm" value="<?= $model->leave_lop ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Late LOP</label>
                                <input type="text" class="form-control form-control-sm" value="<?= $model->late_lop ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Total LOP</label>
                                <input type="number" step="0.5" name="no_of_lop" id="no_of_lop"
                                       class="form-control form-control-sm"
                                       value="<?= $model->total_lop ?>"
                                       onchange="recalcSalary()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Extra Working</label>
                                <input type="number" step="0.5" name="no_of_extra" id="no_of_extra"
                                       class="form-control form-control-sm"
                                       value="<?= $model->additional_working ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Allowances -->
            <?php if (!empty($allowances)): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2 fw-semibold small">Allowances (Earnings)</div>
                    <div class="card-body p-0">
                        <table class="table table-sm upd-table mb-0">
                            <thead class="table-light">
                                <tr><th>Title</th><th>Amount (₹)</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($allowances as $al): ?>
                                <tr>
                                    <td><?= Html::encode($al->title) ?></td>
                                    <td>
                                        <input type="number" step="0.01"
                                               name="allowances[<?= $al->id ?>]"
                                               class="form-control form-control-sm allowance-input"
                                               value="<?= $al->amount ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Deductions -->
            <?php if (!empty($deductions)): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2 fw-semibold small">Deductions</div>
                    <div class="card-body p-0">
                        <table class="table table-sm upd-table mb-0">
                            <thead class="table-light">
                                <tr><th>Title</th><th>Type</th><th>Value</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($deductions as $ded): ?>
                                <tr>
                                    <td><?= Html::encode($ded->title) ?></td>
                                    <td class="text-muted small"><?= $ded->deduction_type == 1 ? 'Percentage' : 'Fixed' ?></td>
                                    <td>
                                        <input type="number" step="0.01"
                                               name="deductions[<?= $ded->id ?>]"
                                               class="form-control form-control-sm deduction-input"
                                               value="<?= $ded->deduction_type == 1 ? $ded->deduction_percentage : $ded->amount ?>"
                                               onchange="recalcSalary()">
                                        <?php if ($ded->deduction_type == 1): ?>
                                            <small class="text-muted">%</small>
                                        <?php else: ?>
                                            <small class="text-muted">₹</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Net Salary -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2 fw-semibold small">Net Pay</div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">LOP Amount (calculated)</label>
                                <div class="form-control form-control-sm bg-light" id="lop_amount_display">
                                    ₹<?= number_format($lopAmt, 2) ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Net Salary</label>
                                <input type="number" step="0.01" name="actual_salary" id="actual_salary"
                                       class="form-control form-control-sm salary-cell"
                                       value="<?= $model->actual_salary ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /row -->

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <?= Html::a('Cancel', ['view', 'id' => $model->id], ['class' => 'btn btn-secondary']) ?>
        </div>
    </form>
</div>

<?php
$gross      = (float)$model->gross_salary;
$dedTypes   = [];
foreach ($deductions as $ded) {
    $dedTypes[$ded->id] = (int)$ded->deduction_type; // 1=%, 2=fixed
}
$dedTypesJson = json_encode($dedTypes);
$this->registerJs(<<<JS
var gross = {$gross};
var daysInMonth = {$daysInMonth};
var dedTypes = {$dedTypesJson};

function recalcSalary() {
    var lop = parseFloat(document.getElementById('no_of_lop').value) || 0;
    var perDay = gross / daysInMonth;
    var lopAmt = lop * perDay;
    document.getElementById('lop_amount_display').textContent = '₹' + lopAmt.toFixed(2);

    var totalPct = 0, totalFixed = 0;
    document.querySelectorAll('.deduction-input').forEach(function(inp) {
        var name = inp.name; // deductions[ID]
        var match = name.match(/deductions\[(\d+)\]/);
        if (!match) return;
        var id = parseInt(match[1]);
        var val = parseFloat(inp.value) || 0;
        if (dedTypes[id] == 1) { totalPct   += val; }
        else                   { totalFixed += val; }
    });

    var salary = gross - lopAmt - totalFixed;
    if (totalPct > 0) { salary -= (salary / 100) * totalPct; }
    salary = Math.max(0, Math.round(salary));
    document.getElementById('actual_salary').value = salary;
}
JS);
?>
