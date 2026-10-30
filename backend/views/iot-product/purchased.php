<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Purchased Products';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$grandTotal = 0;
$sgstTotal  = 0;
$cgstTotal  = 0;
$igstTotal  = 0;
$netTotal   = 0;
$roundTotal = 0;
foreach ($models as $m) {
    $grandTotal += (float)$m->grand_total;
    $sgstTotal  += (float)$m->sgst_amount;
    $cgstTotal  += (float)$m->cgst_amount;
    $igstTotal  += (float)$m->igst_amount;
    $netTotal   += (float)$m->net_total;
    $roundTotal += (float)$m->round_off;
}
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="iot-purchased-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Purchased Products</h5>
            <small class="text-muted"><?= $total ?> invoice<?= $total != 1 ? 's' : '' ?></small>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="search" value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm" placeholder="Invoice No / Title…">
                </div>
                <div class="col-md-2">
                    <select name="invoice_role" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="1" <?= Yii::$app->request->get('invoice_role') == 1 ? 'selected' : '' ?>>Invoice</option>
                        <option value="2" <?= Yii::$app->request->get('invoice_role') == 2 ? 'selected' : '' ?>>Proforma</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('search') || Yii::$app->request->get('invoice_role')): ?>
                        <?= Html::a('Clear', ['purchased'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
                <?php if ($grandTotal > 0): ?>
                <div class="col-auto ms-auto d-flex gap-2 flex-wrap">
                    <span class="badge bg-light text-dark border" style="font-size:.78rem">Grand: ₹<?= number_format($grandTotal, 2) ?></span>
                    <span class="badge bg-light text-dark border" style="font-size:.78rem">SGST: ₹<?= number_format($sgstTotal, 2) ?></span>
                    <span class="badge bg-light text-dark border" style="font-size:.78rem">CGST: ₹<?= number_format($cgstTotal, 2) ?></span>
                    <span class="badge bg-light text-dark border" style="font-size:.78rem">IGST: ₹<?= number_format($igstTotal, 2) ?></span>
                    <span class="badge bg-light text-dark border" style="font-size:.78rem">Round Off: ₹<?= number_format($roundTotal, 2) ?></span>
                    <span class="badge bg-success-subtle text-success border" style="font-size:.82rem">Net Total: ₹<?= number_format($netTotal, 2) ?></span>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Title</th>
                        <th>Role</th>
                        <th class="text-end">Grand Total</th>
                        <th class="text-end">SGST</th>
                        <th class="text-end">CGST</th>
                        <th class="text-end">IGST</th>
                        <th class="text-end">Net Total</th>
                        <th class="text-end">Round Off</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="13" class="text-center text-muted py-5">No purchase invoices found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $inv): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <span class="fw-semibold badge bg-light text-dark border font-monospace">
                                <?= Html::encode($inv->invoice_number) ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?>
                        </td>
                        <td class="text-muted small"><?= Html::encode($inv->vendor->company_name ?? '—') ?></td>
                        <td class="small"><?= Html::encode($inv->title ?? '—') ?></td>
                        <td>
                            <?php if ($inv->invoice_role == 2): ?>
                                <span class="badge bg-warning-subtle text-warning border" style="font-size:.72rem">Proforma</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border" style="font-size:.72rem">Invoice</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small">₹<?= number_format((float)$inv->grand_total, 2) ?></td>
                        <td class="text-end small text-muted">₹<?= number_format((float)$inv->sgst_amount, 2) ?></td>
                        <td class="text-end small text-muted">₹<?= number_format((float)$inv->cgst_amount, 2) ?></td>
                        <td class="text-end small text-muted">₹<?= number_format((float)$inv->igst_amount, 2) ?></td>
                        <td class="text-end fw-semibold text-success small">₹<?= number_format((float)$inv->net_total, 2) ?></td>
                        <td class="text-end small text-muted"><?= number_format((float)$inv->round_off, 2) ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:180px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view-purchase', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php if ((int)$inv->invoice_role === 2): ?>
                                        <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update-purchase', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                        <li>
                                            <a href="#" class="dropdown-item text-primary iot-purchase-convert"
                                               data-id="<?= (int)$inv->id ?>">
                                               <i class="fas fa-exchange-alt"></i> Convert to Invoice
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a href="#" class="dropdown-item text-danger iot-purchase-delete"
                                               data-id="<?= (int)$inv->id ?>"
                                               data-label="<?= Html::encode($inv->invoice_number ?? '') ?>">
                                               <i class="fas fa-trash-alt"></i> Delete
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
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$csrfParam  = Yii::$app->request->csrfParam;
$csrfToken  = Yii::$app->request->getCsrfToken();
$deleteUrl  = Url::to(['/iot-product/delete-purchase']);
$convertUrl = Url::to(['/iot-product/convert-to-invoice']);
$this->registerJs(<<<JS
(function () {
    var csrfData = { '$csrfParam': '$csrfToken' };

    function handleResponse(r) {
        var msg = (r && r.msg) ? String(r.msg).replace(/\\n/g, '<br>') : 'Unknown response.';
        if (r && r.status === 1) {
            Swal.fire({ icon: 'success', title: 'Success', html: msg, timer: 1500, showConfirmButton: false })
                .then(function () { location.reload(); });
        } else {
            Swal.fire({ icon: 'error', title: 'Sorry', html: msg });
        }
    }

    document.querySelectorAll('.iot-purchase-delete').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Delete this Proforma?',
                text: el.dataset.label ? ('Invoice: ' + el.dataset.label) : '',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#dc3545'
            }).then(function (res) {
                if (!res.isConfirmed) return;
                jQuery.post('$deleteUrl', Object.assign({}, csrfData, { id: el.dataset.id }), null, 'json')
                    .done(handleResponse)
                    .fail(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' }); });
            });
        });
    });

    document.querySelectorAll('.iot-purchase-convert').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'question',
                title: 'Convert Proforma to Invoice?',
                text: "After converting you can't update or delete this invoice.",
                showCancelButton: true,
                confirmButtonText: 'Yes, convert'
            }).then(function (res) {
                if (!res.isConfirmed) return;
                jQuery.post('$convertUrl', Object.assign({}, csrfData, { id: el.dataset.id }), null, 'json')
                    .done(handleResponse)
                    .fail(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' }); });
            });
        });
    });
}());
JS, \yii\web\View::POS_READY);
?>
