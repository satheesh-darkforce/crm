<?php

/** @var yii\web\View $this */
/** @var backend\models\ClientQuotation $model */
/** @var backend\models\ClientQuotationFile[] $files */

use common\components\UserAccess;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Quotation Files';
$this->registerCssFile('@web/css/quotation.css');
?>

<div class="quotation-page">
    <div class="quotation-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="dc-title">Quotation Files</h4>
            <p>Quote No: <?= Html::encode($model->quote_no) ?></p>
        </div>
        <div class="quotation-toolbar d-flex gap-2">
            <?= Html::a('View Quotation', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-primary']) ?>
            <?= Html::a('Back to List', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
        </div>
    </div>

    <div class="quotation-card card">
        <div class="card-body">
            <?php if (!empty($files)) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle quotation-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>File</th>
                                <th>Uploaded At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $i => $file): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= Html::encode(basename($file->file_path)) ?></td>
                                    <td><?= Html::encode($file->created_at) ?></td>
                                    <td class="text-end">
                                        <?php if (UserAccess::checkAccess('client-quotation/download-file')): ?>
                                            <?= Html::a('Download', ['download-file', 'id' => $file->id], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No files uploaded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
