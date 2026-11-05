<?php

/** @var yii\web\View $this */
/** @var backend\models\PoDetail $model */

use backend\models\PoDetail;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'PO — ' . $model->po_number;

$client    = $model->getEffectiveClient();
$incharge  = $model->inchargeUser;
$invoices  = $model->invoices;
$payments  = $model->payments;
$totalPaid = $model->getTotalPaid();
$balance   = $model->getBalance();
$fromDash  = Yii::$app->controller->id === 'po-dashboard';

$fmtDate  = static fn($d) => $d ? date('d M Y', strtotime((string)$d)) : '—';
$fmtMoney = static fn($v) => '₹' . number_format((float)$v, 2);
$tone     = [PoDetail::STATUS_PENDING => 'warning', PoDetail::STATUS_IN_PROGRESS => 'accent', PoDetail::STATUS_DELIVERED => 'success', PoDetail::STATUS_CANCELLED => 'neutral'];
$val      = static fn($v) => trim((string)$v) !== '' ? Html::encode($v) : '<span class="text-muted">—</span>';
?>
<div class="ui-page po-view">
    <div class="ui-head">
        <div>
            <h1>PO <span class="mono"><?= Html::encode($model->po_number) ?></span>
                <span class="ui-pill tone-<?= $tone[(int)$model->po_status] ?? 'neutral' ?> align-middle"><?= Html::encode(PoDetail::statusOptions()[(int)$model->po_status] ?? '—') ?></span></h1>
            <div class="ui-sub"><?= Html::encode($client->company_name ?? $client->client_name ?? 'No client') ?> · <?= $fmtDate($model->date) ?></div>
        </div>
        <div class="ui-actions">
            <?php if ($model->file): ?>
                <?= Html::a('<i class="fas fa-paperclip me-1"></i>PO file', Url::to('@web/' . ltrim($model->file, '/')), ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false, 'target' => '_blank', 'rel' => 'noopener']) ?>
            <?php endif; ?>
            <?php if (UserAccess::checkAccess('po/update')): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['/po/update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', [$fromDash ? '/po-dashboard/index' : '/po/index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="ui-tiles">
        <div class="ui-tile is-accent"><div class="t-label">PO value</div><div class="t-value num"><?= $fmtMoney($model->po_cost) ?></div><div class="t-sub"><?= count($invoices) ?> invoice<?= count($invoices) == 1 ? '' : 's' ?> raised</div></div>
        <div class="ui-tile"><div class="t-label">Received</div><div class="t-value num"><?= $fmtMoney($totalPaid) ?></div><div class="t-sub"><?= count($payments) ?> payment<?= count($payments) == 1 ? '' : 's' ?></div></div>
        <div class="ui-tile"><div class="t-label">Balance</div><div class="t-value num<?= $balance > 0 ? ' text-danger' : '' ?>"><?= $fmtMoney($balance) ?></div><div class="t-sub"><?= $balance > 0 ? 'Still to collect' : 'Fully received' ?></div></div>
        <div class="ui-tile"><div class="t-label">Delivery</div><div class="t-value"><?= $fmtDate($model->delivery_date) ?></div><div class="t-sub">In charge: <?= Html::encode($incharge->username ?? '—') ?></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">PO details</div>
                <table class="table ui-detail mb-0">
                    <tr><th scope="row">Client</th><td><?= $val($client->client_name ?? '') ?></td></tr>
                    <tr><th scope="row">Company</th><td><?= $val($client->company_name ?? '') ?></td></tr>
                    <tr><th scope="row">Particulars</th><td><?= trim((string)$model->particulars) !== '' ? nl2br(Html::encode($model->particulars)) : '<span class="text-muted">—</span>' ?></td></tr>
                    <tr><th scope="row">Accountant</th><td><?= $val($model->po_received_by) ?></td></tr>
                    <?php if ($model->quote_id): ?><tr><th scope="row">Quotation</th><td class="mono"><?= Html::encode($model->quote->quote_no ?? '#' . $model->quote_id) ?></td></tr><?php endif; ?>
                    <?php if (!empty($model->reason)): ?><tr><th scope="row">Cost reduction reason</th><td><?= nl2br(Html::encode($model->reason)) ?></td></tr><?php endif; ?>
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header ui-section">Invoices <span class="ui-section-meta"><?= count($invoices) ?></span></div>
                <?php if (!$invoices): ?>
                    <div class="card-body"><?= $this->render('@backend/views/_ui/_empty', ['icon' => 'file-invoice', 'title' => 'No invoices raised yet', 'small' => true]) ?></div>
                <?php else: ?>
                    <table class="table list-table mb-0">
                        <thead class="table-light"><tr><th>Invoice</th><th>Date</th><th class="text-end">File</th></tr></thead>
                        <tbody>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="mono"><?= Html::encode($inv->invoice_number) ?></td>
                                <td><?= $fmtDate($inv->invoice_date) ?></td>
                                <td class="text-end"><?= !empty($inv->invoice_file)
                                    ? Html::a('<i class="fas fa-eye"></i><span class="visually-hidden">Open invoice file</span>', ['/po-dashboard/download-invoice', 'id' => $inv->id], ['class' => 'btn btn-outline-secondary act-btn', 'encode' => false, 'target' => '_blank', 'rel' => 'noopener'])
                                    : '<span class="text-muted">—</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header ui-section">Payments <span class="ui-section-meta"><?= count($payments) ?></span></div>
                <?php if (!$payments): ?>
                    <div class="card-body"><?= $this->render('@backend/views/_ui/_empty', ['icon' => 'money-bill-wave', 'title' => 'No payments recorded yet', 'small' => true]) ?></div>
                <?php else: ?>
                    <table class="table list-table mb-0">
                        <thead class="table-light"><tr><th>Date</th><th>Mode</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= $fmtDate($p->payment_date) ?><?php if ($p->note): ?><div class="small text-muted"><?= Html::encode($p->note) ?></div><?php endif; ?></td>
                                <td><?= Html::encode($p->getModeLabel()) ?></td>
                                <td class="text-end num fw-semibold"><?= $fmtMoney($p->amount) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light"><tr><td colspan="2" class="text-end small fw-semibold">Total received</td><td class="text-end num fw-bold"><?= $fmtMoney($totalPaid) ?></td></tr></tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
