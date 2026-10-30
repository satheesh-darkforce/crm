<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Invoice[] $outInvoices */
/** @var array $outTotals */
/** @var \backend\models\InwardInvoice[] $inInvoices */
/** @var array $inTotals */
/** @var array $netTax */
/** @var int $year */
/** @var int $month */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Monthly GST Report — ' . date('F Y', mktime(0, 0, 0, $month, 1, $year));

$months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
           7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
$years  = range(date('Y'), date('Y') - 5);

$fmt = fn(float $v): string => '₹' . number_format($v, 2);
?>
<style>
.list-table th{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.82rem}
.list-table tfoot td{font-size:.82rem;font-weight:600}
/* Local override — layout forces .table-responsive overflow:visible globally,
   but these GST tables have 8 columns and need to scroll on narrow screens. */
.invoice-monthly-report .table-responsive{overflow-x:auto!important}
.invoice-monthly-report .list-table td,
.invoice-monthly-report .list-table th{white-space:nowrap}
@media (max-width:575.98px){
    .invoice-monthly-report .gst-summary .card-body{padding:.5rem!important}
    .invoice-monthly-report .gst-summary .small{font-size:.7rem}
}
</style>

<div class="invoice-monthly-report">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Monthly GST Report</h5>
    </div>

    <!-- Period selector -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-6 col-sm-4 col-md-2">
                    <select name="month" class="form-select form-select-sm">
                        <?php foreach ($months as $m => $name): ?>
                            <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <select name="year" class="form-select form-select-sm">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-sm-auto">
                    <button class="btn btn-secondary btn-sm w-100 w-sm-auto">View</button>
                </div>
            </form>
        </div>
    </div>

    <!-- GST Summary Box -->
    <div class="row g-2 g-md-3 mb-3 gst-summary">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted mb-1 text-center">Net SGST Payable</div>
                    <div class="fw-bold text-center fs-6 <?= $netTax['sgst'] >= 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $fmt($netTax['sgst']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted mb-1 text-center">Net CGST Payable</div>
                    <div class="fw-bold text-center fs-6 <?= $netTax['cgst'] >= 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $fmt($netTax['cgst']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted mb-1 text-center">Net IGST Payable</div>
                    <div class="fw-bold text-center fs-6 <?= $netTax['igst'] >= 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $fmt($netTax['igst']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm border-2 h-100 <?= $netTax['total'] >= 0 ? 'border-danger' : 'border-success' ?>">
                <div class="card-body py-3">
                    <div class="small text-muted mb-1 text-center">Total Tax Due</div>
                    <div class="fw-bold text-center fs-5 <?= $netTax['total'] >= 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $fmt($netTax['total']) ?>
                    </div>
                    <div class="text-center" style="font-size:.7rem;color:#6c757d">
                        <?= $netTax['total'] < 0 ? '(Input credit surplus)' : '(Payable to Govt)' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- Left: Outbound Invoices -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold" style="font-size:.85rem">Outbound Invoices (Sales)</span>
                    <span class="badge bg-primary"><?= count($outInvoices) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Invoice No</th>
                                <th>Client</th>
                                <th class="text-end">Taxable</th>
                                <th class="text-end">SGST</th>
                                <th class="text-end">CGST</th>
                                <th class="text-end">IGST</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($outInvoices)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No outbound invoices.</td></tr>
                        <?php else: ?>
                            <?php foreach ($outInvoices as $i => $inv): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td><a href="<?= Url::to(['view', 'id' => $inv->id]) ?>"
                                       class="text-decoration-none fw-semibold text-dark small">
                                    <?= Html::encode($inv->invoice_no) ?></a></td>
                                <td class="small text-muted"><?= Html::encode($inv->client->client_name ?? '—') ?></td>
                                <td class="text-end small">₹<?= number_format((float)$inv->grand_total, 2) ?></td>
                                <td class="text-end small">₹<?= number_format((float)$inv->sgst_amount, 2) ?></td>
                                <td class="text-end small">₹<?= number_format((float)$inv->cgst_amount, 2) ?></td>
                                <td class="text-end small">₹<?= number_format((float)$inv->igst_amount, 2) ?></td>
                                <td class="text-end small fw-semibold">₹<?= number_format((float)$inv->net_total, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                        <?php if (!empty($outInvoices)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end">Total</td>
                                <td class="text-end">₹<?= number_format($outTotals['grand'], 2) ?></td>
                                <td class="text-end">₹<?= number_format($outTotals['sgst'], 2) ?></td>
                                <td class="text-end">₹<?= number_format($outTotals['cgst'], 2) ?></td>
                                <td class="text-end">₹<?= number_format($outTotals['igst'], 2) ?></td>
                                <td class="text-end text-primary">₹<?= number_format($outTotals['net'], 2) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Inward Invoices -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold" style="font-size:.85rem">Inward Invoices (Purchases)</span>
                    <span class="badge bg-secondary"><?= count($inInvoices) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Invoice No</th>
                                <th>Vendor</th>
                                <th class="text-end">Taxable</th>
                                <th class="text-end">SGST</th>
                                <th class="text-end">CGST</th>
                                <th class="text-end">IGST</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($inInvoices)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No inward invoices.</td></tr>
                        <?php else: ?>
                            <?php foreach ($inInvoices as $i => $inv): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td class="fw-semibold small"><?= Html::encode($inv->invoice_number) ?></td>
                                <td class="small text-muted"><?= Html::encode($inv->vendor->company_name ?? '—') ?></td>
                                <td class="text-end small">₹<?= number_format((float)$inv->grand_total, 2) ?></td>
                                <td class="text-end small">₹<?= number_format((float)$inv->sgst_amount, 2) ?></td>
                                <td class="text-end small">₹<?= number_format((float)$inv->cgst_amount, 2) ?></td>
                                <td class="text-end small">₹<?= number_format((float)$inv->igst_amount, 2) ?></td>
                                <td class="text-end small fw-semibold">₹<?= number_format((float)$inv->net_total, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                        <?php if (!empty($inInvoices)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end">Total</td>
                                <td class="text-end">₹<?= number_format($inTotals['grand'], 2) ?></td>
                                <td class="text-end">₹<?= number_format($inTotals['sgst'], 2) ?></td>
                                <td class="text-end">₹<?= number_format($inTotals['cgst'], 2) ?></td>
                                <td class="text-end">₹<?= number_format($inTotals['igst'], 2) ?></td>
                                <td class="text-end text-secondary">₹<?= number_format($inTotals['net'], 2) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
