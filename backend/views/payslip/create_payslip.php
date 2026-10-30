<?php
/**
 * Bulk payslip generation — mirrors Yii1 create_payslip.php
 *
 * @var \yii\web\View $this
 * @var string        $month        YYYY-MM-01
 * @var \backend\models\Developer[]          $developers
 * @var \backend\models\SalaryAllowance[]    $allowances
 * @var \backend\models\SalaryAllowance[]    $deductions
 * @var array         $devAllowances  [devId][sadId] => value
 * @var array         $devDeductions  [devId][sadId] => value
 * @var array         $devData        [devId] => attendance + salary computed values
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Generate Payslip';

$grossSalaryTotal  = 0;
$actualSalaryTotal = 0;
?>
<style>
/* Hide number input spinners */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; }

.gen-table th { font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:#495057; font-weight:600; white-space:nowrap; }
.gen-table td { vertical-align:middle; font-size:.82rem; white-space:nowrap; }
.gen-table input[type=number], .gen-table input[type=text] {
    width:70px; padding:2px 5px; font-size:.82rem; border:1px solid #ced4da; border-radius:3px;
}
.salary-cell { font-weight:600; color:#198754; }
.fixedtable  { overflow-x:auto; }
</style>

<div class="payslip-create-bulk">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Generate Payslip</h5>
            <small class="text-muted">For Month : <strong><?= date('F Y', strtotime($month)) ?></strong></small>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <form id="payslip-form" method="post" action="<?= Url::to(['payslip/generatepayslip']) ?>">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <input type="hidden" name="date" id="payslipMonthDate" value="<?= Html::encode($month) ?>">

                <div class="fixedtable p-2">
                <table class="table table-bordered gen-table mb-2">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Employee Name</th>
                            <th>Working Days</th>
                            <th>Leave Balance</th>
                            <th>Leave Allocated</th>
                            <th>Leave Taken</th>
                            <th>Leave LOP</th>
                            <th>Late / Permission</th>
                            <th>Late LOP</th>
                            <th>Additional Working</th>
                            <th>Total LOP</th>
                            <?php foreach ($allowances as $al): ?>
                                <th><?= Html::encode($al->title) ?></th>
                            <?php endforeach; ?>
                            <?php foreach ($deductions as $ded): ?>
                                <th><?= Html::encode($ded->title) ?><?= $ded->type2 == 1 ? ' (%)' : '' ?></th>
                            <?php endforeach; ?>
                            <th>Gross Salary</th>
                            <th>Actual Salary</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    foreach ($developers as $dev):
                        if (!isset($devData[$dev->id])) { continue; } // skip not-present
                        $d = $devData[$dev->id];
                        $grossSalaryTotal  += (float)$dev->gross_salary;
                        $actualSalaryTotal += (float)$d['actual_salary'];
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= Html::encode($dev->developer_name) ?></strong></td>
                        <td>
                            <input type="number" name="workingDays[<?= $dev->id ?>]" value="30" min="1" max="31">
                        </td>
                        <td><?= $d['leave_balance'] ?></td>
                        <td><?= $d['leave_allocated'] ?></td>
                        <td><?= $d['leave_taken'] ?></td>
                        <td><?= $d['leave_lop'] ?></td>
                        <td><?= $d['late_permission'] ?></td>
                        <td><?= $d['late_lop'] ?></td>
                        <td><?= $d['additional_working'] ?></td>
                        <td><?= $d['total_lop'] ?></td>

                        <?php foreach ($allowances as $al): ?>
                            <td><?= isset($devAllowances[$dev->id][$al->id]) ? $devAllowances[$dev->id][$al->id] : 0 ?></td>
                        <?php endforeach; ?>

                        <?php foreach ($deductions as $ded): ?>
                            <td>
                                <input type="text"
                                       class="salaryDeductionClass<?= $dev->id ?>"
                                       id="<?= $ded->id ?>_<?= $dev->id ?>"
                                       name="deduction[<?= $dev->id ?>][<?= $ded->id ?>]"
                                       value="<?= isset($devDeductions[$dev->id][$ded->id]) ? $devDeductions[$dev->id][$ded->id] : (($ded->default_value ?? 0)) ?>"
                                       onkeyup="calculateSalary(<?= $dev->id ?>)">
                            </td>
                        <?php endforeach; ?>

                        <td>
                            <?= $dev->gross_salary ? '₹' . $dev->gross_salary : '₹0' ?>
                        </td>
                        <td id="actualSalaryTd<?= $dev->id ?>" class="salary-cell developerActualSalaries">
                            <?= $d['actual_salary'] ?>
                        </td>
                        <td>N/A</td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Totals row -->
                    <tr class="table-light fw-bold">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <?php foreach ($allowances as $al): ?><td></td><?php endforeach; ?>
                        <?php foreach ($deductions as $ded): ?><td></td><?php endforeach; ?>
                        <td>Total: ₹<?= round($grossSalaryTotal) ?></td>
                        <td id="actualSalaryTotal" class="salary-cell">₹<?= round($actualSalaryTotal) ?></td>
                        <td></td>
                    </tr>
                    </tbody>
                </table>
                </div>

                <div class="px-3 pb-3">
                    <button type="submit" name="generatepayslip" value="1" class="btn btn-primary">
                        Submit &amp; Generate Payslips
                    </button>
                    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-secondary ms-2']) ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$calcUrl = Url::to(['payslip/calculatesalary']);
$this->registerJs(<<<JS
// Include CSRF token in all AJAX POST requests
$.ajaxSetup({ headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') } });

function calculateSalary(devId) {
    if (!devId) { Swal.fire({icon:'error', title:'Error', text:'Developer ID missing!'}); return; }
    var datas = [];
    var date_month = document.getElementById('payslipMonthDate').value;
    $('.salaryDeductionClass' + devId).each(function() {
        var deductionId    = $(this).attr('id');
        var deductionValue = $(this).val();
        datas.push({ deductionID: deductionId.replace('_' + devId, ''), deductionValue: deductionValue });
    });
    $.ajax({
        url: '$calcUrl',
        type: 'POST',
        data: { id: devId, data: datas, date_month: date_month },
        success: function(data) {
            if (data !== '') {
                $('#actualSalaryTd' + devId).text(data);
                calculateTotalSalary();
            }
        }
    });
}

function calculateTotalSalary() {
    var total = 0;
    $('.developerActualSalaries').each(function() {
        var val = parseInt($(this).text(), 10);
        if (!isNaN(val)) { total += val; }
    });
    $('#actualSalaryTotal').text('₹' + total);
}
JS);
?>
