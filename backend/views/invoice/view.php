<?php
/**
 * Invoice View — standalone page that mirrors Yii1 protected/views/invoice/view.php.
 * Rendered via renderPartial (no admin layout) so it opens as a clean printable
 * page with the same letterhead document as the PDF.
 *
 * @var \yii\web\View          $this
 * @var \backend\models\Invoice $model
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$isProforma  = ((int)$model->invoice_role === \backend\models\Invoice::ROLE_PROFORMA);
$isDraft     = ((int)$model->status === -1);
$isCancelled = (int)$model->invoice_status === 0;
$isIntl      = ((int)$model->invoice_type === 3);

$baseUrl     = Yii::$app->request->baseUrl;
$imgBasePath = $baseUrl . '/';
$csrfParam   = Yii::$app->request->csrfParam;
$csrfToken   = Yii::$app->request->getCsrfToken();
$openParam   = Yii::$app->request->get('open', '');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= Html::encode($model->invoice_no ?: '#' . $model->id) ?></title>

<!-- Bootstrap 5 + Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-default@5/default.min.css">

<style>
body { margin: 0; background: #f7f7f7; }
.action-bar {
    max-width: 793px;
    margin: 8px auto;
    padding: 8px 0;
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    flex-wrap: wrap;
}
/* Force browsers to keep background images (the letterhead) when printing.
   Chrome/Edge still respect the user's "Background graphics" toggle, but with this
   hint it defaults to ON for our document. */
html, body, #invoice_div, #printdiv {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    color-adjust: exact !important;
}
@media print {
    .action-bar, .convert-bar { display: none !important; }
    @page { size: A4; margin: 0; }
    html, body { margin: 0 !important; padding: 0 !important; background: white !important; }
    #invoice_div { page-break-inside: avoid; }
    #printdiv {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
}
</style>
</head>
<body>

<!-- ── Action bar (Print / Download / Back / Confirm) ── -->
<div class="action-bar">
    <?php if ($isDraft): ?>
        <a href="<?= Url::to(['update', 'id' => $model->id]) ?>" class="btn btn-primary btn-sm">Back to Edit</a>
        <form id="confirm-invoice-form" method="post" action="<?= Url::to(['confirminvoice', 'id' => $model->id]) ?>" style="display:inline">
            <input type="hidden" name="<?= $csrfParam ?>" value="<?= $csrfToken ?>">
            <button type="button" class="btn btn-info btn-sm" id="btn-confirm-invoice">Confirm and Save</button>
        </form>
    <?php elseif (!$isCancelled): ?>
        <button type="button" class="btn btn-success btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
        <a href="<?= Url::to(['download-pdf', 'id' => $model->id]) ?>" class="btn btn-secondary btn-sm" target="_blank">
            <i class="bi bi-file-earmark-pdf"></i> Download PDF
        </a>
    <?php endif; ?>
    <a href="<?= Url::to([$isProforma ? 'proforma' : 'index']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<!-- ── Invoice document (shared with print.php / download-pdf) ── -->
<?= $this->render('_invoice_doc', [
    'model'       => $model,
    'imgBasePath' => $imgBasePath,
    'isPdf'       => false,
]) ?>


<!-- Bootstrap 5 + SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Confirm-and-Save (drafts) — SweetAlert2 prompt before submit
(function () {
    var btn = document.getElementById('btn-confirm-invoice');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        Swal.fire({
            icon: 'question',
            title: 'Confirm and Save',
            text: 'Are you sure to save this invoice? An invoice number will be assigned.',
            showCancelButton: true,
            confirmButtonText: 'Yes, save it',
            cancelButtonText: 'Cancel'
        }).then(function (r) {
            if (r.isConfirmed) document.getElementById('confirm-invoice-form').submit();
        });
    });
}());

</script>
</body>
</html>
