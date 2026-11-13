<?php

/**
 * Delivery Challan PDF body — exact port of delivery/views/dc/dc_challan.php.
 *
 * @var array $dc       DcDetail row eager-loaded with client, delivery, documents, dcProducts.product
 * @var int   $preview  1 = inline preview (Download/Edit/Back buttons shown), 0 = for PDF
 */

use yii\helpers\Url;

// Absolute path so mpdf can embed the logo.
$logoPath = \backend\components\Company::logoPath();
if (!is_file($logoPath)) {
    $logoPath = \backend\components\Company::logoPath();
}
// For inline browser preview, use a web URL (browsers can't load absolute filesystem paths).
$logoSrc = $logoPath;
if ((int) $preview === 1 && is_file($logoPath)) {
    $logoSrc = Yii::getAlias('@web/images/' . basename($logoPath));
}

$deliveryTypeMap = [
    1 => 'Self',
    2 => 'Customer Pickup',
    3 => 'Courier',
];
$deliveryType = $deliveryTypeMap[(int) ($dc['dc_delivery_type'] ?? 0)] ?? '-';

$downloadParams = ['dc/challan-pdf', 'id' => $dc['id']];
if (empty($dc['dc_no'])) {
    $downloadParams['submit'] = 1;
}

$doc = $dc['documents'] ?? [];
?>

