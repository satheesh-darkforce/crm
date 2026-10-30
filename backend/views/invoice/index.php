<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var array $totals */
/** @var bool $isProforma */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use yii\widgets\Pjax;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$isProforma = $isProforma ?? false;
$this->title = $isProforma ? 'Proforma Invoices' : 'Tax Invoices';

$paymentBadge = [0 => 'danger', 1 => 'warning', 2 => 'success'];
$paymentLabel = [0 => 'Unpaid', 1 => 'Partial', 2 => 'Paid'];
// invoice_type values — inherited from client.client_type. Yii1 displays:
//   1 = Inter State, 2 = Intra State, 3 = International (Client::get_client_type).
$typeBadge    = [1 => 'primary', 2 => 'info', 3 => 'secondary'];
$typeLabel    = [1 => 'Inter State', 2 => 'Intra State', 3 => 'International'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$q = Yii::$app->request->get();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem;white-space:nowrap}
.list-table tbody tr:hover{background:#f8f9ff}
.list-table tfoot td{font-size:.82rem;font-weight:600;background:#f8f9ff}
/* The invoice listing has 19–20 columns. The global layout forces
   .table-responsive overflow:visible so action dropdowns don't get clipped;
   override locally so the wide table actually scrolls horizontally on
   smaller screens. */
.invoice-index .table-responsive{overflow-x:auto!important;-webkit-overflow-scrolling:touch}
.invoice-index .table-responsive .list-table{min-width:1080px}
/* Tighten cells so the table fits a laptop viewport (~1366px) without
   horizontal scroll. Invoice-no and company-name are the longest text
   columns, so they get the smallest font + truncation kicks in. */
.invoice-index .list-table td{font-size:.75rem;padding:.35rem .3rem}
.invoice-index .list-table th{font-size:.72rem;padding:.4rem .3rem}
.invoice-index .list-table .inv-no{font-size:.76rem;font-weight:600}
.invoice-index .list-table .inv-company{font-size:.72rem;max-width:110px}
.invoice-index .list-table .inv-title{font-size:.72rem;max-width:160px}
/* Amount columns — small numbers don't need .875rem */
.invoice-index .list-table td.text-end{font-size:.72rem}
/* Pin the Actions column to the right edge so it stays visible while
   the rest of the wide table scrolls horizontally. Backgrounds are made
   solid (otherwise scrolled cells bleed through), and a soft left
   shadow visually separates the pinned column from the scrolling area. */
.invoice-index .list-table .actions-col{position:sticky;right:0;z-index:2;background:#fff;box-shadow:-4px 0 4px -2px rgba(0,0,0,.06)}
.invoice-index .list-table thead .actions-col{background:#f8f9fa;z-index:3}
.invoice-index .list-table tfoot .actions-col{background:#f8f9ff}
.invoice-index .list-table tbody tr:hover .actions-col{background:#f8f9ff}
/* Pin the Status column next to Actions so it stays visible too. */
.invoice-index .list-table .status-col{position:sticky;right:78px;z-index:2;background:#fff;box-shadow:-4px 0 4px -2px rgba(0,0,0,.04)}
.invoice-index .list-table thead .status-col{background:#f8f9fa;z-index:3}
.invoice-index .list-table tfoot .status-col{background:#f8f9ff}
.invoice-index .list-table tbody tr:hover .status-col{background:#f8f9ff}
/* The action dropdown uses Popper strategy `fixed` (set in JS below), so
   the menu becomes position:fixed and escapes the table's overflow. Push
   it above sticky cells / sticky headers so it always renders on top. */
.invoice-index .dropdown-menu.show{z-index:2050}
/* Raise the open cell too so the menu's anchor (the toggle button) is
   above neighbouring sticky cells while the menu is visible. */
.invoice-index .list-table .actions-col:has(.dropdown-menu.show){z-index:2050}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="invoice-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= $isProforma ? 'Proforma Invoices' : 'Tax Invoices' ?></h5>
            <small class="text-muted"><?= $total ?> invoice<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?php /* Hidden for now — these actions are accessible via sidebar submenu
            <?= Html::a('+ Tax Invoice', ['create', 'type' => 1], ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('+ Proforma',    ['create', 'type' => 2], ['class' => 'btn btn-outline-primary btn-sm']) ?>
            <?= Html::a('Proforma List', ['proforma'],            ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('Monthly Report', ['monthly-report'],     ['class' => 'btn btn-outline-info btn-sm']) ?>
            */ ?>
        </div>
    </div>

    <?php Pjax::begin([
        'id'              => 'invoice-list-pjax',
        'timeout'         => 5000,
        'enablePushState' => true,
        // Intercept ONLY pagination links. Other links inside the container —
        // Export (XLSX), Download PDF, Print, action-menu navigations — are
        // file downloads or full navigations that must NOT be Pjax-fetched,
        // otherwise binary responses end up rendered into the DOM.
        // The filter form opts in separately via data-pjax="1".
        'linkSelector'    => '#invoice-list-pjax .pagination a',
        'formSelector'    => '#invoice-filter-form',
    ]); ?>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" id="invoice-filter-form" data-pjax="1" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <?= Select2::widget([
                        'name'          => 'client_id',
                        'value'         => $q['client_id'] ?? '',
                        'data'          => ['' => 'All Clients'] + ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= Select2::widget([
                        'name'          => 'company_name',
                        'value'         => $q['company_name'] ?? '',
                        'data'          => ['' => 'All Companies'] + ArrayHelper::map($clients, 'company_name', 'company_name'),
                        'options'       => ['placeholder' => 'All Companies'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <input type="text" name="invoice_no" value="<?= Html::encode($q['invoice_no'] ?? '') ?>"
                           class="form-control form-control-sm" placeholder="Invoice No.">
                </div>
                <div class="col-md-2">
                    <select name="invoice_category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="1" <?= ($q['invoice_category'] ?? '') == '1' ? 'selected' : '' ?>>Service</option>
                        <option value="2" <?= ($q['invoice_category'] ?? '') == '2' ? 'selected' : '' ?>>Sales</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <select name="payment_status" class="form-select form-select-sm">
                        <option value="">Payment</option>
                        <option value="0" <?= ($q['payment_status'] ?? '') === '0' ? 'selected' : '' ?>>Unpaid</option>
                        <option value="1" <?= ($q['payment_status'] ?? '') === '1' ? 'selected' : '' ?>>Partial</option>
                        <option value="2" <?= ($q['payment_status'] ?? '') === '2' ? 'selected' : '' ?>>Paid</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <select name="invoice_status" class="form-select form-select-sm">
                        <option value="">Status</option>
                        <option value="1" <?= ($q['invoice_status'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= ($q['invoice_status'] ?? '') === '0' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $q['from_date'] ?? '',
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-from-date', 'placeholder' => 'From date', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $q['to_date'] ?? '',
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'filter-to-date', 'placeholder' => 'To date', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                    <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" id="filter-btn">Filter</button>
                    <?php if (array_filter(array_intersect_key($q, array_flip(['client_id','company_name','invoice_no','invoice_category','payment_status','invoice_status','from_date','to_date'])))): ?>
                        <?= Html::a('Clear', [$isProforma ? 'proforma' : 'index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                    <?php
                        // Export preserves currently-applied filters. If none are
                        // set, the controller defaults the date range to the
                        // current calendar month.
                        $exportParams = array_intersect_key(
                            $q,
                            array_flip(['client_id','company_name','invoice_no','invoice_category','payment_status','invoice_status','from_date','to_date'])
                        );
                        $exportUrl = array_merge(
                            ['export', 'role' => $isProforma ? 'proforma' : 'tax'],
                            $exportParams
                        );
                    ?>
                    <?= Html::a(
                        '<i class="bi bi-file-earmark-excel"></i> Export',
                        $exportUrl,
                        [
                            'class' => 'btn btn-success btn-sm ms-1',
                            'title' => 'Export to Excel. With no filter applied, exports the current month.',
                        ]
                    ) ?>
                </div>
            </form>
<!-- Date-range validation is bound by the registerJs(_invoiceListInitDateRange)
     block at the bottom of this file so it survives Pjax swaps. -->

        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Invoice No</th>
                        <th>Company Name</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th class="text-end">Taxable</th>
                        <th class="text-end">SGST</th>
                        <th class="text-end">CGST</th>
                        <th class="text-end">IGST</th>
                        <th class="text-end">Net Total</th>
                        <th class="text-end">TDS Amt</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Pending</th>
                        <th>Payment</th>
                        <th class="status-col">Status</th>
                        <th class="text-center actions-col" style="width:78px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Total columns: # + InvoiceNo + Company + Category +
                // Date + 5 amount cols + TDS Amt + Paid + Pending + Payment +
                // Status + Actions
                $colCount = 16;
                ?>
                <?php if (empty($models)): ?>
                    <tr><td colspan="<?= $colCount ?>" class="text-center text-muted py-5">No invoices found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $inv):
                        $ps  = (int)$inv->payment_status;
                        $bal = max(0, (float)$inv->net_total - (float)$inv->tds_amount - (float)$inv->paid_amount);
                        $isCancelled = (int)$inv->invoice_status === 0;
                    ?>
                    <tr<?= $isCancelled ? ' class="table-light"' : '' ?>>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="inv-no">
                            <a href="<?= Url::to(['view', 'id' => $inv->id]) ?>"
                               class="text-decoration-none text-dark">
                                <?= Html::encode($inv->invoice_no) ?>
                            </a>
                        </td>
                        <?php $companyName = $inv->client->company_name ?? $inv->client->client_name ?? '—'; ?>
                        <td class="inv-company">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                 data-bs-toggle="tooltip" data-bs-placement="top"
                                 title="<?= Html::encode($companyName) ?>">
                                <?= Html::encode($companyName) ?>
                            </div>
                        </td>
                        <?php $invTitle = (string)($inv->title ?? ''); ?>
                        <td class="inv-title">
                            <?php if ($invTitle !== ''): ?>
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                 data-bs-toggle="tooltip" data-bs-placement="top"
                                 title="<?= Html::encode($invTitle) ?>">
                                <?= Html::encode($invTitle) ?>
                            </div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?>
                        </td>
                        <td class="text-end small"><?= number_format((float)$inv->grand_total, 2) ?></td>
                        <td class="text-end small <?= $inv->sgst_amount > 0 ? '' : 'text-muted' ?>">
                            <?= $inv->sgst_amount > 0 ? number_format((float)$inv->sgst_amount, 2) : '—' ?>
                        </td>
                        <td class="text-end small <?= $inv->cgst_amount > 0 ? '' : 'text-muted' ?>">
                            <?= $inv->cgst_amount > 0 ? number_format((float)$inv->cgst_amount, 2) : '—' ?>
                        </td>
                        <td class="text-end small <?= $inv->igst_amount > 0 ? '' : 'text-muted' ?>">
                            <?= $inv->igst_amount > 0 ? number_format((float)$inv->igst_amount, 2) : '—' ?>
                        </td>
                        <td class="text-end fw-semibold small"><?= number_format((float)$inv->net_total, 2) ?></td>
                        <td class="text-end small <?= $inv->tds_amount > 0 ? '' : 'text-muted' ?>">
                            <?= $inv->tds_amount > 0 ? number_format((float)$inv->tds_amount, 2) : '—' ?>
                        </td>
                        <td class="text-end small <?= $inv->paid_amount > 0 ? 'text-success' : 'text-muted' ?>">
                            <?= $inv->paid_amount > 0 ? number_format((float)$inv->paid_amount, 2) : '—' ?>
                        </td>
                        <td class="text-end small <?= $bal > 0.01 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            <?= $bal > 0.01 ? number_format($bal, 2) : '—' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $paymentBadge[$ps] ?? 'secondary' ?>">
                                <?= $paymentLabel[$ps] ?? '—' ?>
                            </span>
                        </td>
                        <td class="status-col">
                            <?php if ($isCancelled): ?>
                                <span class="badge bg-dark" style="font-size:.7rem">Cancelled</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border" style="font-size:.7rem">Active</span>
                            <?php endif; ?>
                        </td>
                        <?php
                            $isProformaInv = (int)$inv->invoice_role === \backend\models\Invoice::ROLE_PROFORMA;
                            $isTaxInv      = (int)$inv->invoice_role === \backend\models\Invoice::ROLE_TAX_INVOICE;
                            $isIntl        = (int)$inv->invoice_type === 3;
                        ?>
                        <td class="text-center actions-col">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown"
                                        aria-expanded="false" aria-label="Actions">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:180px">
                                    <?php if (UserAccess::checkAccess('invoice/view')): ?>
                                    <li><?= Html::a('<i class="bi bi-eye me-2"></i>View', ['view', 'id' => $inv->id], [
                                        'class' => 'dropdown-item', 'target' => '_blank', 'encode' => false,
                                    ]) ?></li>
                                    <?php endif; ?>

                                    <?php if (!$isCancelled && $isProformaInv && UserAccess::checkAccess('invoice/update')): ?>
                                    <li><?= Html::a('<i class="bi bi-pencil me-2"></i>Edit', ['update', 'id' => $inv->id], [
                                        'class' => 'dropdown-item', 'encode' => false,
                                    ]) ?></li>
                                    <?php endif; ?>

                                    <?php if (!$isCancelled && $isProformaInv && UserAccess::checkAccess('invoice/create')):
                                        // GST is only collected for domestic, non-exempt clients.
                                        // International (client_type=3) and tax-exempt clients have no GSTIN.
                                        $convertNeedGst = (empty($inv->client?->gst_no) && !$isIntl
                                            && (int)($inv->client?->tax_exemption ?? 0) !== 1);
                                    ?>
                                    <li>
                                        <a href="#" class="dropdown-item text-primary btn-convert-invoice"
                                           data-id="<?= (int)$inv->id ?>"
                                           data-no="<?= Html::encode($inv->invoice_no ?: '') ?>"
                                           data-needgst="<?= $convertNeedGst ? '1' : '0' ?>"
                                           data-gst="<?= Html::encode($inv->client->gst_no ?? '') ?>">
                                            <i class="bi bi-arrow-repeat me-2"></i>Convert to Invoice
                                        </a>
                                    </li>
                                    <?php endif; ?>


                                    <?php if (!$isCancelled && $isTaxInv && $ps < 2 && UserAccess::checkAccess('invoice/create-receipt')): ?>
                                    <li><?= Html::a('<i class="bi bi-file-earmark-plus me-2"></i>Add Receipt', ['create-receipt', 'invoice_id' => $inv->id], [
                                        'class' => 'dropdown-item', 'encode' => false,
                                    ]) ?></li>
                                    <?php endif; ?>

                                    <?php if (!$isCancelled && $isTaxInv && UserAccess::checkAccess('invoice/receipts')): ?>
                                    <li><?= Html::a('<i class="bi bi-journal-text me-2"></i>Manage Receipts', ['receipts', 'id' => $inv->id], [
                                        'class' => 'dropdown-item', 'encode' => false,
                                    ]) ?></li>
                                    <?php endif; ?>

                                    <?php if (!$isCancelled && !$isIntl && UserAccess::checkAccess('invoice/update-tds')): ?>
                                    <li>
                                        <a href="#" class="dropdown-item tds-trigger"
                                           data-id="<?= $inv->id ?>"
                                           data-no="<?= Html::encode($inv->invoice_no ?: '#' . $inv->id) ?>"
                                           data-taxable="<?= (float)$inv->grand_total ?>"
                                           data-tds-pct="<?= (float)$inv->tds_percent ?>"
                                           data-tds-amt="<?= (float)$inv->tds_amount ?>">
                                            <i class="bi bi-wrench me-2"></i>TDS Adjustment
                                        </a>
                                    </li>
                                    <?php endif; ?>

                                    <?php if (!$isCancelled && $inv->invoice_status == 1 && UserAccess::checkAccess('invoice/cancel')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a href="#" class="dropdown-item text-danger btn-cancel-invoice"
                                           data-id="<?= $inv->id ?>"
                                           data-no="<?= Html::encode($inv->invoice_no ?: '#' . $inv->id) ?>">
                                            <i class="bi bi-slash-circle me-2"></i>Cancel Invoice
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($models)): ?>
                <tfoot>
                    <tr>
                        <?php
                        // Number of leading non-money columns (# + InvoiceNo +
                        // Company + Category + Date)
                        $totalLeadCols = 5;
                        ?>
                        <td colspan="<?= $totalLeadCols ?>" class="text-end text-muted small fw-semibold">Total</td>
                        <td class="text-end small fw-semibold"><?= number_format($totals['grand'], 2) ?></td>
                        <td class="text-end small fw-semibold"><?= number_format($totals['sgst'], 2) ?></td>
                        <td class="text-end small fw-semibold"><?= number_format($totals['cgst'], 2) ?></td>
                        <td class="text-end small fw-semibold"><?= number_format($totals['igst'], 2) ?></td>
                        <td class="text-end small text-primary fw-semibold"><?= number_format($totals['net'], 2) ?></td>
                        <td class="text-end small fw-semibold"><?= number_format($totals['tds'], 2) ?></td>
                        <td class="text-end small text-success fw-semibold"><?= number_format($totals['paid'], 2) ?></td>
                        <td class="text-end small text-danger fw-semibold">
                            <?= number_format(max(0, $totals['net'] - $totals['tds'] - $totals['paid']), 2) ?>
                        </td>
                        <td></td>
                        <td class="status-col"></td>
                        <td class="actions-col"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?>
                of <?= $total ?>
            </small>
            <?= LinkPager::widget([
                'pagination'   => $pagination,
                'options'      => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»',
                'prevPageLabel'  => '‹', 'nextPageLabel'  => '›',
                'maxButtonCount' => 7,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>

    <?php Pjax::end(); ?>
</div>

<!-- Quick Payment Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="receiptForm" action="">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <input type="hidden" name="payment_mode" value="3">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Quick Payment</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Invoice: <strong id="receiptInv"></strong></p>
                    <p class="text-muted small mb-2">Balance: <strong id="receiptBal" class="text-danger"></strong></p>
                    <div class="mb-2">
                        <input type="number" name="receipt_amount" class="form-control form-control-sm"
                               placeholder="Amount (₹)" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-2">
                        <?= \kartik\date\DatePicker::widget([
                            'name'          => 'receipt_date',
                            'value'         => date('Y-m-d'),
                            'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                            'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                            'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Receipt Date', 'required' => true],
                        ]) ?>
                    </div>
                    <div>
                        <input type="text" name="payment_remarks" class="form-control form-control-sm"
                               placeholder="Reference / UTR (optional)">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
var _baseUrl = <?= \yii\helpers\Json::encode(Yii::$app->request->baseUrl) ?>;


document.getElementById('receiptModal')?.addEventListener('show.bs.modal', function(e) {
    var btn = e.relatedTarget;
    document.getElementById('receiptInv').textContent = btn.getAttribute('data-inv');
    document.getElementById('receiptBal').textContent = '₹' + btn.getAttribute('data-balance');
    document.getElementById('receiptForm').action = _baseUrl + '/invoice/add-receipt?id=' + btn.getAttribute('data-id');
});

// TDS trigger — Bootstrap closes the dropdown before the modal fires show.bs.modal,
// so e.relatedTarget is null. Use a direct click handler instead to capture data first.
// Delegated on document so it survives Pjax swaps of the table rows.
document.addEventListener('click', function (e) {
    var el = e.target.closest('.tds-trigger');
    if (!el) return;
    e.preventDefault();
    var taxable = parseFloat(el.getAttribute('data-taxable') || 0);
    document.getElementById('tds-inv-no').textContent = el.getAttribute('data-no');
    document.getElementById('tds-taxable').value      = taxable.toFixed(2);
    document.getElementById('tds-pct').value          = el.getAttribute('data-tds-pct') || '';
    document.getElementById('tds-amt').value          = el.getAttribute('data-tds-amt') || '';
    document.getElementById('tdsForm').action         = _baseUrl + '/invoice/update-tds?id=' + el.getAttribute('data-id');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('tdsModal')).show();
});

// Auto-calculate TDS amount when TDS % is changed.
// Delegated on document so it works regardless of script/modal source order
// and survives Pjax swaps (mirrors the Yii1 calculate_tds() behaviour).
document.addEventListener('input', function (e) {
    if (!e.target || e.target.id !== 'tds-pct') return;
    var taxableEl = document.getElementById('tds-taxable');
    var amtEl     = document.getElementById('tds-amt');
    if (!taxableEl || !amtEl) return;
    var taxable = parseFloat(taxableEl.value || 0);
    var pct     = parseFloat(e.target.value || 0);
    if (!isNaN(taxable) && !isNaN(pct)) {
        amtEl.value = ((taxable * pct) / 100).toFixed(2);
    }
});

// Cancel invoice with SweetAlert2 confirmation (never native confirm).
// Delegated on document so the binding survives Pjax swaps.
document.addEventListener('click', function (e) {
    var el = e.target.closest('.btn-cancel-invoice');
    if (!el) return;
    e.preventDefault();
    var invoiceId = el.getAttribute('data-id');
    var invoiceNo = el.getAttribute('data-no');
    Swal.fire({
        icon: 'warning',
        title: 'Cancel Invoice?',
        text: 'Are you sure you want to cancel invoice ' + invoiceNo + '?',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, cancel it',
        cancelButtonText: 'No, keep it'
    }).then(function(result) {
        if (result.isConfirmed) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = _baseUrl + '/invoice/cancel?id=' + invoiceId;
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
</script>

<!-- TDS Adjustment Modal -->
<div class="modal fade" id="tdsModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="tdsForm" action="">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <input type="hidden" name="_from" value="<?= $isProforma ? 'proforma' : 'index' ?>">
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold"><i class="fas fa-wrench"></i> TDS Adjustment</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label form-label-sm text-muted">Invoice No</label>
                        <div class="form-control form-control-sm bg-light" id="tds-inv-no"></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm text-muted">Taxable Amount (₹)</label>
                        <input type="number" id="tds-taxable" class="form-control form-control-sm bg-light"
                               step="0.01" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm text-muted">TDS %</label>
                        <input type="number" name="tds_percent" id="tds-pct"
                               class="form-control form-control-sm"
                               step="0.01" min="0" max="100" placeholder="e.g. 10">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm text-muted">TDS Amount (₹)</label>
                        <input type="number" name="tds_amount" id="tds-amt"
                               class="form-control form-control-sm"
                               step="0.01" min="0" placeholder="Auto-calculated or override">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-check"></i> Save TDS</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Action-menu positioning fix — must run AFTER Bootstrap JS is loaded.
//
// Each action menu lives inside .table-responsive (overflow-x: auto). With
// Popper's default `absolute` strategy, the menu is positioned as if it
// were a child of an overflow ancestor and therefore gets clipped. Setting
// strategy to `fixed` makes the menu position itself relative to the
// viewport — fixed-positioned elements escape ancestor overflow clipping
// (no ancestor creates a containing block via transform/filter/perspective).
//
// Bootstrap exposes the strategy only via the JS `popperConfig` option
// (the `data-bs-strategy` attribute does NOT exist), so we re-instantiate
// each action toggle's Dropdown with a custom popperConfig.
//
// Inline <script> tags would run before Yii's endBody() emits Bootstrap
// JS, so we register through registerJs(POS_READY) to guarantee `bootstrap`
// is defined when this code executes.
$this->registerJs(<<<JS
function _invoiceListInitDropdowns() {
    document.querySelectorAll('.invoice-index .actions-col [data-bs-toggle="dropdown"]').forEach(function (toggle) {
        var existing = bootstrap.Dropdown.getInstance(toggle);
        if (existing) existing.dispose();
        new bootstrap.Dropdown(toggle, {
            popperConfig: function (defaultPopperConfig) {
                defaultPopperConfig.strategy = 'fixed';
                return defaultPopperConfig;
            }
        });
    });
}
function _invoiceListInitDateRange() {
    var \$f = jQuery('#filter-from-date'),
        \$t = jQuery('#filter-to-date'),
        \$e = jQuery('#to-date-error'),
        \$b = jQuery('#filter-btn');
    if (!\$f.length || !\$t.length) return;
    function p(v){var d=new Date(v);return v && !isNaN(d) ? d : null;}
    function v(){
        var f=p(\$f.val()), t=p(\$t.val());
        if (f && t && t < f){ \$e.show(); \$b.prop('disabled', true); return false; }
        \$e.hide(); \$b.prop('disabled', false); return true;
    }
    \$f.off('changeDate.invlist change.invlist').on('changeDate.invlist change.invlist', function(){
        var fv = \$f.val();
        \$t.datepicker && \$t.datepicker('setStartDate', fv || null);
        if (p(fv) && p(\$t.val()) && p(\$t.val()) < p(fv)) {
            \$t.val('').datepicker && \$t.datepicker('update');
        }
        v();
    });
    \$t.off('changeDate.invlist change.invlist').on('changeDate.invlist change.invlist', v);
    // Block Pjax form submit while the date range is invalid.
    var \$form = jQuery('#invoice-filter-form');
    \$form.off('submit.invlist').on('submit.invlist', function () { return v(); });
}
_invoiceListInitDropdowns();
_invoiceListInitDateRange();

// Re-run inits whenever Pjax replaces the listing container.
jQuery(document).off('pjax:end.invoiceList').on('pjax:end.invoiceList', '#invoice-list-pjax', function () {
    _invoiceListInitDropdowns();
    _invoiceListInitDateRange();
});
JS
, \yii\web\View::POS_READY);

// Convert to Invoice — proforma listing action.
// Ask for client GST only when it's missing AND the client is domestic & non-exempt
// (international and tax-exempt clients have no GSTIN); otherwise just confirm.
// Invoice date is auto-set to today server-side.
$convertUrl = Url::to(['/invoice/convert-to-invoice']);
$csrfParam  = Yii::$app->request->csrfParam;
$csrfToken  = Yii::$app->request->getCsrfToken();
$this->registerJs(<<<JS
(function () {
    function bindConvert() {
        document.querySelectorAll('.btn-convert-invoice').forEach(function (el) {
            if (el.dataset.bound === '1') return;
            el.dataset.bound = '1';
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var id      = el.dataset.id;
                var no      = (el.dataset.no || '').trim();
                var noHtml  = no ? '<div class="text-muted small mb-2">' + no + '</div>' : '';
                var needGst = (el.dataset.needgst === '1');

                var dialog = needGst
                    ? {
                        icon: 'question',
                        title: 'Convert Proforma to Invoice?',
                        html: noHtml +
                              '<label class="form-label form-label-sm text-muted text-start d-block mb-1">Client GST Number <span class="text-danger">*</span></label>' +
                              '<input id="swal-gst" type="text" class="form-control form-control-sm" placeholder="22ABCDE1234F1Z5">',
                        showCancelButton: true,
                        confirmButtonText: 'Convert to Invoice',
                        confirmButtonColor: '#0d9488',
                        focusConfirm: false,
                        preConfirm: function () {
                            var gstVal = document.getElementById('swal-gst').value.trim();
                            if (gstVal === '') {
                                Swal.showValidationMessage('Client GST number is required.');
                                return false;
                            }
                            return { client_gst_no: gstVal };
                        }
                    }
                    : {
                        icon: 'question',
                        title: 'Are you sure to convert this invoice?',
                        text: no || undefined,
                        showCancelButton: true,
                        confirmButtonText: 'Convert to Invoice',
                        confirmButtonColor: '#0d9488'
                    };

                Swal.fire(dialog).then(function (res) {
                    if (!res.isConfirmed) return;
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.action = '$convertUrl?id=' + encodeURIComponent(id);
                    function add(name, value) {
                        var i = document.createElement('input');
                        i.type = 'hidden';
                        i.name = name;
                        i.value = value;
                        form.appendChild(i);
                    }
                    add('$csrfParam', '$csrfToken');
                    if (needGst && res.value && res.value.client_gst_no) {
                        add('client_gst_no', res.value.client_gst_no);
                    }
                    document.body.appendChild(form);
                    form.submit();
                });
            });
        });
    }
    bindConvert();
    jQuery(document).off('pjax:end.invoiceConvert').on('pjax:end.invoiceConvert', '#invoice-list-pjax', bindConvert);
}());
JS
, \yii\web\View::POS_READY);

// Auto-trigger PDF download when ?download_id= is present in the URL
// (used by the proforma "Save and Download" flow). Drops the param from the
// address bar so a refresh doesn't redownload.
$downloadId = (int) Yii::$app->request->get('download_id', 0);
if ($downloadId > 0) {
    $downloadUrl = Url::to(['/invoice/download-pdf', 'id' => $downloadId]);
    $this->registerJs(<<<JS
(function(){
    var f = document.createElement('iframe');
    f.style.display='none';
    f.src = '$downloadUrl';
    document.body.appendChild(f);
    if (window.history && history.replaceState) {
        var u = new URL(window.location.href);
        u.searchParams.delete('download_id');
        history.replaceState({}, '', u.toString());
    }
})();
JS
, \yii\web\View::POS_READY);
}
?>
