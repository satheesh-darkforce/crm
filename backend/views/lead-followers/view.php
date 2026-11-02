<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadFollowers $model */
/** @var \backend\models\Lead $lead */

use backend\models\Lead;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'View Follow Up';

$rows = [
    'Client Name' => $lead->client_name,
    'Project'     => $lead->title,
    'Follow Up'   => $model->getScheduledLabel(),
    'Subject'     => $model->subject,
    'Assigned To' => Lead::userLabel($model->assignedTo),
    'Mode'        => Lead::modeLabel($model->mode) . ((int)$model->mode === Lead::MODE_OTHER && $model->other_mode ? ' — ' . $model->other_mode : ''),
    'Status'      => $model->getFollowupStatusLabel(),
    'Created By'  => Lead::userLabel($model->createdBy),
    'Created On'  => Lead::formatDateTime($model->created_date),
];
?>
<div class="lead-followers-view" style="max-width:820px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <div class="d-flex gap-2">
            <?php if (UserAccess::checkAccess('lead-followers/update')
                && !in_array((int)$lead->lead_status, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true)): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', Yii::$app->request->referrer ?: ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm mb-3">
        <table class="table table-sm table-striped mb-0" style="font-size:.85rem">
            <?php foreach ($rows as $label => $value): ?>
                <tr><th class="text-muted fw-normal" style="width:30%"><?= $label ?></th><td><?= Html::encode($value === null || $value === '' ? '-' : $value) ?></td></tr>
            <?php endforeach; ?>
            <tr><th class="text-muted fw-normal">Description</th><td><?= Lead::renderRichText($model->description) ?></td></tr>
        </table>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Attachments</div>
        <div class="card-body"><?= $this->render('_attachments', ['attachments' => $model->attachments]) ?></div>
    </div>
</div>