<style>
    body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #333; margin: 0; padding: 0; }
    .master-column-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 15px; }
    .column-box { border: 1px solid #cbd5e0; vertical-align: top; background-color: #ffffff; }
    .header-fill-table { width: 100%; border-collapse: collapse; background-color: #f1f4f9; border-bottom: 1px solid #cbd5e0; }
    .header-text { padding: 6px 10px; font-weight: bold; color: #1a3a5a; font-size: 9pt; text-transform: uppercase; }
    .column-body { padding: 10px; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
    .data-table th, .data-table td { border: 1px solid #cbd5e0; padding: 8px; font-size: 9pt; }
    .data-table th { background-color: #f8fafc; color: #1a3a5a; text-align: left; }
    .signature-table { width: 100%; border-collapse: collapse; margin-top: 20px; page-break-inside: avoid; }
    .signature-table th { border: 1px solid #cbd5e0; background-color: #f1f4f9; padding: 8px; text-align: left; color: #1a3a5a; font-size: 9pt; }
    .signature-table td { border: 1px solid #cbd5e0; padding: 15px; vertical-align: top; height: 100px; }
    .no-border-table td { border: none !important; padding: 2px 0; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .title-large { font-size: 17pt; font-weight: bold; color: #1a3a5a; }
    .auth-sig-box { background-color: #eeeeee; text-align: center; vertical-align: middle !important; position: relative; }
    .sig-body-new { border: 1px solid #000; border-top: none; vertical-align: bottom; text-align: center; height: 120px; padding-bottom: 10px; }
    .data-table th, .data-table td { padding: 6px; vertical-align: top; white-space: normal; }
    .serial-column { width: 15%; white-space: normal; word-break: keep-all; }
</style>

<div class="a4-page">

    <?php if ((int) $preview === 1): ?>
        <?php
            $isReturn    = !empty($dc['inward_dc_no']) && (int)($dc['type'] ?? 0) === 1;
            $editRoute   = $isReturn ? 'dc/update-return' : 'dc/update';
            $cancelRoute = $isReturn ? 'dc/inward-index'  : 'dc/index';
            $editAllowed = \common\components\UserAccess::checkAccess($isReturn ? 'dc/update-return' : 'dc/update');
            $hasSavedDc  = !empty($dc['id']);
        ?>
        <div class="text-right mb-3" style="text-align: right; margin-bottom: 10px;">
            <?php if ($hasSavedDc): ?>
                <a id="dc-save-download" class="btn btn-success" target="_blank" href="<?= Url::to($downloadParams) ?>">Save &amp; Download</a>
                <?php if ($editAllowed): ?>
                    <a id="dc-edit-btn" class="btn btn-primary" href="<?= Url::to([$editRoute, 'id' => $dc['id']]) ?>">Edit</a>
                <?php endif; ?>
                <a class="btn btn-secondary" href="<?= Url::to([$cancelRoute]) ?>">Cancel/Back</a>
            <?php else: ?>
                <span class="badge bg-warning text-dark me-2" style="font-size: 11pt; padding: 6px 12px;">Preview only — not yet saved</span>
                <button type="button" class="btn btn-secondary" onclick="window.close();">Close Preview</button>
            <?php endif; ?>
        </div>
        <?php if ($hasSavedDc): ?>
        <?php
        $redirectUrl = Url::to([$cancelRoute]);
        $this->registerJs(<<<JS
        jQuery('#dc-save-download').on('click', function () {
            jQuery('#dc-edit-btn').remove();
            setTimeout(function () {
                window.location.href = '$redirectUrl';
            }, 800);
        });
JS, \yii\web\View::POS_READY);
        ?>
        <?php endif; ?>
    <?php endif; ?>

    <table style="width: 100%; border-bottom: 2px solid #1a3a5a; padding-bottom: 10px; margin-bottom: 20px;">
        <tr>
            <td width="65%" style="border:none; vertical-align: top;">
                <?php if (is_file($logoPath)): ?>
                    <img src="<?= $logoSrc ?>" style="width: 150px; margin-bottom: 10px;"><br>
                <?php endif; ?>
                <b style="color: #1a3a5a; font-size: 12pt;"><?= \yii\helpers\Html::encode(\backend\components\Company::get('legalName')) ?></b><br>
                <div style="margin-top: 5px; font-size: 9pt; line-height: 1.4; color: #555;">
                    <?= \yii\helpers\Html::encode(\backend\components\Company::get('address')) ?><br>
                    <b>GST:</b> 33AAHCM8302P1Z6 | <b>Ph:</b> 7448517888
                </div>
            </td>
            <td width="35%" class="text-right" style="border:none; vertical-align: bottom;">
                <div class="title-large">DELIVERY CHALLAN</div>
                <div style="margin-top: 5px;"><b>Date:</b>
                    <?= !empty($dc['dc_date']) ? date('Y-m-d', strtotime($dc['dc_date'])) : '-' ?></div>
            </td>
        </tr>
    </table>

    <table class="master-column-table">
        <tr>
            <td width="48%" class="column-box">
                <table class="header-fill-table"><tr><td class="header-text">DC Details</td></tr></table>
                <div class="column-body">
                    <table class="no-border-table" width="100%">
                        <tr>
                            <td width="40%"><b>DC No</b></td>
                            <?php if ((int) $preview === 1): ?>
                                <td>: -</td>
                            <?php else: ?>
                                <td>: <?= $dc['dc_no'] ?? '-' ?></td>
                            <?php endif; ?>
                        </tr>
                        <tr>
                            <td><b>Type</b></td>
                            <td>: <?= match ((int) ($dc['dc_type'] ?? 0)) { 1 => 'Returnable', 2 => 'Sale', 3 => 'Service', default => '-' } ?></td>
                        </tr>
                        <tr>
                            <td><b>Return Date</b></td>
                            <td>: <?= ((int) ($dc['dc_type'] ?? 0) === 1)
                                ? (!empty($dc['dc_return_date']) ? date('Y-m-d', strtotime($dc['dc_return_date'])) : '-')
                                : 'Not Returnable' ?></td>
                        </tr>
                        <tr>
                            <td><b>DC Delivery Type</b></td>
                            <td>: <?= $deliveryType ?></td>
                        </tr>
                    </table>
                </div>
            </td>
            <td width="4%"></td>
            <td width="48%" class="column-box">
                <table class="header-fill-table"><tr><td class="header-text">REFERENCES</td></tr></table>
                <div class="column-body">
                    <table class="no-border-table" width="100%">
                        <tr>
                            <td width="40%"><b>Quotation No</b></td>
                            <td>: <?= !empty($doc['quotation_no']) ? $doc['quotation_no'] : '-' ?></td>
                        </tr>
                        <tr>
                            <td><b>Invoice No</b></td>
                            <td>: <?= !empty($doc['invoice_no']) ? $doc['invoice_no'] : '-' ?></td>
                        </tr>
                        <tr>
                            <td><b>PO No</b></td>
                            <td>: <?= !empty($doc['po_no']) ? $doc['po_no'] : '-' ?></td>
                        </tr>
                        <tr>
                            <td><b>EWAY No</b></td>
                            <td>: <?= !empty($dc['ewbc_no']) ? $dc['ewbc_no'] : '-' ?></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="header-fill-table" style="border: 1px solid #cbd5e0; border-bottom: none;">
        <tr><td class="header-text">CUSTOMER DETAILS (CONSIGNEE / BILL TO)</td></tr>
    </table>
    <div style="border: 1px solid #cbd5e0; padding: 10px; margin-bottom: 15px;">
        <table class="no-border-table" width="100%">
            <tr>
                <td width="60%" style="vertical-align: top;">
                    <b>Company Name:</b> <?= $dc['client']['company_name'] ?? '-' ?><br>
                    <div style="margin-top:4px;"><b>Address:</b> <?= $dc['client']['address'] ?? '-' ?></div>
                </td>
                <td width="40%" style="vertical-align: top;">
                    <?php if (!empty($dc['inward_dc_no'])): ?>
                        <b>Inward DC No:</b> <?= $dc['inward_dc_no'] ?><br>
                    <?php endif; ?>
                    <b>GST No:</b> <?= $dc['client']['gst_no'] ?? '-' ?><br>
                    <b>Contact:</b> <?= $dc['client']['phone'] ?? '-' ?>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">S.No</th>
                <th width="33%">Item Name</th>
                <th width="25%" class="serial-column">Serial No</th>
                <th width="15%" class="text-center">HSN Code</th>
                <th width="10%" class="text-center">Qty</th>
                <th width="12%">Unit</th>
            </tr>
        </thead>
        <tbody>
            <?php $totalQty = 0; ?>
            <?php if (!empty($dc['dcProducts'])): ?>
                <?php foreach ($dc['dcProducts'] as $i => $line):
                    if ((int) ($line['status'] ?? 1) < 1) continue; ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?></td>
                        <td style="word-wrap: break-word; overflow-wrap: break-word;">
                            <?= wordwrap($line['product']['item_name'] ?? '-', 30, '<br>', true) ?>
                        </td>
                        <td class="serial-column"><?= str_replace(',', ', ', (string) ($line['serial_nos'] ?? '')) ?></td>
                        <td class="text-center"><?= $line['product']['hsn_code'] ?? '-' ?></td>
                        <td class="text-center"><?= $line['quantity'] ?? 0 ?></td>
                        <td><?= $line['product']['units'] ?? '-' ?></td>
                    </tr>
                    <?php $totalQty += (int) ($line['quantity'] ?? 0); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="4" class="text-right">Total Quantity:</td>
                <td class="text-center"><?= $totalQty ?></td>
                <td>Nos</td>
            </tr>
        </tfoot>
    </table>

    <table class="header-fill-table" style="border: 1px solid #cbd5e0; border-bottom: none;">
        <tr><td class="header-text">DELIVERY &amp; TRANSPORT DETAILS</td></tr>
    </table>
    <div style="border: 1px solid #cbd5e0; padding: 10px; margin-bottom: 15px;">
        <table class="no-border-table" width="100%">
            <tr>
                <td width="50%">
                    <b>Concern:</b> <?= $dc['delivery']['consern_name'] ?? '-' ?><br>
                    <b>GST No:</b> <?= $dc['delivery']['gst_no'] ?? '-' ?><br>
                    <b>Place of Supply:</b> <?= $dc['delivery']['place'] ?? '-' ?>
                </td>
                <td width="50%">
                    <b>Vehicle No:</b> <?= $dc['delivery']['vehicle_no'] ?? '' ?><br>
                    <b>Contact:</b> <?= $dc['delivery']['contact_no'] ?? '-' ?><br>
                    <b>Alt Contact:</b> <?= !empty($dc['delivery']['alt_contact_no']) ? $dc['delivery']['alt_contact_no'] : '-' ?>
                </td>
            </tr>
        </table>
        <div style="margin-top:8px; border-top:1px dashed #cbd5e0; padding-top:8px;">
            <b>Description:</b> <?= !empty($dc['dc_delivery_description']) ? nl2br($dc['dc_delivery_description']) : '' ?>
        </div>
        <div style="margin-top:8px; border-top:1px dashed #cbd5e0; padding-top:8px;">
            <b>Delivery Address:</b> <?= $dc['delivery']['address'] ?? '-' ?>
        </div>
    </div>

    <table width="100%" style="border:none; margin-top: 10px;">
        <tr>
            <td width="50%" style="border:none; font-size: 8.5pt; padding-right:15px; color: #555;">
                <b>Terms &amp; Conditions:</b><br>
                1: The goods are delivered for POC/Sale<br>
                2: Any damage, shortage, or discrepancy must be reported within 24 hours of delivery, failing which the
                material will be treated as accepted in good condition.
            </td>
            <td width="50%" style="border:none; font-size: 8.5pt; color: #555;">
                <b>Declaration:</b><br>
                We hereby declare that the goods described above are delivered as per the details mentioned in this
                Delivery Challan and are true and correct to the best of our knowledge.
            </td>
        </tr>
    </table>

    <table class="signature-table" autosize="1">
        <tr>
            <th style="width:28%">Received By</th>
            <th style="width:28%">Delivered By</th>
            <th style="width:44%">For, <?= \yii\helpers\Html::encode(\backend\components\Company::get('legalName')) ?></th>
        </tr>
        <tr>
            <td>
                <div style="font-size: 8pt;">
                    Name: ________________<br><br>
                    Date: ________________<br><br>
                    Sign: ________________
                </div>
            </td>
            <td>
                <div style="font-size: 8pt;">
                    Name: ________________<br><br>
                    Date: ________________<br><br>
                    Sign: ________________
                </div>
            </td>
            <td class="sig-body-new" style="vertical-align: bottom; text-align: center; padding: 15px 15px 12px 15px;">
                <div style="font-weight: bold; font-size: 9pt; color: #000; padding-top: 70px;">Authorized Signature</div>
            </td>
        </tr>
    </table>
</div>
