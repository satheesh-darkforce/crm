<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\LeadFollowers[] $followers */
/** @var int $openFollowUps */
/** @var \backend\models\LeadAttachments[] $attachments */
/** @var bool $assignedView  opened from Assigned Leads: follow-up status is read-only */

use backend\components\LeadAccess;
use backend\controllers\Controller;
use backend\models\Lead;
use backend\models\LeadFollowers;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'View Lead';

$ls        = (int)$lead->lead_status;
$isClosed  = in_array($ls, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true);
$options   = UserAccess::checkAccess('lead/change-status') ? $lead->nextLeadStatusOptions(LeadAccess::canReopen()) : [];
$canCloseFollowUp = !$assignedView && UserAccess::checkAccess('lead/change-followup-status');
$canAttachments   = UserAccess::checkAccess('lead-followers/view-attachments');
$backUrl   = $assignedView ? ['assigned'] : (Yii::$app->request->referrer ?: ['index']);

$row = function (string $label, $value, bool $raw = false) {
    $value = ($value === null || $value === '') ? '-' : $value;
    return '<tr><th class="text-muted fw-normal" style="width:42%">' . $label . '</th><td>' . ($raw ? $value : Html::encode($value)) . '</td></tr>';
};
?>
<style>
.lead-detail th,.lead-detail td{font-size:.85rem;padding:.4rem .5rem}
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.85rem}
.list-table tr.row-overdue td{background:#fff3e6}
.list-table tr.row-today td{background:#fff9db}
.list-table tr.row-tomorrow td{background:#ebfbee}
.list-table .wrap{white-space:normal;min-width:140px;max-width:320px}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="lead-view">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="fw-semibold mb-0"><?= Html::encode($lead->client_name) ?></h5>
            <small class="text-muted"><?= Html::encode($lead->company_name ?: 'Lead #' . $lead->id) ?></small>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if (count($options) > 1): ?>
                <?= Html::dropDownList('lead_status', $ls, $options, [
                    'class' => 'form-select form-select-sm js-lead-status', 'style' => 'width:auto;min-width:170px',
                    'data-id' => $lead->id, 'data-prev' => $ls,
                ]) ?>
            <?php else: ?>
                <span class="badge bg-<?= Lead::leadStatusBadge($ls) ?> fs-6 fw-normal"><?= $lead->getLeadStatusLabel() ?></span>
            <?php endif; ?>
            <?php if (!$isClosed && UserAccess::checkAccess('lead/update')): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $lead->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if ($ls === Lead::LEAD_STATUS_CONVERTED && (int)$lead->is_client === 0 && UserAccess::checkAccess('lead/add-to-client')): ?>
                <?= Html::a('<i class="fas fa-user-plus me-1"></i>Add To Client', ['add-to-client', 'id' => $lead->id], ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $backUrl, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Client</div>
                <table class="table table-sm table-striped mb-0 lead-detail">
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
                <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Lead</div>
                <table class="table table-sm table-striped mb-0 lead-detail">
                    <?= $row('Project', $lead->title) ?>
                    <?= $row('Subject', $lead->subject) ?>
                    <?= $row('Priority', $lead->getPriorityLabel()) ?>
                    <?= $row('Mode', $lead->getModeLabel() . ((int)$lead->mode === Lead::MODE_OTHER && $lead->other_mode ? ' — ' . $lead->other_mode : '')) ?>
                    <?= $row('Current Stage', $lead->getStageLabel()) ?>
                    <?= $row('Lead Value (in dollars)', $lead->lead_value) ?>
                    <?= $row('Expected Budget', $lead->expected_budget) ?>
                    <?= $row('Industry', $lead->industry) ?>
                    <?= $row('Offering Service', $lead->offering_service) ?>
                    <?= $row('Attended Date & Time', Lead::formatDateTime($lead->meeting_attended_date)) ?>
                    <?= $row('Next Follow Up Date & Time', Lead::formatDateTime($lead->meeting_reported_date)) ?>
                    <?= $row('Assigned To', Lead::userLabel($lead->assignedTo)) ?>
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
                <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Description</div>
                <div class="card-body small"><?= Lead::renderRichText($lead->description) ?></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold small text-uppercase text-muted">Attachments</div>
        <div class="card-body">
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
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold small text-uppercase text-muted">Follow Ups</span>
            <?php if (!$isClosed && UserAccess::checkAccess('lead-followers/create')): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Follow Up', ['/lead-followers/create', 'id' => $lead->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Follow Up</th>
                    <th>Subject</th>
                    <th>Description</th>
                    <th>Created By</th>
                    <th>Assigned To</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th class="text-end" style="width:60px">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$followers): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No follow-ups yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($followers as $i => $f): ?>
                    <?php $open = (int)$f->followup_status === LeadFollowers::FOLLOWUP_OPEN; ?>
                    <tr class="<?= $open ? Lead::followUpRowClass($f->follow_up_date) : '' ?>">
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="small" style="white-space:nowrap"><?= Html::encode($f->getScheduledLabel()) ?></td>
                        <td class="wrap"><?= Html::encode($f->subject) ?></td>
                        <td class="wrap small"><?= Lead::renderRichText($f->description) ?></td>
                        <td class="small"><?= Html::encode(Lead::userLabel($f->createdBy)) ?></td>
                        <td class="small"><?= Html::encode(Lead::userLabel($f->assignedTo)) ?></td>
                        <td class="small"><?= Html::encode(Lead::modeLabel($f->mode) . ((int)$f->mode === Lead::MODE_OTHER && $f->other_mode ? ' — ' . $f->other_mode : '')) ?></td>
                        <td>
                            <span class="badge bg-<?= $open ? 'warning text-dark' : 'secondary' ?>"><?= $f->getFollowupStatusLabel() ?></span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:190px">
                                    <?php if (UserAccess::checkAccess('lead-followers/view')): ?>
                                        <li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['/lead-followers/view', 'id' => $f->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canAttachments): ?>
                                        <li><a href="#" class="dropdown-item js-followup-attachments" data-url="<?= Url::to(['/lead-followers/view-attachments', 'id' => $f->id]) ?>">
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
</div>

<div class="modal fade" id="followup-attachments-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Attachments</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
jQuery(document).on('click', '.js-followup-attachments', function (e) {
    e.preventDefault();
    var \$modal = jQuery('#followup-attachments-modal');
    \$modal.find('.modal-body').html('<div class="text-muted small">Loading…</div>').load(jQuery(this).data('url'), function (r, status) {
        if (status === 'error') { jQuery(this).html('<div class="text-danger small">Could not load attachments.</div>'); }
    });
    bootstrap.Modal.getOrCreateInstance(\$modal[0]).show();
});
JS);
echo $this->render('_status-js');
