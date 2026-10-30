<?php

/** @var yii\web\View $this */
/** @var array $model  DC row with eager-loaded client, delivery, documents, dcProducts.product */

use common\components\UserAccess;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Delivery Challan #' . ($model['dc_no'] ?? $model['id']);

$dcTypeId  = (int)($model['dc_type'] ?? 0);
$dcTypeTxt = match ($dcTypeId) {
    1 => 'Returnable',
    2 => 'Sale',
    3 => 'Service',
    default => '—',
};
$status    = (int)($model['status'] ?? 0);
$statusTxt = $status === 2 ? 'Cancelled' : ($status === 3 ? 'Draft' : 'Active');
$statusCls = $status === 2 ? 'bg-danger' : ($status === 3 ? 'bg-warning text-dark' : 'bg-success');

$delMap = [1 => 'Self', 2 => 'Customer Pickup', 3 => 'Courier'];
$delTxt = $delMap[(int)($model['dc_delivery_type'] ?? 0)] ?? '—';

$client   = $model['client']     ?? [];
$delivery = $model['delivery']   ?? [];
$docs     = $model['documents']  ?? [];
$lines    = $model['dcProducts'] ?? [];

$fmtDate = static fn($d) => !empty($d) ? date('d M Y', strtotime((string) $d)) : '—';
?>

