<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer[] $developers */
/** @var array $lopMap  developer_id => auto-calculated LOP days for the previous month */
/** @var \backend\models\UploadPayslip|null $lastUpload  last generated payslip for the month */
/** @var string|null $lastGeneratedAt  latest of upload_payslip.uploaded_date / payslip.created_date */
/** @var string $monthParam  previous month as YYYY-MM, for the Download link */

use backend\components\PayrollCalculator;
use backend\models\SalaryAllowance;
use yii\bootstrap5\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'Create Payslip';

// Effective net salary — falls back to gross_salary when net_salary is empty/0,
// so the table is usable for employees who don't have a separate net figure.
$effectiveNet = static fn(\backend\models\Developer $d): float =>
    (float)$d->net_salary > 0 ? (float)$d->net_salary : (float)$d->gross_salary;

$grandNetSalary = 0.0;
foreach ($developers as $_dev) { $grandNetSalary += $effectiveNet($_dev); }

// Payslips are generated for the PREVIOUS (just-closed) month, never the current
// in-progress month. Lock the period to the previous month — not user-selectable.
$prevTs    = strtotime('first day of last month');
$prevValue = date('Y-m', $prevTs);   // submitted value, e.g. 2026-05
$prevLabel = date('F Y', $prevTs);   // display label, e.g. May 2026

// Real calendar days in the payslip month — drives the per-day rate everywhere.
$daysInMonth = (int)date('t', $prevTs) ?: 30;

// Central payroll config (PF/ESI rates + ceilings, earnings split, PT amount).
$cfg  = SalaryAllowance::activeByCode();
$cval = static function (string $code, string $attr, $def) use ($cfg) {
    $row = $cfg[$code] ?? null;
    return $row && $row->$attr !== null && $row->$attr !== '' ? (float)$row->$attr : $def;
};
// Professional tax is a flat statutory amount common to all — shown once at the top.
$profTax = $cval(SalaryAllowance::CODE_PT, 'default_value', 0.0);

