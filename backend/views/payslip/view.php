<?php
/**
 * Payslip detail view — mirrors Yii1 view.php style
 *
 * @var \yii\web\View $this
 * @var \backend\models\Payslip $model
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$devName  = $model->developer->developer_name ?? '—';
$monthStr = $model->month_year ? date('F Y', strtotime($model->month_year)) : '—';

$this->title = 'Payslip — ' . $devName . ' — ' . $monthStr;

// Separate earnings/deductions
$earnings   = [];
$deductions = [];
foreach ($model->details as $d) {
    if ($d->type == 1) $earnings[]   = $d;
    else               $deductions[] = $d;
}

$gross       = (float)$model->gross_salary;
$netPay      = (float)$model->actual_salary;
$totalLop    = (float)$model->total_lop;
$workDays    = 30;
$lopAmount   = ($gross > 0 && $totalLop > 0) ? round(($gross / $workDays) * $totalLop) : 0;
$earnedGross = $gross - $lopAmount;
?>
<style>
.detail-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600}
.detail-table td{font-size:.875rem;vertical-align:middle}
.pay-preview{border-collapse:collapse;width:100%}
.pay-preview th,.pay-preview td{border:1px solid #dee2e6;padding:6px 10px;font-size:.85rem}
.pay-preview thead th{background:#f8f9fa;font-weight:600;text-align:center}
.pay-preview .amt{text-align:right}
.pay-preview .subtotal{background:#f0fff0;font-weight:700}
.pay-preview .netpay{background:#e8f4e8;font-weight:700;font-size:.95rem}
.pay-preview .lbl{font-weight:600}
</style>

<div class="payslip-view">

    <!-- Header bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($devName) ?></h5>
            <small class="text-muted"><?= Html::encode($monthStr) ?> &nbsp;|&nbsp; <?= Html::encode($model->designation ?? '') ?> &nbsp;|&nbsp; <?= Html::encode($model->emp_id ?? '') ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-warning btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-comment"></i> Download PDF', ['download-pdf', 'id' => $model->id], [
                'class'  => 'btn btn-success btn-sm',
                'encode' => false,
            ]) ?>
            <?= Html::a('<i class="fas fa-comment"></i> Print', ['print', 'id' => $model->id], [
                'class'  => 'btn btn-outline-success btn-sm',
                'target' => '_blank',
                'encode' => false,
            ]) ?>
            <?= Html::a('<i class="fas fa-times"></i> Delete', ['delete', 'id' => $model->id], [
                'class'        => 'btn btn-danger btn-sm',
                'data-method'  => 'post',
                'data-confirm' => 'Delete this payslip?',
            ]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3">

        <!-- Left: Employee info -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light py-2 fw-semibold small">Employee Details</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 detail-table">
                        <tbody>
                            <tr><th class="w-45">Name</th><td><?= Html::encode($model->name ?? '—') ?></td></tr>
                            <tr><th>Month</th><td><?= Html::encode($monthStr) ?></td></tr>
                            <tr><th>Emp. Code</th><td><?= Html::encode($model->emp_id ?? '—') ?></td></tr>
                            <tr><th>Designation</th><td><?= Html::encode($model->designation ?? '—') ?></td></tr>
                            <tr><th>Working Days</th><td><?= $model->no_of_working_days ?? 30 ?></td></tr>
                            <tr><th>Leave Balance</th><td><?= $model->leave_balance ?></td></tr>
                            <tr><th>Leave Allocated</th><td><?= $model->leave_allocated ?></td></tr>
                            <tr><th>Leave Taken</th><td><?= $model->leave_taken ?></td></tr>
                            <tr><th>Leave LOP</th><td><?= $model->leave_lop ?></td></tr>
                            <tr><th>Late / Permission</th><td><?= $model->late_permission ?></td></tr>
                            <tr><th>Late LOP</th><td><?= $model->late_lop ?></td></tr>
                            <tr><th>Additional Working</th><td><?= $model->additional_working ?></td></tr>
                            <tr><th>Total LOP</th><td><?= $model->total_lop ?></td></tr>
                            <tr><th>Gross Salary</th><td class="fw-semibold">&#8377;<?= number_format($gross, 2) ?></td></tr>
                            <tr><th>Net Salary</th><td class="fw-bold text-success">&#8377;<?= number_format($netPay, 2) ?></td></tr>
                            <?php if ($model->remarks): ?>
                            <tr><th>Remarks</th><td><?= Html::encode($model->remarks) ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Payslip preview -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light py-2 fw-semibold small">Pay Slip Preview</div>
                <div class="card-body">
                    <table class="pay-preview">
                        <thead>
                            <tr>
                                <th style="width:30%">Earnings</th>
                                <th style="width:15%">In Rupees</th>
                                <th style="width:35%">Deductions</th>
                                <th style="width:20%">In Rupees</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- CTC row -->
                            <tr>
                                <td class="lbl">Cost to Company (CTC)</td>
                                <td class="amt">&#8377;<?= number_format($gross) ?></td>
                                <td class="lbl">Loss of Pay <?= $totalLop > 0 ? "({$totalLop} days)" : '' ?></td>
                                <td class="amt">&#8377;<?= number_format($lopAmount) ?></td>
                            </tr>

                            <!-- Earned Gross Pay -->
                            <tr class="subtotal">
                                <td colspan="3" class="text-center">Earned Gross Pay</td>
                                <td class="amt">&#8377;<?= number_format($earnedGross) ?></td>
                            </tr>

                            <!-- Earnings vs Deductions breakdown -->
                            <?php
                            $maxRows = max(count($earnings), count($deductions), 1);
                            for ($i = 0; $i < $maxRows; $i++):
                            ?>
                            <tr>
                                <td><?= isset($earnings[$i]) ? Html::encode($earnings[$i]->title) : '' ?></td>
                                <td class="amt">
                                    <?php if (isset($earnings[$i])): ?>
                                        &#8377;<?= number_format((float)$earnings[$i]->amount) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($deductions[$i])): ?>
                                        <?= Html::encode($deductions[$i]->title) ?>
                                        <?php if ($deductions[$i]->deduction_type == 1 && $deductions[$i]->deduction_percentage > 0): ?>
                                            <span class="text-muted small">(<?= $deductions[$i]->deduction_percentage ?>%)</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="amt">
                                    <?php if (isset($deductions[$i])): ?>
                                        &#8377;<?= number_format((float)$deductions[$i]->amount) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endfor; ?>

                            <!-- Net Pay -->
                            <tr class="netpay">
                                <td colspan="3" class="text-center">Net Pay</td>
                                <td class="amt">&#8377;<?= number_format((int)round($netPay)) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
