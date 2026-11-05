<?php
/**
 * Inward invoice form (create & update). Line maths and payment toggles live in invoices.js;
 * the server recomputes every total on save.
 *
 * @var \yii\web\View $this
 * @var \backend\models\InwardInvoice $model
 * @var \backend\models\Vendor $vendor
 * @var \backend\models\InwardInvoiceDetails[] $lines
 * @var \backend\models\InwardInvoiceDetails[] $expenses
 * @var array $ledgerGroups    [category title => [ledger id => title]]
 * @var array $paymentTypes    [category id => title]
 * @var array $paymentLedgers  [category id => [ledger id => title]]
 */

use backend\components\SafeUpload;
use backend\models\InwardInvoice;
use backend\models\Vendor;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$isNew = $model->isNewRecord;
$type  = (int)$vendor->vendor_type;
$taxes = match ($type) {
    Vendor::TYPE_INTRA_STATE => ['sgst' => 'SGST', 'cgst' => 'CGST'],
    Vendor::TYPE_INTER_STATE => ['igst' => 'IGST'],
    default                  => [],
};
$money = fn($v) => number_format((float)$v, 2, '.', '');
$err   = fn(string $attr) => $model->hasErrors($attr) ? '<div class="invalid-feedback d-block">' . Html::encode($model->getFirstError($attr)) . '</div>' : '';
$bad   = fn(string $attr) => $model->hasErrors($attr) ? ' is-invalid' : '';
$isPaid = (string)$model->payment_status === (string)InwardInvoice::PAY_PAID;

