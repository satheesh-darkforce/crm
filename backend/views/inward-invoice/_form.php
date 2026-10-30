<?php
/**
 * @var \yii\web\View $this
 * @var \backend\models\InwardInvoice $model
 * @var \backend\models\Vendor $vendor
 * @var \backend\models\AccLedgerCategory[] $ledgerCategories
 * @var \backend\models\AccLedgerCategory[] $paymentCategories
 */

use backend\models\Vendor;
use kartik\date\DatePicker;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$isNew = $model->isNewRecord;
$typeLabels  = Vendor::typeLabels();
$typeColors  = [1 => 'primary', 2 => 'success', 3 => 'info'];

// Tax field enable/disable based on vendor type
$sgstEnabled = ($vendor->vendor_type == 2);
$cgstEnabled = ($vendor->vendor_type == 2);
$igstEnabled = ($vendor->vendor_type == 1);
$rdSgst = $sgstEnabled ? '' : 'readonly';
$rdCgst = $cgstEnabled ? '' : 'readonly';
$rdIgst = $igstEnabled ? '' : 'readonly';

// Pre-load dropdown data
$catMap        = ArrayHelper::map($ledgerCategories, 'id', 'title');
$paymentCatMap = ArrayHelper::map($paymentCategories, 'id', 'title');

// Existing items & expenses for edit
$existingItems    = !$isNew ? ($model->lineItems ?? []) : [];
$existingExpenses = !$isNew ? ($model->expenses ?? []) : [];
$existingAttachments = !$isNew ? ($model->attachments ?? []) : [];

