<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadFollowers $model */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\LeadFollowers|null $lastFollowUp */
/** @var \backend\models\LeadFollowers|null $closing  follow-up closed once this one is saved */
/** @var array $users */

use backend\models\Lead;
use yii\bootstrap5\Html;

$this->title = 'Add Follow Up';

// Context: the previous follow-up, or the lead itself for its first one (Yii1 behaviour).
if ($lastFollowUp) {
    $context = [
        'Last Follow Up' => $lastFollowUp->getScheduledLabel(),
        'Subject'        => $lastFollowUp->subject,
        'Assigned To'    => Lead::userLabel($lastFollowUp->assignedTo),
        'Mode'           => Lead::modeLabel($lastFollowUp->mode),
        'Created By'     => Lead::userLabel($lastFollowUp->createdBy),
    ];
    $contextDescription = $lastFollowUp->description;
} else {
    $context = [
        'Attended'       => Lead::formatDateTime($lead->meeting_attended_date),
        'Next Follow Up' => Lead::formatDateTime($lead->meeting_reported_date),
        'Subject'        => $lead->subject,
        'Assigned To'    => Lead::userLabel($lead->assignedTo),
    ];
    $contextDescription = $lead->description;
}
?>
<div class="ui-page lead-followers-create" style="max-width:1000px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub"><?= Html::encode($lead->client_name) ?><?= $lead->title ? ' · ' . Html::encode($lead->title) : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <?php if ($closing): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="fas fa-info-circle mt-1"></i>
        <div>Saving this follow-up will close the one scheduled for <strong><?= Html::encode($closing->getScheduledLabel()) ?></strong>
            (<?= Html::encode($closing->subject ?: 'no subject') ?>).</div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section"><?= $lastFollowUp ? 'Previous follow-up' : 'Lead' ?></div>
        <table class="table ui-detail">
            <?php foreach ($context as $label => $value): ?>
                <tr><th scope="row"><?= $label ?></th><td><?= Html::encode($value === null || $value === '' ? '-' : $value) ?></td></tr>
            <?php endforeach; ?>
            <tr><th scope="row">Description</th><td><?= trim((string)$contextDescription) !== '' ? Lead::renderRichText($contextDescription) : '-' ?></td></tr>
        </table>
    </div>

    <?= $this->render('_form', ['model' => $model, 'lead' => $lead, 'users' => $users]) ?>
</div>
