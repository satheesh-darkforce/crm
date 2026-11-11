<?php

/** @var yii\web\View $this */
/** @var backend\models\ClientQuotation $model */
/** @var backend\models\ClientQuotation $childModel */
/** @var backend\models\ClientQuotationFile $childFileModel */
/** @var backend\models\ClientQuotation[] $relatedQuotes */
/** @var backend\models\ClientQuotation $rootQuote */
/** @var backend\models\PoDetail[] $poList */
/** @var backend\models\User|null $user */
/** @var backend\models\User|null $additionalUser */

use common\components\UserAccess;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Quotation — ' . $model->quote_no;

$quoteForLabels = [1 => 'IT', 2 => 'IOT', 3 => 'Hardware + Software'];

$status      = (int) $model->status;
$rootStatus  = (int) ($rootQuote->status ?? $status);
$rootCompleted = (int) ($rootQuote->completed_status ?? 0);
$isCancelled = $rootStatus === 2;
$isCompleted = $rootCompleted === 1;
$statusTxt   = $isCancelled ? 'Cancelled' : ($isCompleted ? 'Completed' : 'Active');
$statusCls   = $isCancelled ? 'bg-danger' : ($isCompleted ? 'bg-primary' : 'bg-success');
$quoteForTxt = $quoteForLabels[(int) $model->quote_for] ?? '—';
$simple = \backend\components\Crm::simple(); // Green Divine mode: revisions, PO and IT fields hidden

