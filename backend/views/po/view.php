<?php

/** @var yii\web\View $this */
/** @var backend\models\PoDetail $model */

use common\components\UserAccess;
use backend\models\PoDetail;
use backend\models\PoPayment;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'PO — ' . $model->po_number;

$client    = $model->getEffectiveClient();
$incharge  = $model->inchargeUser;
$invoices  = $model->invoices;
$payments  = $model->payments;
$totalPaid = $model->getTotalPaid();
$balance   = $model->getBalance();

$fmtDate  = static fn($d) => $d ? date('d M Y', strtotime((string) $d)) : '—';
$fmtMoney = static fn($v) => '₹ ' . number_format((float) $v, 2);

$statusMap = [
    PoDetail::STATUS_PENDING     => ['Pending',     'bg-warning text-dark'],
    PoDetail::STATUS_IN_PROGRESS => ['In Progress', 'bg-info text-dark'],
    PoDetail::STATUS_DELIVERED   => ['Delivered',   'bg-success'],
    PoDetail::STATUS_CANCELLED   => ['Cancelled',   'bg-danger'],
];
[$statusLabel, $statusCls] = $statusMap[(int) $model->po_status] ?? ['—', 'bg-secondary'];
?>
<style>
.po-v-wrap        { background:#f8fafc; padding:24px 20px; border-radius:14px; }
.po-v-hero        { background: linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%); color:#fff; padding: 24px 28px; border-radius: 14px; box-shadow: 0 10px 25px -10px rgba(14,165,233,.4); margin-bottom: 22px; }
.po-v-hero h2     { margin: 0 0 4px; font-weight: 700; letter-spacing: .3px; }
.po-v-hero .hsub  { opacity:.9; font-size:.78rem; text-transform:uppercase; letter-spacing:.6px; font-weight:600; }
.po-v-section     { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:22px 24px; margin-bottom:20px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04); }
.po-v-section h5  { margin:0; font-weight:700; color:#1e293b; font-size:1rem; text-transform:uppercase; letter-spacing:.5px; }
.po-v-section .head { display:flex; align-items:center; padding-bottom:12px; margin-bottom:18px; border-bottom:2px solid #f1f5f9; }
.po-v-icon        { width:34px; height:34px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; color:#fff; margin-right:12px; font-size:.95rem; }
.po-v-field       { margin-bottom:12px; }
.po-v-field .lbl  { display:block; font-size:.72rem; font-weight:600; letter-spacing:.6px; color:#64748b; text-transform:uppercase; margin-bottom:2px; }
.po-v-field .val  { font-size:.95rem; color:#0f172a; font-weight:500; word-break:break-word; }
.po-v-field .val.muted { color:#94a3b8; font-weight:400; }
.po-v-table       { width:100%; border-collapse:separate; border-spacing:0; }
.po-v-table thead th { background:#f1f5f9; color:#334155; font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; padding:10px 12px; border-bottom:1px solid #e2e8f0; text-align:left; white-space:nowrap; }
.po-v-table tbody td { padding:10px 12px; border-bottom:1px solid #f1f5f9; font-size:.9rem; color:#1e293b; vertical-align:middle; }
.po-v-table tbody tr:last-child td { border-bottom:0; }
.po-v-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:14px; }
.po-v-stat    { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; }
.po-v-stat .lbl { font-size:.7rem; color:#64748b; text-transform:uppercase; letter-spacing:.5px; font-weight:600; }
.po-v-stat .val { font-size:1.1rem; font-weight:700; margin-top:4px; }
</style>

<div class="po-v-wrap">
    <div class="po-v-hero d-flex flex-wrap justify-content-between align-items-start">
        <div>
            <div class="hsub">Purchase Order</div>
            <h2><?= Html::encode($model->po_number) ?></h2>
            <div style="opacity:.95; margin-top:8px; font-size:.85rem;">
                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i><?= $fmtDate($model->date) ?></span>
                <span class="badge <?= $statusCls ?>"><?= Html::encode($statusLabel) ?></span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <?php if (UserAccess::checkAccess('po/update')): ?>
                <a class="btn btn-sm btn-warning text-dark" href="<?= Url::to(['/po/update', 'id' => $model->id]) ?>">
                    <i class="fas fa-edit me-1"></i> Edit
                </a>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-light" href="<?= Url::to([Yii::$app->controller->id === 'po-dashboard' ? '/po-dashboard/index' : '/po/index']) ?>">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- PO Info -->
    <div class="po-v-section">
        <div class="head">
            <span class="po-v-icon" style="background:#0ea5e9;"><i class="fas fa-id-card"></i></span>
            <h5>PO Info</h5>
        </div>
        <div class="row">
            <div class="col-md-4 po-v-field">
                <span class="lbl">Client</span>
                <span class="val <?= empty($client->client_name) ? 'muted' : '' ?>"><?= Html::encode($client->client_name ?? '—') ?></span>
            </div>
            <div class="col-md-4 po-v-field">
                <span class="lbl">Company</span>
                <span class="val <?= empty($client->company_name) ? 'muted' : '' ?>"><?= Html::encode($client->company_name ?? '—') ?></span>
            </div>
            <div class="col-md-4 po-v-field">
                <span class="lbl">PO In-charge</span>
                <span class="val <?= empty($incharge) ? 'muted' : '' ?>"><?= Html::encode($incharge->username ?? '—') ?></span>
            </div>
            <div class="col-md-4 po-v-field">
                <span class="lbl">Value (₹)</span>
                <span class="val"><?= $fmtMoney($model->po_cost) ?></span>
            </div>
            <div class="col-md-4 po-v-field">
                <span class="lbl">Delivery Date</span>
                <span class="val <?= empty($model->delivery_date) ? 'muted' : '' ?>"><?= $fmtDate($model->delivery_date) ?></span>
            </div>
            <div class="col-md-4 po-v-field">
                <span class="lbl">Accountant</span>
                <span class="val <?= empty($model->po_received_by) ? 'muted' : '' ?>"><?= Html::encode($model->po_received_by ?: '—') ?></span>
            </div>
            <div class="col-12 po-v-field">
                <span class="lbl">Particulars</span>
                <span class="val"><?= nl2br(Html::encode($model->particulars ?: '—')) ?></span>
            </div>
            <?php if (!empty($model->reason)): ?>
            <div class="col-12 po-v-field">
                <span class="lbl">Cost Reduction Reason</span>
                <span class="val"><?= nl2br(Html::encode($model->reason)) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoices -->
    <div class="po-v-section">
        <div class="head">
            <span class="po-v-icon" style="background:#185fa5;"><i class="fas fa-file-invoice"></i></span>
            <h5>Invoices</h5>
            <span class="ms-auto small text-muted"><?= count($invoices) ?> invoice(s)</span>
        </div>
        <?php if (empty($invoices)): ?>
            <div class="text-muted small">No invoices raised yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="po-v-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $i => $inv): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><strong><?= Html::encode($inv->invoice_number) ?></strong></td>
                            <td><?= $fmtDate($inv->invoice_date) ?></td>
                            <td>
                                <?php if (!empty($inv->invoice_file)): ?>
                                    <a target="_blank" class="btn btn-sm btn-outline-primary"
                                       href="<?= Url::to(['/po-dashboard/download-invoice', 'id' => $inv->id]) ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payments -->
    <div class="po-v-section">
        <div class="head">
            <span class="po-v-icon" style="background:#10b981;"><i class="fas fa-money-bill-wave"></i></span>
            <h5>Payment Ledger</h5>
            <span class="ms-auto small text-muted"><?= count($payments) ?> entry(ies)</span>
        </div>

        <div class="po-v-summary">
            <div class="po-v-stat">
                <div class="lbl">PO Value</div>
                <div class="val"><?= $fmtMoney($model->po_cost) ?></div>
            </div>
            <div class="po-v-stat">
                <div class="lbl">Received</div>
                <div class="val" style="color:#16a34a;"><?= $fmtMoney($totalPaid) ?></div>
            </div>
            <div class="po-v-stat">
                <div class="lbl">Balance</div>
                <div class="val" style="color:<?= $balance > 0 ? '#dc2626' : '#16a34a' ?>;"><?= $fmtMoney($balance) ?></div>
            </div>
        </div>

        <?php if (empty($payments)): ?>
            <div class="text-muted small">No receipts recorded yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="po-v-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Date</th>
                            <th class="text-end">Amount (₹)</th>
                            <th>Mode</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $i => $p): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><?= $fmtDate($p->payment_date) ?></td>
                            <td class="text-end"><?= number_format((float) $p->amount, 2) ?></td>
                            <td><span class="badge bg-light text-dark"><?= Html::encode($p->getModeLabel()) ?></span></td>
                            <td><?= Html::encode($p->note ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
