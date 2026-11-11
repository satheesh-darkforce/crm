<?php
/**
 * Invoice document body — shared between view.php (in-app) and print.php (standalone/PDF).
 * Mirrors Yii1 protected/views/invoice/view.php exactly: 793×1122 letterhead canvas,
 * line items table, tax rows by invoice_type, payment bank details block.
 *
 * @var \backend\models\Invoice $model
 * @var string                 $imgBasePath  Base URL/path used to load images/bg.jpg
 * @var bool                   $isPdf        Optional: render-for-mPDF mode
 * @var string|null            $previewInvoiceNo  Predicted next invoice number for drafts
 */

use common\components\Utility;

$isPdf = $isPdf ?? false;
$displayInvoiceNo = trim((string)($model->invoice_no ?? '')) !== ''
    ? $model->invoice_no
    : (isset($previewInvoiceNo) ? $previewInvoiceNo : '');
?>
<section id="invoice_div" style="height:1122px">
<div id="printdiv" style="background:url(<?= $imgBasePath ?>images/bg.jpg) top no-repeat; font-family:arial; width:793px; height:1122px; margin:auto; font-size:16px; margin-top:15px;">

    <div style="margin-bottom:10px;padding-top: 75px;">

            <?php
            $__addr = (string)($model->to_address ?? '');
            $__addr = preg_replace('/<br\s*\/?>/i', "\n", $__addr);
            ?>
            <!-- Two-column row: To address (left) and invoice header (right) on
                 the same row — mirrors Yii1. Uses a borderless table so it
                 renders identically in both the browser and mPDF (mPDF only
                 partially supports CSS float). -->
            <table style="width:100%; border-collapse:collapse; line-height:1.5; margin-top:15px;">
                <tr>
                    <td style="vertical-align:top; width:60%; padding:0 15px 0 60px; line-height:24px;">
                        <p style="padding-bottom:0; margin:0 0 4px 0;"><strong>To</strong></p>
                        <div style="padding-left:15px;">
                            <?= nl2br(htmlspecialchars($__addr)) ?>
                        </div>
                    </td>
                    <td style="vertical-align:top; width:40%; text-align:right; padding-right:60px;">
                        <?php if ((int)$model->invoice_role === 1): // Tax Invoice ?>
                            <p style="margin:0 0 4px 0;"><strong>Invoice No:</strong> <?= htmlspecialchars($displayInvoiceNo) ?> </p>
                        <?php endif; ?>
                        <?php if ((int)$model->invoice_role === 2): // Proforma — matches Yii1: text only, no number ?>
                            <p style="margin:0 0 4px 0;">Proforma Invoice</p>
                        <?php endif; ?>
                        <p style="margin:0 0 4px 0;"><strong>Date:</strong> <?= $model->invoice_date ? date('Y-m-d', strtotime($model->invoice_date)) : '' ?></p>
                        <?php if (isset($model->lut_arn) && $model->lut_arn != ""): ?>
                        <p style="margin:0 0 4px 0;"><strong>LUT ARN : </strong> <?= htmlspecialchars($model->lut_arn) ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div style="clear:both; text-align:center; text-decoration:underline; font-weight:bold; width:100%; box-sizing:border-box; padding:0 60px; margin:20px 0; font-size:18px;">
                <?= htmlspecialchars($model->title ?? '') ?>
            </div>


            <?php
                // Detect whether any line item carries sale-only data so we
                // know whether to render the Model/Qty/Rate columns. Only show
                // these columns for sale invoices, and only if at least one row
                // actually has values to display.
                // Each sale column is independent — show MODEL only when at
                // least one row has a model name, QTY only when at least one
                // row has qty > 0, RATE only when at least one row has rate > 0.
                $cat = (int)($model->invoice_category ?? 1);
                $hasModel = false; $hasQty = false; $hasRate = false;
                if ($cat === 2 && isset($model->details) && !empty($model->details)) {
                    foreach ($model->details as $d) {
                        $m = $d->hasAttribute('model')     ? trim((string)($d->model ?? '')) : '';
                        $q = $d->hasAttribute('quantity')  ? ($d->quantity ?? null)          : null;
                        $r = $d->hasAttribute('unit_rate') ? ($d->unit_rate ?? null)         : null;
                        if ($m !== '')                              $hasModel = true;
                        if ($q !== null && (float)$q > 0)           $hasQty   = true;
                        if ($r !== null && (float)$r > 0)           $hasRate  = true;
                        if ($hasModel && $hasQty && $hasRate)       break;
                    }
                }
                $extraCols   = ($hasModel ? 1 : 0) + ($hasQty ? 1 : 0) + ($hasRate ? 1 : 0);
                $hasSaleCols = $extraCols > 0;

                // Totals prefix: Service (no extra cols) covers S.No + HSN as
                // two empty cells, label spans the Particulars column.
                // Sale (any extra cols) covers S.No + HSN + Particulars as a
                // colspan=3 cell; label then spans the extra cols (1–3).
                $totalsPrefix = $hasSaleCols
                    ? '<td style="border:1px solid black; padding:5px 10px;" colspan="3"></td>'
                    : '<td style="border:1px solid black; padding:5px 10px;"></td>'
                    . '<td style="border:1px solid black; padding:5px 10px;"></td>';
                $totalsPrefixRowspan = $hasSaleCols
                    ? '<td rowspan="2" style="border:1px solid black; padding:5px 10px;" colspan="3"></td>'
                    : '<td rowspan="2" style="border:1px solid black; padding:5px 10px;"></td>'
                    . '<td rowspan="2" style="border:1px solid black; padding:5px 10px;"></td>';
                $labelColspan = $hasSaleCols && $extraCols > 1 ? ' colspan="' . $extraCols . '"' : '';

                // Header currency label — strip out numeric currency IDs that
                // can sneak into old rows; show the readable code or "INR".
                $headerCurrency = trim((string)($model->currency ?? ''));
                if ($headerCurrency === '' || is_numeric($headerCurrency)) {
                    $headerCurrency = 'INR';
                }
            ?>
            <style>
                /* Transparent backgrounds so the page watermark/logo shows through. */
                .inv-items{ border-collapse:collapse; width:730px; border:1px solid black; font-size:13px; font-family:Arial, Helvetica, sans-serif; background:transparent; }
                .inv-items th, .inv-items td{ border:1px solid black; padding:6px 8px; vertical-align:middle; background:transparent; }
                .inv-items thead th{ font-weight:700; letter-spacing:.02em; background:transparent; white-space:nowrap; }
                .inv-items td.particulars-cell{ font-size:12px; word-break:break-word; }
                .inv-items th.sno-col, .inv-items td.sno-col{ padding:6px 2px; }
                .inv-items .num{ text-align:right; }
                .inv-items .center{ text-align:center; }
                .inv-items .tot-label{ text-align:right; font-weight:600; white-space:nowrap; padding-right:14px; }
                .inv-items .tot-value{ text-align:right; font-weight:600; padding-right:8px; white-space:nowrap; }
                .inv-items .tot-final .tot-label, .inv-items .tot-final .tot-value{ font-weight:700; }
                .inv-items .ghost{ background:transparent; }
            </style>
            <?php
            // Invoices with GST per line (product line items) use their own table.
            $perLineGst = false;
            foreach (($model->details ?? []) as $d0) { if ($d0->hasAttribute('gst_rate') && $d0->gst_rate !== null) { $perLineGst = true; break; } }
            ?>
            <?php if ($perLineGst): ?>
                <?= $this->render('@backend/views/invoice/_invoice_items_gst', ['model' => $model, 'headerCurrency' => $headerCurrency]) ?>
            <?php else: ?>
            <table align="center" class="inv-items">

            <tr>
                <th width="3%" class="center sno-col" style="white-space:nowrap;">#</th>
                <th width="<?= $hasSaleCols ? '13%' : '14%' ?>" class="center" style="white-space:nowrap;">HSN/SAC</th>
                <th width="<?= $hasSaleCols ? '30%' : '63%' ?>">PARTICULARS</th>
                <?php if ($hasModel): ?><th width="13%" class="center" style="white-space:nowrap;">MODEL</th><?php endif; ?>
                <?php if ($hasQty):   ?><th width="8%"  class="center" style="white-space:nowrap;">QTY</th><?php endif; ?>
                <?php if ($hasRate):  ?><th width="11%" class="center" style="white-space:nowrap;">RATE</th><?php endif; ?>
                <th width="<?= $hasSaleCols ? '17%' : '15%' ?>" class="center" style="white-space:nowrap;">AMOUNT (<?= htmlspecialchars($headerCurrency) ?>)</th>
            </tr>

            <?php
            $cnt = 1;
            if (isset($model->details) && !empty($model->details)):
                foreach ($model->details as $invoice_detail):
                    $rowModel = $invoice_detail->hasAttribute('model')     ? trim((string)($invoice_detail->model ?? ''))     : '';
                    $rowQty   = $invoice_detail->hasAttribute('quantity')  ? ($invoice_detail->quantity ?? null)               : null;
                    $rowRate  = $invoice_detail->hasAttribute('unit_rate') ? ($invoice_detail->unit_rate ?? null)              : null;
                    $rowQtyShown  = ($rowQty  !== null && (float)$rowQty  > 0) ? rtrim(rtrim(number_format((float)$rowQty, 4, '.', ''), '0'), '.') : '';
                    $rowRateShown = ($rowRate !== null && (float)$rowRate > 0) ? number_format((float)$rowRate, 2, '.', '')                       : '';
            ?>
            <tr>
                <td class="center sno-col"><?= $cnt++ ?></td>
                <td class="center" style="white-space:nowrap;"><?= htmlspecialchars($invoice_detail->sac ?? '') ?></td>
                <td class="particulars-cell"><?= htmlspecialchars($invoice_detail->particulars ?? '') ?></td>
                <?php if ($hasModel): ?><td class="center"><?= $rowModel    !== '' ? htmlspecialchars($rowModel)    : '&nbsp;' ?></td><?php endif; ?>
                <?php if ($hasQty):   ?><td class="center"><?= $rowQtyShown  !== '' ? htmlspecialchars($rowQtyShown)  : '&nbsp;' ?></td><?php endif; ?>
                <?php if ($hasRate):  ?><td class="center"><?= $rowRateShown !== '' ? htmlspecialchars($rowRateShown) : '&nbsp;' ?></td><?php endif; ?>
                <td class="num"><?= number_format((float)$invoice_detail->amount, 2) ?></td>
            </tr>
            <?php
                endforeach;
            endif;
            ?>

            <tr class="ghost">
                <?= $totalsPrefix ?>
                <td class="tot-label"<?= $labelColspan ?>>Item Total</td>
                <td class="tot-value"><?= number_format((float)$model->grand_total, 2) ?></td>
            </tr>

            <?php if ($model->invoice_type == 1): // Inter state - IGST ?>
            <tr class="ghost">
                <?= $totalsPrefix ?>
                <td class="tot-label"<?= $labelColspan ?>>IGST @ <?= $model->igst_percent ?>%</td>
                <td class="tot-value"><?= number_format((float)$model->igst_amount, 2) ?></td>
            </tr>
            <?php elseif ($model->invoice_type == 2): // Intra state - SGST + CGST ?>
            <tr class="ghost">
                <?= $totalsPrefixRowspan ?>
                <td class="tot-label"<?= $labelColspan ?>>SGST @ <?= $model->sgst_percent ?>%</td>
                <td class="tot-value"><?= number_format((float)$model->sgst_amount, 2) ?></td>
            </tr>
            <tr class="ghost">
                <td class="tot-label"<?= $labelColspan ?>>CGST @ <?= $model->cgst_percent ?>%</td>
                <td class="tot-value"><?= number_format((float)$model->cgst_amount, 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php if ($model->invoice_type == 1 || $model->invoice_type == 2): ?>
            <tr class="ghost">
                <?= $totalsPrefix ?>
                <td class="tot-label"<?= $labelColspan ?>>Sub Total</td>
                <td class="tot-value"><?= number_format((float)$model->sub_total, 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php if ((float)$model->round_off != 0): // Round Off row only when there is an actual rounding amount ?>
            <tr class="ghost">
                <?= $totalsPrefix ?>
                <td class="tot-label"<?= $labelColspan ?>>Round Off</td>
                <td class="tot-value"><?= number_format((float)$model->round_off, 2) ?></td>
            </tr>
            <?php endif; ?>

            <tr class="ghost tot-final">
                <?= $totalsPrefix ?>
                <td class="tot-label"<?= $labelColspan ?>>TOTAL</td>
                <td class="tot-value"><?= number_format((float)$model->net_total, 2) ?></td>
            </tr>

        </table>
            <?php endif; /* per-line GST */ ?>

        <br>
        <p style="text-align:center;">Total in words: <b><?= htmlspecialchars($headerCurrency) ?>. <?= $model->net_total ?>/-(<?= ucfirst(Utility::convertNumberToWords(round($model->net_total))) ?>  <?= htmlspecialchars($model->currency_in_words ?? '') ?> only)</b></p>

        <?php if ($model->payment_status == 2): ?>
        <br>
        <p style="text-align:center;">Payment Status : <b> Paid </b></p>
        <?php endif; ?>

        <?php if ($model->invoice_type != 3 && $model->tax_exempted == 1): ?>
        <br>
        <p style="text-align:center;"><b> <?= (isset($model->tax_exempt_reason) && $model->tax_exempt_reason != '') ? '(' . htmlspecialchars($model->tax_exempt_reason) . ')' : '' ?> </b></p>
        <?php endif; ?>

    </div>

    <?php if (isset($model->bank) && !empty($model->bank)): ?>
    <div id="movingElement">
        <div>
            <h5 style="padding:10px; text-align:center; font-weight:800; width:780px; margin:auto;">PAYMENT BANK DETAILS</h5>
            <table align="center" style="width:750px; text-align:center; font-size:12px; border-collapse:collapse; margin:auto; background:transparent;">

                <tr>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;">A/c Name </td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;"> <?= htmlspecialchars($model->bank->acc_name ?? '') ?></td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;">Bank Name </td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;"> <?= htmlspecialchars($model->bank->bank_name ?? '') ?> </td>
                </tr>

                <tr>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;">Branch </td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;"> <?= htmlspecialchars($model->bank->branch ?? '') ?> </td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;">A/c Number </td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;"> <?= htmlspecialchars($model->bank->acc_no ?? '') ?> </td>
                </tr>

                <tr>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;">IFSC Code </td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;"> <?= htmlspecialchars($model->bank->ifsc ?? '') ?> </td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;">SWIFT Code </td>
                    <td align="left" style="padding:5px 17px; text-align:left; border:1px solid #dfdfdf; background:transparent;"> <?= htmlspecialchars($model->bank->swift ?? '') ?> </td>
                </tr>

            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php
        // Terms & Conditions — render after bank details. Hidden when empty.
        $tcRaw = $model->hasAttribute('terms_and_conditions') ? trim((string)($model->terms_and_conditions ?? '')) : '';
    ?>
    <?php if ($tcRaw !== ''): ?>
    <div class="invoice-tc-block" style="width:730px; margin:20px auto 0 auto; padding:8px 12px; font-size:12px; background:transparent;">
        <div style="font-weight:bold; margin-bottom:6px; text-decoration:underline;">Terms &amp; Conditions</div>
        <div class="invoice-tc-body" style="white-space:pre-wrap; line-height:1.15; color:#000; margin:0; padding:0;"><?= nl2br(htmlspecialchars($tcRaw)) ?></div>
    </div>
    <style>.invoice-tc-body p { margin:0; padding:0; }</style>
    <?php endif; ?>

</div>
</section>