$fmtDate  = static fn($d) => !empty($d) ? date('d M Y', strtotime((string) $d)) : '—';
$fmtStamp = static fn($d) => !empty($d) ? date('d M Y, h:i A', strtotime((string) $d)) : '—';
?>
<style>
.cq-wrap           { padding: 0; }
.cq-hero           { background: linear-gradient(135deg, #5d4ff0 0%, #7366ff 100%); color:#fff; padding: 24px 28px; border-radius: 14px; box-shadow: none; margin-bottom: 22px; }
.cq-hero h2        { margin: 0 0 4px; font-weight: 700; letter-spacing: .3px; }
.cq-hero .hero-sub { opacity: .9; font-size: .78rem; text-transform: uppercase; letter-spacing: .6px; font-weight: 600; }
.cq-hero .hero-meta{ font-size: .85rem; opacity: .95; margin-top: 12px; }
.cq-hero .badge    { font-size: .72rem; padding: .45em .75em; letter-spacing: .5px; }
.cq-hero .btn      { border-radius: 8px; font-weight: 600; }
.cq-hero .btn-outline-light:hover { color: #0ea5e9; }

.cq-section        { background:#fff; border:1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04); }
.cq-section-head   { display:flex; align-items:center; padding-bottom: 12px; margin-bottom: 18px; border-bottom: 2px solid #f1f5f9; }
.cq-section-head h5{ margin: 0; font-weight: 700; color: #1e293b; font-size: 1rem; text-transform: uppercase; letter-spacing: .5px; }
.cq-section-icon   { width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color:#fff; margin-right: 12px; font-size: .95rem; }
.icon-info         { background: #0ea5e9; }
.icon-history      { background: #f59e0b; }
.icon-reason       { background: #ef4444; }
.icon-files        { background: #8b5cf6; }

.file-row-icon     { width: 32px; height: 32px; border-radius: 8px; background: #ede9fe; color: #6d28d9; display: inline-flex; align-items: center; justify-content: center; font-size: .85rem; margin-right: 8px; }

.cq-field          { margin-bottom: 12px; }
.cq-field .lbl     { display:block; font-size: .72rem; font-weight: 600; letter-spacing: .6px; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
.cq-field .val     { font-size: .95rem; color: #0f172a; font-weight: 500; word-break: break-word; }
.cq-field .val.muted { color: #94a3b8; font-weight: 400; }

.cq-table          { width: 100%; border-collapse: separate; border-spacing: 0; }
.cq-table thead th { background:#f1f5f9; color:#334155; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; white-space: nowrap; }
.cq-table tbody td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: .9rem; color: #1e293b; vertical-align: middle; }
.cq-table tbody tr:last-child td { border-bottom: 0; }
.cq-table tbody tr:hover { background: #f8fafc; }
.cq-table tr.active-row  { background: #eef2ff !important; }

.reason-box        { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; padding: 12px 14px; border-radius: 8px; font-size: .9rem; white-space: pre-line; }
.reason-box.soft   { background: #fffbeb; border-color: #fde68a; color: #78350f; }
</style>

<div class="ui-page cq-wrap">

    <!-- Hero ───────────────────────────────────────────────────────────── -->
    <div class="cq-hero d-flex flex-wrap justify-content-between align-items-start">
        <div>
            <div class="hero-sub">Client Quotation</div>
            <h2><?= Html::encode($model->quote_no) ?></h2>
            <div class="hero-meta">
                <span class="me-3"><i class="fas fa-calendar-alt me-1"></i> <?= $fmtDate($model->quote_date) ?></span>
                <?php if (!$simple): ?><span class="me-3"><i class="fas fa-tag me-1"></i> <?= Html::encode($quoteForTxt) ?></span><?php endif; ?>
                <span class="badge <?= $statusCls ?>"><?= Html::encode($statusTxt) ?></span>
                <?php [$qsLabel, $qsTone] = $model->getQuoteStatusPill(); ?>
                <span class="ui-pill tone-<?= $qsTone ?> ms-1" title="Sales status"><?= Html::encode($qsLabel) ?></span>
                <?php if ($model->valid_until): ?><span class="ms-2"><i class="fas fa-hourglass-half me-1"></i>Valid until <?= $fmtDate($model->valid_until) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <?php if (UserAccess::checkAccess('client-quotation/cancel') && !$isCancelled && !$isCompleted): ?>
                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#cancelQuoteModal">
                    <i class="fas fa-ban me-1"></i> Cancel
                </button>
            <?php endif; ?>
            <?php if (UserAccess::checkAccess('client-quotation/download-latest')): ?>
                <a class="btn btn-sm btn-light" target="_blank"
                   href="<?= Url::to(['download-latest', 'id' => $model->id]) ?>">
                    <i class="fas fa-file-pdf me-1"></i> Download PDF
                </a>
            <?php endif; ?>
            <?php if (!$simple && UserAccess::checkAccess('client-quotation/add-child') && !$isCancelled && !$isCompleted): ?>
                <button type="button" class="btn btn-sm btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#addChildModal">
                    <i class="fas fa-plus me-1"></i> Add Revision
                </button>
            <?php endif; ?>
            <?php if (!$simple && UserAccess::checkAccess('client-quotation/add-po') && !$isCancelled && !$isCompleted): ?>
                <a class="btn btn-sm btn-success" href="<?= Url::to(['add-po', 'id' => $model->id]) ?>"
                   onclick="event.preventDefault(); window.location.replace(this.href); return false;">
                    <i class="fas fa-file-invoice-dollar me-1"></i> Add PO
                </a>
            <?php endif; ?>
            <?php if (!$simple && UserAccess::checkAccess('client-quotation/complete-quote') && !$isCancelled && !$isCompleted && !empty($poList)): ?>
                <button type="button" class="btn btn-sm btn-primary" id="btn-complete-quote">
                    <i class="fas fa-check-circle me-1"></i> Complete Quote
                </button>
            <?php endif; ?>
            <?php
            $qs       = (int)$model->quote_status;
            $canEdit  = UserAccess::checkAccess('client-quotation/update') && !$isCancelled && $qs !== \backend\models\ClientQuotation::QS_INVOICED;
            $hasItems = $model->hasItems();
            ?>
            <?php if ($canEdit): ?>
                <a class="btn btn-sm btn-light" href="<?= Url::to(['update', 'id' => $model->id]) ?>"><i class="fas fa-pen me-1"></i> Edit</a>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-flag me-1"></i> Status</button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <?php foreach ([\backend\models\ClientQuotation::QS_SENT => ['paper-plane', 'Mark as sent'], \backend\models\ClientQuotation::QS_ACCEPTED => ['check', 'Mark as accepted'],
                                        \backend\models\ClientQuotation::QS_REJECTED => ['times', 'Mark as rejected'], \backend\models\ClientQuotation::QS_DRAFT => ['undo', 'Back to draft']] as $st => [$icon, $label]): ?>
                            <?php if ($st !== $qs): ?>
                                <li><?= Html::a('<i class="fas fa-' . $icon . ' fa-fw me-1"></i>' . $label, ['set-status', 'id' => $model->id], ['class' => 'dropdown-item', 'encode' => false,
                                    'data' => ['method' => 'post', 'params' => ['quote_status' => $st]]]) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($hasItems && UserAccess::checkAccess('invoice/create')): ?>
                <?php if ($qs === \backend\models\ClientQuotation::QS_INVOICED && $model->invoice_id): ?>
                    <a class="btn btn-sm btn-light" href="<?= Url::to(['/invoice/view', 'id' => $model->invoice_id]) ?>"><i class="fas fa-file-invoice me-1"></i> View invoice</a>
                <?php elseif ($qs === \backend\models\ClientQuotation::QS_ACCEPTED): ?>
                    <a class="btn btn-sm btn-success" href="<?= Url::to(['create-invoice', 'id' => $model->id]) ?>"><i class="fas fa-file-invoice me-1"></i> Create invoice</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (UserAccess::checkAccess('client-quotation/duplicate')): ?>
                <?= Html::a('<i class="fas fa-copy me-1"></i> Duplicate', ['duplicate', 'id' => $model->id], ['class' => 'btn btn-sm btn-light', 'encode' => false,
                    'data' => ['method' => 'post', 'confirm' => 'Make a new draft quotation with the same client, items and terms?']]) ?>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-light" href="<?= Url::to(['index']) ?>"
               onclick="event.preventDefault(); window.location.replace(this.href); return false;">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- Quotation Info ──────────────────────────────────────────────────── -->
    <div class="cq-section">
        <div class="cq-section-head">
            <span class="cq-section-icon icon-info"><i class="fas fa-id-card"></i></span>
            <h5>Quotation Info</h5>
        </div>
        <div class="row">
            <div class="col-md-4 cq-field">
                <span class="lbl">Client</span>
                <span class="val <?= empty($model->client->client_name) ? 'muted' : '' ?>"><?= Html::encode($model->client->client_name ?? '—') ?></span>
            </div>
            <div class="col-md-4 cq-field">
                <span class="lbl">Company</span>
                <span class="val <?= empty($model->client->company_name) ? 'muted' : '' ?>"><?= Html::encode($model->client->company_name ?? '—') ?></span>
            </div>
            <div class="col-md-4 cq-field">
                <span class="lbl">Project</span>
                <span class="val <?= empty($model->project_name) ? 'muted' : '' ?>"><?= Html::encode($model->project_name ?: '—') ?></span>
            </div>
            <div class="col-md-4 cq-field">
                <span class="lbl">Quote Date</span>
                <span class="val"><?= $fmtDate($model->quote_date) ?></span>
            </div>
            <div class="col-md-4 cq-field<?= $simple ? ' d-none' : '' ?>">
                <span class="lbl">Deployment Type</span>
                <span class="val"><?= Html::encode(backend\models\ClientQuotation::deploymentTypeOptions()[(int) $model->deployment_type] ?? 'Deployment') ?></span>
            </div>
            <div class="col-md-4 cq-field">
                <span class="lbl"><?= $model->grand_total !== null ? 'Quote Total' : 'Estimation Cost' ?></span>
                <span class="val <?= empty($model->estimation_cost) ? 'muted' : '' ?>"><?= $model->grand_total !== null ? '₹' . Yii::$app->formatter->asDecimal((float)$model->grand_total, 0) : Html::encode($model->estimation_cost ?: '—') ?></span>
            </div>
            <div class="col-md-4 cq-field">
                <span class="lbl">Quoted By</span>
                <span class="val <?= empty($user->username) ? 'muted' : '' ?>"><?= Html::encode($user->username ?? '—') ?></span>
            </div>
            <div class="col-md-4 cq-field<?= $simple ? ' d-none' : '' ?>">
                <span class="lbl">Additional Quote By</span>
                <span class="val <?= empty($additionalUser->username) ? 'muted' : '' ?>"><?= Html::encode($additionalUser->username ?? '—') ?></span>
            </div>
            <div class="col-12 cq-field">
                <span class="lbl">Address</span>
                <span class="val <?= empty($model->address) ? 'muted' : '' ?>"><?= nl2br(Html::encode($model->address ?: '—')) ?></span>
            </div>
        </div>

        <?php if (!empty($model->cancel_reason)): ?>
            <div class="mt-3">
                <span class="lbl d-block mb-1" style="font-size:.72rem;font-weight:600;letter-spacing:.6px;color:#64748b;text-transform:uppercase;">Cancel Reason</span>
                <div class="reason-box"><?= Html::encode($model->cancel_reason) ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($model->alter_reason)): ?>
            <div class="mt-3">
                <span class="lbl d-block mb-1" style="font-size:.72rem;font-weight:600;letter-spacing:.6px;color:#64748b;text-transform:uppercase;">Alter Reason</span>
                <div class="reason-box soft"><?= Html::encode($model->alter_reason) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <?= $this->render('_items', ['model' => $model]) ?>

    <!-- Attached Files (revision history + per-revision downloads) ───── -->
    <?php if (!empty($relatedQuotes)): ?>
    <div class="cq-section">
        <div class="cq-section-head">
            <span class="cq-section-icon icon-files"><i class="fas fa-paperclip"></i></span>
            <h5>Attached Files</h5>
            <span class="ms-auto small text-muted"><?= count($relatedQuotes) ?> row(s)</span>
        </div>
        <div class="table-responsive">
            <table class="cq-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Quote No</th>
                        <th>Quote For</th>
                        <th>Estimated Cost</th>
                        <th>Alter Reason</th>
                        <th>Created At</th>
                        <th>Status</th>
                        <th>Files</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relatedQuotes as $i => $q):
                        $isParent   = empty($q->parent_id);
                        $alter      = $isParent ? '' : (string) ($q->alter_reason ?? '');
                        $createdRaw = $q->created_at ?? $q->quote_date ?? null;
                        $rowFiles   = $q->files ?? []; ?>
                        <tr class="<?= $q->id === $model->id ? 'active-row' : '' ?>">
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><strong><?= Html::encode($q->quote_no) ?></strong></td>
                            <td><?= Html::encode($quoteForLabels[(int) $q->quote_for] ?? '—') ?></td>
                            <td><?= $q->estimation_cost !== null && $q->estimation_cost !== '' ? Html::encode($q->estimation_cost) : '<span class="text-muted">—</span>' ?></td>
                            <td style="max-width:220px;white-space:pre-line;">
                                <?= $alter !== '' ? Html::encode($alter) : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td class="meta-cell"><?= $fmtStamp($createdRaw) ?></td>
                            <td>
                                <?php $qc = (int)$q->status === 2 ? 'bg-danger' : 'bg-success'; ?>
                                <span class="badge <?= $qc ?>"><?= (int)$q->status === 2 ? 'Cancelled' : 'Active' ?></span>
                            </td>
                            <td style="min-width:180px;">
                                <?php if (empty($rowFiles)): ?>
                                    <span class="text-muted small">—</span>
                                <?php else: ?>
                                    <?php foreach ($rowFiles as $f):
                                        $ext  = strtolower(pathinfo((string) $f->file_path, PATHINFO_EXTENSION));
                                        $icon = in_array($ext, ['pdf'], true)
                                            ? 'fa-file-pdf'
                                            : (in_array($ext, ['doc','docx'], true)
                                                ? 'fa-file-word'
                                                : (in_array($ext, ['png','jpg','jpeg'], true) ? 'fa-file-image' : 'fa-file'));
                                        $name = basename((string) $f->file_path); ?>
                                        <a href="<?= Url::to(['download-file', 'id' => $f->id]) ?>"
                                           class="btn btn-sm btn-outline-primary me-1 mb-1"
                                           target="_blank" rel="noopener"
                                           title="View / Download <?= Html::encode($name) ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Purchase Orders ───────────────────────────────────────────────── -->
    <?php if (!$simple): ?>
    <?php
    // Given Cost = the latest quotation's estimation_cost in this tree.
    $latestQuoteForView = end($relatedQuotes) ?: $rootQuote;
    if (is_array($relatedQuotes) && !empty($relatedQuotes)) {
        $latestQuoteForView = $relatedQuotes[count($relatedQuotes) - 1];
    }
    $givenCostDisplay = (string) ($latestQuoteForView->estimation_cost ?? '');
    ?>
    <div class="cq-section">
        <div class="cq-section-head">
            <span class="cq-section-icon" style="background:#10b981;"><i class="fas fa-file-invoice-dollar"></i></span>
            <h5>Purchase Orders</h5>
            <span class="ms-auto small text-muted"><?= count($poList) ?> PO(s)</span>
        </div>

        <?php if (empty($poList)): ?>
            <div class="text-muted small">No purchase orders raised yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="cq-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>PO Number</th>
                            <th>Date</th>
                            <th>Particulars</th>
                            <th class="text-end">Given Cost</th>
                            <th class="text-end">PO Cost</th>
                            <th>File</th>
                            <th>Created</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poList as $i => $po):
                            $reduced = is_numeric($po->po_cost) && is_numeric($givenCostDisplay)
                                && (float)$po->po_cost < (float)$givenCostDisplay; ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td><strong><?= Html::encode($po->po_number) ?></strong></td>
                                <td><?= $fmtDate($po->date) ?></td>
                                <td style="max-width:240px; white-space:pre-line;"><?= Html::encode($po->particulars) ?></td>
                                <td class="text-end"><?= $givenCostDisplay !== '' ? Html::encode($givenCostDisplay) : '—' ?></td>
                                <td class="text-end <?= $reduced ? 'text-danger fw-semibold' : '' ?>">
                                    <?= Html::encode($po->po_cost) ?>
                                </td>
                                <td>
                                    <?php if (!empty($po->file)):
                                        $poExt  = strtolower(pathinfo((string) $po->file, PATHINFO_EXTENSION));
                                        $poIcon = $poExt === 'pdf'
                                            ? 'fa-file-pdf'
                                            : (in_array($poExt, ['doc','docx'], true)
                                                ? 'fa-file-word'
                                                : (in_array($poExt, ['png','jpg','jpeg','gif','webp'], true)
                                                    ? 'fa-file-image'
                                                    : 'fa-file'));
                                    ?>
                                        <a href="<?= Url::to(['download-po', 'id' => $po->id]) ?>"
                                           class="btn btn-sm btn-outline-primary"
                                           target="_blank" rel="noopener"
                                           title="View / Download PO (<?= strtoupper(Html::encode($poExt)) ?>)">
                                            <i class="fas <?= $poIcon ?>"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="meta-cell"><?= $fmtDate($po->created_at) ?></td>
                                <td style="max-width:240px; white-space:pre-line;">
                                    <?= !empty($po->reason) ? Html::encode($po->reason) : '<span class="text-muted">—</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; /* purchase orders */ ?>

</div>

<!-- Cancel modal ───────────────────────────────────────────────────────── -->
<?php if ($status !== 2 && UserAccess::checkAccess('client-quotation/cancel')): ?>
<div class="modal fade" id="cancelQuoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Quotation <?= Html::encode($model->quote_no) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea id="cancel-reason" class="form-control form-control-sm" rows="3" placeholder="Why is this quotation being cancelled?"></textarea>
                <div class="text-danger small mt-1" id="cancel-reason-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirm-cancel-quote">Cancel Quotation</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Revision modal ─────────────────────────────────────────────────── -->
<?php
// A revision inherits the root's deployment type. When the tree is a Trial, the
// estimation cost is optional on the revision too (mirrors the create form).
$isTrialRevision = (int) $rootQuote->deployment_type === \backend\models\ClientQuotation::TRIAL;
?>
<?php if ($status !== 2 && UserAccess::checkAccess('client-quotation/add-child')): ?>
<div class="modal fade" id="addChildModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <?php $childForm = ActiveForm::begin([
                'id' => 'child-quotation-form',
                'action' => Url::to(['add-child', 'id' => $model->id]),
                'options' => ['enctype' => 'multipart/form-data'],
                'fieldConfig' => [
                    'options' => ['class' => 'mb-3'],
                    'labelOptions' => ['class' => 'form-label'],
                    'inputOptions' => ['class' => 'form-control form-control-sm'],
                ],
            ]);
            // Hidden fields mirror the parent's data so preview-pdf can render
            // the same template that the create form uses.
            echo Html::activeHiddenInput($childModel, 'client_id',          ['value' => $model->client_id]);
            echo Html::activeHiddenInput($childModel, 'project_name',       ['value' => $model->project_name]);
            echo Html::activeHiddenInput($childModel, 'address',            ['value' => $model->address]);
            echo Html::activeHiddenInput($childModel, 'quote_date',         ['value' => date('Y-m-d')]);
            echo Html::activeHiddenInput($childModel, 'quoted_by',          ['value' => (int) Yii::$app->user->id]);
            echo Html::activeHiddenInput($childModel, 'alternate_quote_by', ['value' => $rootQuote->alternate_quote_by ?? '']);
            // Deployment type is always inherited from the root — never editable
            // on a revision. Carried so preview-pdf renders the same as the parent.
            echo Html::activeHiddenInput($childModel, 'deployment_type', ['value' => (int) $rootQuote->deployment_type]);
            ?>
            <div class="modal-header">
                <h5 class="modal-title">Add Revision to <?= Html::encode($model->quote_no) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6"><?= $childForm->field($childModel, 'quote_no')->textInput(['readonly' => true])->label('Quote No <span class="text-danger">*</span>', ['encode' => false]) ?></div>
                    <?php
                    // Quote For is inherited from the parent and not editable on a revision.
                    $childModel->quote_for = $model->quote_for;
                    $parentQuoteForLabel = $quoteForLabels[$model->quote_for] ?? '';
                    ?>
                    <div class="col-md-6">
                        <?= Html::activeHiddenInput($childModel, 'quote_for') ?>
                        <div class="mb-3">
                            <label class="form-label">Quote For <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" value="<?= Html::encode($parentQuoteForLabel) ?>" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?= $childForm->field($childModel, 'estimation_cost')->textInput([
                            'type' => 'number',
                            'min' => 0,
                            'required' => !$isTrialRevision,
                            'placeholder' => 'Estimated Cost',
                        ])->label('Estimation Cost' . ($isTrialRevision ? '' : ' <span class="text-danger">*</span>'), ['encode' => false]) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $childForm->field($childFileModel, 'file_path')->fileInput([
                            'accept' => 'application/pdf,.pdf',
                            'required' => true,
                        ])->label('Attach File (PDF only) <span class="text-danger">*</span>', ['encode' => false]) ?>
                    </div>
                    <?php if ($isTrialRevision): ?>
                    <div class="col-12 mb-3">
                        <small class="text-muted">Optional for a Trial quotation — leave blank to omit it from the proposal.</small>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <?= $childForm->field($childModel, 'alter_reason')->textarea([
                            'rows'        => 2,
                            'placeholder' => 'Why is this revision being issued?',
                            'required'    => true,
                        ])->label('Alter Reason <span class="text-danger">*</span>', ['encode' => false]) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info btn-sm" id="child-preview-doc" style="display:none;">
                    <i class="fas fa-eye me-1"></i> Preview
                </button>
                <button type="submit" class="btn btn-primary btn-sm">Save Revision</button>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$cancelUrl   = Url::to(['cancel']);
$completeUrl = Url::to(['complete-quote']);
$previewUrl  = Url::to(['preview-pdf']);
$isTrialJs   = $isTrialRevision ? 'true' : 'false';
$this->registerJs(<<<JS
(function () {
    $('#btn-complete-quote').on('click', function () {
        if (!window.Swal) { return; }
        Swal.fire({
            icon: 'question',
            title: 'Mark this quotation as completed?',
            text: 'Once completed, no further POs can be added under this quotation.',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Yes, complete it',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (!result.isConfirmed) { return; }
            $.ajax({
                url: '$completeUrl', type: 'POST',
                data: { id: {$model->id} },
                dataType: 'json',
                success: function (r) {
                    if (r.success) {
                        Swal.fire({icon:'success', title:'Completed', text:r.message||'Quotation completed.', timer:1400, showConfirmButton:false})
                            .then(function(){ location.reload(); });
                    } else {
                        Swal.fire({icon:'error', title:'Error', text:r.message || 'Unable to complete quotation.'});
                    }
                },
                error: function () {
                    Swal.fire({icon:'error', title:'Error', text:'Something went wrong. Please try again.'});
                }
            });
        });
    });

    $('#confirm-cancel-quote').on('click', function () {
        var reason = $('#cancel-reason').val().trim();
        if (!reason) { $('#cancel-reason-error').text('Please enter a reason.'); return; }
        $('#cancel-reason-error').text('');
        $.ajax({
            url: '$cancelUrl', type: 'POST',
            data: { id: {$model->id}, reason: reason },
            dataType: 'json',
            success: function (r) {
                if (r.success) location.reload();
                else if (window.Swal) Swal.fire({title:'Error', text:r.message||'Unable to cancel', icon:'error'});
                else if (window.Swal) Swal.fire({icon: 'error', title: 'Error', text: r.message || 'Unable to cancel'});
                else alert(r.message || 'Unable to cancel');
            }
        });
    });

    // Defensive: ensure every Attached-Files file link opens in a new tab,
    // regardless of cached HTML. The PHP view already emits target="_blank",
    // this is just a runtime safety net.
    document.querySelectorAll('a[href*="download-file"]').forEach(function (a) {
        a.setAttribute('target', '_blank');
        a.setAttribute('rel', 'noopener');
    });

    // ── Add Revision: Preview button ────────────────────────────────────
    // Shows once estimation_cost, alter_reason and a PDF file have all been
    // entered, then submits the modal form to preview-pdf in a new tab.
    var childForm   = $('#child-quotation-form');
    var previewBtn  = $('#child-preview-doc');
    var costInput   = $('#clientquotation-estimation_cost');
    var reasonInput = $('#clientquotation-alter_reason');
    var fileInput   = $('#clientquotationfile-file_path');

    function childRevisionReady() {
        var cost   = (costInput.val() || '').trim();
        var reason = (reasonInput.val() || '').trim();
        var hasPdf = !!(fileInput[0] && fileInput[0].files && fileInput[0].files[0]);
        if (!hasPdf) return false;
        var name = fileInput[0].files[0].name.toLowerCase();
        var isPdf = (fileInput[0].files[0].type === 'application/pdf') || name.slice(-4) === '.pdf';
        if (!isPdf) return false;
        // Trial revisions may leave the cost blank; if entered it must still be a
        // positive integer. Deployment revisions require a positive cost.
        if ($isTrialJs) {
            if (cost && (!/^\d+\$/.test(cost) || parseInt(cost, 10) <= 0)) return false;
        } else {
            if (!cost || !/^\d+\$/.test(cost) || parseInt(cost, 10) <= 0) return false;
        }
        if (!reason) return false;
        return true;
    }
    function refreshPreviewBtn() {
        previewBtn.toggle(childRevisionReady());
    }

    costInput.on('input change', refreshPreviewBtn);
    reasonInput.on('input change', refreshPreviewBtn);
    fileInput.on('change', refreshPreviewBtn);

    previewBtn.on('click', function (e) {
        e.preventDefault();
        if (!childRevisionReady()) {
            if (window.Swal) Swal.fire({title:'Missing Fields', text:'Please fill all the details before preview', icon:'warning'});
            else alert('Please fill all the details before preview');
            return false;
        }
        var prevAction = childForm.attr('action');
        var prevTarget = childForm.attr('target');
        var prevMethod = childForm.attr('method');

        childForm.attr('action', '$previewUrl');
        childForm.attr('target', '_blank');
        childForm.attr('method', 'post');
        childForm[0].submit();

        window.setTimeout(function () {
            childForm.attr('action', prevAction || '');
            if (prevTarget) childForm.attr('target', prevTarget); else childForm.removeAttr('target');
            childForm.attr('method', prevMethod || 'post');
        }, 0);
    });

    // Reset the button visibility when the modal is reopened.
    $('#addChildModal').on('shown.bs.modal hidden.bs.modal', refreshPreviewBtn);
})();
JS);
?>