/** One ledger <select> with categories as groups. */
$ledgerSelect = function (string $name, $selected, string $cls = '') use ($ledgerGroups) {
    $html = '<select name="' . $name . '" class="form-select form-select-sm js-ledger ' . $cls . '" aria-label="Ledger"><option value="">No ledger</option>';
    foreach ($ledgerGroups as $group => $items) {
        $html .= '<optgroup label="' . Html::encode($group) . '">';
        foreach ($items as $id => $title) {
            $html .= '<option value="' . $id . '"' . ((string)$selected === (string)$id ? ' selected' : '') . '>' . Html::encode($title) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html . '</select>';
};

/** A line row; $i is the array index ("__i__" in the template). */
$lineRow = function ($i, $d) use ($taxes, $ledgerSelect, $money) {
    $f = fn($k) => 'lines[' . $i . '][' . $k . ']';
    $html = '<tr class="js-line">'
        . '<td class="cell-num muted js-sno"></td>'
        . '<td class="cell-title" style="min-width:220px">'
        .   '<input type="text" name="' . $f('product') . '" class="form-control form-control-sm js-product" maxlength="5000" placeholder="What was bought" value="' . Html::encode($d->product ?? '') . '" aria-label="Description">'
        .   '<div class="d-flex gap-1 mt-1"><input type="text" name="' . $f('hsn') . '" class="form-control form-control-sm" style="max-width:110px" maxlength="500" placeholder="HSN/SAC" value="' . Html::encode($d->hsn ?? '') . '" aria-label="HSN or SAC code">'
        .   '<div class="flex-fill">' . $ledgerSelect($f('ledger_id'), $d->ledger_id ?? '') . '</div></div>'
        . '</td>'
        . '<td data-label="Qty"><input type="number" name="' . $f('qty') . '" class="form-control form-control-sm text-end js-qty" step="any" min="0" value="' . Html::encode($d->qty ?? 1) . '" aria-label="Quantity"></td>'
        . '<td data-label="Rate (₹)"><input type="number" name="' . $f('price_per_qty') . '" class="form-control form-control-sm text-end js-rate" step="0.01" min="0" value="' . Html::encode($d->price_per_qty ?? '') . '" aria-label="Rate"></td>';
    foreach ($taxes as $k => $label) {
        $html .= '<td data-label="' . $label . ' %"><input type="number" name="' . $f($k . '_percent') . '" class="form-control form-control-sm text-end js-tax" data-tax="' . $k . '" step="0.01" min="0" max="100" value="' . Html::encode((float)($d->{$k . '_percent'} ?? 0)) . '" aria-label="' . $label . ' percent"></td>';
    }
    return $html
        . '<td class="text-end num fw-semibold js-line-total" data-label="Amount">' . $money($d->total_amount ?? 0) . '</td>'
        . '<td class="cell-actions text-end"><button type="button" class="btn btn-outline-danger act-btn js-remove-line" title="Remove line" aria-label="Remove line"><i class="fas fa-times"></i></button></td>'
        . '</tr>';
};

$expenseRow = function ($i, $e) use ($ledgerSelect, $money) {
    $f = fn($k) => 'expenses[' . $i . '][' . $k . ']';
    return '<div class="row g-2 align-items-center js-expense mb-2">'
        . '<div class="col-md-5"><input type="text" name="' . $f('product') . '" class="form-control form-control-sm" maxlength="5000" placeholder="e.g. Courier charges" value="' . Html::encode($e->product ?? '') . '" aria-label="Expense description"></div>'
        . '<div class="col-md-4">' . $ledgerSelect($f('ledger_id'), $e->ledger_id ?? '') . '</div>'
        . '<div class="col-md-2 col-9"><input type="number" name="' . $f('amount') . '" class="form-control form-control-sm text-end js-expense-amount" step="0.01" min="0" placeholder="0.00" value="' . Html::encode(isset($e->item_total) && (float)$e->item_total ? $money($e->item_total) : '') . '" aria-label="Expense amount"></div>'
        . '<div class="col-md-1 col-3 text-end"><button type="button" class="btn btn-outline-danger act-btn js-remove-expense" title="Remove expense" aria-label="Remove expense"><i class="fas fa-times"></i></button></div>'
        . '</div>';
};
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 d-flex flex-wrap gap-3 align-items-center small">
        <span><span class="text-muted">Vendor</span> <strong><?= Html::encode($vendor->company_name) ?></strong></span>
        <span class="ui-pill tone-<?= $type === Vendor::TYPE_INTRA_STATE ? 'success' : ($type === Vendor::TYPE_INTER_STATE ? 'accent' : 'neutral') ?>"><?= Html::encode(Vendor::typeLabels()[$type] ?? '—') ?></span>
        <span><span class="text-muted">GSTIN</span> <span class="mono"><?= Html::encode($vendor->gst_no ?: '—') ?></span></span>
        <span class="text-muted ms-md-auto"><?= $taxes ? implode(' + ', $taxes) . ' applies to this vendor.' : 'No GST applies to international vendors.' ?></span>
    </div>
</div>

<form id="inward-form" class="js-inward-form" method="post" enctype="multipart/form-data" novalidate
      data-ledgers="<?= Html::encode(json_encode($paymentLedgers)) ?>">
    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
    <?php if ($model->hasErrors()): ?>
        <div class="alert alert-danger" role="alert">
            <p class="mb-1">Please fix the following:</p>
            <ul class="mb-0"><?php foreach ($model->getErrorSummary(true) as $e): ?><li><?= Html::encode($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <p class="ui-req-note">Fields marked <b>*</b> are required.</p>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Invoice</div>
        <div class="card-body row g-3 ui-form">
            <div class="col-md-4 required">
                <label class="form-label" for="ii-number">Vendor invoice number</label>
                <input type="text" id="ii-number" name="InwardInvoice[invoice_number]" class="form-control form-control-sm<?= $bad('invoice_number') ?>" maxlength="100" required
                       value="<?= Html::encode($model->invoice_number) ?>" placeholder="As printed on the bill" autocomplete="off">
                <?= $err('invoice_number') ?>
            </div>
            <div class="col-md-3 required">
                <label class="form-label" for="ii-date">Invoice date</label>
                <?= DatePicker::widget([
                    'name' => 'InwardInvoice[invoice_date]', 'value' => $model->invoice_date, 'type' => DatePicker::TYPE_INPUT,
                    'options' => ['id' => 'ii-date', 'class' => 'form-control form-control-sm' . $bad('invoice_date'), 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off', 'required' => true],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true, 'endDate' => date('Y-m-d')],
                ]) ?>
                <?= $err('invoice_date') ?>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="ii-title">Title</label>
                <input type="text" id="ii-title" name="InwardInvoice[title]" class="form-control form-control-sm" maxlength="5000" value="<?= Html::encode($model->title) ?>" placeholder="What this purchase is for (optional)">
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section d-flex align-items-center">Items <span class="ui-section-meta ms-2">Amounts include tax</span></div>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0 inward-lines">
                <thead class="table-light">
                <tr>
                    <th style="width:36px">#</th>
                    <th>Description · HSN · Ledger</th>
                    <th class="text-end" style="width:90px">Qty</th>
                    <th class="text-end" style="width:120px">Rate (₹)</th>
                    <?php foreach ($taxes as $label): ?><th class="text-end" style="width:90px"><?= $label ?> %</th><?php endforeach; ?>
                    <th class="text-end" style="width:120px">Amount</th>
                    <th style="width:44px"><span class="visually-hidden">Remove</span></th>
                </tr>
                </thead>
                <tbody id="ii-lines">
                <?php foreach ($lines ?: [null] as $i => $d): ?>
                    <?= $lineRow($i, $d ?? new \backend\models\InwardInvoiceDetails(['qty' => 1])) ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body pt-2">
            <button type="button" class="btn btn-outline-primary btn-sm js-add-line"><i class="fas fa-plus me-1"></i>Add line</button>
        </div>
        <template id="ii-line-tpl"><?= $lineRow('__i__', new \backend\models\InwardInvoiceDetails(['qty' => 1])) ?></template>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">Extra expenses <span class="ui-section-meta">Freight, packing, etc. — no tax</span></div>
                <div class="card-body">
                    <div id="ii-expenses">
                        <?php foreach ($expenses as $i => $e): ?><?= $expenseRow($i, $e) ?><?php endforeach; ?>
                    </div>
                    <p class="small text-muted mb-2 js-no-expenses"<?= $expenses ? ' hidden' : '' ?>>No extra expenses.</p>
                    <button type="button" class="btn btn-outline-secondary btn-sm js-add-expense"><i class="fas fa-plus me-1"></i>Add expense</button>
                    <template id="ii-expense-tpl"><?= $expenseRow('__i__', null) ?></template>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">Totals</div>
                <div class="card-body">
                    <dl class="inv-totals mb-0">
                        <dt>Sub total</dt><dd class="num js-sum-sub">0.00</dd>
                        <?php foreach ($taxes as $k => $label): ?><dt><?= $label ?></dt><dd class="num js-sum-<?= $k ?>">0.00</dd><?php endforeach; ?>
                        <dt>Extra expenses</dt><dd class="num js-sum-extra">0.00</dd>
                        <dt><label for="ii-round" class="mb-0">Round off</label></dt>
                        <dd><input type="number" id="ii-round" name="InwardInvoice[round_off]" class="form-control form-control-sm text-end js-round<?= $bad('round_off') ?>" step="0.01" min="-1000" max="1000"
                                   value="<?= Html::encode($money($model->round_off)) ?>" style="max-width:130px;margin-left:auto"><?= $err('round_off') ?></dd>
                        <dt class="is-net">Net total</dt><dd class="num is-net">₹ <span class="js-sum-net">0.00</span></dd>
                    </dl>
                    <?= $err('net_total') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Payment</div>
        <div class="card-body row g-3 ui-form">
            <div class="col-12 required">
                <span class="form-label d-block" id="ii-pay-label">Payment status</span>
                <div role="radiogroup" aria-labelledby="ii-pay-label" class="d-flex gap-3">
                    <?php foreach (InwardInvoice::paymentOptions() as $v => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input js-pay-status<?= $bad('payment_status') ?>" type="radio" name="InwardInvoice[payment_status]" id="ii-pay-<?= $v ?>" value="<?= $v ?>"
                                <?= (string)$model->payment_status === (string)$v ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ii-pay-<?= $v ?>"><?= $label ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?= $err('payment_status') ?>
            </div>
            <div class="col-12 js-paid-fields"<?= $isPaid ? '' : ' hidden' ?>>
                <div class="row g-3">
                    <div class="col-md-3 required">
                        <label class="form-label" for="ii-pay-type">Payment type</label>
                        <?= Html::dropDownList('InwardInvoice[payment_type]', $model->payment_type, $paymentTypes,
                            ['id' => 'ii-pay-type', 'class' => 'form-select form-select-sm js-pay-type' . $bad('payment_type'), 'prompt' => 'Select']) ?>
                        <?= $err('payment_type') ?>
                    </div>
                    <div class="col-md-3 required">
                        <label class="form-label" for="ii-pay-ledger">Paid from</label>
                        <?= Select2::widget([
                            'name' => 'InwardInvoice[payment_ledger_id]', 'value' => $model->payment_ledger_id,
                            'data' => $paymentLedgers[(int)$model->payment_type] ?? [],
                            'options' => ['id' => 'ii-pay-ledger', 'placeholder' => 'Select ledger', 'class' => 'js-pay-ledger'], 'size' => Select2::SMALL,
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                        <?= $err('payment_ledger_id') ?>
                    </div>
                    <div class="col-md-2 required">
                        <label class="form-label" for="ii-pay-date">Payment date</label>
                        <?= DatePicker::widget([
                            'name' => 'InwardInvoice[payment_date]', 'value' => $model->payment_date, 'type' => DatePicker::TYPE_INPUT,
                            'options' => ['id' => 'ii-pay-date', 'class' => 'form-control form-control-sm' . $bad('payment_date'), 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true, 'endDate' => date('Y-m-d')],
                        ]) ?>
                        <?= $err('payment_date') ?>
                    </div>
                    <div class="col-md-2 col-6 required">
                        <label class="form-label" for="ii-pay-amount">Amount paid (₹)</label>
                        <input type="number" id="ii-pay-amount" name="InwardInvoice[payment_amount]" class="form-control form-control-sm text-end js-pay-amount<?= $bad('payment_amount') ?>" step="0.01" min="0"
                               value="<?= (float)$model->payment_amount ? Html::encode($money($model->payment_amount)) : '' ?>">
                        <?= $err('payment_amount') ?>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label" for="ii-tds">TDS deducted (₹)</label>
                        <input type="number" id="ii-tds" name="InwardInvoice[tds_amount]" class="form-control form-control-sm text-end js-tds<?= $bad('tds_amount') ?>" step="0.01" min="0"
                               value="<?= (float)$model->tds_amount ? Html::encode($money($model->tds_amount)) : '' ?>">
                        <?= $err('tds_amount') ?>
                    </div>
                    <div class="col-12 small text-muted js-pay-hint"></div>
                </div>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="ii-remarks">Remarks</label>
                <textarea id="ii-remarks" name="InwardInvoice[payment_remarks]" class="form-control form-control-sm" rows="2" maxlength="5000" placeholder="Optional"><?= Html::encode($model->payment_remarks) ?></textarea>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Attachments</div>
        <div class="card-body">
            <?php if (!$isNew && $model->attachments): ?>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($model->attachments as $k => $att): ?>
                        <li class="ui-file d-flex align-items-center gap-2 mb-1" id="ii-att-<?= $att->id ?>">
                            <i class="fas fa-paperclip text-muted" aria-hidden="true"></i>
                            <?= Html::a('Attachment ' . ($k + 1) . ' (' . strtoupper(pathinfo($att->path, PATHINFO_EXTENSION)) . ')', Url::to('@web' . $att->path), ['target' => '_blank', 'rel' => 'noopener']) ?>
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-2" data-ii-delete-att="<?= $att->id ?>"
                                    data-url="<?= Url::to(['delete-attachment', 'id' => $att->id]) ?>">Remove</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <label class="form-label" for="ii-files">Add files</label>
            <input type="file" id="ii-files" name="attachments[]" multiple class="form-control form-control-sm" style="max-width:420px" accept="<?= SafeUpload::acceptAttribute() ?>">
            <div class="form-text">PDF, images, Office files or ZIP; up to <?= SafeUpload::MAX_BYTES / 1024 / 1024 ?> MB each.</div>
        </div>
    </div>

    <div class="ui-form-actions">
        <button type="submit" class="btn btn-primary btn-sm px-4"><i class="fas fa-check me-1"></i><?= $isNew ? 'Save invoice' : 'Save changes' ?></button>
        <?= Html::a('Cancel', $isNew ? ['index'] : ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>
</form>
