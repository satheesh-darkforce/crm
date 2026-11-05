<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\ClientModel $client */

use backend\models\Invoice;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'My Invoices';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$tone       = [Invoice::PAYMENT_STATUS_UNPAID => 'danger', Invoice::PAYMENT_STATUS_PARTIAL => 'warning', Invoice::PAYMENT_STATUS_PAID => 'success'];
$canView    = UserAccess::checkAccess('invoice/view');
$canPdf     = UserAccess::checkAccess('invoice/download-pdf');
$fmt        = fn(Invoice $i, $v) => Html::encode($i->currency ?: '₹') . ' ' . number_format((float)$v, 2);
$due        = 0.0;
foreach ($rows as $r) {
    $due += $r->getBalanceAmount();
}
?>
<div class="ui-page invoice-client">
    <div class="ui-head">
        <div>
            <h1>Your invoices</h1>
            <div class="ui-sub"><?= Html::encode($client->company_name ?: $client->client_name) ?> · <?= number_format($total) ?> invoice<?= $total == 1 ? '' : 's' ?></div>
        </div>
        <?php if ($rows): ?>
            <div class="ui-actions"><span class="ui-total">Due on this page <strong><?= number_format($due, 2) ?></strong></span></div>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Due</th>
                    <th>Status</th>
                    <th class="text-end" style="width:120px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="8" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'file-invoice', 'title' => 'No invoices yet', 'text' => 'Invoices raised to you will appear here.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $inv): /** @var Invoice $inv */ $bal = $inv->getBalanceAmount(); ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title"><span class="mono"><?= Html::encode($inv->invoice_no) ?></span>
                            <div class="small text-muted fw-normal"><?= Html::encode($inv->title) ?></div></td>
                        <td class="text-nowrap" data-label="Date"><?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?></td>
                        <td class="text-end num" data-label="Amount"><?= $fmt($inv, $inv->net_total) ?></td>
                        <td class="text-end num" data-label="Paid"><?= (float)$inv->paid_amount ? $fmt($inv, $inv->paid_amount) : '—' ?></td>
                        <td class="text-end num<?= $bal > 0.01 ? ' fw-semibold text-danger' : ' text-muted' ?>" data-label="Due"><?= $bal > 0.01 ? $fmt($inv, $bal) : '—' ?></td>
                        <td data-label="Status"><span class="ui-pill tone-<?= $tone[(int)$inv->payment_status] ?? 'neutral' ?>"><?= Html::encode($inv->getPaymentStatusLabel()) ?></span></td>
                        <td class="text-end text-nowrap" data-label="">
                            <?php if ($canView): ?><?= Html::a('View', ['view', 'id' => $inv->id], ['class' => 'btn btn-outline-secondary btn-sm', 'target' => '_blank', 'rel' => 'noopener']) ?><?php endif; ?>
                            <?php if ($canPdf): ?><?= Html::a('<i class="fas fa-file-pdf"></i><span class="visually-hidden">Download PDF</span>', ['download-pdf', 'id' => $inv->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false, 'title' => 'Download PDF']) ?><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'invoices']) ?>
    </div>
</div>
