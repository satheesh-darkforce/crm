<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadFollowerAttachments[] $attachments */

use backend\controllers\Controller;
use yii\bootstrap5\Html;
?>
<?php if (!$attachments): ?>
    <span class="text-muted small">No attachments.</span>
<?php else: ?>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($attachments as $att): ?>
            <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener"
               href="<?= Html::encode(Controller::uploadUrl($att->attachment_path)) ?>">
                <i class="fas fa-paperclip me-1"></i><?= Html::encode(basename($att->attachment_path)) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
