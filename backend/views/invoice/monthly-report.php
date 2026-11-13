<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Invoice[] $outInvoices */
/** @var array $outTotals */
/** @var \backend\models\InwardInvoice[] $inInvoices */
/** @var array $inTotals */
/** @var array $netTax   sgst, cgst, igst, total — output tax minus input credit */
/** @var int $year */
/** @var int $month */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$label = date('F Y', mktime(0, 0, 0, $month, 1, $year));
$this->title = 'Monthly GST Report — ' . $label;

$money = fn($v) => number_format((float)$v, 2);
$step  = fn(int $d) => ['monthly-report', 'year' => (int)date('Y', mktime(0, 0, 0, $month + $d, 1, $year)), 'month' => (int)date('n', mktime(0, 0, 0, $month + $d, 1, $year))];
$isFuture = mktime(0, 0, 0, $month + 1, 1, $year) > time();

$monthOptions = [];
for ($t = strtotime(date('Y-m-01')); $t >= strtotime('first day of -36 months'); $t = strtotime('-1 month', $t)) {
    $monthOptions[date('Y-n', $t)] = date('F Y', $t);
}

$tabs = array_filter([
    'index'          => UserAccess::checkAccess('invoice/index') ? 'Tax invoices' : null,
    'proforma'       => UserAccess::checkAccess('invoice/proforma') ? 'Proforma' : null,
    'monthly-report' => 'Monthly GST report',
]);

/** One GST table (outward sales or inward purchases). */
$table = function (array $rows, array $totals, string $party, callable $partyName, callable $link) use ($money) {
    ob_start(); ?>
    <div class="table-responsive">
        <table class="table list-table ui-table stack mb-0">
            <thead class="table-light">
            <tr><th style="width:40px">#</th><th>Invoice</th><th><?= $party ?></th><th class="text-end">Taxable</th><th class="text-end">SGST</th><th class="text-end">CGST</th><th class="text-end">IGST</th><th class="text-end">Net</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr class="ui-empty-row"><td colspan="8" class="ui-empty-cell"><?= $this->render('@backend/views/_ui/_empty', ['icon' => 'file-invoice', 'title' => 'None this month', 'small' => true]) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td class="cell-num muted"><?= $i + 1 ?></td>
                    <td class="cell-title"><?= $link($r) ?><div class="small text-muted fw-normal"><?= date('d M', strtotime($r->invoice_date)) ?></div></td>
                    <td class="small" data-label="<?= $party ?>"><?= Html::encode($partyName($r)) ?></td>
                    <td class="text-end num" data-label="Taxable"><?= $money($r->grand_total) ?></td>
                    <td class="text-end num" data-label="SGST"><?= (float)$r->sgst_amount ? $money($r->sgst_amount) : '—' ?></td>
                    <td class="text-end num" data-label="CGST"><?= (float)$r->cgst_amount ? $money($r->cgst_amount) : '—' ?></td>
                    <td class="text-end num" data-label="IGST"><?= (float)$r->igst_amount ? $money($r->igst_amount) : '—' ?></td>
                    <td class="text-end num fw-semibold" data-label="Net"><?= $money($r->net_total) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($rows): ?>
            <tfoot class="table-light"><tr>
                <td colspan="3" class="text-end small fw-semibold">Total</td>
                <td class="text-end num fw-semibold"><?= $money($totals['grand']) ?></td><td class="text-end num fw-semibold"><?= $money($totals['sgst']) ?></td>
                <td class="text-end num fw-semibold"><?= $money($totals['cgst']) ?></td><td class="text-end num fw-semibold"><?= $money($totals['igst']) ?></td>
                <td class="text-end num fw-bold"><?= $money($totals['net']) ?></td>
            </tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php return ob_get_clean();
};
$tile = fn(string $name, float $v) => '<div class="ui-tile' . ($name === 'Total' ? ' is-accent' : '') . '"><div class="t-label">' . $name . ' ' . ($v < 0 ? 'credit' : 'payable') . '</div>'
    . '<div class="t-value num' . ($v < 0 ? ' text-success' : '') . '">₹' . number_format(abs($v), 2) . '</div><div class="t-sub">' . ($v < 0 ? 'Input exceeds output' : 'Output − input') . '</div></div>';
