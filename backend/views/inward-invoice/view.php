<?php
/** @var \yii\web\View $this */
/** @var \backend\models\InwardInvoice $model */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Inward Invoice #' . $model->invoice_number;

$typeLabels  = \backend\models\Vendor::typeLabels();
$typeColors  = [1 => 'primary', 2 => 'success', 3 => 'info'];
$roleLabels  = [1 => 'Actual Invoice', 2 => 'Provisional'];
$roleColors  = [1 => 'success', 2 => 'warning'];
$payLabels   = [0 => 'Not Paid', 1 => 'Paid'];
$payColors   = [0 => 'danger', 1 => 'success'];

$lineItems  = $model->lineItems;
$expenses   = $model->expenses;
$attachments = $model->attachments;
$baseUrl    = Yii::$app->request->baseUrl;
$convertUrl = Url::to(['convert-to-invoice', 'id' => $model->id]);
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.sec-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6c757d}
</style>

<div class="inward-invoice-view">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold">#<?= Html::encode($model->invoice_number) ?></h5>
            <small>
                <span class="badge bg-<?= $roleColors[$model->invoice_role] ?? 'secondary' ?>"><?= $roleLabels[$model->invoice_role] ?? '—' ?></span>
                <?php if ($model->invoice_role == 2): ?>
                    <button class="btn btn-sm btn-outline-success ms-1" id="btn-convert" style="font-size:.72rem;padding:.1rem .4rem">Convert to Actual</button>
                <?php endif; ?>
            </small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Invoice Info -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header py-2"><strong class="small">Invoice Details</strong></div>
                <div class="card-body py-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><th class="text-muted" style="width:40%">Vendor</th><td><?= Html::encode($model->vendor->company_name ?? '—') ?>
                            <?php if ($model->vendor): ?>
                                <span class="badge bg-<?= $typeColors[$model->vendor->vendor_type] ?? 'secondary' ?> ms-1" style="font-size:.7rem"><?= $typeLabels[$model->vendor->vendor_type] ?? '' ?></span>
                            <?php endif; ?>
                        </td></tr>
                        <tr><th class="text-muted">Invoice No</th><td><?= Html::encode($model->invoice_number) ?></td></tr>
                        <tr><th class="text-muted">Date</th><td><?= $model->invoice_date ? date('d M Y', strtotime($model->invoice_date)) : '—' ?></td></tr>
                        <tr><th class="text-muted">Title</th><td><?= Html::encode($model->title ?: '—') ?></td></tr>
                        <tr><td colspan="2"><hr class="my-1"></td></tr>
                        <tr><th class="text-muted">Grand Total</th><td>₹<?= number_format((float)$model->grand_total, 2) ?></td></tr>
                        <tr><th class="text-muted">SGST</th><td>₹<?= number_format((float)$model->sgst_amount, 2) ?></td></tr>
                        <tr><th class="text-muted">CGST</th><td>₹<?= number_format((float)$model->cgst_amount, 2) ?></td></tr>
                        <tr><th class="text-muted">IGST</th><td>₹<?= number_format((float)$model->igst_amount, 2) ?></td></tr>
                        <?php if (!empty($expenses)): ?>
                        <tr><th class="text-muted">Extra Expenses</th><td>₹<?= number_format(array_sum(array_map(fn($e) => (float)$e->item_total, $expenses)), 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if ((float)$model->round_off != 0): ?>
                        <tr><th class="text-muted">Round Off</th><td>₹<?= number_format((float)$model->round_off, 2) ?></td></tr>
                        <?php endif; ?>
                        <tr><th class="fw-bold">Net Total</th><td class="fw-bold text-primary">₹<?= number_format((float)$model->net_total, 2) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment Info -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header py-2"><strong class="small">Payment</strong></div>
                <div class="card-body py-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><th class="text-muted" style="width:40%">Status</th>
                            <td><span class="badge bg-<?= $payColors[$model->payment_status ?? 0] ?? 'secondary' ?>"><?= $payLabels[$model->payment_status ?? 0] ?? '—' ?></span></td></tr>
                        <?php if ((int)$model->payment_status === 1): ?>
                        <tr><th class="text-muted">Payment Date</th><td><?= $model->payment_date ? date('d M Y', strtotime($model->payment_date)) : '—' ?></td></tr>
                        <tr><th class="text-muted">Payment Amount</th><td>₹<?= number_format((float)$model->payment_amount, 2) ?></td></tr>
                        <?php if ((float)$model->tds_amount > 0): ?>
                        <tr><th class="text-muted">TDS Amount</th><td>₹<?= number_format((float)$model->tds_amount, 2) ?></td></tr>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($model->payment_remarks): ?>
                        <tr><th class="text-muted">Remarks</th><td><?= Html::encode($model->payment_remarks) ?></td></tr>
                        <?php endif; ?>
                    </table>

                    <?php if (!empty($attachments)): ?>
                    <hr class="my-2">
                    <div class="sec-title mb-1">Attachments</div>
                    <?php foreach ($attachments as $ai => $att): ?>
                        <a href="<?= $att->path ?>" target="_blank" class="small d-block">Attachment <?= $ai + 1 ?></a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Line Items -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white py-2"><strong class="small">Line Items</strong> <span class="badge bg-secondary ms-1"><?= count($lineItems) ?></span></div>
        <div class="table-responsive">
            <table class="table table-sm list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:30px">#</th>
                        <th>Product</th>
                        <th>HSN</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Item Total</th>
                        <th class="text-end">SGST</th>
                        <th class="text-end">CGST</th>
                        <th class="text-end">IGST</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lineItems)): ?>
                        <tr><td colspan="10" class="text-muted text-center py-3">No line items.</td></tr>
                    <?php else: ?>
                        <?php foreach ($lineItems as $i => $d): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><?= Html::encode($d->product) ?></td>
                            <td class="text-muted small"><?= Html::encode($d->hsn ?: '—') ?></td>
                            <td class="text-end"><?= $d->qty ?></td>
                            <td class="text-end">₹<?= number_format((float)$d->price_per_qty, 2) ?></td>
                            <td class="text-end">₹<?= number_format((float)$d->item_total, 2) ?></td>
                            <td class="text-end small"><?= (float)$d->sgst_amount > 0 ? '₹' . number_format((float)$d->sgst_amount, 2) . ' (' . $d->sgst_percent . '%)' : '—' ?></td>
                            <td class="text-end small"><?= (float)$d->cgst_amount > 0 ? '₹' . number_format((float)$d->cgst_amount, 2) . ' (' . $d->cgst_percent . '%)' : '—' ?></td>
                            <td class="text-end small"><?= (float)$d->igst_amount > 0 ? '₹' . number_format((float)$d->igst_amount, 2) . ' (' . $d->igst_percent . '%)' : '—' ?></td>
                            <td class="text-end fw-semibold">₹<?= number_format((float)$d->total_amount, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($expenses)): ?>
    <!-- Extra Expenses -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white py-2"><strong class="small">Extra Expenses</strong></div>
        <div class="table-responsive">
            <table class="table table-sm list-table mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Description</th><th>Ledger</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $ei => $ex): ?>
                    <tr>
                        <td class="text-muted"><?= $ei + 1 ?></td>
                        <td><?= Html::encode($ex->product) ?></td>
                        <td class="small text-muted"><?= Html::encode($ex->ledger->title ?? '—') ?></td>
                        <td class="text-end fw-semibold">₹<?= number_format((float)$ex->item_total, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($model->invoice_role == 2): ?>
<?php $this->registerJs(<<<JS
jQuery('#btn-convert').on('click', function() {
    var btn = jQuery(this);
    Swal.fire({
        icon: 'question', title: 'Convert to Actual Invoice?',
        text: 'This will mark this invoice as an actual invoice.',
        showCancelButton: true, confirmButtonText: 'Yes, convert'
    }).then(function(r) {
        if (r.isConfirmed) {
            jQuery.post('{$convertUrl}', function(resp) {
                if (resp.status == 1) {
                    Swal.fire({icon:'success',title:'Success',text:resp.msg,timer:1500,showConfirmButton:false}).then(function(){ location.reload(); });
                } else {
                    Swal.fire({icon:'error',title:'Error',text:resp.msg});
                }
            }, 'json');
        }
    });
});
JS, \yii\web\View::POS_READY); ?>
<?php endif; ?>
