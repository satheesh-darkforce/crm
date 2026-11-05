<?php

/** @var yii\web\View $this */
/** @var array $rows */
/** @var array $paidByPo */
/** @var array $invoiceCounts */
/** @var array $inchargeOptions */
/** @var array $stats */

use common\components\UserAccess;
use backend\models\PoDetail;
use backend\models\PoPayment;
use kartik\date\DatePicker;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'PO Dashboard';

$statusOptions = PoDetail::statusOptions();
$modeOptions   = PoPayment::modeOptions();

$canChangeStatus = UserAccess::checkAccess('po-dashboard/change-status');
$canEdit         = UserAccess::checkAccess('po/update');
$canInvoices     = UserAccess::checkAccess('po-dashboard/add-invoice');
$canPayments     = UserAccess::checkAccess('po-dashboard/add-payment');
$canView         = UserAccess::checkAccess('po-dashboard/view');
$canAdd          = UserAccess::checkAccess('po/create');

$statusBadge = static function (int $s): string {
    $map = [
        PoDetail::STATUS_PENDING     => ['Pending',     'bg-warning text-dark'],
        PoDetail::STATUS_IN_PROGRESS => ['In Progress', 'bg-info text-dark'],
        PoDetail::STATUS_DELIVERED   => ['Delivered',   'bg-success'],
        PoDetail::STATUS_CANCELLED   => ['Cancelled',   'bg-danger'],
    ];
    [$label, $cls] = $map[$s] ?? ['—', 'bg-secondary'];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
};
$fmtDate  = static fn($d) => $d ? date('d M Y', strtotime((string) $d)) : '';
$fmtMoney = static fn($v) => '₹ ' . number_format((float) $v, 2);

