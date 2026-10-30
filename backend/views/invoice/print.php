<?php
/**
 * Invoice Print View - Standalone HTML for PDF generation and browser print.
 * Matches Yii1 view.php template exactly: same table structure, same tax logic,
 * same bank details section, same amount-in-words.
 *
 * @var \backend\models\Invoice $model
 * @var bool $isPdf True when rendering for mPDF (optional, default false)
 * @var bool $autoPrint True to auto-open the browser print dialog on load
 */

$isPdf      = $isPdf      ?? false;
$autoPrint  = $autoPrint  ?? false;

// For mPDF, images need absolute filesystem paths; for browser, use baseUrl
if ($isPdf) {
    $imgBasePath = Yii::getAlias('@backend/web') . '/';
} else {
    $imgBasePath = Yii::$app->request->baseUrl . '/';
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Invoice</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style type="text/css">
body {
    margin-left: 0px;
    margin-top: 0px;
    margin-right: 0px;
    margin-bottom: 0px;
    background: #f7f7f7;
}
.no-print { text-align: right; max-width: 793px; margin: 8px auto; }
.no-print button { padding: 6px 18px; margin-left: 6px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
/* Force browsers to keep the bg.jpg letterhead when printing. */
html, body, #invoice_div, #printdiv {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
}
<?php if (!$isPdf): ?>
@media print {
    @page { size: A4; margin: 0; }
    .no-print { display: none !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: white !important; }
    #invoice_div { page-break-inside: avoid; }
    #printdiv, section#invoice_div {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
        margin-top: 0 !important;
    }
}
<?php endif; ?>
</style>
</head>
<body>

<?php if (!$isPdf): ?>
<div class="no-print">
    <button onclick="printbill()" style="background:#2c6a2f;color:#fff;">Print</button>
    <button onclick="window.close()" style="background:#888;color:#fff;">Close</button>
</div>
<?php endif; ?>

<?php
// Body of the invoice document is shared with view.php (in-app preview).
echo $this->render('_invoice_doc', [
    'model'       => $model,
    'imgBasePath' => $imgBasePath,
    'isPdf'       => $isPdf,
]);
?>

<?php if (!$isPdf): ?>
<script type="text/javascript">
function printbill() {
    window.print();
}
<?php if ($autoPrint): ?>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 250);
});
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>
