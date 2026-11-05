<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var array $totals   grand, sgst, cgst, igst, net, tds, paid (active invoices matching the filters) */
/** @var bool $isProforma */

use backend\models\Invoice;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$isProforma  = $isProforma ?? false;
$route       = $isProforma ? 'proforma' : 'index';
$this->title = $isProforma ? 'Proforma Invoices' : 'Tax Invoices';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$q          = Yii::$app->request->get();
$filterKeys = ['client_id', 'company_name', 'invoice_no', 'invoice_category', 'payment_status', 'invoice_status', 'from_date', 'to_date'];
$hasFilter  = (bool)array_filter(array_intersect_key($q, array_flip($filterKeys)), fn($v) => $v !== '' && $v !== null);
$money      = fn($v) => number_format((float)$v, 2);
$pending    = max(0, $totals['net'] - $totals['tds'] - $totals['paid']);
$payTone    = [Invoice::PAYMENT_STATUS_UNPAID => 'danger', Invoice::PAYMENT_STATUS_PARTIAL => 'warning', Invoice::PAYMENT_STATUS_PAID => 'success'];

$can = [
    'create'   => UserAccess::checkAccess('invoice/create'),
    'view'     => UserAccess::checkAccess('invoice/view'),
    'update'   => UserAccess::checkAccess('invoice/update'),
    'receipt'  => UserAccess::checkAccess('invoice/create-receipt'),
    'receipts' => UserAccess::checkAccess('invoice/receipts'),
    'tds'      => UserAccess::checkAccess('invoice/update-tds'),
    'cancel'   => UserAccess::checkAccess('invoice/cancel'),
    'export'   => UserAccess::checkAccess('invoice/export'),
    'pdf'      => UserAccess::checkAccess('invoice/download-pdf'),
];

$actions = '';
if ($can['export']) {
    // With no filter the export covers the current month (controller default).
    $actions .= Html::a('<i class="fas fa-file-excel me-1"></i>Export', array_merge(['export', 'role' => $isProforma ? 'proforma' : 'tax'],
        array_intersect_key($q, array_flip($filterKeys))), ['class' => 'btn btn-outline-success btn-sm', 'encode' => false,
        'title' => 'Export to Excel. Without a search, exports this month.']);
}
if ($can['create']) {
    $actions .= Html::a('<i class="fas fa-plus me-1"></i>' . ($isProforma ? 'New proforma' : 'New tax invoice'), ['create', 'type' => $isProforma ? 2 : 1],
        ['class' => 'btn btn-primary btn-sm', 'encode' => false]);
}

$tabs = array_filter([
    'index'          => UserAccess::checkAccess('invoice/index') ? 'Tax invoices' : null,
    'proforma'       => UserAccess::checkAccess('invoice/proforma') ? 'Proforma' : null,
    'monthly-report' => UserAccess::checkAccess('invoice/monthly-report') ? 'Monthly GST report' : null,
]);

