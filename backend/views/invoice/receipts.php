<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Invoice $invoice */
/** @var \backend\models\InvoiceReceipt[] $receipts */
/** @var \backend\models\InvoiceReceipt $receipt */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use kartik\date\DatePicker;

$this->title = 'Receipts — ' . ($invoice->invoice_no ?: '#' . $invoice->id);

$payBadge = [0 => 'danger', 1 => 'warning', 2 => 'success'];
$payLabel = [0 => 'Unpaid',  1 => 'Partial',  2 => 'Paid'];
$ps       = (int)$invoice->payment_status;
$balance  = max(0, (float)$invoice->net_total - (float)$invoice->tds_amount - (float)$invoice->paid_amount);

// Yii1 parity: invoice.currency holds the per-invoice symbol/code (₹ for INR, USD/SGD/HKD/AED/QAR for foreign).
$cur = (string)($invoice->currency !== '' ? $invoice->currency : '₹');
$fmt = fn(float $v): string => Html::encode($cur) . ' ' . number_format($v, 2);
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.mode-section{display:none}
</style>

<div class="invoice-receipts">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold">Payment Receipts</h5>
            <small class="text-muted">
                Invoice: <strong><?= Html::encode($invoice->invoice_no ?: '#' . $invoice->id) ?></strong>
                &nbsp;•&nbsp;
                <?= Html::encode($invoice->client->client_name ?? '—') ?>
                &nbsp;•&nbsp;
                <span class="badge bg-<?= $payBadge[$ps] ?>"><?= $payLabel[$ps] ?></span>
            </small>
        </div>
        <?= Html::a('← Back to Invoice', ['view', 'id' => $invoice->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <!-- Invoice summary strip -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <div class="row g-2 text-center">
                <div class="col">
                    <div class="small text-muted">Net Total</div>
                    <div class="fw-semibold"><?= $fmt((float)$invoice->net_total) ?></div>
                </div>
                <?php if ($invoice->tds_amount > 0): ?>
                <div class="col">
                    <div class="small text-muted">TDS</div>
                    <div class="fw-semibold text-warning"><?= $fmt((float)$invoice->tds_amount) ?></div>
                </div>
                <?php endif; ?>
                <div class="col">
                    <div class="small text-muted">Paid</div>
                    <div class="fw-semibold text-success"><?= $fmt((float)$invoice->paid_amount) ?></div>
                </div>
                <div class="col">
                    <div class="small text-muted">Balance</div>
                    <div class="fw-semibold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $fmt($balance) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- Left: Add Receipt Form -->
        <?php if ($invoice->payment_status < \backend\models\Invoice::PAYMENT_STATUS_PAID): ?>
        <div class="col-md-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-2 fw-semibold" style="font-size:.85rem">Record Payment</div>
                <div class="card-body">
                    <form method="post" action="<?= Url::to(['add-receipt', 'id' => $invoice->id]) ?>">
                        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                        <input type="hidden" name="_from" value="receipts">

                        <!-- Amount & Date -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label form-label-sm text-muted">Amount (<?= Html::encode($cur) ?>) <span class="text-danger">*</span></label>
                                <input type="number" name="InvoiceReceipt[receipt_amount]" step="0.01" min="0.01"
                                       max="<?= $balance ?>"
                                       class="form-control form-control-sm" placeholder="0.00" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label form-label-sm text-muted">Date <span class="text-danger">*</span></label>
                                <?= DatePicker::widget([
                                    'name'          => 'InvoiceReceipt[receipt_date]',
                                    'value'         => date('Y-m-d'),
                                    'type'          => DatePicker::TYPE_INPUT,
                                    'options'       => ['class' => 'form-control form-control-sm', 'required' => true],
                                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                                ]) ?>
                            </div>
                        </div>

                        <!-- Payment Mode -->
                        <div class="mb-3">
                            <label class="form-label form-label-sm text-muted">Payment Mode</label>
                            <select name="InvoiceReceipt[payment_mode]" id="paymentMode" class="form-select form-select-sm">
                                <option value="3">Bank Transfer</option>
                                <option value="1">Cash</option>
                                <option value="2">Cheque</option>
                                <option value="4">PayPal</option>
                                <option value="5">Others</option>
                            </select>
                        </div>

                        <!-- Cash fields -->
                        <div id="sec-cash" class="mode-section mb-3">
                            <label class="form-label form-label-sm text-muted">Cash Date</label>
                            <?= DatePicker::widget([
                                'name'          => 'InvoiceReceipt[cash_date]',
                                'type'          => DatePicker::TYPE_INPUT,
                                'options'       => ['class' => 'form-control form-control-sm'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                            ]) ?>
                        </div>

                        <!-- Cheque fields -->
                        <div id="sec-cheque" class="mode-section">
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Sender Bank</label>
                                    <input type="text" name="InvoiceReceipt[cheque_sender_bank]" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Cheque No.</label>
                                    <input type="text" name="InvoiceReceipt[cheque_no]" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Cheque Date</label>
                                    <?= DatePicker::widget([
                                        'name'          => 'InvoiceReceipt[cheque_date]',
                                        'type'          => DatePicker::TYPE_INPUT,
                                        'options'       => ['class' => 'form-control form-control-sm'],
                                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                                    ]) ?>
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Cheque Amount</label>
                                    <input type="number" name="InvoiceReceipt[cheque_amount]" step="0.01" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Received Bank</label>
                                    <input type="text" name="InvoiceReceipt[cheque_received_bank]" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Received A/c No.</label>
                                    <input type="text" name="InvoiceReceipt[cheque_received_ac_no]" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form-label-sm text-muted">Deposit Date</label>
                                <?= DatePicker::widget([
                                    'name'          => 'InvoiceReceipt[cheque_deposit_date]',
                                    'type'          => DatePicker::TYPE_INPUT,
                                    'options'       => ['class' => 'form-control form-control-sm'],
                                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                                ]) ?>
                            </div>
                        </div>

                        <!-- Bank Transfer fields -->
                        <div id="sec-bank" class="mode-section">
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Sender Bank</label>
                                    <input type="text" name="InvoiceReceipt[bank_sender_bank]" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Transaction Ref No.</label>
                                    <input type="text" name="InvoiceReceipt[bank_ref_no]" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Transaction Date</label>
                                    <?= DatePicker::widget([
                                        'name'          => 'InvoiceReceipt[bank_date]',
                                        'type'          => DatePicker::TYPE_INPUT,
                                        'options'       => ['class' => 'form-control form-control-sm'],
                                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                                    ]) ?>
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Amount</label>
                                    <input type="number" name="InvoiceReceipt[bank_amount]" step="0.01" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Received Bank</label>
                                    <input type="text" name="InvoiceReceipt[bank_received_bank]" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Received A/c No.</label>
                                    <input type="text" name="InvoiceReceipt[bank_received_ac_no]" class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>

                        <!-- PayPal fields -->
                        <div id="sec-paypal" class="mode-section">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">PayPal Ref No.</label>
                                    <input type="text" name="InvoiceReceipt[paypal_ref_no]" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label form-label-sm text-muted">Payment Date</label>
                                    <?= DatePicker::widget([
                                        'name'          => 'InvoiceReceipt[paypal_payment_date]',
                                        'type'          => DatePicker::TYPE_INPUT,
                                        'options'       => ['class' => 'form-control form-control-sm'],
                                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                                    ]) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Remarks (always visible) -->
                        <div class="mb-3">
                            <label class="form-label form-label-sm text-muted">Remarks</label>
                            <textarea name="InvoiceReceipt[payment_remarks]" class="form-control form-control-sm" rows="2"></textarea>
                        </div>

                        <button type="submit" class="btn btn-success btn-sm px-4">Save Receipt</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Right: Receipt History -->
        <div class="col">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-2 fw-semibold d-flex justify-content-between" style="font-size:.85rem">
                    <span>Receipt History</span>
                    <?php if (!empty($receipts)): ?>
                        <small class="text-muted fw-normal">
                            <?= count($receipts) ?> receipt<?= count($receipts) != 1 ? 's' : '' ?> &nbsp;•&nbsp;
                            Total: <?= $fmt(array_sum(array_column(array_map(fn($r) => ['a' => (float)$r->receipt_amount], $receipts), 'a'))) ?>
                        </small>
                    <?php endif; ?>
                </div>
                <?php if (empty($receipts)): ?>
                    <div class="card-body text-center text-muted py-4 small">No receipts recorded yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Receipt No.</th>
                                <th>Date</th>
                                <th>Mode</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Balance</th>
                                <th>Remarks</th>
                                <th class="text-end" style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($receipts as $i => $r): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="fw-semibold small"><?= Html::encode($r->receipt_no ?: '—') ?></td>
                            <td class="small"><?= $r->receipt_date ? date('d M Y', strtotime($r->receipt_date)) : '—' ?></td>
                            <td>
                                <span class="badge bg-light text-dark border" style="font-size:.72rem">
                                    <?= \backend\models\InvoiceReceipt::modeLabel((int)$r->payment_mode) ?>
                                </span>
                                <?php if ($r->payment_mode == \backend\models\InvoiceReceipt::MODE_BANK && $r->bank_ref_no): ?>
                                    <div class="small text-muted"><?= Html::encode($r->bank_ref_no) ?></div>
                                <?php elseif ($r->payment_mode == \backend\models\InvoiceReceipt::MODE_CHEQUE && $r->cheque_no): ?>
                                    <div class="small text-muted">Chq: <?= Html::encode($r->cheque_no) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold text-success">
                                <?= $fmt((float)$r->receipt_amount) ?>
                            </td>
                            <td class="text-end small text-muted">
                                <?= $r->balance_amount !== null ? $fmt((float)$r->balance_amount) : '—' ?>
                            </td>
                            <td class="small text-muted"><?= Html::encode($r->payment_remarks ?? '') ?></td>
                            <td class="text-end">
                                <a href="#" class="btn btn-outline-danger btn-sm px-1 py-0 btn-delete-receipt"
                                   title="Delete receipt"
                                   data-id="<?= $r->id ?>"
                                   data-amount="<?= Html::encode($fmt((float)$r->receipt_amount)) ?>"
                                   style="font-size:.75rem"><i class="fas fa-times"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end small fw-semibold">Total Received</td>
                                <td class="text-end fw-bold text-success">
                                    <?= $fmt(array_sum(array_map(fn($r) => (float)$r->receipt_amount, $receipts))) ?>
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
(function() {
    var modeMap = {1:'cash', 2:'cheque', 3:'bank', 4:'paypal', 5:null};
    var sel = document.getElementById('paymentMode');
    if (!sel) return;

    function showMode(val) {
        ['cash','cheque','bank','paypal'].forEach(function(m) {
            document.getElementById('sec-' + m).style.display = 'none';
        });
        var key = modeMap[parseInt(val)];
        if (key) document.getElementById('sec-' + key).style.display = '';
    }

    sel.addEventListener('change', function() { showMode(this.value); });
    showMode(sel.value); // initial
})();

// Delete receipt with SweetAlert2 confirmation
document.querySelectorAll('.btn-delete-receipt').forEach(function(el) {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        var receiptId = this.getAttribute('data-id');
        var amount = this.getAttribute('data-amount');
        Swal.fire({
            icon: 'warning',
            title: 'Delete Receipt?',
            text: 'Are you sure you want to delete this receipt of ' + amount + '?',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = <?= \yii\helpers\Json::encode(Yii::$app->request->baseUrl) ?> + '/invoice/delete-receipt?id=' + receiptId;
                var csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = <?= \yii\helpers\Json::encode(Yii::$app->request->csrfParam) ?>;
                csrf.value = <?= \yii\helpers\Json::encode(Yii::$app->request->getCsrfToken()) ?>;
                form.appendChild(csrf);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});
</script>
