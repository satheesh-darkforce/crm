<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\LeadFollowers[] $followers */
/** @var \backend\models\LeadAttachments[] $attachments */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = $lead->subject ?: $lead->client_name;

$statusLabels = [1 => 'Open', 2 => 'Follow-up', 3 => 'Quoted', 4 => 'Won', 5 => 'Converted', 6 => 'Dropped'];
$statusBadge  = [1 => 'secondary', 2 => 'info', 3 => 'warning', 4 => 'success', 5 => 'primary', 6 => 'danger'];
$st = (int)$lead->lead_status;

$modeLabels     = [1 => 'Online', 2 => 'Offline', 3 => 'Reference'];
$priorityLabels = [1 => 'High', 2 => 'Medium', 3 => 'Low'];
?>
<style>
.info-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#6c757d;font-weight:600}
.info-value{font-size:.875rem}
</style>

<div class="lead-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h4>
            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>"><?= $statusLabels[$st] ?? '—' ?></span>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('Edit', ['update', 'id' => $lead->id], ['class' => 'btn btn-warning btn-sm']) ?>
            <?php if ($lead->lead_status < \backend\models\Lead::LEAD_STATUS_CONVERTED): ?>
                <?= Html::a('Convert to Client', ['convert-to-client', 'id' => $lead->id], ['class' => 'btn btn-success btn-sm']) ?>
            <?php endif; ?>
            <?= Html::a('Delete', ['delete', 'id' => $lead->id], [
                'class' => 'btn btn-danger btn-sm',
                'data'  => ['confirm' => 'Delete this lead?', 'method' => 'post'],
            ]) ?>
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Lead Details -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold py-2">Lead Details</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="info-label">Client Name</div>
                            <div class="info-value fw-semibold"><?= Html::encode($lead->client_name ?: '—') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Company</div>
                            <div class="info-value"><?= Html::encode($lead->company_name ?: '—') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?= Html::encode($lead->contact_no ?: '—') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?= Html::encode($lead->email ?: '—') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">City</div>
                            <div class="info-value"><?= Html::encode($lead->country ?: '—') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Industry</div>
                            <div class="info-value"><?= Html::encode($lead->industry ?: '—') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Source</div>
                            <div class="info-value"><?= $modeLabels[(int)$lead->mode] ?? '—' ?><?= $lead->other_mode ? ' — ' . Html::encode($lead->other_mode) : '' ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Priority</div>
                            <div class="info-value"><?= $priorityLabels[(int)$lead->priority] ?? '—' ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Lead Value</div>
                            <div class="info-value"><?= $lead->lead_value ? '$' . number_format($lead->lead_value, 2) : '—' ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Assigned To</div>
                            <div class="info-value"><?= Html::encode($lead->assignedTo->username ?? '—') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Created By</div>
                            <div class="info-value"><?= Html::encode($lead->createdBy->username ?? '—') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Created Date</div>
                            <div class="info-value"><?= $lead->created_date ? date('d M Y', strtotime($lead->created_date)) : '—' ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Next Follow-up</div>
                            <div class="info-value"><?= $lead->meeting_reported_date ? date('d M Y', strtotime($lead->meeting_reported_date)) : '—' ?></div>
                        </div>
                        <?php if ($lead->offering_service): ?>
                        <div class="col-md-8">
                            <div class="info-label">Offering / Service</div>
                            <div class="info-value"><?= Html::encode($lead->offering_service) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($lead->description): ?>
                        <div class="col-12">
                            <div class="info-label">Description</div>
                            <div class="info-value"><?= nl2br(Html::encode($lead->description)) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($lead->reason): ?>
                        <div class="col-12">
                            <div class="info-label">Reason / Note</div>
                            <div class="info-value text-muted"><?= nl2br(Html::encode($lead->reason)) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Attachments -->
            <?php if (!empty($attachments)): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold py-2">Attachments</div>
                <div class="card-body py-2">
                    <?php foreach ($attachments as $att): ?>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <a href="/uploads/lead_attachments/<?= basename($att->attachment_path) ?>" target="_blank" rel="noreferrer noopener" class="small">
                                <?= Html::encode(basename($att->attachment_path)) ?>
                            </a>
                            <?= Html::a('Remove', ['delete-attachment', 'id' => $att->id], [
                                'class' => 'btn btn-outline-danger btn-sm py-0 px-2',
                                'style' => 'font-size:.72rem',
                                'data'  => ['confirm' => 'Remove this attachment?', 'method' => 'post'],
                            ]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right panel -->
        <div class="col-md-4">
            <!-- Change Status -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold py-2">Change Status</div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin(['action' => ['change-status', 'id' => $lead->id]]); ?>
                    <div class="mb-2">
                        <select name="lead_status" class="form-select form-select-sm">
                            <?php foreach ($statusLabels as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= $lead->lead_status == $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason (optional)" value="<?= Html::encode($lead->reason ?? '') ?>">
                    </div>
                    <?= Html::submitButton('Update Status', ['class' => 'btn btn-sm btn-primary w-100']) ?>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>

            <!-- Add Follow-up -->
            <div class="card border-0 shadow-sm" id="followup">
                <div class="card-header bg-white fw-semibold py-2">Add Follow-up</div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin(['action' => ['add-follower', 'id' => $lead->id]]); ?>
                    <div class="mb-2">
                        <?= \kartik\date\DatePicker::widget([
                            'name'          => 'follow_up_date',
                            'value'         => '',
                            'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                            'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                            'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'Follow-up Date', 'required' => true],
                        ]) ?>
                    </div>
                    <div class="mb-2">
                        <input type="time" name="follow_up_time" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <input type="text" name="subject" class="form-control form-control-sm" placeholder="Subject" required>
                    </div>
                    <div class="mb-2">
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Notes…"></textarea>
                    </div>
                    <?= Html::submitButton('Add Follow-up', ['class' => 'btn btn-sm btn-success w-100']) ?>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Follow-up History -->
    <div class="mt-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-2">Follow-up History (<?= count($followers) ?>)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Subject</th>
                            <th>Notes</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followers as $f): ?>
                            <tr>
                                <td class="small text-muted">
                                    <?= Html::encode($f->follow_up_date) ?>
                                    <?= $f->follow_up_time ? '<br><span style="font-size:.72rem">' . Html::encode($f->follow_up_time) . '</span>' : '' ?>
                                </td>
                                <td class="small"><?= Html::encode($f->subject) ?></td>
                                <td class="small text-muted"><?= nl2br(Html::encode($f->description)) ?></td>
                                <td class="small"><?= Html::encode($f->assignedTo->username ?? '—') ?></td>
                                <td>
                                    <span class="badge bg-<?= $f->followup_status ? 'warning' : 'success' ?>">
                                        <?= $f->followup_status ? 'Active' : 'Done' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($followers)): ?>
                            <tr><td colspan="5" class="text-muted text-center py-4">No follow-ups yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