// "Save and download" lands here with ?download_id=…; invoices.js fetches the PDF once.
$downloadId = (int)($q['download_id'] ?? 0);
?>
<div class="ui-page invoice-index"<?= $downloadId && $can['pdf'] ? ' data-download-url="' . Html::encode(Url::to(['download-pdf', 'id' => $downloadId])) . '"' : '' ?>>
    <?= $this->render('@backend/views/_ui/_head', [
        'title'     => $isProforma ? 'Proforma invoices' : 'Tax invoices',
        'sub'       => number_format($total) . ' invoice' . ($total == 1 ? '' : 's'),
        'meta'      => '<span class="ui-total">Pending <strong>' . $money($pending) . '</strong></span>',
        'actions'   => $actions,
        'route'     => $route,
        'countKeys' => $filterKeys,
        'filters'   => array_values(array_filter([
            ['type' => 'text', 'name' => 'invoice_no', 'label' => 'Invoice no.', 'placeholder' => 'e.g. MAE/26-27/012', 'col' => 'col-lg-2 col-md-4'],
            ['type' => 'select', 'name' => 'client_id', 'label' => 'Client', 'data' => ArrayHelper::map($clients, 'id', fn($c) => $c->company_name ?: $c->client_name), 'placeholder' => 'All clients', 'col' => 'col-lg-3 col-md-8'],
            ['type' => 'dropdown', 'name' => 'invoice_category', 'label' => 'Category', 'data' => [Invoice::CATEGORY_SERVICE => 'Service', Invoice::CATEGORY_PRODUCT => 'Sales'], 'col' => 'col-lg-2 col-md-4 col-6'],
            $isProforma ? null : ['type' => 'dropdown', 'name' => 'payment_status', 'label' => 'Payment', 'data' => [0 => 'Unpaid', 1 => 'Partial', 2 => 'Paid'], 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'dropdown', 'name' => 'invoice_status', 'label' => 'Status', 'data' => [1 => 'Active', 0 => 'Cancelled'], 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
        ])),
    ]) ?>

    <?php if (count($tabs) > 1): ?>
    <nav class="ui-tabs" aria-label="Invoice lists">
        <?php foreach ($tabs as $r => $label): ?>
            <?= Html::a($label, [$r], ['class' => $r === $route ? 'active' : '', 'aria-current' => $r === $route ? 'page' : null]) ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($rows): ?>
    <div class="ui-tiles">
        <div class="ui-tile"><div class="t-label">Taxable</div><div class="t-value num"><?= $money($totals['grand']) ?></div><div class="t-sub">GST <?= $money($totals['sgst'] + $totals['cgst'] + $totals['igst']) ?></div></div>
        <div class="ui-tile is-accent"><div class="t-label">Net total</div><div class="t-value num"><?= $money($totals['net']) ?></div><div class="t-sub">TDS <?= $money($totals['tds']) ?></div></div>
        <div class="ui-tile"><div class="t-label">Received</div><div class="t-value num"><?= $money($totals['paid']) ?></div><div class="t-sub">Cancelled invoices excluded</div></div>
        <div class="ui-tile"><div class="t-label">Pending</div><div class="t-value num"><?= $money($pending) ?></div><div class="t-sub"><?= $hasFilter ? 'For this search' : 'All time' ?></div></div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Invoice</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th class="text-end d-none d-xxl-table-cell">Taxable</th>
                    <th class="text-end">GST</th>
                    <th class="text-end">Net</th>
                    <th class="text-end">Pending</th>
                    <th>Status</th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="10" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilter
                            ? ['icon' => 'search', 'title' => 'No invoices match your search', 'text' => 'Try a wider date range or fewer filters.',
                               'action' => Html::a('Clear search', [$route], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'file-invoice', 'title' => $isProforma ? 'No proforma invoices yet' : 'No tax invoices yet', 'text' => 'Invoices you raise appear here.',
                               'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>' . ($isProforma ? 'New proforma' : 'New tax invoice'), ['create', 'type' => $isProforma ? 2 : 1], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $inv): /** @var Invoice $inv */
                    $ps          = (int)$inv->payment_status;
                    $bal         = $inv->getBalanceAmount();
                    $isCancelled = (int)$inv->invoice_status === 0;
                    $isPro       = (int)$inv->invoice_role === Invoice::ROLE_PROFORMA;
                    $isTax       = (int)$inv->invoice_role === Invoice::ROLE_TAX_INVOICE;
                    $isIntl      = (int)$inv->invoice_type === Invoice::TYPE_INTERNATIONAL;
                    $gst         = (float)$inv->sgst_amount + (float)$inv->cgst_amount + (float)$inv->igst_amount;
                    $gstSplit    = implode(' · ', array_filter([
                        (float)$inv->sgst_amount ? 'SGST ' . $money($inv->sgst_amount) : '', (float)$inv->cgst_amount ? 'CGST ' . $money($inv->cgst_amount) : '',
                        (float)$inv->igst_amount ? 'IGST ' . $money($inv->igst_amount) : '']));
                    $no          = $inv->invoice_no ?: 'Draft #' . $inv->id;
                    $client      = $inv->client->company_name ?? $inv->client->client_name ?? '—';
                    $needGst     = empty($inv->client?->gst_no) && !$isIntl && (int)($inv->client?->tax_exemption ?? 0) !== 1;
                    $cur         = $isIntl && $inv->currency ? Html::encode($inv->currency) . ' ' : '';
                ?>
                    <tr<?= $isCancelled ? ' class="is-cancelled"' : '' ?>>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title">
                            <?= $can['view'] ? Html::a(Html::encode($no), ['view', 'id' => $inv->id], ['class' => 'text-reset', 'target' => '_blank', 'rel' => 'noopener']) : Html::encode($no) ?>
                            <?php if ($inv->title): ?><div class="small text-muted fw-normal text-truncate" style="max-width:220px" title="<?= Html::encode($inv->title) ?>"><?= Html::encode($inv->title) ?></div><?php endif; ?>
                        </td>
                        <td class="small" data-label="Client"><span class="d-inline-block text-truncate" style="max-width:170px" title="<?= Html::encode($client) ?>"><?= Html::encode($client) ?></span></td>
                        <td class="text-nowrap" data-label="Date"><?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?></td>
                        <td class="text-end num d-none d-xxl-table-cell" data-label="Taxable"><?= $cur . $money($inv->grand_total) ?></td>
                        <td class="text-end num text-muted" data-label="GST" title="<?= Html::encode($gstSplit) ?>"><?= $gst ? $money($gst) : '—' ?></td>
                        <td class="text-end num fw-semibold" data-label="Net"><?= $cur . $money($inv->net_total) ?>
                            <?php if ((float)$inv->tds_amount): ?><div class="small text-muted fw-normal">TDS <?= $money($inv->tds_amount) ?></div><?php endif; ?></td>
                        <td class="text-end num<?= !$isCancelled && $bal > 0.01 ? ' text-danger fw-semibold' : ' text-muted' ?>" data-label="Pending"><?= !$isCancelled && $bal > 0.01 ? $money($bal) : '—' ?></td>
                        <td data-label="Status">
                            <?php if ($isCancelled): ?>
                                <span class="ui-pill tone-neutral">Cancelled</span>
                            <?php elseif ($isPro): ?>
                                <span class="ui-pill tone-accent plain">Proforma</span>
                            <?php else: ?>
                                <span class="ui-pill tone-<?= $payTone[$ps] ?? 'neutral' ?>"><?= Html::encode($inv->getPaymentStatusLabel()) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?= Html::encode($no) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye fa-fw me-1"></i>View', ['view', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false, 'target' => '_blank', 'rel' => 'noopener']) ?></li><?php endif; ?>
                                    <?php if ($can['pdf']): ?><li><?= Html::a('<i class="fas fa-file-pdf fa-fw me-1"></i>Download PDF', ['download-pdf', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if (!$isCancelled && $isPro && $can['update']): ?><li><?= Html::a('<i class="fas fa-pen fa-fw me-1"></i>Edit', ['update', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if (!$isCancelled && $isPro && $can['create']): ?>
                                        <li><a href="#" class="dropdown-item" data-inv-convert="<?= (int)$inv->id ?>" data-url="<?= Url::to(['convert-to-invoice', 'id' => $inv->id]) ?>"
                                               data-no="<?= Html::encode($no) ?>" data-needgst="<?= $needGst ? '1' : '0' ?>"><i class="fas fa-exchange-alt fa-fw me-1"></i>Convert to tax invoice</a></li>
                                    <?php endif; ?>
                                    <?php if (!$isCancelled && $isTax && $ps < Invoice::PAYMENT_STATUS_PAID && $can['receipt']): ?><li><?= Html::a('<i class="fas fa-hand-holding-usd fa-fw me-1"></i>Record payment', ['create-receipt', 'invoice_id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if (!$isCancelled && $isTax && $can['receipts']): ?><li><?= Html::a('<i class="fas fa-receipt fa-fw me-1"></i>Receipts', ['receipts', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if (!$isCancelled && !$isIntl && $can['tds']): ?>
                                        <li><a href="#" class="dropdown-item" data-inv-tds="<?= (int)$inv->id ?>" data-url="<?= Url::to(['update-tds', 'id' => $inv->id]) ?>" data-no="<?= Html::encode($no) ?>"
                                               data-taxable="<?= (float)$inv->grand_total ?>" data-max="<?= round((float)$inv->net_total - (float)$inv->paid_amount, 2) ?>"
                                               data-pct="<?= (float)$inv->tds_percent ?>" data-amt="<?= (float)$inv->tds_amount ?>"><i class="fas fa-percent fa-fw me-1"></i>TDS</a></li>
                                    <?php endif; ?>
                                    <?php if (!$isCancelled && $can['cancel']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-ban fa-fw me-1"></i>Cancel invoice', ['cancel', 'id' => $inv->id], ['class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data' => ['method' => 'post', 'confirm' => 'Cancel ' . $no . ' for ' . $client . '?'
                                                . ((float)$inv->paid_amount > 0 ? ' Payments of ' . $money($inv->paid_amount) . ' are recorded on it.' : '') . ' This cannot be undone.']]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($rows): ?>
                <tfoot class="table-light">
                <tr>
                    <td colspan="4" class="text-end small fw-semibold">Total<?= $pagination && $pagination->getPageCount() > 1 ? ' (all pages)' : '' ?></td>
                    <td class="text-end num fw-semibold d-none d-xxl-table-cell"><?= $money($totals['grand']) ?></td>
                    <td class="text-end num fw-semibold"><?= $money($totals['sgst'] + $totals['cgst'] + $totals['igst']) ?></td>
                    <td class="text-end num fw-bold"><?= $money($totals['net']) ?></td>
                    <td class="text-end num fw-semibold text-danger"><?= $money($pending) ?></td>
                    <td colspan="2"></td>
                </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'invoices']) ?>
    </div>

    <?php if ($can['tds']): ?>
    <div class="modal fade" id="tdsModal" tabindex="-1" aria-labelledby="tdsModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" class="modal-content js-busy-form" id="tdsForm" action="">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <input type="hidden" name="_from" value="<?= $route === 'proforma' ? 'proforma' : 'index' ?>">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold" id="tdsModalTitle">TDS for <span class="js-tds-no"></span></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12 small text-muted">Taxable amount <strong class="num js-tds-taxable"></strong>. TDS reduces the amount the client still owes.</div>
                    <div class="col-6">
                        <label class="form-label" for="tds-pct">TDS %</label>
                        <input type="number" name="tds_percent" id="tds-pct" class="form-control form-control-sm text-end" step="0.01" min="0" max="100" placeholder="e.g. 10">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="tds-amt">TDS amount</label>
                        <input type="number" name="tds_amount" id="tds-amt" class="form-control form-control-sm text-end" step="0.01" min="0">
                    </div>
                    <div class="col-12"><div class="invalid-feedback d-block js-tds-error"></div></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Save TDS</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
