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
        'Client Name'    => $lead->client_name,
        'Project'        => $lead->title,
        'Last Follow Up' => $lastFollowUp->getScheduledLabel(),
        'Subject'        => $lastFollowUp->subject,
        'Assigned To'    => Lead::userLabel($lastFollowUp->assignedTo),
        'Mode'           => Lead::modeLabel($lastFollowUp->mode),
        'Created By'     => Lead::userLabel($lastFollowUp->createdBy),
    ];
    $contextDescription = $lastFollowUp->description;
} else {
    $context = [
        'Client Name'   => $lead->client_name,
        'Project'       => $lead->title,
        'Attended'      => Lead::formatDateTime($lead->meeting_attended_date),
        'Next Follow Up'=> Lead::formatDateTime($lead->meeting_reported_date),
        'Subject'       => $lead->subject,
        'Assigned To'   => Lead::userLabel($lead->assignedTo),
    ];
    $contextDescription = $lead->description;
}
?>
<div class="lead-followers-create" style="max-width:1000px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?> <small class="text-muted fw-normal">— <?= Html::encode($lead->client_name) ?></small></h5>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <?php if ($closing): ?>
    <div class="alert alert-warning py-2 small">
        Saving this follow-up will close the follow-up scheduled for <strong><?= Html::encode($closing->getScheduledLabel()) ?></strong>
        (<?= Html::encode($closing->subject) ?>).
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold small text-uppercase text-muted"><?= $lastFollowUp ? 'Previous follow-up' : 'Lead' ?></div>
        <table class="table table-sm table-striped mb-0" style="font-size:.85rem">
            <?php foreach ($context as $label => $value): ?>
                <tr><th class="text-muted fw-normal" style="width:30%"><?= $label ?></th><td><?= Html::encode($value === null || $value === '' ? '-' : $value) ?></td></tr>
            <?php endforeach; ?>
            <tr><th class="text-muted fw-normal">Description</th><td><?= Lead::renderRichText($contextDescription) ?></td></tr>
        </table>
    </div>

    <?= $this->render('_form', ['model' => $model, 'lead' => $lead, 'users' => $users]) ?>
</div>
