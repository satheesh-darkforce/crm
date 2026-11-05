<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Invoice $invoice */
/** @var \backend\models\InvoiceReceipt[] $receipts */
/** @var \backend\models\InvoiceReceipt $receipt */

use backend\models\Invoice;
use backend\models\InvoiceReceipt;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Receipts — ' . ($invoice->invoice_no ?: '#' . $invoice->id);

$cur       = (string)($invoice->currency !== '' ? $invoice->currency : '₹');
$fmt       = fn($v) => Html::encode($cur) . ' ' . number_format((float)$v, 2);
$balance   = $invoice->getBalanceAmount();
$canAdd    = (int)$invoice->payment_status < Invoice::PAYMENT_STATUS_PAID && (int)$invoice->invoice_status === 1 && UserAccess::checkAccess('invoice/add-receipt');
$canDelete = UserAccess::checkAccess('invoice/delete-receipt');
?>
<div class="ui-page invoice-receipts">
    <div class="ui-head">
        <div>
            <h1>Payments for <span class="mono"><?= Html::encode($invoice->invoice_no ?: '#' . $invoice->id) ?></span></h1>
            <div class="ui-sub"><?= Html::encode($invoice->client->company_name ?? '') ?> · <?= Html::encode($invoice->title) ?></div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('invoice/view')): ?>
                <?= Html::a('<i class="fas fa-file-invoice me-1"></i>Open invoice', ['view', 'id' => $invoice->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false, 'target' => '_blank', 'rel' => 'noopener']) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Invoices', [(int)$invoice->invoice_role === Invoice::ROLE_PROFORMA ? 'proforma' : 'index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <?= $this->render('_summary', ['invoice' => $invoice]) ?>

    <div class="row g-3">
        <?php if ($canAdd): ?>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header ui-section">Record a payment</div>
                <div class="card-body"><?= $this->render('_receipt-form', ['invoice' => $invoice, 'balance' => $balance, 'from' => 'receipts']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="<?= $canAdd ? 'col-lg-7' : 'col-12' ?>">
            <div class="card border-0 shadow-sm">
                <div class="card-header ui-section">Receipts <span class="ui-section-meta"><?= count($receipts) ?></span></div>
                <div class="table-responsive">
                    <table class="table list-table ui-table stack mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Receipt</th>
                            <th>Date</th>
                            <th>Paid by</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Balance after</th>
                            <th class="text-end" style="width:44px"><span class="visually-hidden">Delete</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$receipts): ?>
                            <tr class="ui-empty-row"><td colspan="6" class="ui-empty-cell">
                                <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'receipt', 'title' => 'No payments yet', 'small' => true,
                                    'text' => $canAdd ? 'Record the first payment with the form.' : 'Payments recorded against this invoice appear here.']) ?>
                            </td></tr>
                        <?php endif; ?>
                        <?php foreach ($receipts as $r): /** @var InvoiceReceipt $r */
                            $ref = match ((int)$r->payment_mode) {
                                InvoiceReceipt::MODE_BANK   => $r->bank_ref_no,
                                InvoiceReceipt::MODE_CHEQUE => $r->cheque_no ? 'Cheque ' . $r->cheque_no : '',
                                InvoiceReceipt::MODE_PAYPAL => $r->paypal_ref_no,
                                default                     => '',
                            };
                        ?>
                            <tr>
                                <td class="cell-title"><span class="mono"><?= Html::encode($r->receipt_no ?: '—') ?></span>
                                    <?php if ($r->payment_remarks): ?><div class="small text-muted fw-normal"><?= Html::encode($r->payment_remarks) ?></div><?php endif; ?>
                                    <?php foreach ($r->attachments ?? [] as $k => $att): ?>
                                        <?= Html::a('<i class="fas fa-paperclip me-1"></i>File ' . ($k + 1), Url::to('@web' . $att->path), ['class' => 'small me-2 fw-normal', 'encode' => false, 'target' => '_blank', 'rel' => 'noopener']) ?>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-nowrap" data-label="Date"><?= $r->receipt_date ? date('d M Y', strtotime($r->receipt_date)) : '—' ?></td>
                                <td data-label="Paid by"><?= Html::encode(InvoiceReceipt::modeLabel((int)$r->payment_mode)) ?><?php if ($ref): ?><div class="small text-muted mono"><?= Html::encode($ref) ?></div><?php endif; ?></td>
                                <td class="text-end num fw-semibold" data-label="Amount"><?= $fmt($r->receipt_amount) ?></td>
                                <td class="text-end num text-muted" data-label="Balance after"><?= $r->balance_amount !== null ? $fmt($r->balance_amount) : '—' ?></td>
                                <td class="cell-actions text-end">
                                    <?php if ($canDelete): ?>
                                        <?= Html::a('<i class="fas fa-trash"></i>', ['delete-receipt', 'id' => $r->id], ['class' => 'btn btn-outline-danger act-btn', 'encode' => false,
                                            'title' => 'Delete receipt', 'aria-label' => 'Delete receipt ' . $r->receipt_no,
                                            'data' => ['method' => 'post', 'confirm' => 'Delete receipt ' . $r->receipt_no . ' (' . $cur . ' ' . number_format((float)$r->receipt_amount, 2) . ')? The invoice balance goes back up.']]) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if ($receipts): ?>
                        <tfoot class="table-light">
                        <tr><td colspan="3" class="text-end small fw-semibold">Total received</td>
                            <td class="text-end num fw-bold"><?= $fmt(array_sum(array_map(fn($r) => (float)$r->receipt_amount, $receipts))) ?></td><td colspan="2"></td></tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
