<?php

/** @var yii\web\View $this */
/** @var backend\models\ClientQuotation $model */
/** @var backend\models\ClientQuotationFile[] $files */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Quotation Files';
$canDownload = UserAccess::checkAccess('client-quotation/download-file');
?>
<div class="ui-page quotation-files" style="max-width:900px">
    <div class="ui-head">
        <div>
            <h1>Files for <span class="mono"><?= Html::encode($model->quote_no) ?></span></h1>
            <div class="ui-sub"><?= Html::encode($model->project_name) ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('View quotation', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm']) ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Quotations', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <?php if (!$files): ?>
            <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'file-pdf', 'title' => 'No files uploaded yet', 'text' => 'Attach a PDF when you create or revise the quotation.']) ?>
        <?php else: ?>
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light"><tr><th style="width:40px">#</th><th>File</th><th>Uploaded</th><th class="text-end" style="width:120px"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($files as $i => $file): ?>
                    <tr>
                        <td class="cell-num muted"><?= $i + 1 ?></td>
                        <td class="cell-title"><i class="fas fa-file-pdf text-danger me-1" aria-hidden="true"></i><?= Html::encode(basename($file->file_path)) ?></td>
                        <td class="text-nowrap" data-label="Uploaded"><?= $file->created_at ? date('d M Y, h:i a', strtotime($file->created_at)) : '—' ?></td>
                        <td class="text-end" data-label="">
                            <?php if ($canDownload): ?><?= Html::a('<i class="fas fa-eye me-1"></i>Open', ['download-file', 'id' => $file->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false, 'target' => '_blank', 'rel' => 'noopener']) ?><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
