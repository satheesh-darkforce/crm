<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Invoice $model */
/** @var string|null $previewInvoiceNo */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$isProforma   = ((int)$model->invoice_role === \backend\models\Invoice::ROLE_PROFORMA);
$label        = $isProforma ? 'Proforma' : 'Invoice';
$confirmUrl   = $isProforma ? '/invoice/confirm-proforma' : '/invoice/confirminvoice';
$previewInvoiceNo = $previewInvoiceNo ?? ($model->invoice_no ?: '');
$this->title  = "Preview {$label} — " . ($model->invoice_no ?: ($previewInvoiceNo ?: 'Draft'));
$baseUrl      = Yii::$app->request->baseUrl;
$imgBasePath  = $baseUrl . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= Html::encode($this->title) ?></title>
<?= Html::csrfMetaTags() ?>
<style>
*{box-sizing:border-box}
body{margin:0;padding:0;background:#f1f3f7;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
.preview-actions{position:sticky;top:0;background:#fff;border-bottom:1px solid #dde1e7;padding:10px 16px;z-index:10;display:flex;justify-content:flex-end;gap:8px}
.preview-actions form{display:inline;margin:0}
.preview-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:4px;font-size:14px;font-weight:500;cursor:pointer;border:1px solid transparent;line-height:1}
.preview-btn-secondary{background:#fff;color:#475569;border-color:#cbd5e1}
.preview-btn-secondary:hover{background:#f1f5f9}
.preview-btn-primary{background:#0d9488;color:#fff;border-color:#0d9488}
.preview-btn-primary:hover{background:#0b7c72}
.preview-doc-wrap{padding:18px 0}
</style>
</head>
<body>

<div class="preview-actions">
    <a href="<?= Url::to(['/invoice/update', 'id' => $model->id]) ?>" class="preview-btn preview-btn-secondary">Back &amp; Edit</a>
    <form method="post" action="<?= Url::to([$confirmUrl, 'id' => $model->id]) ?>">
        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
        <button type="submit" class="preview-btn preview-btn-primary">Save and Download</button>
    </form>
</div>

<div class="preview-doc-wrap">
    <?= $this->render('_invoice_doc', [
        'model'            => $model,
        'imgBasePath'      => $imgBasePath,
        'isPdf'            => false,
        'previewInvoiceNo' => $previewInvoiceNo,
    ]) ?>
</div>

</body>
</html>
