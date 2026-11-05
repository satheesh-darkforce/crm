<?php

/** @var yii\web\View $this */
/** @var array $model  Inward DC row with eager-loaded client, dcProducts.product */

use common\components\UserAccess;
use backend\models\DcDetail;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Inward DC #' . ($model['inward_dc_no'] ?? $model['id']);

$client = $model['client']     ?? [];
$lines  = $model['dcProducts'] ?? [];

$fmtDate = static fn($d) => !empty($d) ? date('d M Y', strtotime((string) $d)) : '—';

$alreadyReturned = !empty($model['inward_dc_no']) && DcDetail::find()
    ->where(['type' => 1, 'inward_dc_no' => $model['inward_dc_no']])
    ->andWhere(['!=', 'status', 0])
    ->exists();
?>

<style>
.dc-view-wrap      { padding: 0; }
.dc-hero           { background: linear-gradient(135deg, #0f766e 0%, #b45309 160%); color: #fff; padding: 24px 28px; border-radius: 14px; box-shadow: none; margin-bottom: 24px; }
.dc-hero h2        { margin: 0 0 4px 0; font-weight: 700; letter-spacing: .3px; }
.dc-hero .hero-sub { opacity: .9; font-size: .9rem; }
.dc-hero .hero-meta{ font-size: .82rem; opacity: .95; margin-top: 10px; }
.dc-hero .btn      { border-radius: 8px; font-weight: 600; }
.dc-hero .btn-outline-light:hover { color: #0f766e; }
.dc-section        { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04); }
.dc-section-head   { display:flex; align-items:center; padding-bottom: 12px; margin-bottom: 18px; border-bottom: 2px solid #f1f5f9; }
.dc-section-head h5{ margin: 0; font-weight: 700; color: #1e293b; font-size: 1rem; text-transform: uppercase; letter-spacing: .5px; }
.dc-section-icon   { width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #fff; margin-right: 12px; font-size: .95rem; }
.icon-client       { background: #0ea5e9; }
.icon-product      { background: #f59e0b; }
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
</style>

<div class="ui-page dc-view-wrap">
    <div class="dc-hero d-flex flex-wrap justify-content-between align-items-start">
        <div>
            <div class="hero-sub text-uppercase small fw-semibold">Inward Delivery Challan</div>
            <h2>Inward #<?= Html::encode($model['inward_dc_no'] ?? '—') ?></h2>
            <div class="hero-meta">
                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i> <?= $fmtDate($model['dc_date'] ?? null) ?></span>
                <?php if (!empty($model['dc_return_date'])): ?>
                    <span class="me-3"><i class="fas fa-undo me-1"></i> Expected Return: <?= $fmtDate($model['dc_return_date']) ?></span>
                <?php endif; ?>
                <?php if (!empty($model['ewbc_no'])): ?>
                    <span class="ms-3"><i class="fas fa-route me-1"></i> EWAY: <?= Html::encode($model['ewbc_no']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <a href="<?= Url::to(['/dc/inward-index']) ?>" class="btn btn-sm btn-outline-light">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
            <?php if (UserAccess::checkAccess('dc/add-return')): ?>
                <?php if ($alreadyReturned): ?>
                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                        <i class="fas fa-check me-1"></i> Returned
                    </button>
                <?php else: ?>
                    <a href="<?= Url::to(['/dc/add-return', 'id' => $model['id']]) ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-undo me-1"></i> Add Return
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="dc-section">
        <div class="dc-section-head">
            <span class="dc-section-icon icon-client"><i class="fas fa-user"></i></span>
            <h5>Client Details</h5>
        </div>
        <div class="row">
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
                            <td><?= Html::encode($p['hsn_code'] ?? '—') ?></td>
                            <td><?= Html::encode($p['units']    ?? '—') ?></td>
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
</div>
