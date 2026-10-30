<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Vendor $model */
/** @var \backend\models\InwardInvoice[] $inwardInvoices */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = $model->company_name;

$typeColors      = [1 => 'primary', 2 => 'success', 3 => 'info'];
$payStatusLabels = [0 => 'Unpaid', 1 => 'Partial', 2 => 'Paid'];
$payStatusColors = [0 => 'danger',  1 => 'warning', 2 => 'success'];
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.sec-title{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6c757d}
</style>

<div class="vendor-view">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($model->company_name) ?></h5>
            <small class="text-muted">
                <span class="badge bg-<?= $typeColors[$model->vendor_type] ?? 'secondary' ?> bg-opacity-10 text-<?= $typeColors[$model->vendor_type] ?? 'secondary' ?> border border-<?= $typeColors[$model->vendor_type] ?? 'secondary' ?>" style="font-size:.75rem">
                    <?= Html::encode($model->getTypeLabel()) ?>
                </span>
            </small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id],
                ['class' => 'btn btn-sm btn-outline-primary', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'],
                ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
        </div>
    </div>

    <!-- Vendor Info Card -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header py-2">
            <strong class="small">Vendor Details</strong>
        </div>
        <div class="card-body py-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="sec-title mb-1">Company Name</div>
                    <div class="fw-semibold"><?= Html::encode($model->company_name) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="sec-title mb-1">Vendor Type</div>
                    <span class="badge bg-<?= $typeColors[$model->vendor_type] ?? 'secondary' ?>">
                        <?= Html::encode($model->getTypeLabel()) ?>
                    </span>
                </div>
                <div class="col-md-3">
                    <div class="sec-title mb-1">GST Number</div>
                    <div><?= Html::encode($model->gst_no ?: '—') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="sec-title mb-1">Address</div>
                    <div style="white-space:pre-line"><?= Html::encode($model->address ?: '—') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Invoices (Inward Invoices) -->
    <div class="card border-0 shadow-sm">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <strong class="small">Purchase Invoices</strong>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary"><?= count($inwardInvoices) ?></span>
                <?php if ($model->vendor_type >= 1): ?>
                    <?= Html::a('+ Add Invoice', ['/inward-invoice/create', 'vendor_id' => $model->id],
                        ['class' => 'btn btn-outline-primary btn-sm', 'style' => 'font-size:.75rem;padding:.2rem .5rem']) ?>
                <?php else: ?>
                    <span class="btn btn-outline-secondary btn-sm disabled" style="font-size:.75rem;padding:.2rem .5rem"
                          title="Set vendor type first">+ Add Invoice</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (empty($inwardInvoices)): ?>
            <div class="card-body text-center text-muted py-4 small">No purchase invoices for this vendor.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Invoice No.</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end">Net Total</th>
                        <th>Tax</th>
                        <th>Payment</th>
                        <th class="text-end" style="width:60px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $grandSum = 0;
                $netSum   = 0;
                foreach ($inwardInvoices as $i => $inv):
                    $grandSum += $inv->grand_total;
                    $netSum   += $inv->net_total;
                    $ps        = $inv->payment_status ?? 0;
                    $taxTotal  = $inv->sgst_amount + $inv->cgst_amount + $inv->igst_amount;
                ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td>
                        <a href="<?= Url::to(['/inward-invoice/view', 'id' => $inv->id]) ?>"
                           class="text-decoration-none fw-semibold small">
                            <?= Html::encode($inv->invoice_number) ?>
                        </a>
                    </td>
                    <td class="small"><?= Html::encode($inv->title ?: '—') ?></td>
                    <td class="small text-muted">
                        <?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?>
                    </td>
                    <td class="text-end fw-semibold small">
                        ₹<?= number_format((float)$inv->grand_total, 2) ?>
                    </td>
                    <td class="text-end small">
                        ₹<?= number_format((float)$inv->net_total, 2) ?>
                    </td>
                    <td class="small text-muted">
                        <?php if ($taxTotal > 0): ?>
                            <?php if ($inv->igst_amount > 0): ?>
                                IGST ₹<?= number_format((float)$inv->igst_amount, 2) ?>
                            <?php else: ?>
                                SGST ₹<?= number_format((float)$inv->sgst_amount, 2) ?>
                                + CGST ₹<?= number_format((float)$inv->cgst_amount, 2) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $payStatusColors[$ps] ?? 'secondary' ?>">
                            <?= $payStatusLabels[$ps] ?? '—' ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <?= Html::a('View', ['/inward-invoice/view', 'id' => $inv->id], [
                            'class' => 'btn btn-outline-secondary btn-sm px-2 py-0',
                            'style' => 'font-size:.75rem',
                        ]) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="text-end fw-semibold small text-muted">Totals</td>
                        <td class="text-end fw-semibold small">₹<?= number_format($grandSum, 2) ?></td>
                        <td class="text-end fw-semibold small">₹<?= number_format($netSum, 2) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