$accOptions = [];
$statusCounts = [];
foreach ($statusOptions as $k => $v) { $statusCounts[(int) $k] = 0; }
foreach ($rows as $r) {
    $acc = trim((string) $r['po_received_by']);
    if ($acc !== '' && !in_array($acc, $accOptions, true)) {
        $accOptions[] = $acc;
    }
    $s = (int) $r['po_status'];
    if (isset($statusCounts[$s])) {
        $statusCounts[$s]++;
    }
}
sort($accOptions);
?>
<style>
.pod-wrap          { padding:0; }
.pod-stats-grid    { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:14px; margin-bottom:18px; }
.pod-stat          { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px 16px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04); min-width:0; }
.pod-stat .lbl     { font-size:.72rem; color:#64748b; text-transform:uppercase; letter-spacing:.5px; font-weight:600; }
.pod-stat .val     { font-size:1.4rem; font-weight:700; margin-top:6px; color:#0f172a; word-break:break-word; }
.pod-stat .sub     { font-size:.75rem; color:#94a3b8; margin-top:2px; }
.pod-status-list   { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.pod-status-chip   { display:inline-flex; align-items:center; gap:5px; font-size:.65rem; font-weight:600; padding:3px 8px; border-radius:6px; line-height:1; }
.pod-status-chip strong { font-size:.78rem; font-weight:700; }
.pod-toolbar       { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:14px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04); }
.pod-toolbar input, .pod-toolbar select { font-size:.85rem; }
.pod-search-wrap   { width:260px; max-width:100%; }
.pod-card          { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.04); max-width:100%; }
.pod-table         { width:100%; min-width:1100px; border-collapse:separate; border-spacing:0; font-size:.85rem; table-layout:fixed; }
.pod-table .truncate-cell { overflow:hidden; text-overflow:ellipsis; }

/* Status tabs */
.pod-tabs          { display:flex; gap:4px; border-bottom:1px solid #e2e8f0; margin-bottom:14px; flex-wrap:wrap; }
.pod-tabs a        { padding:8px 16px; font-size:.85rem; font-weight:500; color:#64748b; text-decoration:none; border-bottom:2px solid transparent; transition:all .15s; cursor:pointer; }
.pod-tabs a:hover  { color:#0f172a; }
.pod-tabs a.active { color:var(--app-accent); border-bottom-color:var(--app-accent); font-weight:600; }
.pod-tabs .badge   { font-size:.7rem; padding:2px 7px; margin-left:4px; }

@media (max-width: 1200px) {
    .pod-stats-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media (max-width: 640px) {
    .pod-stats-grid { grid-template-columns:1fr; }
    .pod-search-wrap { width:100%; }
    .pod-toolbar select { flex:1; }
}
.pod-table thead th { background:#f1f5f9; color:#334155; font-weight:600; font-size:.65rem; text-transform:uppercase; letter-spacing:.3px; padding:6px 4px; border-bottom:1px solid #e2e8f0; text-align:left; white-space:nowrap; position:sticky; top:0; overflow:hidden; text-overflow:ellipsis; }
.pod-table tbody td { padding:5px 4px; border-bottom:1px solid #f1f5f9; color:#1e293b; vertical-align:middle; font-size:.76rem; }
.pod-table .status-cell { gap:3px; }
.pod-table .status-cell .badge { font-size:.62rem; padding:.22em .45em; font-weight:600; }
.pod-table .status-cell .btn-icon { width:20px; height:20px; font-size:.65rem; padding:0; }
.pod-table .file-pill { padding:2px 7px; font-size:.7rem; gap:4px; }
@media (max-width: 1500px) {
    .pod-table .file-pill { padding:2px 2px; }
}
.pod-table .act-btn { padding:.1rem .35rem; }
.pod-table tbody tr:hover { background:#f8fafc; }
.pod-table .right { text-align:right; }
.pod-table .nowrap { white-space:nowrap; }
.pod-truncate     { display:block; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.file-pill         { display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; color:#64748b; padding:4px 10px; border-radius:6px; font-size:.75rem; cursor:pointer; border:1px solid #e2e8f0; }
.file-pill.has    { background:rgba(13,148,136,.1); color:var(--app-accent); border-color:rgba(13,148,136,.25); }
.btn-icon         { background:#f8fafc; border:1px solid #e2e8f0; color:#475569; border-radius:6px; width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; font-size:.85rem; }
.btn-icon:hover   { background:#e2e8f0; }
.status-cell      { display:inline-flex; align-items:center; gap:6px; }
.empty-state      { text-align:center; padding:50px 20px; color:#94a3b8; }
.act-btn          { padding:.15rem .45rem; font-size:1rem; line-height:1; }
.pod-scroll       { overflow-x:auto; max-height:65vh; overflow-y:auto; }
.pod-scroll:has(.dropdown-menu.show){ overflow:visible; }
.pod-table .actions-col:has(.dropdown-menu.show){ z-index:2050; position:relative; }
.pod-table .dropdown-menu.show{ z-index:2050; }
</style>

<div class="ui-page pod-wrap">
    <div class="ui-head">
        <div>
            <h1>PO dashboard</h1>
            <div class="ui-sub">Track every purchase order: delivery, invoices and payments.</div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('po/index')): ?><?= Html::a('List view', ['/po/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?><?php endif; ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="pod-stats-grid">
        <div class="pod-stat">
            <div class="lbl">Total POs</div>
            <div class="val" id="pod-total-pos"><?= (int) $stats['total'] ?></div>
            <div class="pod-status-list">
                <span class="pod-status-chip bg-warning text-dark">
                    Pending <strong id="pod-status-count-1"><?= (int) ($statusCounts[1] ?? 0) ?></strong>
                </span>
                <span class="pod-status-chip bg-info text-dark">
                    In Progress <strong id="pod-status-count-2"><?= (int) ($statusCounts[2] ?? 0) ?></strong>
                </span>
                <span class="pod-status-chip bg-success text-white">
                    Delivered <strong id="pod-status-count-3"><?= (int) ($statusCounts[3] ?? 0) ?></strong>
                </span>
                <span class="pod-status-chip bg-danger text-white">
                    Cancelled <strong id="pod-status-count-4"><?= (int) ($statusCounts[4] ?? 0) ?></strong>
                </span>
            </div>
        </div>
        <div class="pod-stat">
            <div class="lbl">Total Value</div>
            <div class="val" id="pod-total-value" style="font-size:1.05rem;"><?= $fmtMoney($stats['totalValue']) ?></div>
            <div class="sub">across all orders</div>
        </div>
        <div class="pod-stat">
            <div class="lbl">Payment Received</div>
            <div class="val" id="pod-total-received" style="font-size:1.05rem; color:#16a34a;"><?= $fmtMoney($stats['totalReceived']) ?></div>
            <div class="sub">collected so far</div>
        </div>
        <div class="pod-stat">
            <div class="lbl">Balance Pending</div>
            <div class="val" id="pod-total-balance" style="font-size:1.05rem; color:#dc2626;"><?= $fmtMoney($stats['totalBalance']) ?></div>
            <div class="sub">yet to be received</div>
        </div>
    </div>

    <!-- Status tabs -->
    <div class="pod-tabs" id="pod-tabs">
        <a data-status="" class="active">All <span class="badge bg-light text-secondary"><?= (int) $stats['total'] ?></span></a>
        <?php foreach ($statusOptions as $k => $v): ?>
            <a data-status="<?= (int) $k ?>"><?= Html::encode($v) ?>
                <span class="badge bg-light text-secondary"><?= (int) ($statusCounts[(int) $k] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="pod-toolbar">
        <div class="pod-search-wrap">
            <input id="pod-search" type="text" class="form-control form-control-sm" placeholder="Search company, PO number, particulars…">
        </div>
        <input type="hidden" id="pod-status" value="">
        <select id="pod-acc" class="form-select form-select-sm" style="width:180px;">
            <option value="">All Accountants</option>
            <?php foreach ($accOptions as $a): ?>
                <option value="<?= Html::encode($a) ?>"><?= Html::encode($a) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="ms-auto small text-muted" id="pod-count">—</div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="pod-export">
            <i class="fas fa-download me-1"></i> Export
        </button>
        <?php if ($canAdd): ?>
            <a href="<?= Url::to(['/po/create']) ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i> Add PO
            </a>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="pod-card">
        <div class="pod-scroll">
            <table class="pod-table" id="pod-table">
                <colgroup>
                    <col style="width:2.5%;">
                    <col style="width:7%;">
                    <col style="width:5.04%;">
                    <col style="width:9.36%;">
                    <col style="width:5.6%;">
                    <col style="width:11.6%;">
                    <col style="width:8%;">
                    <col style="width:7%;">
                    <col style="width:6.3%;">
                    <col style="width:6.6%;">
                    <col style="width:4.4%;">
                    <col style="width:5.6%;">
                    <col style="width:4%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>PO Date</th>
                        <th>PO NO</th>
                        <th>Company</th>
                        <th class="right">Value</th>
                        <th>Particulars</th>
                        <th>Accountant</th>
                        <th>Delivery</th>
                        <th>Status</th>
                        <th>Invoices</th>
                        <th class="right">Received</th>
                        <th class="right">Balance</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="pod-body">
                <?php foreach ($rows as $i => $r):
                    $id      = (int) $r['id'];
                    $paid    = (float) ($paidByPo[$id]      ?? 0);
                    $invCnt  = (int)   ($invoiceCounts[$id] ?? 0);
                    $value   = (float) $r['po_cost'];
                    $balance = $value - $paid;
                    $status  = (int) $r['po_status'];
                ?>
                    <tr data-id="<?= $id ?>"
                        data-status="<?= $status ?>"
                        data-acc="<?= Html::encode((string) $r['po_received_by']) ?>"
                        data-search="<?= Html::encode(strtolower(($r['po_number'] ?? '') . ' ' . ($r['company_name'] ?? '') . ' ' . ($r['particulars'] ?? ''))) ?>">
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="nowrap"><?= $fmtDate($r['date']) ?></td>
                        <td class="truncate-cell" title="<?= Html::encode($r['po_number']) ?>"><strong><?= Html::encode($r['po_number']) ?></strong></td>
                        <td class="truncate-cell" title="<?= Html::encode($r['company_name'] ?? '') ?>"><?= Html::encode($r['company_name'] ?? '—') ?></td>
                        <td class="right nowrap"><?= number_format($value, 0) ?></td>
                        <td class="truncate-cell" title="<?= Html::encode($r['particulars']) ?>"><?= Html::encode($r['particulars']) ?></td>
                        <td class="truncate-cell" title="<?= Html::encode($r['po_received_by']) ?>"><?= Html::encode($r['po_received_by'] ?: '—') ?></td>
                        <td class="nowrap delivery-cell">
                            <?php if (!empty($r['delivery_date'])): ?>
                                <?= $fmtDate($r['delivery_date']) ?>
                            <?php elseif ($canEdit): ?>
                                <a href="javascript:void(0)" class="small text-primary" onclick="podOpenDelivery(<?= (int) $r['id'] ?>)">
                                    <i class="fas fa-plus-circle me-1"></i>Click to add
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-cell">
                                <span class="status-badge"><?= $statusBadge($status) ?></span>
                                <?php if ($canChangeStatus): ?>
                                    <button type="button" class="btn-icon" onclick="podOpenStatus(<?= $id ?>, <?= $status ?>)" title="Change status">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($canInvoices): ?>
                                <span class="file-pill <?= $invCnt ? 'has' : '' ?>" onclick="podOpenInvoices(<?= $id ?>)">
                                    <i class="fas fa-file-invoice"></i>
                                    <span class="inv-count"><?= $invCnt ? $invCnt . ' invoice' . ($invCnt > 1 ? 's' : '') : 'Add' ?></span>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small"><?= $invCnt ?: '—' ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="right nowrap" style="color:#16a34a;"><?= $paid ? number_format($paid, 0) : '—' ?></td>
                        <td class="right nowrap" style="color:<?= $balance > 0 ? '#dc2626' : '#16a34a' ?>;"><?= number_format($balance, 0) ?></td>
                        <td class="text-center actions-col">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn"
                                        type="button" data-bs-toggle="dropdown"
                                        aria-expanded="false" aria-label="Actions">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem; min-width:170px;">
                                    <?php if ($canView): ?>
                                        <li><a class="dropdown-item" href="<?= Url::to(['view', 'id' => $id]) ?>"><i class="fas fa-eye me-2 text-primary"></i>View</a></li>
                                    <?php endif; ?>
                                    <?php if ($canPayments): ?>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="podOpenPayments(<?= $id ?>)"><i class="fas fa-money-bill-wave me-2 text-success"></i>Payment Ledger</a></li>
                                    <?php endif; ?>
                                    <?php if ($canInvoices): ?>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="podOpenInvoices(<?= $id ?>)"><i class="fas fa-file-invoice me-2" style="color:#185fa5;"></i>Invoices</a></li>
                                    <?php endif; ?>
                                    <?php if ($canEdit): ?>
                                        <li><a class="dropdown-item" href="<?= Url::to(['/po/update', 'id' => $id]) ?>"><i class="fas fa-edit me-2 text-warning"></i>Edit</a></li>
                                    <?php endif; ?>
                                    <?php if (!empty($r['file'])): ?>
                                        <li><a class="dropdown-item" target="_blank" download href="<?= \backend\controllers\Controller::uploadUrl($r['file']) ?>"><i class="fas fa-file-download me-2" style="color:#dc2626;"></i>Download File</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="13"><div class="empty-state"><i class="fas fa-file-invoice" style="font-size:30px;"></i><p>No purchase orders found</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delivery date modal -->
<div class="modal fade" id="podDeliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Delivery Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Delivery Date <span class="text-danger">*</span></label>
                <?= DatePicker::widget([
                    'name' => 'podDeliveryDate',
                    'options' => ['id' => 'podDeliveryDate', 'class' => 'form-control form-control-sm', 'placeholder' => 'Select date'],
                    'type' => DatePicker::TYPE_INPUT,
                    'pluginOptions' => [
                        'autoclose'      => true,
                        'format'         => 'yyyy-mm-dd',
                        'todayHighlight' => true,
                        'container'      => '#podDeliveryModal',
                    ],
                ]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="podDeliverySave">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Status modal -->
<div class="modal fade" id="podStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select id="podStatusSelect" class="form-select form-select-sm">
                    <?php foreach ($statusOptions as $k => $v): ?>
                        <option value="<?= (int) $k ?>"><?= Html::encode($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="podStatusSave">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Invoices modal -->
<div class="modal fade" id="podInvoicesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoices</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Invoice No <span class="text-danger">*</span></label>
                        <input type="text" id="invNum" class="form-control form-control-sm" placeholder="INV/26-27/001">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Date <span class="text-danger">*</span></label>
                        <?= DatePicker::widget([
                            'name' => 'invDate',
                            'options' => ['id' => 'invDate', 'class' => 'form-control form-control-sm', 'placeholder' => 'Select date'],
                            'type' => DatePicker::TYPE_INPUT,
                            'pluginOptions' => [
                                'autoclose'      => true,
                                'format'         => 'yyyy-mm-dd',
                                'todayHighlight' => true,
                                'endDate'        => '0d',
                                'container'      => '#podInvoicesModal',
                            ],
                        ]) ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">PDF (optional)</label>
                        <input type="file" id="invFile" accept="application/pdf,.pdf" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary btn-sm w-100" id="invAdd">+ Add</button>
                    </div>
                </div>
                <div id="invList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<!-- Payments modal -->
<div class="modal fade" id="podPaymentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Ledger</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="paySummary" class="row g-2 mb-3"></div>
                <div class="row g-2 mb-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small">Date <span class="text-danger">*</span></label>
                        <?= DatePicker::widget([
                            'name' => 'payDate',
                            'options' => ['id' => 'payDate', 'class' => 'form-control form-control-sm', 'placeholder' => 'Select date'],
                            'type' => DatePicker::TYPE_INPUT,
                            'pluginOptions' => [
                                'autoclose'      => true,
                                'format'         => 'yyyy-mm-dd',
                                'todayHighlight' => true,
                                'endDate'        => '0d',
                                'container'      => '#podPaymentsModal',
                            ],
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" id="payAmount" min="0" step="0.01" class="form-control form-control-sm" placeholder="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Mode <span class="text-danger">*</span></label>
                        <select id="payMode" class="form-select form-select-sm">
                            <?php foreach ($modeOptions as $k => $v): ?>
                                <option value="<?= (int) $k ?>"><?= Html::encode($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Note</label>
                        <textarea id="payNote" rows="2" class="form-control form-control-sm" placeholder="e.g. Advance" style="resize:none; height:56px; min-height:56px;"></textarea>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary btn-sm w-100" id="payAdd">+ Add</button>
                    </div>
                </div>
                <div id="payList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<?php
$urls = [
    'changeStatus'   => Url::to(['change-status']),
    'addInvoice'     => Url::to(['add-invoice']),
    'listInvoices'   => Url::to(['list-invoices']),
    'deleteInvoice'  => Url::to(['delete-invoice']),
    'addPayment'     => Url::to(['add-payment']),
    'listPayments'   => Url::to(['list-payments']),
    'deletePayment'  => Url::to(['delete-payment']),
    'export'         => Url::to(['export']),
    'setDelivery'    => Url::to(['set-delivery-date']),
];
$urlsJson = Json::encode($urls);
$csrf     = Yii::$app->request->csrfToken;
$canDeleteInvoice = UserAccess::checkAccess('po-dashboard/delete-invoice') ? 1 : 0;
$canDeletePayment = UserAccess::checkAccess('po-dashboard/delete-payment') ? 1 : 0;
$this->registerJs(<<<JS
var podUrls = $urlsJson;
var podCsrf = '$csrf';
var podCurrentPo  = null;
var podCanDelInv  = $canDeleteInvoice;
var podCanDelPay  = $canDeletePayment;
var podStatusBadgeMap = {
    1: '<span class="badge bg-warning text-dark">Pending</span>',
    2: '<span class="badge bg-info text-dark">In Progress</span>',
    3: '<span class="badge bg-success">Delivered</span>',
    4: '<span class="badge bg-danger">Cancelled</span>'
};

function podFmtMoney(v) { return '₹ ' + (Number(v) || 0).toFixed(2); }
function podToast(msg, ok) {
    if (window.Swal) Swal.fire({toast:true, position:'top-end', icon: ok ? 'success' : 'error', title:msg, timer:1500, showConfirmButton:false});
}

// ── Filter ──────────────────────────────────────
function podFilter() {
    var q  = (jQuery('#pod-search').val() || '').toLowerCase().trim();
    var st = jQuery('#pod-status').val();
    var ac = jQuery('#pod-acc').val();
    var shown = 0;
    jQuery('#pod-body tr[data-id]').each(function () {
        var t = jQuery(this);
        var ok = true;
        if (q  && t.data('search').indexOf(q) === -1) ok = false;
        if (ok && st && String(t.data('status')) !== String(st)) ok = false;
        if (ok && ac && String(t.data('acc')) !== String(ac)) ok = false;
        t.toggle(ok);
        if (ok) shown++;
    });
    jQuery('#pod-count').text(shown + ' of ' + jQuery('#pod-body tr[data-id]').length);
}
function podResetTabs() {
    jQuery('#pod-tabs a').removeClass('active');
    jQuery('#pod-tabs a[data-status=""]').addClass('active');
    jQuery('#pod-status').val('');
}

jQuery('#pod-search').on('input', function () {
    // Any free-text search resets the status tab to "All" per spec.
    podResetTabs();
    podFilter();
});
jQuery('#pod-acc').on('change', function () {
    // Accountant filter also resets the tab to "All".
    podResetTabs();
    podFilter();
});
jQuery('#pod-tabs').on('click', 'a', function (e) {
    e.preventDefault();
    var st = jQuery(this).data('status');
    jQuery('#pod-tabs a').removeClass('active');
    jQuery(this).addClass('active');
    jQuery('#pod-status').val(st === undefined || st === null ? '' : String(st));
    podFilter();
});
podFilter();

// ── Export XLSX ─────────────────────────────────
// Forwards the currently-active filters (tab status, search text, accountant)
// to the server which formats the matching rows as an .xlsx file.
jQuery('#pod-export').on('click', function () {
    var params = {
        status: jQuery('#pod-status').val() || '',
        search: jQuery('#pod-search').val() || '',
        acc:    jQuery('#pod-acc').val()    || ''
    };
    var qs = jQuery.param(params);
    window.location.href = podUrls.export + (podUrls.export.indexOf('?') === -1 ? '?' : '&') + qs;
});

// ── Status modal ────────────────────────────────
function podOpenStatus(id, current) {
    podCurrentPo = id;
    jQuery('#podStatusSelect').val(current);
    new bootstrap.Modal(document.getElementById('podStatusModal')).show();
}
window.podOpenStatus = podOpenStatus;

function podParseNum(txt) {
    return parseFloat(String(txt || '').replace(/[^0-9.\-]/g, '')) || 0;
}

function podRecomputeStats() {
    var counts = {1:0, 2:0, 3:0, 4:0};
    var total = 0;
    var totalValue = 0, totalReceived = 0, totalBalance = 0;
    jQuery('#pod-body tr[data-id]').each(function () {
        var \$r = jQuery(this);
        var s   = parseInt(\$r.data('status'), 10);
        counts[s] = (counts[s] || 0) + 1;
        total++;
        totalValue    += podParseNum(\$r.find('td').eq(4).text());
        totalReceived += podParseNum(\$r.find('td').eq(10).text());
        totalBalance  += podParseNum(\$r.find('td').eq(11).text());
    });
    // Tab badges
    jQuery('#pod-tabs a').each(function () {
        var key = jQuery(this).data('status');
        var n   = key === '' || key === undefined ? total : (counts[String(key)] || 0);
        jQuery(this).find('.badge').text(n);
    });
    // Total POs card
    jQuery('#pod-total-pos').text(total);
    for (var k = 1; k <= 4; k++) {
        jQuery('#pod-status-count-' + k).text(counts[k] || 0);
    }
    // Money cards
    jQuery('#pod-total-value').text(podFmtMoney(totalValue));
    jQuery('#pod-total-received').text(podFmtMoney(totalReceived));
    jQuery('#pod-total-balance').text(podFmtMoney(totalBalance));
}
// Back-compat alias for any legacy callers.
function podRecomputeTabCounts() { podRecomputeStats(); }

function podOpenDelivery(id) {
    podCurrentPo = id;
    jQuery('#podDeliveryDate').val('');
    new bootstrap.Modal(document.getElementById('podDeliveryModal')).show();
}
window.podOpenDelivery = podOpenDelivery;

jQuery('#podDeliverySave').on('click', function () {
    var date = jQuery('#podDeliveryDate').val();
    if (!date) { podToast('Please pick a date', false); return; }
    jQuery.post(podUrls.setDelivery, {id: podCurrentPo, delivery_date: date, _csrf: podCsrf}, function (r) {
        if (r && r.success) {
            var \$row  = jQuery('#pod-body tr[data-id="' + podCurrentPo + '"]');
            var \$cell = \$row.find('.delivery-cell');
            \$cell.html(r.delivery_date);
            bootstrap.Modal.getInstance(document.getElementById('podDeliveryModal')).hide();
            podToast('Delivery date saved', true);
        } else {
            podToast((r && r.message) || 'Failed to save', false);
        }
    }, 'json');
});

jQuery('#podStatusSave').on('click', function () {
    var status = jQuery('#podStatusSelect').val();
    jQuery.post(podUrls.changeStatus, {id: podCurrentPo, status: status, _csrf: podCsrf}, function (r) {
        if (r && r.success) {
            var \$row = jQuery('#pod-body tr[data-id="' + podCurrentPo + '"]');
            \$row.attr('data-status', status).data('status', status);
            \$row.find('.status-badge').html(podStatusBadgeMap[status] || '');
            \$row.find('.btn-icon[title="Change status"]').attr('onclick', 'podOpenStatus(' + podCurrentPo + ',' + status + ')');
            bootstrap.Modal.getInstance(document.getElementById('podStatusModal')).hide();
            podToast('Status updated', true);
            // Update tab badges + Total POs card + money totals to reflect
            // the new state, then re-apply the active filter so the row leaves
            // the view if it no longer matches the currently selected tab.
            podRecomputeStats();
            podFilter();
        } else {
            podToast((r && r.message) || 'Failed', false);
        }
    }, 'json');
});

// ── Invoices ────────────────────────────────────
function podOpenInvoices(id) {
    podCurrentPo = id;
    jQuery('#invNum, #invDate').val('');
    jQuery('#invFile').val('');
    jQuery('#invList').html('<p class="text-muted small text-center py-3">Loading…</p>');
    new bootstrap.Modal(document.getElementById('podInvoicesModal')).show();
    podRefreshInvoices();
}
window.podOpenInvoices = podOpenInvoices;

function podRefreshInvoices() {
    jQuery.getJSON(podUrls.listInvoices, {po_id: podCurrentPo}, function (r) {
        if (!r || !r.success) return;
        var rowEl = jQuery('#pod-body tr[data-id="' + podCurrentPo + '"]');
        rowEl.find('.inv-count').text(r.items.length ? r.items.length + ' invoice' + (r.items.length > 1 ? 's' : '') : 'Add');
        rowEl.find('.file-pill').toggleClass('has', r.items.length > 0);
        if (!r.items.length) {
            jQuery('#invList').html('<p class="text-muted small text-center py-3">No invoices raised yet</p>');
            return;
        }
        var html = '<table class="table table-sm"><thead><tr><th>#</th><th>Invoice No</th><th>Date</th><th>File</th><th></th></tr></thead><tbody>';
        for (var i = 0; i < r.items.length; i++) {
            var it = r.items[i];
            html += '<tr><td>' + (i+1) + '</td><td><strong>' + jQuery('<div>').text(it.invoice_number).html() + '</strong></td><td>' + it.invoice_date + '</td><td>';
            html += it.has_file ? '<a class="btn btn-sm btn-outline-primary" target="_blank" href="' + it.view_url + '"><i class="fas fa-eye"></i></a>' : '<span class="text-muted small">—</span>';
            html += '</td><td>';
            if (podCanDelInv) html += '<button class="btn btn-sm btn-outline-danger" onclick="podDeleteInvoice(' + it.id + ')"><i class="fas fa-trash"></i></button>';
            html += '</td></tr>';
        }
        html += '</tbody></table>';
        jQuery('#invList').html(html);
    });
}

jQuery('#invAdd').on('click', function () {
    var num  = (jQuery('#invNum').val() || '').trim();
    var date = jQuery('#invDate').val();
    var file = jQuery('#invFile')[0].files[0];
    if (!num)  { podToast('Enter invoice number', false); return; }
    if (!date) { podToast('Enter invoice date', false); return; }
    if (date > new Date().toISOString().slice(0, 10)) { podToast('Invoice date cannot be in the future', false); return; }
    var fd = new FormData();
    fd.append('po_id', podCurrentPo);
    fd.append('invoice_number', num);
    fd.append('invoice_date', date);
    if (file) fd.append('invoice_file', file);
    fd.append('_csrf', podCsrf);
    jQuery.ajax({
        url: podUrls.addInvoice, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
        success: function (r) {
            if (r && r.success) {
                podToast('Invoice added', true);
                jQuery('#invNum, #invDate').val(''); jQuery('#invFile').val('');
                podRefreshInvoices();
            } else { podToast((r && r.message) || 'Failed', false); }
        }
    });
});

window.podDeleteInvoice = function (id) {
    var doDelete = function () {
        jQuery.post(podUrls.deleteInvoice, {id:id, _csrf:podCsrf}, function (r) {
            if (r && r.success) { podToast('Invoice removed', true); podRefreshInvoices(); }
            else { podToast((r && r.message) || 'Failed to delete invoice', false); }
        }, 'json');
    };
    if (window.Swal) {
        Swal.fire({
            icon: 'warning',
            title: 'Delete this invoice?',
            text: 'This invoice will be removed.',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc2626'
        }).then(function (r) { if (r.isConfirmed) doDelete(); });
    } else if (confirm('Delete this invoice?')) {
        doDelete();
    }
};

// ── Payments ────────────────────────────────────
function podOpenPayments(id) {
    podCurrentPo = id;
    jQuery('#payDate, #payAmount, #payNote').val('');
    jQuery('#payList').html('<p class="text-muted small text-center py-3">Loading…</p>');
    new bootstrap.Modal(document.getElementById('podPaymentsModal')).show();
    podRefreshPayments();
}
window.podOpenPayments = podOpenPayments;

function podRefreshPayments() {
    var \$row    = jQuery('#pod-body tr[data-id="' + podCurrentPo + '"]');
    var poValue = parseFloat((\$row.find('td').eq(4).text() || '0').replace(/,/g,'')) || 0;
    jQuery.getJSON(podUrls.listPayments, {po_id: podCurrentPo}, function (r) {
        if (!r || !r.success) return;
        var received = 0;
        for (var i = 0; i < r.items.length; i++) received += parseFloat(r.items[i].amount) || 0;
        var balance = poValue - received;
        jQuery('#paySummary').html(
            '<div class="col-4"><div class="pod-stat"><div class="lbl">PO Value</div><div class="val" style="font-size:1rem;">' + podFmtMoney(poValue) + '</div></div></div>' +
            '<div class="col-4"><div class="pod-stat"><div class="lbl">Received</div><div class="val" style="font-size:1rem;color:#16a34a;">' + podFmtMoney(received) + '</div></div></div>' +
            '<div class="col-4"><div class="pod-stat"><div class="lbl">Balance</div><div class="val" style="font-size:1rem;color:' + (balance > 0 ? '#dc2626':'#16a34a') + ';">' + podFmtMoney(balance) + '</div></div></div>'
        );
        // Update the row's Received and Balance columns.
        \$row.find('td').eq(10).text(received ? Math.round(received).toLocaleString() : '—').css('color','#16a34a');
        \$row.find('td').eq(11).text(balance.toFixed(2)).css('color', balance > 0 ? '#dc2626':'#16a34a');
        // Reflect the new received/balance in the top stats cards immediately.
        podRecomputeStats();

        if (!r.items.length) {
            jQuery('#payList').html('<p class="text-muted small text-center py-3">No receipts recorded yet</p>');
            return;
        }
        var html = '<table class="table table-sm"><thead><tr><th>#</th><th>Date</th><th class="text-end">Amount (₹)</th><th>Mode</th><th>Note</th><th></th></tr></thead><tbody>';
        for (var j = 0; j < r.items.length; j++) {
            var p = r.items[j];
            html += '<tr><td>' + (j+1) + '</td><td>' + p.payment_date + '</td><td class="text-end" style="color:#16a34a;">' + Number(p.amount).toFixed(2) + '</td><td><span class="badge bg-light text-dark">' + jQuery('<div>').text(p.mode_label).html() + '</span></td><td>' + jQuery('<div>').text(p.note || '—').html() + '</td><td>';
            if (podCanDelPay) html += '<button class="btn btn-sm btn-outline-danger" onclick="podDeletePayment(' + p.id + ')"><i class="fas fa-trash"></i></button>';
            html += '</td></tr>';
        }
        html += '</tbody></table>';
        jQuery('#payList').html(html);
    });
}

jQuery('#payAdd').on('click', function () {
    var date   = jQuery('#payDate').val();
    var amount = jQuery('#payAmount').val();
    var mode   = jQuery('#payMode').val();
    var note   = jQuery('#payNote').val();
    if (!date)   { podToast('Enter payment date', false); return; }
    if (date > new Date().toISOString().slice(0, 10)) { podToast('Payment date cannot be in the future', false); return; }
    if (!amount || Number(amount) <= 0) { podToast('Enter a valid amount', false); return; }
    if (!mode)   { podToast('Pick a payment mode', false); return; }
    jQuery.post(podUrls.addPayment, {
        po_id: podCurrentPo, payment_date: date, amount: amount, mode: mode, note: note, _csrf: podCsrf
    }, function (r) {
        if (r && r.success) {
            podToast('Payment recorded', true);
            jQuery('#payDate, #payAmount, #payNote').val('');
            podRefreshPayments();
        } else { podToast((r && r.message) || 'Failed', false); }
    }, 'json');
});

window.podDeletePayment = function (id) {
    var doDelete = function () {
        jQuery.post(podUrls.deletePayment, {id:id, _csrf:podCsrf}, function (r) {
            if (r && r.success) { podToast('Payment removed', true); podRefreshPayments(); }
            else { podToast((r && r.message) || 'Failed to remove payment', false); }
        }, 'json');
    };
    if (window.Swal) {
        Swal.fire({
            icon: 'warning',
            title: 'Remove this receipt?',
            text: 'This payment entry will be removed from the ledger.',
            showCancelButton: true,
            confirmButtonText: 'Remove',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc2626'
        }).then(function (r) { if (r.isConfirmed) doDelete(); });
    } else if (confirm('Remove this receipt?')) {
        doDelete();
    }
};
JS);
?>