?>
<div class="ui-page invoice-monthly-report">
    <div class="ui-head">
        <div>
            <h1>GST for <?= Html::encode($label) ?></h1>
            <div class="ui-sub"><?= count($outInvoices) ?> tax invoice<?= count($outInvoices) == 1 ? '' : 's' ?> out · <?= count($inInvoices) ?> purchase invoice<?= count($inInvoices) == 1 ? '' : 's' ?> in</div>
        </div>
        <div class="ui-actions">
            <form method="get" action="<?= Url::to(['monthly-report']) ?>" class="d-flex gap-1 js-month-form">
                <label class="visually-hidden" for="gst-month">Month</label>
                <div style="min-width:180px"><?= \kartik\select2\Select2::widget(['name' => 'ym', 'value' => $year . '-' . $month, 'data' => $monthOptions, 'size' => \kartik\select2\Select2::SMALL, 'options' => ['id' => 'gst-month'], 'pluginOptions' => ['width' => '100%']]) ?></div>
                <input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $month ?>">
            </form>
            <div class="btn-group btn-group-sm" role="group" aria-label="Change month">
                <?= Html::a('<i class="fas fa-chevron-left"></i><span class="visually-hidden">Previous month</span>', $step(-1), ['class' => 'btn btn-outline-secondary', 'encode' => false, 'title' => 'Previous month']) ?>
                <?= $isFuture ? '<span class="btn btn-outline-secondary disabled" aria-disabled="true"><i class="fas fa-chevron-right"></i></span>'
                    : Html::a('<i class="fas fa-chevron-right"></i><span class="visually-hidden">Next month</span>', $step(1), ['class' => 'btn btn-outline-secondary', 'encode' => false, 'title' => 'Next month']) ?>
            </div>
        </div>
    </div>

    <?php if (count($tabs) > 1): ?>
    <nav class="ui-tabs" aria-label="Invoice lists">
        <?php foreach ($tabs as $r => $tab): ?>
            <?= Html::a($tab, [$r], ['class' => $r === 'monthly-report' ? 'active' : '', 'aria-current' => $r === 'monthly-report' ? 'page' : null]) ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="ui-tiles">
        <?= $tile('SGST', (float)$netTax['sgst']) . $tile('CGST', (float)$netTax['cgst']) . $tile('IGST', (float)$netTax['igst']) . $tile('Total', (float)$netTax['total']) ?>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Sales — tax invoices <span class="ui-section-meta">Output tax ₹<?= $money($outTotals['sgst'] + $outTotals['cgst'] + $outTotals['igst']) ?></span></div>
        <?= $table($outInvoices, $outTotals, 'Client', fn($r) => $r->client->company_name ?? $r->client->client_name ?? '—',
            fn($r) => UserAccess::checkAccess('invoice/view') ? Html::a(Html::encode($r->invoice_no), ['view', 'id' => $r->id], ['class' => 'text-reset mono', 'target' => '_blank', 'rel' => 'noopener']) : '<span class="mono">' . Html::encode($r->invoice_no) . '</span>') ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">Purchases — inward invoices <span class="ui-section-meta">Input credit ₹<?= $money($inTotals['sgst'] + $inTotals['cgst'] + $inTotals['igst']) ?></span></div>
        <?= $table($inInvoices, $inTotals, 'Vendor', fn($r) => $r->vendor->company_name ?? '—',
            fn($r) => UserAccess::checkAccess('inward-invoice/view') ? Html::a(Html::encode($r->invoice_number), ['/inward-invoice/view', 'id' => $r->id], ['class' => 'text-reset']) : Html::encode($r->invoice_number)) ?>
    </div>
</div>