// Config mirrored into JS so the live preview matches the server calculation.
$jsCfg = [
    'days'       => $daysInMonth,
    'basicPct'   => $cval(SalaryAllowance::CODE_BASIC, 'default_value', 50),
    'daPct'      => $cval(SalaryAllowance::CODE_DA, 'default_value', 12),
    'hraPct'     => $cval(SalaryAllowance::CODE_HRA, 'default_value', 30),
    'pfEmp'      => $cval(SalaryAllowance::CODE_PF, 'employee_value', 0),
    'pfEmpr'     => $cval(SalaryAllowance::CODE_PF, 'employer_value', 0),
    'pfAdmin'    => $cval(SalaryAllowance::CODE_PF, 'admin_charge', 0),
    'pfCeiling'  => isset($cfg[SalaryAllowance::CODE_PF]) && $cfg[SalaryAllowance::CODE_PF]->wage_ceiling !== null
        ? (float)$cfg[SalaryAllowance::CODE_PF]->wage_ceiling : null,
    'pfBasis'    => isset($cfg[SalaryAllowance::CODE_PF]) ? (int)$cfg[SalaryAllowance::CODE_PF]->calc_basis : SalaryAllowance::BASIS_BASIC_PLUS_DA,
    'esiEmp'     => $cval(SalaryAllowance::CODE_ESI, 'employee_value', 0),
    'esiEmpr'    => $cval(SalaryAllowance::CODE_ESI, 'employer_value', 0),
    'esiCeiling' => isset($cfg[SalaryAllowance::CODE_ESI]) && $cfg[SalaryAllowance::CODE_ESI]->wage_ceiling !== null
        ? (float)$cfg[SalaryAllowance::CODE_ESI]->wage_ceiling : null,
    'esiBasis'   => isset($cfg[SalaryAllowance::CODE_ESI]) ? (int)$cfg[SalaryAllowance::CODE_ESI]->calc_basis : SalaryAllowance::BASIS_PAY_AFTER_LOP,
    'pt'         => $profTax,
];
$jsCfgJson = Json::encode($jsCfg);
?>
<style>
.payslip-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;white-space:nowrap}
.payslip-table td{vertical-align:middle;font-size:.875rem}
.payslip-table tfoot td{font-weight:600;border-top:2px solid #dee2e6}
.payslip-table tbody tr td{transition:background-color .12s ease}
#main-content .payslip-table tbody tr:hover td{background-color:#c3c8d0 !important}
input.lop-input,input.extra-input,input.allowance-input,input.incentive-input,input.pt-input{width:90px}
</style>

<div class="developer-create-payslip">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Create Payslip</h5>
            <small class="text-muted">Lists salary-applicable employees. Enter LOP, Allowance and Incentive — PF/ESI &amp; Net Pay update automatically from Manage Deductions settings.</small>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['/developer/index'], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <?php if (empty($developers)): ?>
        <div class="alert alert-warning">No salary-applicable employees found. Set <strong>Salary Applicable = Applicable</strong> on an employee to list them here.</div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <form method="post" action="<?= Url::to(['/developer/generate-payslip']) ?>" id="payslipForm">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>

            <div class="card-body border-bottom">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Payslip Month</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?= Html::encode($prevLabel) ?>" readonly>
                        <?= Html::hiddenInput('month_year', $prevValue) ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">No. of Days in Month</label>
                        <input type="text" class="form-control form-control-sm"
                               value="<?= $daysInMonth ?> days" readonly
                               title="Per-day salary = Net Salary ÷ <?= $daysInMonth ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">
                            Professional Tax <span class="text-muted fw-normal">(default)</span>
                        </label>
                        <input type="text" class="form-control form-control-sm"
                               value="₹<?= number_format($profTax, 2) ?>" readonly
                               title="Default PT from Manage Deductions — editable per employee in the table below">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table payslip-table mb-0" id="payslipTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Net Salary (₹)</th>
                            <th>Per Day (₹)</th>
                            <th>No of LOP</th>
                            <th>Worked Days</th>
                            <th>Pay After LOP (₹)</th>
                            <th>Allowance (₹)</th>
                            <th>Incentive (₹)</th>
                            <th>PF (₹)</th>
                            <th>ESI (₹)</th>
                            <th>Prof. Tax (₹)</th>
                            <th>Net Pay (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($developers as $i => $dev):
                        $net          = $effectiveNet($dev);
                        $incentiveOn  = (int)$dev->incentive_status === 1;
                        $incentiveVal = $incentiveOn ? (float)$dev->incentive_amount : 0;
                        $allowanceOn  = (int)$dev->allowance_applicable === 1;
                        $allowance    = $allowanceOn ? (float)$dev->allowance_amount : 0;

                        // Initial figures from the central engine at the auto-calculated LOP.
                        // PF/ESI/PT applicability is driven by the employee's own flags.
                        $initLop = (float)($lopMap[$dev->id] ?? 0);
                        $calc    = PayrollCalculator::forDeveloper($dev, $net, $daysInMonth, $initLop, $cfg);
                        $pfAmt   = $calc['employeePf'] + $calc['employerPf'] + $calc['pfAdmin'];
                        $esiAmt  = $calc['employeeEsi'] + $calc['employerEsi'];
                    ?>
                        <tr data-pf="<?= (int)$dev->pf_status === 1 ? 1 : 0 ?>"
                            data-esi="<?= (int)$dev->esi_status === 1 ? 1 : 0 ?>"
                            data-pt="<?= (int)$dev->professional_tax_status === 1 ? 1 : 0 ?>">
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td>
                                <?= Html::encode($dev->developer_name) ?>
                                <?php if ((int)$dev->status === 0): ?>
                                    <span class="badge bg-warning text-dark" title="Ex-employee — checked in during this month">Ex</span>
                                <?php endif; ?>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline preview-btn d-none"
                                        title="Preview full salary breakdown" onclick="payslipPreview(this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <input type="hidden" name="developer_id[]" value="<?= (int)$dev->id ?>">
                                <input type="hidden" name="emp_code[]"     value="<?= Html::encode($dev->emp_code) ?>">
                                <input type="hidden" name="name[]"         value="<?= Html::encode($dev->developer_name) ?>">
                                <input type="hidden" name="net_salary[]"   class="net-salary-hidden" value="<?= number_format($net, 2, '.', '') ?>">
                            </td>
                            <td class="text-muted small net-salary">₹<?= number_format($net, 2) ?></td>
                            <td class="text-muted small per-day">₹<?= number_format($net / $daysInMonth, 2) ?></td>
                            <td>
                                <input type="number" name="lop[]" class="form-control form-control-sm lop-input"
                                       value="<?= rtrim(rtrim(number_format($initLop, 2, '.', ''), '0'), '.') ?>"
                                       min="0" max="31" step="0.5">
                            </td>
                            <td class="text-muted small worked-days"><?= rtrim(rtrim(number_format(max(0, $daysInMonth - $initLop), 2, '.', ''), '0'), '.') ?></td>
                            <td class="text-muted small pay-after-lop">₹<?= number_format($calc['payAfterLop'], 2) ?></td>
                            <td>
                                <input type="number" name="allowance[]"
                                       class="form-control form-control-sm allowance-input"
                                       value="<?= number_format($allowance, 2, '.', '') ?>"
                                       min="0" step="0.01"
                                       <?= $allowanceOn ? '' : 'readonly title="Allowance not applicable for this employee"' ?>>
                            </td>
                            <td>
                                <input type="number" name="incentive[]"
                                       class="form-control form-control-sm incentive-input"
                                       value="<?= number_format($incentiveVal, 2, '.', '') ?>"
                                       min="0" step="0.01"
                                       <?= $incentiveOn ? '' : 'readonly title="Incentive not applicable for this employee"' ?>>
                            </td>
                            <td class="text-muted small pf-cell">₹<?= number_format($pfAmt, 2) ?></td>
                            <td class="text-muted small esi-cell">₹<?= number_format($esiAmt, 2) ?></td>
                            <td>
                                <input type="number" name="pt[]" class="form-control form-control-sm pt-input"
                                       value="<?= number_format($calc['pt'], 2, '.', '') ?>" min="0" step="0.01">
                            </td>
                            <td class="fw-semibold net-pay">₹<?= number_format($calc['netPay'] + $allowance + $incentiveVal, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-end">Total</td>
                            <td id="totalNetSalary">₹<?= number_format($grandNetSalary, 2) ?></td>
                            <td colspan="9"></td>
                            <td id="totalNetPay">₹0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
                <small class="text-muted">
                    <?php if (!empty($lastGeneratedAt)): ?>
                        <i class="fas fa-clock"></i> Payslip last generated:
                        <strong><?= date('d M Y, h:i A', strtotime($lastGeneratedAt)) ?></strong>
                    <?php else: ?>
                        <i class="fas fa-info-circle"></i> Not created yet for this month.
                    <?php endif; ?>
                </small>
                <div class="d-flex gap-2">
                    <button type="submit" name="do" value="create" class="btn btn-primary btn-sm">
                        <i class="fas fa-file-csv"></i> Create Payslip
                    </button>
                    <?php if ($lastUpload): ?>
                        <a href="<?= Url::to(['/developer/download-payslip-file', 'month' => $monthParam]) ?>"
                           class="btn btn-success btn-sm"><i class="fas fa-download"></i> Download</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-success btn-sm" disabled
                                title="Create the payslip first"><i class="fas fa-download"></i> Download</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Salary breakdown preview (populated by payslipPreview() from the live row values) -->
<div class="modal fade" id="payslipPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">
                    <i class="fas fa-file-invoice-dollar me-1"></i>
                    Salary Breakdown &mdash; <span id="ppName" class="fw-semibold"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="font-size:.8rem">
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.68rem;letter-spacing:.04em">Attendance &amp; Pay</div>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="text-muted">Net Salary</td><td class="text-end" id="ppNetSalary">—</td></tr>
                                <tr><td class="text-muted">Per Day</td><td class="text-end" id="ppPerDay">—</td></tr>
                                <tr><td class="text-muted">No. of LOP</td><td class="text-end" id="ppLop">—</td></tr>
                                <tr><td class="text-muted">Worked Days</td><td class="text-end" id="ppWorked">—</td></tr>
                                <tr><td class="text-muted">Pay After LOP</td><td class="text-end fw-semibold" id="ppPayAfterLop">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.68rem;letter-spacing:.04em">Earnings</div>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="text-muted">Basic</td><td class="text-end" id="ppBasic">—</td></tr>
                                <tr><td class="text-muted">DA</td><td class="text-end" id="ppDa">—</td></tr>
                                <tr><td class="text-muted">HRA</td><td class="text-end" id="ppHra">—</td></tr>
                                <tr><td class="text-muted">Others</td><td class="text-end" id="ppOthers">—</td></tr>
                                <tr><td class="text-muted">Gross</td><td class="text-end fw-semibold" id="ppGross">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.68rem;letter-spacing:.04em">Additions</div>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="text-muted">Allowance</td><td class="text-end" id="ppAllowance">—</td></tr>
                                <tr><td class="text-muted">Incentive</td><td class="text-end" id="ppIncentive">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.68rem;letter-spacing:.04em">Deductions</div>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr><td class="text-muted">Employee PF</td><td class="text-end" id="ppEmployeePf">—</td></tr>
                                <tr><td class="text-muted">Employer PF</td><td class="text-end" id="ppEmployerPf">—</td></tr>
                                <tr><td class="text-muted">Employee ESI</td><td class="text-end" id="ppEmployeeEsi">—</td></tr>
                                <tr><td class="text-muted">Employer ESI</td><td class="text-end" id="ppEmployerEsi">—</td></tr>
                                <tr><td class="text-muted">Professional Tax</td><td class="text-end" id="ppPt">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                    <span class="fw-semibold">Net Pay</span>
                    <span class="fw-bold text-success fs-6" id="ppNetPay">—</span>
                </div>
            </div>
            <div class="modal-footer py-2">
                <span class="text-muted small me-auto"><i class="fas fa-calendar-alt me-1"></i><?= Html::encode($prevLabel) ?></span>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
var PAY = {$jsCfgJson};

// Mirror of backend\\components\\PayrollCalculator::compute (net excludes allowance/incentive).
function computeRow(net, lop, pfOn, esiOn, ptOn) {
    var days        = PAY.days > 0 ? PAY.days : 30;
    var perDay      = net > 0 ? net / days : 0;
    var payAfterLop = net - perDay * lop;

    // Earnings split (% of Pay After LOP); Others is the remainder to avoid drift.
    var basic  = payAfterLop * PAY.basicPct / 100;
    var da     = payAfterLop * PAY.daPct    / 100;
    var hra    = payAfterLop * PAY.hraPct   / 100;
    var others = payAfterLop - (basic + da + hra);
    var grossSalary = basic + da + hra + others;
    var basicPlusDa = basic + da;

    var employeePf = 0, employerPf = 0, pfAdmin = 0;
    if (pfOn) {
        var pfBase = (PAY.pfBasis === 2) ? basicPlusDa : payAfterLop;
        var epf = (PAY.pfCeiling !== null) ? Math.min(pfBase, PAY.pfCeiling) : pfBase;
        employeePf = epf * PAY.pfEmp   / 100;
        employerPf = epf * PAY.pfEmpr  / 100;
        pfAdmin    = epf * PAY.pfAdmin / 100;
    }
    var employeeEsi = 0, employerEsi = 0;
    if (esiOn) {
        var esiBase = (PAY.esiBasis === 2) ? basicPlusDa : payAfterLop;
        var esic = (PAY.esiCeiling !== null && esiBase > PAY.esiCeiling) ? 0 : esiBase;
        employeeEsi = esic * PAY.esiEmp  / 100;
        employerEsi = esic * PAY.esiEmpr / 100;
    }
    var pt  = ptOn ? PAY.pt : 0;
    var pf  = employeePf + employerPf + pfAdmin;
    var esi = employeeEsi + employerEsi;
    var netPay = payAfterLop - (pf + esi + pt); // TDS hidden = 0
    return {
        perDay: perDay, payAfterLop: payAfterLop,
        basic: basic, da: da, hra: hra, others: others, grossSalary: grossSalary,
        employeePf: employeePf, employerPf: employerPf,
        employeeEsi: employeeEsi, employerEsi: employerEsi,
        pt: pt, pf: pf, esi: esi, netPay: netPay
    };
}

function recalculate() {
    var rows = document.querySelectorAll('#payslipTable tbody tr');
    var totalNetSalary = 0, totalNetPay = 0;

    rows.forEach(function(row) {
        var netSalary = parseFloat(row.querySelector('.net-salary-hidden').value) || 0;
        var lop       = parseFloat(row.querySelector('.lop-input').value)         || 0;
        var incentive = parseFloat(row.querySelector('.incentive-input').value)   || 0;
        var allowance = parseFloat(row.querySelector('.allowance-input').value)   || 0;
        var pfOn  = row.getAttribute('data-pf')  === '1';
        var esiOn = row.getAttribute('data-esi') === '1';
        var ptOn  = row.getAttribute('data-pt')  === '1';
        var ptInput = parseFloat(row.querySelector('.pt-input').value) || 0;

        var r      = computeRow(netSalary, lop, pfOn, esiOn, ptOn);
        // PT is edited per-row in the table, so use the field value (not the engine default).
        var netPay = r.payAfterLop - r.pf - r.esi - ptInput + allowance + incentive;
        var worked = Math.round((PAY.days - lop) * 100) / 100;

        row.querySelector('.worked-days').textContent   = (worked < 0 ? 0 : worked);
        row.querySelector('.pay-after-lop').textContent = '₹' + r.payAfterLop.toFixed(2);
        row.querySelector('.pf-cell').textContent  = '₹' + r.pf.toFixed(2);
        row.querySelector('.esi-cell').textContent = '₹' + r.esi.toFixed(2);
        row.querySelector('.net-pay').textContent  = '₹' + netPay.toFixed(2);
        totalNetSalary += netSalary;
        totalNetPay    += netPay;
    });

    document.getElementById('totalNetSalary').textContent = '₹' + totalNetSalary.toFixed(2);
    document.getElementById('totalNetPay').textContent    = '₹' + totalNetPay.toFixed(2);
}

document.querySelectorAll('.lop-input, .allowance-input, .incentive-input, .pt-input').forEach(function(el) {
    el.addEventListener('input', recalculate);
});
recalculate();

// ── Salary breakdown preview ───────────────────────────────────────────────
// Reads the row's LIVE values (so edited LOP/Allowance/Incentive are reflected),
// recomputes the full split, fills the modal and shows it.
function ppMoney(x) {
    return '₹' + (Math.round((x + Number.EPSILON) * 100) / 100)
        .toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function ppNum(x) {
    return (Math.round(x * 100) / 100).toString();
}
window.payslipPreview = function (btn) {
    var row = btn.closest('tr');
    if (!row) { return; }

    var name      = (row.querySelector('input[name="name[]"]') || {}).value || '';
    var netSalary = parseFloat(row.querySelector('.net-salary-hidden').value) || 0;
    var lop       = parseFloat(row.querySelector('.lop-input').value)         || 0;
    var allowance = parseFloat(row.querySelector('.allowance-input').value)   || 0;
    var incentive = parseFloat(row.querySelector('.incentive-input').value)   || 0;
    var pfOn  = row.getAttribute('data-pf')  === '1';
    var esiOn = row.getAttribute('data-esi') === '1';
    var ptOn  = row.getAttribute('data-pt')  === '1';

    var ptInput = parseFloat(row.querySelector('.pt-input').value) || 0;
    var r      = computeRow(netSalary, lop, pfOn, esiOn, ptOn);
    var worked = Math.max(0, Math.round((PAY.days - lop) * 100) / 100);
    var netPay = r.payAfterLop - r.pf - r.esi - ptInput + allowance + incentive;

    var set = function (id, val) { var el = document.getElementById(id); if (el) { el.textContent = val; } };
    set('ppName',        name);
    set('ppNetSalary',   ppMoney(netSalary));
    set('ppPerDay',      ppMoney(r.perDay));
    set('ppLop',         ppNum(lop));
    set('ppWorked',      ppNum(worked));
    set('ppPayAfterLop', ppMoney(r.payAfterLop));
    set('ppBasic',       ppMoney(r.basic));
    set('ppDa',          ppMoney(r.da));
    set('ppHra',         ppMoney(r.hra));
    set('ppOthers',      ppMoney(r.others));
    set('ppGross',       ppMoney(r.grossSalary));
    set('ppAllowance',   ppMoney(allowance));
    set('ppIncentive',   ppMoney(incentive));
    set('ppEmployeePf',  pfOn  ? ppMoney(r.employeePf)  : '—');
    set('ppEmployerPf',  pfOn  ? ppMoney(r.employerPf)  : '—');
    set('ppEmployeeEsi', esiOn ? ppMoney(r.employeeEsi) : '—');
    set('ppEmployerEsi', esiOn ? ppMoney(r.employerEsi) : '—');
    set('ppPt',          ppMoney(ptInput));
    set('ppNetPay',      ppMoney(netPay));

    var modalEl = document.getElementById('payslipPreviewModal');
    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
};
JS); ?>
