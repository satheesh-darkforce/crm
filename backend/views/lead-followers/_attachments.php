<?php
/**
 * Attachment chips — lead view, follow-up view and the follow-up attachments modal.
 *
 * @var \yii\web\View $this
 * @var \backend\models\LeadAttachments[]|\backend\models\LeadFollowerAttachments[] $attachments
 */

use backend\controllers\Controller;
use yii\bootstrap5\Html;
?>
<?php if (!$attachments): ?>
    <span class="text-muted small"><i class="fas fa-paperclip me-1"></i>No attachments.</span>
<?php else: ?>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($attachments as $att): ?>
            <?php $name = basename((string)$att->attachment_path); ?>
            <a class="ui-file" target="_blank" rel="noopener" title="<?= Html::encode($name) ?>"
               href="<?= Html::encode(Controller::uploadUrl((string)$att->attachment_path)) ?>">
                <i class="fas fa-paperclip"></i><span><?= Html::encode($name) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