<style>
.dc-view-wrap      { background-color: #f8fafc; padding: 28px 20px; border-radius: 15px; }
.dc-hero           { background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%); color: #fff; padding: 24px 28px; border-radius: 14px; box-shadow: 0 10px 25px -10px rgba(59,130,246,.45); margin-bottom: 24px; }
.dc-hero h2        { margin: 0 0 4px 0; font-weight: 700; letter-spacing: .3px; }
.dc-hero .hero-sub { opacity: .9; font-size: .9rem; }
.dc-hero .hero-meta{ font-size: .82rem; opacity: .95; margin-top: 10px; }
.dc-hero .badge    { font-size: .75rem; padding: .45em .75em; letter-spacing: .5px; }
.dc-hero .btn      { border-radius: 8px; font-weight: 600; }
.dc-hero .btn-outline-light:hover { color: #3b82f6; }

.dc-section        { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04); }
.dc-section-head   { display:flex; align-items:center; padding-bottom: 12px; margin-bottom: 18px; border-bottom: 2px solid #f1f5f9; }
.dc-section-head h5{ margin: 0; font-weight: 700; color: #1e293b; font-size: 1rem; text-transform: uppercase; letter-spacing: .5px; }
.dc-section-icon   { width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #fff; margin-right: 12px; font-size: .95rem; }
.icon-client       { background: #0ea5e9; }
.icon-ship         { background: #10b981; }
.icon-product      { background: #f59e0b; }
.icon-doc          { background: #8b5cf6; }

.dc-field          { margin-bottom: 12px; }
.dc-field .lbl     { font-size: .72rem; font-weight: 600; letter-spacing: .6px; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }
.dc-field .val     { font-size: .95rem; color: #0f172a; font-weight: 500; word-break: break-word; }
.dc-field .val.muted { color: #94a3b8; font-weight: 400; }

.dc-table          { width: 100%; border-collapse: separate; border-spacing: 0; }
.dc-table thead th { background: #f1f5f9; color: #334155; font-weight: 600; font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
.dc-table tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: .9rem; color: #1e293b; vertical-align: top; }
.dc-table tbody tr:last-child td { border-bottom: 0; }
.dc-table .serial-cell { white-space: pre-wrap; color: #475569; font-size: .85rem; }
.dc-table .qty-badge   { display: inline-block; min-width: 38px; text-align: center; padding: 3px 10px; border-radius: 999px; background: #ecfeff; color: #0e7490; font-weight: 600; font-size: .85rem; }

.doc-tile          { border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; background: #fafbff; height: 100%; transition: all .2s; }
.doc-tile:hover    { border-color: #8b5cf6; box-shadow: 0 4px 10px -4px rgba(139,92,246,.25); transform: translateY(-1px); }
.doc-tile .doc-title { font-weight: 600; color: #4c1d95; font-size: .85rem; letter-spacing: .5px; text-transform: uppercase; }
.doc-tile .doc-no  { font-size: 1rem; color: #1e293b; font-weight: 600; margin-top: 4px; }
.doc-tile .doc-link a { font-size: .82rem; font-weight: 600; }
.doc-tile.empty    { background: #f8fafc; color: #94a3b8; }
</style>

<div class="dc-view-wrap">

    <!-- Hero header ──────────────────────────────────────────────────── -->
    <div class="dc-hero d-flex flex-wrap justify-content-between align-items-start">
        <div>
            <div class="hero-sub text-uppercase small fw-semibold">Delivery Challan</div>
            <h2>DC #<?= Html::encode($model['dc_no'] ?? '—') ?></h2>
            <div class="hero-meta">
                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i> <?= $fmtDate($model['dc_date'] ?? null) ?></span>
                <span class="me-3"><i class="fas fa-tag me-1"></i> <?= Html::encode($dcTypeTxt) ?></span>
                <span class="me-3"><i class="fas fa-truck me-1"></i> Delivery: <?= Html::encode($delTxt) ?></span>
                <span class="badge <?= $statusCls ?>"><?= Html::encode($statusTxt) ?></span>
                <?php if (!empty($model['ewbc_no'])): ?>
                    <span class="ms-3"><i class="fas fa-route me-1"></i> EWAY: <?= Html::encode($model['ewbc_no']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($status === 2 && !empty($model['cancel_reason'])): ?>
                <div class="mt-2 p-2" style="background:rgba(220,53,69,.12);border:1px solid rgba(220,53,69,.35);border-radius:6px;max-width:640px;">
                    <span style="font-size:.72rem;font-weight:700;letter-spacing:.6px;color:#fff;text-transform:uppercase;">Cancellation Reason</span>
                    <div style="color:#fff;font-size:.9rem;margin-top:2px;"><?= nl2br(Html::encode($model['cancel_reason'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <a href="<?= Yii::$app->request->referrer ?: Url::to(['/quotation-dashboard/index']) ?>" class="btn btn-sm btn-outline-light">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            <?php if (UserAccess::checkAccess('dc/challan-pdf')): ?>
                <a href="<?= Url::to(['/dc/challan-pdf', 'id' => $model['id']]) ?>" target="_blank" class="btn btn-sm btn-light">
                    <i class="fas fa-file-pdf me-1"></i> Download PDF
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Client + Shipping side by side on wide screens ──────────────────── -->
    <div class="row g-3">
        <div class="col-12 col-lg-<?= $delivery ? '6' : '12' ?>">
            <div class="dc-section h-100">
                <div class="dc-section-head">
                    <span class="dc-section-icon icon-client"><i class="fas fa-user"></i></span>
                    <h5>Client Details</h5>
                </div>
                <div class="row">
                    <?php if (!empty($model['inward_dc_no'])): ?>
                        <div class="col-12 dc-field">
                            <span class="lbl">Inward DC No</span>
                            <span class="val fw-bold" style="color:#b45309"><?= Html::encode($model['inward_dc_no']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Client Name</span>
                        <span class="val <?= empty($client['client_name']) ? 'muted' : '' ?>"><?= Html::encode($client['client_name'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Company</span>
                        <span class="val <?= empty($client['company_name']) ? 'muted' : '' ?>"><?= Html::encode($client['company_name'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Phone</span>
                        <span class="val <?= empty($client['phone']) ? 'muted' : '' ?>"><?= Html::encode($client['phone'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">GST No</span>
                        <span class="val <?= empty($client['gst_no']) ? 'muted' : '' ?>"><?= Html::encode($client['gst_no'] ?? '—') ?></span>
                    </div>
                    <div class="col-12 dc-field">
                        <span class="lbl">Address</span>
                        <span class="val <?= empty($client['address']) ? 'muted' : '' ?>"><?= nl2br(Html::encode($client['address'] ?? '—')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($delivery): ?>
        <div class="col-12 col-lg-6">
            <div class="dc-section h-100">
                <div class="dc-section-head">
                    <span class="dc-section-icon icon-ship"><i class="fas fa-truck"></i></span>
                    <h5>Shipping &amp; Logistics</h5>
                </div>
                <div class="row">
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Consignee</span>
                        <span class="val <?= empty($delivery['consern_name']) ? 'muted' : '' ?>"><?= Html::encode($delivery['consern_name'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Place of Supply</span>
                        <span class="val <?= empty($delivery['place']) ? 'muted' : '' ?>"><?= Html::encode($delivery['place'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Vehicle No</span>
                        <span class="val <?= empty($delivery['vehicle_no']) ? 'muted' : '' ?>"><?= Html::encode($delivery['vehicle_no'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Delivery Type</span>
                        <span class="val"><?= Html::encode($delTxt) ?></span>
                    </div>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Contact</span>
                        <span class="val <?= empty($delivery['contact_no']) ? 'muted' : '' ?>"><?= Html::encode($delivery['contact_no'] ?? '—') ?></span>
                    </div>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">GST No</span>
                        <span class="val <?= empty($delivery['gst_no']) ? 'muted' : '' ?>"><?= Html::encode($delivery['gst_no'] ?? '—') ?></span>
                    </div>
                    <div class="col-12 dc-field">
                        <span class="lbl">Address</span>
                        <span class="val <?= empty($delivery['address']) ? 'muted' : '' ?>"><?= nl2br(Html::encode($delivery['address'] ?? '—')) ?></span>
                    </div>
                    <?php if (!empty($model['dc_delivery_description'])): ?>
                        <div class="col-12 dc-field">
                            <span class="lbl">Delivery Description</span>
                            <span class="val"><?= nl2br(Html::encode($model['dc_delivery_description'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php
        $hasReturnInfo = $dcTypeId === 1 && (
            !empty($model['dc_return_date'])
            || !empty($model['dc_returned_date'])
            || !empty($model['dc_return_description'])
        );
    ?>
    <?php if ($hasReturnInfo): ?>
        <div class="dc-section">
            <div class="dc-section-head">
                <span class="dc-section-icon icon-ship"><i class="fas fa-calendar-check"></i></span>
                <h5>Return Details</h5>
                <?php if (!empty($model['dc_returned_date'])): ?>
                    <span class="ms-auto badge bg-success">Returned</span>
                <?php else: ?>
                    <span class="ms-auto badge bg-secondary">Not Returned</span>
                <?php endif; ?>
            </div>
            <div class="row">
                <?php if (!empty($model['dc_return_date'])): ?>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Expected Return</span>
                        <span class="val"><?= $fmtDate($model['dc_return_date']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($model['dc_returned_date'])): ?>
                    <div class="col-md-6 dc-field">
                        <span class="lbl">Returned On</span>
                        <span class="val"><?= $fmtDate($model['dc_returned_date']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($model['dc_return_description'])): ?>
                    <div class="col-12 dc-field">
                        <span class="lbl">Return Description / Remarks</span>
                        <span class="val"><?= nl2br(Html::encode($model['dc_return_description'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Products ──────────────────────────────────────────────────────── -->
    <div class="dc-section">
        <div class="dc-section-head">
            <span class="dc-section-icon icon-product"><i class="fas fa-boxes"></i></span>
            <h5>Products</h5>
            <span class="ms-auto small text-muted">
                <?= count(array_filter($lines, fn($l) => (int)($l['status'] ?? 0) >= 1)) ?> item(s)
            </span>
        </div>
        <div class="table-responsive">
            <table class="dc-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Item Name</th>
                        <th style="width:110px;">HSN</th>
                        <th style="width:90px;">Units</th>
                        <th style="width:90px;">Qty</th>
                        <th>Serial Numbers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lines):
                        $rowNum = 0;
                        foreach ($lines as $line):
                            if ((int)($line['status'] ?? 0) < 1) continue;
                            $rowNum++;
                            $p = $line['product'] ?? []; ?>
                        <tr>
                            <td><?= $rowNum ?></td>
                            <td><strong><?= Html::encode($p['item_name'] ?? '—') ?></strong></td>
                            <td><?= Html::encode($p['hsn_code']  ?? '—') ?></td>
                            <td><?= Html::encode($p['units']     ?? '—') ?></td>
                            <td><span class="qty-badge"><?= (int)($line['quantity'] ?? 0) ?></span></td>
                            <td class="serial-cell"><?= Html::encode($line['serial_nos'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No line items</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Documents ─────────────────────────────────────────────────────── -->
    <?php
    $docMap = [
        'quotation' => ['Quotation', 'fas fa-file-contract'],
        'invoice'   => ['Invoice',   'fas fa-file-invoice'],
        'po'        => ['Purchase Order', 'fas fa-file-alt'],
    ];
    ?>
    <div class="dc-section">
        <div class="dc-section-head">
            <span class="dc-section-icon icon-doc"><i class="fas fa-folder-open"></i></span>
            <h5>Documents</h5>
        </div>
        <div class="row g-3">
            <?php foreach ($docMap as $k => [$label, $icon]):
                $no   = $docs[$k . '_no']   ?? '';
                $path = $docs[$k . '_path'] ?? '';
                $empty = !$no && !$path; ?>
                <div class="col-md-4">
                    <div class="doc-tile <?= $empty ? 'empty' : '' ?>">
                        <div class="doc-title"><i class="<?= $icon ?> me-1"></i> <?= $label ?></div>
                        <?php if ($empty): ?>
                            <div class="doc-no muted text-muted">— Not provided —</div>
                        <?php else: ?>
                            <div class="doc-no"><?= Html::encode($no !== '' ? $no : '—') ?></div>
                            <?php if ($path): ?>
                                <div class="doc-link mt-1">
                                    <a target="_blank" href="<?= Url::base(true) . '/' . ltrim($path, '/') ?>">
                                        <i class="fas fa-download me-1"></i> Download file
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>
