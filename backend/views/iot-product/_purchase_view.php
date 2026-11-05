<?php
/** @var \yii\web\View $this */
/** @var \backend\models\InwardInvoice $model */

use yii\bootstrap5\Html;

$this->title = 'Purchase Invoice: ' . $model->invoice_number;

$lineItems = $model->lineItems;
$expenses  = $model->expenses;
$vendor    = $model->vendor;
?>
<div class="ui-page purchase-view">
    <div class="ui-head">
        <div>
            <h1>View Purchase Invoice</h1>
        </div>
        <div class="ui-actions">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="printBill()">
                <i class="bi bi-printer"></i> Print
            </button>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['purchased'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm" id="printArea">
        <div class="card-body">
            <!-- Header Info -->
            <div class="row mb-3" style="font-size:.9rem">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Company Name :</strong> <?= Html::encode($vendor->company_name ?? '') ?></p>
                    <p class="mb-1"><strong>GST Number :</strong> <?= Html::encode($vendor->gst_no ?? '') ?></p>
                    <p class="mb-1"><strong>Address :</strong> <?= Html::encode($vendor->address ?? '') ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><strong>Invoice No :</strong> <?= Html::encode($model->invoice_number) ?></p>
                    <p class="mb-1"><strong>Invoice Name :</strong> <?= Html::encode($model->title ?? '') ?></p>
                    <p class="mb-1"><strong>Date :</strong> <?= $model->invoice_date ? date('Y-m-d', strtotime($model->invoice_date)) : '' ?></p>
                    <p class="mb-1"><strong>Role :</strong>
                        <?= $model->invoice_role == 2
                            ? '<span class="badge bg-warning-subtle text-warning border">Proforma</span>'
                            : '<span class="badge bg-success-subtle text-success border">Invoice</span>' ?>
                    </p>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-sm" style="font-size:.82rem">
                    <thead class="table-light">
                        <tr>
                            <th>S.No</th>
                            <th>Products</th>
                            <th>HSN</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Price per Qty</th>
                            <th class="text-end">Item Total</th>
                            <th class="text-end">SGST %</th>
                            <th class="text-end">SGST Amount</th>
                            <th class="text-end">CGST %</th>
                            <th class="text-end">CGST Amount</th>
                            <th class="text-end">IGST %</th>
                            <th class="text-end">IGST Amount</th>
                            <th class="text-end">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($lineItems)): ?>
                        <?php $sno = 1; foreach ($lineItems as $d): ?>
                        <tr>
                            <td class="text-center"><?= $sno++ ?></td>
                            <td><?= Html::encode($d->product ?? '') ?></td>
                            <td class="text-center"><?= Html::encode($d->hsn ?? '') ?></td>
                            <td class="text-end"><?= $d->qty ?></td>
                            <td class="text-end"><?= number_format((float)$d->price_per_qty, 2) ?></td>
                            <td class="text-end"><?= number_format((float)$d->item_total, 2) ?></td>
                            <td class="text-end"><?= $d->sgst_percent ?></td>
                            <td class="text-end"><?= number_format((float)$d->sgst_amount, 2) ?></td>
                            <td class="text-end"><?= $d->cgst_percent ?></td>
                            <td class="text-end"><?= number_format((float)$d->cgst_amount, 2) ?></td>
                            <td class="text-end"><?= $d->igst_percent ?></td>
                            <td class="text-end"><?= number_format((float)$d->igst_amount, 2) ?></td>
                            <td class="text-end"><?= number_format((float)$d->total_amount, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="13" class="text-center text-muted">No line items found.</td></tr>
                    <?php endif; ?>

                    <!-- Totals -->
                    <tr class="fw-semibold">
                        <td colspan="12" class="text-end">Grand Total</td>
                        <td class="text-end"><?= number_format((float)$model->grand_total, 2) ?></td>
                    </tr>
                    <tr class="fw-semibold">
                        <td colspan="12" class="text-end">SGST Amount</td>
                        <td class="text-end"><?= number_format((float)$model->sgst_amount, 2) ?></td>
                    </tr>
                    <tr class="fw-semibold">
                        <td colspan="12" class="text-end">CGST Amount</td>
                        <td class="text-end"><?= number_format((float)$model->cgst_amount, 2) ?></td>
                    </tr>
                    <tr class="fw-semibold">
                        <td colspan="12" class="text-end">IGST Amount</td>
                        <td class="text-end"><?= number_format((float)$model->igst_amount, 2) ?></td>
                    </tr>

                    <!-- Extra Expenses (line_item_type=2) -->
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $exp): ?>
                        <tr class="fw-semibold">
                            <td colspan="12" class="text-end"><?= Html::encode($exp->product ?? 'Extra Expense') ?></td>
                            <td class="text-end"><?= number_format((float)$exp->total_amount, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <tr class="fw-semibold">
                        <td colspan="12" class="text-end">Round Off</td>
                        <td class="text-end"><?= number_format((float)$model->round_off, 2) ?></td>
                    </tr>
                    <tr class="fw-bold table-light">
                        <td colspan="12" class="text-end">Net Amount</td>
                        <td class="text-end text-success"><?= number_format((float)$model->net_total, 2) ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Payment Info -->
            <div class="mt-3" style="font-size:.875rem">
                <p class="mb-1"><strong>Payment Status :</strong>
                    <?php if ($model->payment_status == 1): ?>
                        <span class="badge bg-success">Paid</span>
                    <?php elseif ($model->payment_status !== null && $model->payment_status !== '' && $model->payment_status == 0): ?>
                        <span class="badge bg-danger">Not Paid</span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </p>
                <?php if (!empty($model->payment_remarks)): ?>
                    <p class="mb-1"><strong>Payment Remarks :</strong> <?= Html::encode($model->payment_remarks) ?></p>
                <?php endif; ?>

                <?php if ($model->payment_status == 1): ?>
                    <?php if (!empty($model->payment_date)): ?>
                        <p class="mb-1"><strong>Payment Date :</strong> <?= Html::encode($model->payment_date) ?></p>
                    <?php endif; ?>
                    <?php if ($model->payment_amount > 0): ?>
                        <p class="mb-1"><strong>Payment Amount :</strong> <?= number_format((float)$model->payment_amount, 2) ?></p>
                    <?php endif; ?>
                    <?php if ($model->tds_amount > 0): ?>
                        <p class="mb-1"><strong>TDS Amount :</strong> <?= number_format((float)$model->tds_amount, 2) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Attachments -->
            <?php $attachments = $model->attachments; ?>
            <?php if (!empty($attachments)): ?>
            <div class="mt-3" style="font-size:.875rem">
                <strong>Attachments:</strong>
                <ul class="mt-1 mb-0">
                    <?php $cnt = 1; foreach ($attachments as $att): ?>
                        <li><a href="<?= Html::encode($att->path) ?>" target="_blank">Attachment <?= $cnt++ ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<'JS'
window.printBill = function() {
    var content = document.getElementById('printArea').innerHTML;
    var w = window.open('', '', 'width=900,height=700');
    w.document.write('<html><head><title>Print Bill</title>');
    w.document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">');
    w.document.write('</head><body class="p-3">');
    w.document.write(content);
    w.document.write('</body></html>');
    w.document.close();
    setTimeout(function(){ w.print(); }, 500);
};
JS, \yii\web\View::POS_END);
?>
