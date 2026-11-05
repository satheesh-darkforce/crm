<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadFollowers $model */
/** @var \backend\models\Lead $lead */

use backend\models\Lead;
use backend\models\LeadFollowers;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'View Follow Up';

$isClosed = in_array((int)$lead->lead_status, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true);
$open     = (int)$model->followup_status === LeadFollowers::FOLLOWUP_OPEN;
$rows = [
    'Client Name' => $lead->client_name,
    'Project'     => $lead->title,
    'Subject'     => $model->subject,
    'Assigned To' => Lead::userLabel($model->assignedTo),
    'Mode'        => Lead::modeLabel($model->mode) . ((int)$model->mode === Lead::MODE_OTHER && $model->other_mode ? ' — ' . $model->other_mode : ''),
    'Created By'  => Lead::userLabel($model->createdBy),
    'Created On'  => Lead::formatDateTime($model->created_date),
];
?>
<div class="ui-page lead-followers-view" style="max-width:860px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->subject ?: $this->title) ?></h1>
            <div class="ui-sub">Follow-up for <?= Html::encode($lead->client_name) ?></div>
        </div>
        <div class="ui-actions">
            <?php if (!$isClosed && UserAccess::checkAccess('lead-followers/update')): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', Yii::$app->request->referrer ?: ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Details</div>
        <table class="table ui-detail">
            <tr><th scope="row">Follow Up</th><td><?= $this->render('@backend/views/lead/_due', [
                'label' => $model->getScheduledLabel(),
                'state' => $open && !$isClosed ? Lead::followUpState($model->follow_up_date) : '',
            ]) ?></td></tr>
            <tr><th scope="row">Status</th><td><span class="ui-pill tone-<?= $open ? 'accent' : 'neutral' ?>"><?= Html::encode($model->getFollowupStatusLabel()) ?></span></td></tr>
            <?php foreach ($rows as $label => $value): ?>
                <tr><th scope="row"><?= $label ?></th><td><?= Html::encode($value === null || $value === '' ? '-' : $value) ?></td></tr>
            <?php endforeach; ?>
            <tr><th scope="row">Description</th><td><?= trim((string)$model->description) !== '' ? Lead::renderRichText($model->description) : '-' ?></td></tr>
        </table>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">Attachments</div>
        <div class="card-body"><?= $this->render('_attachments', ['attachments' => $model->attachments]) ?></div>
    </div>
</div>