$baseUrl   = Yii::$app->request->baseUrl;
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->csrfToken;
?>
<style>
.inv-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#6c757d;font-weight:600;white-space:nowrap;padding:.35rem .3rem!important}
.inv-table td{padding:.25rem .3rem!important;vertical-align:middle}
.inv-table input,.inv-table select,.inv-table textarea{font-size:.8rem!important;padding:.2rem .35rem!important}
.inv-table .form-control,.inv-table .form-select{min-height:0}
.summary-row td{border-top:0!important;font-size:.85rem}
.expense-row td{border-top:0!important;font-size:.85rem;background:#fffdf5}
</style>

<div class="inward-invoice-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= $isNew ? 'Add Inward Invoice' : 'Edit Inward Invoice' ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <!-- Vendor Info -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 small">
                <div class="col-md-3"><span class="text-muted">Company:</span> <strong><?= Html::encode($vendor->company_name) ?></strong></div>
                <div class="col-md-2"><span class="text-muted">Type:</span>
                    <span class="badge bg-<?= $typeColors[$vendor->vendor_type] ?? 'secondary' ?>"><?= Html::encode($typeLabels[$vendor->vendor_type] ?? '—') ?></span>
                </div>
                <div class="col-md-3"><span class="text-muted">GST:</span> <?= Html::encode($vendor->gst_no ?: '—') ?></div>
                <div class="col-md-4"><span class="text-muted">Address:</span> <?= Html::encode($vendor->address ?: '—') ?></div>
            </div>
        </div>
    </div>

    <form id="inward-invoice-form" method="post" enctype="multipart/form-data">
        <?= Html::hiddenInput($csrfParam, $csrfToken) ?>
        <?= Html::hiddenInput('InwardInvoice[vendor_id]', $vendor->id) ?>

        <!-- Header Fields -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Invoice Number <span class="text-danger">*</span></label>
                        <input type="text" name="InwardInvoice[invoice_number]" id="invoice_number"
                               value="<?= Html::encode($model->invoice_number) ?>"
                               class="form-control form-control-sm" maxlength="255" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Invoice Date <span class="text-danger">*</span></label>
                        <?= DatePicker::widget([
                            'name'    => 'InwardInvoice[invoice_date]',
                            'value'   => $model->invoice_date ?: date('Y-m-d'),
                            'type'    => DatePicker::TYPE_INPUT,
                            'options' => ['class' => 'form-control form-control-sm', 'id' => 'invoice_date', 'readonly' => true],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Title</label>
                        <input type="text" name="InwardInvoice[title]" id="invoice_title"
                               value="<?= Html::encode($model->title) ?>"
                               class="form-control form-control-sm" maxlength="5000" placeholder="Description / purpose">
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Items Table -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2"><strong class="small">Line Items</strong></div>
            <div class="table-responsive">
                <table class="table table-sm inv-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px">#</th>
                            <th style="width:130px">Ledger Cat</th>
                            <th style="width:130px">Ledger</th>
                            <th>Particulars <span class="text-danger">*</span></th>
                            <th style="width:70px">HSN</th>
                            <th style="width:70px">Qty <span class="text-danger">*</span></th>
                            <th style="width:90px">Price/Qty <span class="text-danger">*</span></th>
                            <th style="width:90px">Item Total</th>
                            <th style="width:60px">SGST%</th>
                            <th style="width:80px">SGST Amt</th>
                            <th style="width:60px">CGST%</th>
                            <th style="width:80px">CGST Amt</th>
                            <th style="width:60px">IGST%</th>
                            <th style="width:80px">IGST Amt</th>
                            <th style="width:90px">Total Amt</th>
                            <th style="width:35px"></th>
                        </tr>
                    </thead>

                    <!-- Template Row -->
                    <tbody id="tpl-row">
                        <tr class="table-warning">
                            <td class="text-muted text-center">—</td>
                            <td>
                                <select id="tpl-ledger-cat" class="form-select form-select-sm">
                                    <option value="">Category…</option>
                                    <?php foreach ($catMap as $cid => $ctitle): ?>
                                        <option value="<?= $cid ?>"><?= Html::encode($ctitle) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select id="tpl-ledger" class="form-select form-select-sm">
                                    <option value="">Ledger…</option>
                                </select>
                            </td>
                            <td><input type="text" id="tpl-particular" class="form-control form-control-sm" placeholder="Product / Service"></td>
                            <td><input type="text" id="tpl-hsn" class="form-control form-control-sm"></td>
                            <td><input type="number" id="tpl-qty" class="form-control form-control-sm" step="0.01" min="0" value="1"></td>
                            <td><input type="number" id="tpl-rate" class="form-control form-control-sm" step="0.01" min="0"></td>
                            <td><input type="number" id="tpl-item-total" class="form-control form-control-sm" step="0.01" readonly></td>
                            <td><input type="number" id="tpl-sgst-pct" class="form-control form-control-sm" step="0.01" min="0" max="100" value="0" <?= $rdSgst ?>></td>
                            <td><input type="number" id="tpl-sgst-amt" class="form-control form-control-sm" step="0.01" <?= $rdSgst ?>></td>
                            <td><input type="number" id="tpl-cgst-pct" class="form-control form-control-sm" step="0.01" min="0" max="100" value="0" <?= $rdCgst ?>></td>
                            <td><input type="number" id="tpl-cgst-amt" class="form-control form-control-sm" step="0.01" <?= $rdCgst ?>></td>
                            <td><input type="number" id="tpl-igst-pct" class="form-control form-control-sm" step="0.01" min="0" max="100" value="0" <?= $rdIgst ?>></td>
                            <td><input type="number" id="tpl-igst-amt" class="form-control form-control-sm" step="0.01" <?= $rdIgst ?>></td>
                            <td><input type="number" id="tpl-total-amt" class="form-control form-control-sm" step="0.01" readonly></td>
                            <td><button type="button" id="btn-add-row" class="btn btn-sm btn-success fw-bold px-2">+</button></td>
                        </tr>
                    </tbody>

                    <!-- Added / Existing Items -->
                    <tbody id="items-body">
                        <?php foreach ($existingItems as $idx => $d): ?>
                        <tr class="item-row">
                            <td class="sno text-muted text-center"><?= $idx + 1 ?></td>
                            <td class="small text-muted"><?= Html::encode($d->ledger ? $d->ledger->category->title ?? '' : '') ?></td>
                            <td class="small text-muted">
                                <?= Html::encode($d->ledger->title ?? '') ?>
                                <input type="hidden" name="ledger_ids[]" value="<?= $d->ledger_id ?>">
                            </td>
                            <td><input type="text" name="particular[]" class="form-control form-control-sm" value="<?= Html::encode($d->product) ?>"></td>
                            <td><input type="text" name="hsn[]" class="form-control form-control-sm" value="<?= Html::encode($d->hsn) ?>"></td>
                            <td><input type="number" name="qty[]" class="form-control form-control-sm r-qty" step="0.01" min="0" value="<?= $d->qty ?>"></td>
                            <td><input type="number" name="price_per_qty[]" class="form-control form-control-sm r-rate" step="0.01" min="0" value="<?= $d->price_per_qty ?>"></td>
                            <td><input type="number" name="item_total[]" class="form-control form-control-sm r-item-total" step="0.01" value="<?= $d->item_total ?>" readonly></td>
                            <td><input type="number" name="sgst_percent[]" class="form-control form-control-sm r-sgst-pct" step="0.01" max="100" value="<?= $d->sgst_percent ?>" <?= $rdSgst ?>></td>
                            <td><input type="number" name="sgst_amount[]" class="form-control form-control-sm r-sgst-amt" step="0.01" value="<?= $d->sgst_amount ?>" <?= $rdSgst ?>></td>
                            <td><input type="number" name="cgst_percent[]" class="form-control form-control-sm r-cgst-pct" step="0.01" max="100" value="<?= $d->cgst_percent ?>" <?= $rdCgst ?>></td>
                            <td><input type="number" name="cgst_amount[]" class="form-control form-control-sm r-cgst-amt" step="0.01" value="<?= $d->cgst_amount ?>" <?= $rdCgst ?>></td>
                            <td><input type="number" name="igst_percent[]" class="form-control form-control-sm r-igst-pct" step="0.01" max="100" value="<?= $d->igst_percent ?>" <?= $rdIgst ?>></td>
                            <td><input type="number" name="igst_amount[]" class="form-control form-control-sm r-igst-amt" step="0.01" value="<?= $d->igst_amount ?>" <?= $rdIgst ?>></td>
                            <td><input type="number" name="total_amount[]" class="form-control form-control-sm r-total-amt" step="0.01" value="<?= $d->total_amount ?>" readonly></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger btn-del-row px-2">−</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                    <!-- Summary -->
                    <tbody id="summary-body">
                        <tr class="summary-row table-light"><td colspan="14" class="text-end fw-semibold">Grand Total <span class="text-danger">*</span></td>
                            <td><input type="number" name="grandtotal" id="grandtotal" class="form-control form-control-sm fw-bold" step="0.01" value="<?= $model->grand_total ?>" readonly></td><td></td></tr>
                        <tr class="summary-row"><td colspan="14" class="text-end">SGST Amount<?= $sgstEnabled ? ' <span class="text-danger">*</span>' : '' ?></td>
                            <td><input type="number" name="sgstamount" id="sgstamount" class="form-control form-control-sm" step="0.01" value="<?= $model->sgst_amount ?>" <?= $sgstEnabled ? '' : 'readonly' ?>></td><td></td></tr>
                        <tr class="summary-row"><td colspan="14" class="text-end">CGST Amount<?= $cgstEnabled ? ' <span class="text-danger">*</span>' : '' ?></td>
                            <td><input type="number" name="cgstamount" id="cgstamount" class="form-control form-control-sm" step="0.01" value="<?= $model->cgst_amount ?>" <?= $cgstEnabled ? '' : 'readonly' ?>></td><td></td></tr>
                        <tr class="summary-row"><td colspan="14" class="text-end">IGST Amount<?= $igstEnabled ? ' <span class="text-danger">*</span>' : '' ?></td>
                            <td><input type="number" name="igstamount" id="igstamount" class="form-control form-control-sm" step="0.01" value="<?= $model->igst_amount ?>" <?= $igstEnabled ? '' : 'readonly' ?>></td><td></td></tr>

                        <!-- Extra Expenses Header -->
                        <tr class="expense-row"><td colspan="15" class="text-end">
                            <button type="button" id="btn-add-expense" class="btn btn-outline-info btn-sm">+ Add Extra Expense</button>
                        </td><td></td></tr>
                    </tbody>

                    <!-- Existing Expenses -->
                    <tbody id="expenses-body">
                        <?php foreach ($existingExpenses as $ex): ?>
                        <tr class="expense-row exp-item">
                            <td></td>
                            <td colspan="2" class="small text-muted"><?= Html::encode($ex->ledger->title ?? '') ?>
                                <input type="hidden" name="expense_ledger_id[]" value="<?= $ex->ledger_id ?>"></td>
                            <td colspan="10">
                                <input type="text" name="expense_title[]" class="form-control form-control-sm" value="<?= Html::encode($ex->product) ?>" placeholder="Expense label">
                            </td>
                            <td colspan="2">
                                <input type="number" name="expense_amount[]" class="form-control form-control-sm exp-amount" step="0.01" min="0" value="<?= $ex->item_total ?>">
                            </td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger btn-del-expense px-2">−</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                    <!-- Round Off & Net -->
                    <tbody>
                        <tr class="summary-row"><td colspan="14" class="text-end">Round Off</td>
                            <td><input type="number" name="roundoff" id="round_off" class="form-control form-control-sm" step="0.01" value="<?= $model->round_off ?>"></td><td></td></tr>
                        <tr class="summary-row table-light"><td colspan="14" class="text-end fw-bold">Net Total <span class="text-danger">*</span></td>
                            <td><input type="number" name="nettotal" id="nettotal" class="form-control form-control-sm fw-bold" step="0.01" value="<?= $model->net_total ?>" readonly></td><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Section -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2"><strong class="small">Payment Information</strong></div>
            <div class="card-body py-3">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Payment Status <span class="text-danger">*</span></label>
                        <select name="InwardInvoice[payment_status]" id="payment_status" class="form-select form-select-sm">
                            <option value="">— Select —</option>
                            <option value="0" <?= $model->payment_status !== null && $model->payment_status !== '' && (int)$model->payment_status === 0 ? 'selected' : '' ?>>Not Paid</option>
                            <option value="1" <?= (int)$model->payment_status === 1 ? 'selected' : '' ?>>Paid</option>
                        </select>
                    </div>
                </div>

                <!-- Payment details (shown when Paid) -->
                <div id="payment-details" class="row g-3 mt-1" style="display:<?= (int)$model->payment_status === 1 ? 'flex' : 'none' ?>">
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Payment Type</label>
                        <select name="InwardInvoice[payment_type]" id="payment_type" class="form-select form-select-sm">
                            <option value="">— Select —</option>
                            <?php foreach ($paymentCatMap as $pid => $ptitle): ?>
                                <option value="<?= $pid ?>" <?= (int)$model->payment_type === $pid ? 'selected' : '' ?>><?= Html::encode($ptitle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Payment Ledger <span class="text-danger">*</span></label>
                        <select name="InwardInvoice[payment_ledger_id]" id="payment_ledger_id" class="form-select form-select-sm">
                            <option value="">— Select —</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Payment Date</label>
                        <?= DatePicker::widget([
                            'name'    => 'InwardInvoice[payment_date]',
                            'value'   => $model->payment_date ?: '',
                            'type'    => DatePicker::TYPE_INPUT,
                            'options' => ['class' => 'form-control form-control-sm', 'id' => 'payment_date', 'readonly' => true],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Payment Amount</label>
                        <input type="number" name="InwardInvoice[payment_amount]" id="payment_amount"
                               class="form-control form-control-sm" step="0.01" min="0"
                               value="<?= $model->payment_amount ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">TDS Amount</label>
                        <input type="number" name="InwardInvoice[tds_amount]" id="tds_amount"
                               class="form-control form-control-sm" step="0.01" min="0"
                               value="<?= $model->tds_amount ?>">
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Payment Remarks</label>
                        <textarea name="InwardInvoice[payment_remarks]" class="form-control form-control-sm"
                                  rows="2" maxlength="5000" placeholder="Optional remarks"><?= Html::encode($model->payment_remarks) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attachments -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2"><strong class="small">Attachments</strong></div>
            <div class="card-body py-3">
                <input type="file" name="attachments[]" multiple class="form-control form-control-sm" style="max-width:400px">
                <?php if (!empty($existingAttachments)): ?>
                <table class="table table-sm mt-2 mb-0" style="max-width:500px">
                    <?php foreach ($existingAttachments as $ai => $att): ?>
                    <tr id="att-<?= $att->id ?>">
                        <td class="small">
                            <a href="<?= $att->path ?>" target="_blank">Attachment <?= $ai + 1 ?></a>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-del-att px-2"
                                    data-id="<?= $att->id ?>"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submit -->
        <div class="d-flex gap-2 mb-4">
            <?= Html::submitButton($isNew ? 'Create Invoice' : 'Save Changes', [
                'class' => 'btn btn-primary btn-sm px-4',
                'id'    => 'btn-submit',
            ]) ?>
            <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
    </form>
</div>

<!-- Extra Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Add Extra Expense</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Ledger Category</label>
                    <select id="exp-ledger-cat" class="form-select form-select-sm">
                        <option value="">— Select —</option>
                        <?php foreach ($catMap as $cid => $ctitle): ?>
                            <option value="<?= $cid ?>"><?= Html::encode($ctitle) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Ledger</label>
                    <select id="exp-ledger" class="form-select form-select-sm">
                        <option value="">— Select —</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Label / Title</label>
                    <input type="text" id="exp-title" class="form-control form-control-sm" placeholder="Expense description">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Amount</label>
                    <input type="number" id="exp-amount" class="form-control form-control-sm" step="0.01" min="0">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-expense-add">Add</button>
            </div>
        </div>
    </div>
</div>

<?php
$autocompleteLedgerUrl = Url::to(['autocomplete-ledger']);
$getPaymentLedgersUrl  = Url::to(['get-payment-ledgers']);
$deleteAttachmentUrl   = Url::to(['delete-attachment']);
$existingPaymentType   = (int)$model->payment_type;
$existingPaymentLedger = (int)$model->payment_ledger_id;
$sgstJs = $sgstEnabled ? 'true' : 'false';
$cgstJs = $cgstEnabled ? 'true' : 'false';
$igstJs = $igstEnabled ? 'true' : 'false';

$this->registerJs(<<<JS
var sgstEnabled = {$sgstJs},
    cgstEnabled = {$cgstJs},
    igstEnabled = {$igstJs};

/* ── Ledger AJAX helper ──────────────────────────────────── */
function loadLedgers(categoryId, selectEl, callback) {
    selectEl.html('<option value="">Loading…</option>');
    jQuery.post('{$autocompleteLedgerUrl}', {categoryId: categoryId}, function(resp) {
        selectEl.html('<option value="">Ledger…</option>');
        if (resp.status == 1 && resp.data) {
            jQuery.each(resp.data, function(id, title) {
                selectEl.append('<option value="'+id+'">'+title+'</option>');
            });
        }
        if (callback) callback();
    }, 'json');
}

/* ── Template row: category → ledger ─────────────────────── */
jQuery('#tpl-ledger-cat').on('change', function() {
    var cid = jQuery(this).val();
    if (cid) { loadLedgers(cid, jQuery('#tpl-ledger')); }
    else { jQuery('#tpl-ledger').html('<option value="">Ledger…</option>'); }
});

/* ── Template row: calculate on input ────────────────────── */
jQuery('#tpl-qty, #tpl-rate, #tpl-sgst-pct, #tpl-cgst-pct, #tpl-igst-pct, #tpl-sgst-amt, #tpl-cgst-amt, #tpl-igst-amt').on('input', function() {
    calcTplRow();
});

function calcTplRow() {
    var qty  = pf('#tpl-qty'), rate = pf('#tpl-rate');
    var it   = qty * rate;
    jQuery('#tpl-item-total').val(it.toFixed(2));
    // Tax from percentages
    var sp = pf('#tpl-sgst-pct'), cp = pf('#tpl-cgst-pct'), ip = pf('#tpl-igst-pct');
    jQuery('#tpl-sgst-amt').val((it * sp / 100).toFixed(2));
    jQuery('#tpl-cgst-amt').val((it * cp / 100).toFixed(2));
    jQuery('#tpl-igst-amt').val((it * ip / 100).toFixed(2));
    var total = it + pf('#tpl-sgst-amt') + pf('#tpl-cgst-amt') + pf('#tpl-igst-amt');
    jQuery('#tpl-total-amt').val(total.toFixed(2));
}

/* ── Add row from template ───────────────────────────────── */
jQuery('#btn-add-row').on('click', function() {
    var particular = jQuery('#tpl-particular').val().trim();
    if (!particular) { Swal.fire({icon:'error',title:'Error',text:'Particulars is required.'}); return; }
    if (pf('#tpl-qty') <= 0) { Swal.fire({icon:'error',title:'Error',text:'Qty must be > 0.'}); return; }
    if (pf('#tpl-rate') <= 0) { Swal.fire({icon:'error',title:'Error',text:'Price per Qty must be > 0.'}); return; }

    var ledgerId   = jQuery('#tpl-ledger').val() || '';
    var ledgerText = jQuery('#tpl-ledger option:selected').text() || '';
    var catText    = jQuery('#tpl-ledger-cat option:selected').text() || '';
    var rdS = sgstEnabled ? '' : 'readonly', rdC = cgstEnabled ? '' : 'readonly', rdI = igstEnabled ? '' : 'readonly';

    var row = '<tr class="item-row">'
        + '<td class="sno text-muted text-center"></td>'
        + '<td class="small text-muted">'+esc(catText)+'</td>'
        + '<td class="small text-muted">'+esc(ledgerText)+'<input type="hidden" name="ledger_ids[]" value="'+ledgerId+'"></td>'
        + '<td><input type="text" name="particular[]" class="form-control form-control-sm" value="'+esc(particular)+'"></td>'
        + '<td><input type="text" name="hsn[]" class="form-control form-control-sm" value="'+esc(jQuery('#tpl-hsn').val())+'"></td>'
        + '<td><input type="number" name="qty[]" class="form-control form-control-sm r-qty" step="0.01" min="0" value="'+pf('#tpl-qty')+'"></td>'
        + '<td><input type="number" name="price_per_qty[]" class="form-control form-control-sm r-rate" step="0.01" min="0" value="'+pf('#tpl-rate')+'"></td>'
        + '<td><input type="number" name="item_total[]" class="form-control form-control-sm r-item-total" step="0.01" value="'+pf('#tpl-item-total')+'" readonly></td>'
        + '<td><input type="number" name="sgst_percent[]" class="form-control form-control-sm r-sgst-pct" step="0.01" max="100" value="'+pf('#tpl-sgst-pct')+'" '+rdS+'></td>'
        + '<td><input type="number" name="sgst_amount[]" class="form-control form-control-sm r-sgst-amt" step="0.01" value="'+pf('#tpl-sgst-amt')+'" '+rdS+'></td>'
        + '<td><input type="number" name="cgst_percent[]" class="form-control form-control-sm r-cgst-pct" step="0.01" max="100" value="'+pf('#tpl-cgst-pct')+'" '+rdC+'></td>'
        + '<td><input type="number" name="cgst_amount[]" class="form-control form-control-sm r-cgst-amt" step="0.01" value="'+pf('#tpl-cgst-amt')+'" '+rdC+'></td>'
        + '<td><input type="number" name="igst_percent[]" class="form-control form-control-sm r-igst-pct" step="0.01" max="100" value="'+pf('#tpl-igst-pct')+'" '+rdI+'></td>'
        + '<td><input type="number" name="igst_amount[]" class="form-control form-control-sm r-igst-amt" step="0.01" value="'+pf('#tpl-igst-amt')+'" '+rdI+'></td>'
        + '<td><input type="number" name="total_amount[]" class="form-control form-control-sm r-total-amt" step="0.01" value="'+pf('#tpl-total-amt')+'" readonly></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-del-row px-2">−</button></td>'
        + '</tr>';

    jQuery('#items-body').append(row);
    bindRowEvents(jQuery('#items-body tr:last'));
    clearTemplate();
    updateSerials();
    recalcTotals();
});

/* ── Clear template row ──────────────────────────────────── */
function clearTemplate() {
    jQuery('#tpl-ledger-cat').val('');
    jQuery('#tpl-ledger').html('<option value="">Ledger…</option>');
    jQuery('#tpl-particular, #tpl-hsn').val('');
    jQuery('#tpl-qty').val('1');
    jQuery('#tpl-rate, #tpl-item-total, #tpl-sgst-pct, #tpl-sgst-amt, #tpl-cgst-pct, #tpl-cgst-amt, #tpl-igst-pct, #tpl-igst-amt, #tpl-total-amt').val('0');
}

/* ── Bind events to an item row ──────────────────────────── */
function bindRowEvents(row) {
    row.find('.r-qty, .r-rate').on('input', function() { calcItemRow(row); });
    row.find('.r-sgst-pct, .r-cgst-pct, .r-igst-pct').on('input', function() { calcItemTax(row); });
    row.find('.btn-del-row').on('click', function() {
        if (jQuery('#items-body .item-row').length <= 1) { Swal.fire({icon:'warning',title:'Warning',text:'At least one line item is required.'}); return; }
        row.remove(); updateSerials(); recalcTotals();
    });
}

/* ── Calculate a single item row ─────────────────────────── */
function calcItemRow(row) {
    var qty  = parseFloat(row.find('.r-qty').val()) || 0;
    var rate = parseFloat(row.find('.r-rate').val()) || 0;
    var it   = qty * rate;
    row.find('.r-item-total').val(it.toFixed(2));
    calcItemTax(row);
}

function calcItemTax(row) {
    var it = parseFloat(row.find('.r-item-total').val()) || 0;
    var sp = parseFloat(row.find('.r-sgst-pct').val()) || 0;
    var cp = parseFloat(row.find('.r-cgst-pct').val()) || 0;
    var ip = parseFloat(row.find('.r-igst-pct').val()) || 0;
    row.find('.r-sgst-amt').val((it * sp / 100).toFixed(2));
    row.find('.r-cgst-amt').val((it * cp / 100).toFixed(2));
    row.find('.r-igst-amt').val((it * ip / 100).toFixed(2));
    var total = it + parseFloat(row.find('.r-sgst-amt').val()) + parseFloat(row.find('.r-cgst-amt').val()) + parseFloat(row.find('.r-igst-amt').val());
    row.find('.r-total-amt').val(total.toFixed(2));
    recalcTotals();
}

/* ── Recalc summary totals ───────────────────────────────── */
function recalcTotals() {
    var grand = 0, sgst = 0, cgst = 0, igst = 0;
    jQuery('#items-body .item-row').each(function() {
        grand += parseFloat(jQuery(this).find('.r-item-total').val()) || 0;
        sgst  += parseFloat(jQuery(this).find('.r-sgst-amt').val()) || 0;
        cgst  += parseFloat(jQuery(this).find('.r-cgst-amt').val()) || 0;
        igst  += parseFloat(jQuery(this).find('.r-igst-amt').val()) || 0;
    });
    jQuery('#grandtotal').val(grand.toFixed(2));
    jQuery('#sgstamount').val(sgst.toFixed(2));
    jQuery('#cgstamount').val(cgst.toFixed(2));
    jQuery('#igstamount').val(igst.toFixed(2));
    calcNet();
}

function calcNet() {
    var grand   = pf('#grandtotal'), sgst = pf('#sgstamount'), cgst = pf('#cgstamount'), igst = pf('#igstamount');
    var roundOff = parseFloat(jQuery('#round_off').val()) || 0;
    var expTotal = 0;
    jQuery('#expenses-body .exp-amount').each(function() { expTotal += parseFloat(jQuery(this).val()) || 0; });
    var net = grand + sgst + cgst + igst + roundOff + expTotal;
    jQuery('#nettotal').val(net.toFixed(2));
    jQuery('#payment_amount').val(net.toFixed(2));
}

jQuery('#round_off').on('input', calcNet);

/* ── Serial numbers ──────────────────────────────────────── */
function updateSerials() {
    jQuery('#items-body .item-row .sno').each(function(i) { jQuery(this).text(i + 1); });
}

/* ── Init existing rows ──────────────────────────────────── */
jQuery('#items-body .item-row').each(function() { bindRowEvents(jQuery(this)); });
recalcTotals();

/* ── Payment status toggle ───────────────────────────────── */
jQuery('#payment_status').on('change', function() {
    if (jQuery(this).val() == '1') {
        jQuery('#payment-details').show();
        jQuery('#payment_amount').val(pf('#nettotal').toFixed(2));
    } else {
        jQuery('#payment-details').hide();
    }
});

/* ── Payment type → ledgers ──────────────────────────────── */
jQuery('#payment_type').on('change', function() {
    var cid = jQuery(this).val();
    jQuery('#payment_ledger_id').html('<option value="">— Select —</option>');
    if (cid) {
        jQuery.post('{$getPaymentLedgersUrl}', {payment_type: cid}, function(resp) {
            if (resp.status == 1 && resp.data) {
                jQuery.each(resp.data, function(id, title) {
                    jQuery('#payment_ledger_id').append('<option value="'+id+'">'+title+'</option>');
                });
            }
        }, 'json');
    }
});

/* ── Pre-load payment ledger on edit ─────────────────────── */
var existPT = {$existingPaymentType}, existPL = {$existingPaymentLedger};
if (existPT > 0) {
    jQuery.post('{$getPaymentLedgersUrl}', {payment_type: existPT}, function(resp) {
        if (resp.status == 1 && resp.data) {
            jQuery.each(resp.data, function(id, title) {
                var opt = '<option value="'+id+'"'+(id == existPL ? ' selected' : '')+'>'+title+'</option>';
                jQuery('#payment_ledger_id').append(opt);
            });
        }
    }, 'json');
}

/* ── Extra Expenses ──────────────────────────────────────── */
jQuery('#btn-add-expense').on('click', function() {
    jQuery('#exp-ledger-cat, #exp-ledger, #exp-title, #exp-amount').val('');
    jQuery('#exp-ledger').html('<option value="">— Select —</option>');
    new bootstrap.Modal(document.getElementById('expenseModal')).show();
});

jQuery('#exp-ledger-cat').on('change', function() {
    var cid = jQuery(this).val();
    var sel = jQuery('#exp-ledger');
    sel.html('<option value="">— Select —</option>');
    if (cid) {
        jQuery.post('{$autocompleteLedgerUrl}', {categoryId: cid}, function(resp) {
            if (resp.status == 1 && resp.data) {
                jQuery.each(resp.data, function(id, title) { sel.append('<option value="'+id+'">'+title+'</option>'); });
            }
        }, 'json');
    }
});

jQuery('#btn-expense-add').on('click', function() {
    var amt = parseFloat(jQuery('#exp-amount').val()) || 0;
    if (amt <= 0) { Swal.fire({icon:'error',title:'Error',text:'Amount must be > 0.'}); return; }
    var ledgerId   = jQuery('#exp-ledger').val() || '';
    var ledgerText = jQuery('#exp-ledger option:selected').text() || '';
    var title      = jQuery('#exp-title').val().trim() || ledgerText;

    var row = '<tr class="expense-row exp-item">'
        + '<td></td>'
        + '<td colspan="2" class="small text-muted">'+esc(ledgerText)+'<input type="hidden" name="expense_ledger_id[]" value="'+ledgerId+'"></td>'
        + '<td colspan="10"><input type="text" name="expense_title[]" class="form-control form-control-sm" value="'+esc(title)+'"></td>'
        + '<td colspan="2"><input type="number" name="expense_amount[]" class="form-control form-control-sm exp-amount" step="0.01" min="0" value="'+amt.toFixed(2)+'"></td>'
        + '<td><button type="button" class="btn btn-sm btn-outline-danger btn-del-expense px-2">−</button></td>'
        + '</tr>';
    jQuery('#expenses-body').append(row);
    bindExpenseEvents(jQuery('#expenses-body tr:last'));
    bootstrap.Modal.getInstance(document.getElementById('expenseModal')).hide();
    calcNet();
});

function bindExpenseEvents(row) {
    row.find('.exp-amount').on('input', calcNet);
    row.find('.btn-del-expense').on('click', function() { row.remove(); calcNet(); });
}
jQuery('#expenses-body .exp-item').each(function() { bindExpenseEvents(jQuery(this)); });

/* ── Delete attachment ───────────────────────────────────── */
jQuery('.btn-del-att').on('click', function() {
    var btn = jQuery(this), id = btn.data('id');
    Swal.fire({icon:'warning',title:'Delete attachment?',showCancelButton:true,confirmButtonColor:'#d33',confirmButtonText:'Yes, delete'}).then(function(r) {
        if (r.isConfirmed) {
            jQuery.post('{$deleteAttachmentUrl}?id='+id, function(resp) {
                if (resp.status == 1) { jQuery('#att-'+id).remove(); }
                else { Swal.fire({icon:'error',title:'Error',text:resp.msg}); }
            }, 'json');
        }
    });
});

/* ── Form validation ─────────────────────────────────────── */
jQuery('#inward-invoice-form').on('submit', function(e) {
    var errors = [];
    if (!jQuery('#invoice_number').val().trim()) errors.push('Invoice Number is required.');
    if (!jQuery('#invoice_date').val().trim()) errors.push('Invoice Date is required.');
    if (jQuery('#items-body .item-row').length === 0) errors.push('At least one line item is required.');
    if (pf('#nettotal') <= 0) errors.push('Net Total must be > 0.');

    var ps = jQuery('#payment_status').val();
    if (ps === '') errors.push('Payment Status is required.');
    if (ps == '1') {
        if (!jQuery('#payment_type').val()) errors.push('Payment Type is required when Paid.');
        if (!jQuery('#payment_ledger_id').val()) errors.push('Payment Ledger is required when Paid.');
        if (pf('#payment_amount') <= 0) errors.push('Payment Amount must be > 0 when Paid.');
        if ((pf('#payment_amount') + pf('#tds_amount')) > pf('#nettotal') + 0.01) errors.push('Payment + TDS cannot exceed Net Total.');
    }

    if (errors.length) {
        e.preventDefault();
        Swal.fire({icon:'error',title:'Validation Error',html:errors.join('<br>')});
        return false;
    }

    e.preventDefault();
    Swal.fire({icon:'question',title:'Submit Invoice?',text:'Are you sure you want to save this invoice?',showCancelButton:true,confirmButtonText:'Yes, save'}).then(function(r) {
        if (r.isConfirmed) { e.target.submit(); }
    });
});

/* ── Helpers ─────────────────────────────────────────────── */
function pf(sel) { return parseFloat(jQuery(sel).val()) || 0; }
function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
JS, \yii\web\View::POS_READY);
?>
