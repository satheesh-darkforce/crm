<?php
/** @var \yii\web\View $this */
/** @var \backend\models\InwardInvoice $model */

use backend\models\InwardInvoice;
use backend\models\Vendor;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Inward Invoice ' . $model->invoice_number;

$lines    = $model->lineItems;
$expenses = $model->expenses;
$extra    = array_sum(array_map(fn($e) => (float)$e->item_total, $expenses));
$inr      = fn($v) => '₹' . number_format((float)$v, 2);
$isProv   = (int)$model->invoice_role === InwardInvoice::ROLE_PROVISIONAL;
$isPaid   = (int)$model->payment_status === InwardInvoice::PAY_PAID && $model->payment_status !== null;
$vendor   = $model->vendor;
$taxCols  = array_filter(['sgst' => 'SGST', 'cgst' => 'CGST', 'igst' => 'IGST'], fn($k) => (float)$model->{$k . '_amount'} > 0
    || array_filter($lines, fn($d) => (float)$d->{$k . '_amount'} > 0), ARRAY_FILTER_USE_KEY);
?>
<div class="ui-page inward-invoice-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->invoice_number) ?>
                <span class="ui-pill tone-<?= $isProv ? 'warning' : 'success' ?> align-middle"><?= InwardInvoice::roleOptions()[(int)$model->invoice_role] ?? '—' ?></span>
                <span class="ui-pill tone-<?= $model->getPaymentTone() ?> align-middle"><?= Html::encode($model->getPaymentLabel()) ?></span>
            </h1>
            <div class="ui-sub"><?= Html::encode($vendor->company_name ?? 'Vendor removed') ?> · <?= $model->invoice_date ? date('d M Y', strtotime($model->invoice_date)) : '—' ?></div>
        </div>
        <div class="ui-actions">
            <?php if ($isProv && UserAccess::checkAccess('inward-invoice/convert-to-invoice')): ?>
                <button type="button" class="btn btn-outline-success btn-sm" data-ii-convert="<?= $model->id ?>" data-url="<?= Url::to(['convert-to-invoice', 'id' => $model->id]) ?>">
                    <i class="fas fa-check-double me-1"></i>Mark actual</button>
            <?php endif; ?>
            <?php if (UserAccess::checkAccess('inward-invoice/update')): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if (UserAccess::checkAccess('inward-invoice/delete')): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">&#8942;</button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><?= Html::a('<i class="fas fa-trash fa-fw me-1"></i>Delete', ['delete', 'id' => $model->id], ['class' => 'dropdown-item text-danger', 'encode' => false,
                            'data' => ['method' => 'post', 'confirm' => 'Delete inward invoice ' . $model->invoice_number . ' (' . $inr($model->net_total) . ')?']]) ?></li>
                    </ul>
                </div>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="ui-tiles">
        <div class="ui-tile is-accent"><div class="t-label">Net total</div><div class="t-value num"><?= $inr($model->net_total) ?></div>
            <div class="t-sub">Sub total <?= $inr($model->grand_total) ?></div></div>
        <div class="ui-tile"><div class="t-label">GST</div><div class="t-value num"><?= $inr((float)$model->sgst_amount + (float)$model->cgst_amount + (float)$model->igst_amount) ?></div>
            <div class="t-sub"><?= $taxCols ? implode(' + ', $taxCols) : 'No GST' ?></div></div>
        <div class="ui-tile"><div class="t-label">Paid</div><div class="t-value num"><?= $isPaid ? $inr($model->payment_amount) : '—' ?></div>
            <div class="t-sub"><?= $isPaid ? ($model->payment_date ? date('d M Y', strtotime($model->payment_date)) : 'Date not set') . ((float)$model->tds_amount ? ' · TDS ' . $inr($model->tds_amount) : '') : Html::encode($model->getPaymentLabel()) ?></div></div>
        <div class="ui-tile"><div class="t-label">Vendor</div><div class="t-value"><?= Html::encode($vendor->company_name ?? '—') ?></div>
            <div class="t-sub"><?= Html::encode(Vendor::typeLabels()[(int)($vendor->vendor_type ?? 0)] ?? '') ?><?= !empty($vendor->gst_no) ? ' · ' . Html::encode($vendor->gst_no) : '' ?></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Items <span class="ui-section-meta"><?= count($lines) ?></span></div>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:36px">#</th>
                    <th>Item</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Amount</th>
                    <?php foreach ($taxCols as $label): ?><th class="text-end"><?= $label ?></th><?php endforeach; ?>
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$lines): ?>
                    <tr class="ui-empty-row"><td colspan="<?= 6 + count($taxCols) ?>" class="ui-empty-cell"><?= $this->render('@backend/views/_ui/_empty', ['icon' => 'list', 'title' => 'No items', 'small' => true]) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($lines as $i => $d): ?>
                    <tr>
                        <td class="cell-num muted"><?= $i + 1 ?></td>
                        <td class="cell-title"><?= Html::encode($d->product) ?>
                            <div class="small text-muted fw-normal"><?= implode(' · ', array_filter([$d->hsn ? 'HSN ' . Html::encode($d->hsn) : '', Html::encode($d->ledger->title ?? '')])) ?></div></td>
                        <td class="text-end num" data-label="Qty"><?= (float)$d->qty ?></td>
                        <td class="text-end num" data-label="Rate"><?= $inr($d->price_per_qty) ?></td>
                        <td class="text-end num" data-label="Amount"><?= $inr($d->item_total) ?></td>
                        <?php foreach ($taxCols as $k => $label): ?>
                            <td class="text-end num small" data-label="<?= $label ?>"><?= (float)$d->{$k . '_amount'} ? $inr($d->{$k . '_amount'}) . ' <span class="text-muted">(' . (float)$d->{$k . '_percent'} . '%)</span>' : '—' ?></td>
                        <?php endforeach; ?>
                        <td class="text-end num fw-semibold" data-label="Total"><?= $inr($d->total_amount) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <?php if ($expenses): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header ui-section">Extra expenses</div>
                <table class="table ui-detail mb-0">
                    <?php foreach ($expenses as $ex): ?>
                        <tr><th scope="row"><?= Html::encode($ex->product) ?><div class="small text-muted fw-normal"><?= Html::encode($ex->ledger->title ?? '') ?></div></th><td class="text-end num"><?= $inr($ex->item_total) ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header ui-section">Payment</div>
                <table class="table ui-detail mb-0">
                    <tr><th scope="row">Status</th><td><span class="ui-pill tone-<?= $model->getPaymentTone() ?>"><?= Html::encode($model->getPaymentLabel()) ?></span></td></tr>
                    <?php if ($isPaid): ?>
                        <tr><th scope="row">Paid from</th><td><?= Html::encode(trim(($model->paymentCategory->title ?? '') . ' · ' . ($model->paymentLedger->title ?? ''), ' ·') ?: '—') ?></td></tr>
                        <tr><th scope="row">Payment date</th><td><?= $model->payment_date ? date('d M Y', strtotime($model->payment_date)) : '—' ?></td></tr>
                        <tr><th scope="row">Amount paid</th><td class="num"><?= $inr($model->payment_amount) ?></td></tr>
                        <tr><th scope="row">TDS deducted</th><td class="num"><?= (float)$model->tds_amount ? $inr($model->tds_amount) : '—' ?></td></tr>
                    <?php endif; ?>
                    <tr><th scope="row">Remarks</th><td><?= trim((string)$model->payment_remarks) !== '' ? nl2br(Html::encode($model->payment_remarks)) : '<span class="text-muted">—</span>' ?></td></tr>
                    <?php if ($model->title): ?><tr><th scope="row">Title</th><td><?= Html::encode($model->title) ?></td></tr><?php endif; ?>
                </table>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header ui-section">Attachments</div>
                <div class="card-body">
                    <?php if (!$model->attachments): ?>
                        <span class="small text-muted">No files attached.</span>
                    <?php endif; ?>
                    <?php foreach ($model->attachments as $k => $att): ?>
                        <div class="ui-file mb-1"><i class="fas fa-paperclip text-muted me-1" aria-hidden="true"></i>
                            <?= Html::a('Attachment ' . ($k + 1) . ' (' . strtoupper(pathinfo($att->path, PATHINFO_EXTENSION)) . ')', Url::to('@web' . $att->path), ['target' => '_blank', 'rel' => 'noopener']) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header ui-section">Totals</div>
                <div class="card-body">
                    <dl class="inv-totals mb-0">
                        <dt>Sub total</dt><dd class="num"><?= $inr($model->grand_total) ?></dd>
                        <?php foreach ($taxCols as $k => $label): ?><dt><?= $label ?></dt><dd class="num"><?= $inr($model->{$k . '_amount'}) ?></dd><?php endforeach; ?>
                        <?php if ($expenses): ?><dt>Extra expenses</dt><dd class="num"><?= $inr($extra) ?></dd><?php endif; ?>
                        <?php if ((float)$model->round_off): ?><dt>Round off</dt><dd class="num"><?= $inr($model->round_off) ?></dd><?php endif; ?>
                        <dt class="is-net">Net total</dt><dd class="num is-net"><?= $inr($model->net_total) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
