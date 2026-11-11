<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\LeadFollowers[] $followers */
/** @var int $openFollowUps */
/** @var \backend\models\LeadAttachments[] $attachments */
/** @var array $insight  score, band, actions, duplicates, estimate (LeadInsight) */
/** @var bool $assignedView  opened from Assigned Leads: follow-up status is read-only */

use backend\components\LeadAccess;
use backend\models\Lead;
use backend\models\LeadFollowers;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'View Lead';

$ls               = (int)$lead->lead_status;
$isClosed         = in_array($ls, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true);
$options          = UserAccess::checkAccess('lead/change-status') ? $lead->nextLeadStatusOptions(LeadAccess::canReopen()) : [];
$canCloseFollowUp = !$assignedView && UserAccess::checkAccess('lead/change-followup-status');
$canAttachments   = UserAccess::checkAccess('lead-followers/view-attachments');
$canAddFollowUp   = !$isClosed && UserAccess::checkAccess('lead-followers/create');
$backUrl          = $assignedView ? ['assigned'] : (Yii::$app->request->referrer ?: ['index']);

$row = function (string $label, $value, bool $raw = false) {
    $value = ($value === null || $value === '') ? '-' : $value;
    return '<tr><th scope="row">' . $label . '</th><td>' . ($raw ? $value : Html::encode($value)) . '</td></tr>';
};
?>
<div class="ui-page lead-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($lead->client_name ?: 'Lead #' . $lead->id) ?></h1>
            <div class="ui-sub">
                <?= Html::encode($lead->company_name ?: 'Lead #' . $lead->id) ?>
                <?php if ($lead->title): ?> · <?= Html::encode($lead->title) ?><?php endif; ?>
            </div>
        </div>
        <div class="ui-actions">
            <?php if ($dial = $lead->getDialNumber()): ?>
                <?= Html::a('<i class="fas fa-phone me-1"></i>Call', 'tel:+' . $dial, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
                <?= Html::a('<i class="fab fa-whatsapp me-1"></i>WhatsApp', 'https://wa.me/' . $dial, ['class' => 'btn btn-outline-success btn-sm', 'encode' => false, 'target' => '_blank', 'rel' => 'noopener']) ?>
            <?php endif; ?>
            <?php if (count($options) > 1): ?>
                <?= Html::dropDownList('lead_status', $ls, $options, [
                    'class' => 'form-select form-select-sm js-lead-status', 'style' => 'width:auto;min-width:180px',
                    'data-id' => $lead->id, 'data-prev' => $ls, 'aria-label' => 'Lead status',
                ]) ?>
            <?php else: ?>
                <span class="ui-pill lg tone-<?= Lead::leadStatusTone($ls) ?>"><?= Html::encode($lead->getLeadStatusLabel()) ?></span>
            <?php endif; ?>
            <?php if (!$isClosed && UserAccess::checkAccess('lead/update')): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $lead->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if ($ls === Lead::LEAD_STATUS_CONVERTED && (int)$lead->is_client === 0 && UserAccess::checkAccess('lead/add-to-client')): ?>
                <?= Html::a('<i class="fas fa-user-plus me-1"></i>Add To Client', ['add-to-client', 'id' => $lead->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $backUrl, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <?= $this->render('_insight', ['lead' => $lead, 'insight' => $insight]) ?>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">Client</div>
                <table class="table ui-detail">
                    <?= $row('Client Name', $lead->client_name) ?>
                    <?= $row('Company Name', $lead->company_name) ?>
                    <?= $row('Email', $lead->email) ?>
                    <?= $row('Contact No', $lead->contact_no) ?>
                    <?= $row('Contact No 2', $lead->contact_no2) ?>
                    <?= $row('City', $lead->country) ?>
                    <?= $row('Address', $lead->address) ?>
                    <?= $row('Contact Person Name', $lead->contact_person_name) ?>
                    <?= $row('Contact Person Email', $lead->contact_person_email) ?>
                    <?= $row('Contact Person Mobile', $lead->contact_person_mobile) ?>
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">Lead</div>
                <table class="table ui-detail">
                    <?= $row('Project', $lead->title) ?>
                    <?= $row('Subject', $lead->subject) ?>
                    <?= $row('Temperature', $lead->getPriorityLabel()) ?>
                    <?= $row('Mode', $lead->getModeLabel() . ((int)$lead->mode === Lead::MODE_OTHER && $lead->other_mode ? ' — ' . $lead->other_mode : '')) ?>
                    <?= $row('Current Stage', $lead->getStageLabel()) ?>
                    <?= $row('Estimated value (₹)', $lead->lead_value) ?>
                    <?= $row('Expected Budget', $lead->expected_budget) ?>
                    <?= $row('Industry', $lead->industry) ?>
                    <?= $row('Offering Service', $lead->offering_service) ?>
                    <?= $row('Attended Date & Time', Lead::formatDateTime($lead->meeting_attended_date)) ?>
                    <?= $row('Next Follow Up', $this->render('_due', [
                        'label' => Lead::formatDateTime($lead->meeting_reported_date),
                        'state' => $isClosed ? '' : Lead::followUpState($lead->meeting_reported_date),
                    ]), true) ?>
                    <?= $row('Sales person', Lead::userLabel($lead->assignedTo)) ?>
                    <?= $row('Site visit', implode(' · ', array_filter([$lead->site_visit_date ? Lead::formatDateTime($lead->site_visit_date) : '', $lead->site_visit_by ? Lead::userLabel($lead->siteVisitBy) : '']))) ?>
                    <?= $row('Quotation by', $lead->quotation_by ? Lead::userLabel($lead->quotationBy) : '') ?>
                    <?= $row('Created By', Lead::userLabel($lead->createdBy)) ?>
                    <?= $row('Created Date', Lead::formatDateTime($lead->created_date)) ?>
                    <?php if ($ls === Lead::LEAD_STATUS_CONVERTED): ?>
                        <?= $row('Converted On', Lead::formatDateTime($lead->converted_date_time)) ?>
                    <?php elseif ($ls === Lead::LEAD_STATUS_DROPPED): ?>
                        <?= $row('Dropped On', Lead::formatDateTime($lead->dropped_date_time)) ?>
                        <?= $row('Reason', $lead->reason) ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header ui-section">Enquiry</div>
                <table class="table ui-detail mb-0">
                    <?= $row('Source', $lead->getSourceLabel()) ?>
                    <?= $row('Interested in', $lead->interests ? implode(', ', array_map(fn($c) => $c->name, $lead->interests)) : '') ?>
                    <?= $row('Approx. area', $lead->getAreaLabel()) ?>
                    <?= $row('Installation wanted by', $lead->expected_install_date ? date('d M Y', strtotime($lead->expected_install_date)) : '') ?>
                </table>
                <div class="card-body small border-top">
                    <div class="text-muted text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.04em">Requirement</div>
                    <?= trim((string)$lead->description) !== '' ? Lead::renderRichText($lead->description) : '<span class="text-muted">No requirement noted.</span>' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Attachments<?= $attachments ? ' (' . count($attachments) . ')' : '' ?></div>
        <div class="card-body">
            <?= $this->render('@backend/views/lead-followers/_attachments', ['attachments' => $attachments]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">
            <span>Follow Ups<?= $followers ? ' (' . count($followers) . ')' : '' ?></span>
            <?php if ($canAddFollowUp): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Follow Up', ['/lead-followers/create', 'id' => $lead->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Subject</th>
                    <th>Follow Up</th>
                    <th>Description</th>
                    <th>Created By</th>
                    <th>Assigned To</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$followers): ?>
                    <tr class="ui-empty-row"><td colspan="9" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', [
                            'icon' => 'calendar-check', 'title' => 'No follow-ups yet', 'small' => true,
                            'text' => $canAddFollowUp ? 'Schedule the next conversation with this lead.' : null,
                        ]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($followers as $i => $f): ?>
                    <?php
                    $open  = (int)$f->followup_status === LeadFollowers::FOLLOWUP_OPEN;
                    $state = $open && !$isClosed ? Lead::followUpState($f->follow_up_date) : '';
                    ?>
                    <tr class="<?= $state ? 'fu-' . $state : '' ?>">
                        <td class="cell-num muted"><?= $i + 1 ?></td>
                        <td class="cell-title wrap"><?= Html::encode($f->subject ?: '-') ?></td>
                        <td class="small" data-label="Follow Up"><?= $this->render('_due', ['label' => $f->getScheduledLabel(), 'state' => $state]) ?></td>
                        <td class="wrap small" data-label="Description"><?= trim((string)$f->description) !== '' ? Lead::renderRichText($f->description) : '-' ?></td>
                        <td class="small" data-label="Created By"><?= Html::encode(Lead::userLabel($f->createdBy)) ?></td>
                        <td class="small" data-label="Assigned To"><?= Html::encode(Lead::userLabel($f->assignedTo)) ?></td>
                        <td class="small" data-label="Mode"><?= Html::encode(Lead::modeLabel($f->mode) . ((int)$f->mode === Lead::MODE_OTHER && $f->other_mode ? ' — ' . $f->other_mode : '')) ?></td>
                        <td data-label="Status">
                            <span class="ui-pill tone-<?= $open ? 'accent' : 'neutral' ?>"><?= Html::encode($f->getFollowupStatusLabel()) ?></span>
                        </td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Follow-up actions">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:190px">
                                    <?php if (UserAccess::checkAccess('lead-followers/view')): ?>
                                        <li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['/lead-followers/view', 'id' => $f->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canAttachments): ?>
                                        <li><a href="#" class="dropdown-item" data-ui-modal="#followup-attachments-modal"
                                               data-url="<?= Url::to(['/lead-followers/view-attachments', 'id' => $f->id]) ?>">
                                            <i class="fas fa-paperclip me-2"></i>Attachments</a></li>
                                    <?php endif; ?>
                                    <?php if (!$isClosed && UserAccess::checkAccess('lead-followers/update')): ?>
                                        <li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['/lead-followers/update', 'id' => $f->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($open && !$isClosed && $canCloseFollowUp): ?>
                                        <li><a href="#" class="dropdown-item js-close-followup" data-id="<?= $f->id ?>" data-lead="<?= $lead->id ?>"
                                               data-only-open="<?= $openFollowUps <= 1 ? 1 : 0 ?>"><i class="fas fa-check me-2"></i>Close Follow-up</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="followup-attachments-modal" tabindex="-1" aria-labelledby="followup-attachments-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="followup-attachments-title">Follow-up attachments</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
</div>
