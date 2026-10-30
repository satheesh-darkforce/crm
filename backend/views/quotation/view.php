<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Quotation $model */
/** @var \backend\models\QuotationDetail $detail */
/** @var \backend\models\Currency[] $currencies */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$this->title = 'Quotation — ' . Html::encode($model->unique_id);

$remarks = $model->remarks;
$details = $model->details;

$fmtDate  = static fn($d) => !empty($d) ? date('d M Y', strtotime((string) $d)) : '—';
$fmtStamp = static fn($d) => !empty($d) ? date('d M Y, h:i A', strtotime((string) $d)) : '—';
?>
<style>
.qv-wrap      { background: #f8fafc; padding: 28px 20px; border-radius: 15px; }
.qv-hero      { background: linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%); color:#fff; padding: 24px 28px; border-radius: 14px; box-shadow: 0 10px 25px -10px rgba(14,165,233,.4); margin-bottom: 22px; }
.qv-hero h2   { margin: 0 0 4px; font-weight: 700; letter-spacing: .3px; }
.qv-hero .hero-sub { opacity: .9; font-size: .85rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
.qv-hero .hero-meta{ font-size: .85rem; opacity: .95; margin-top: 12px; }
.qv-hero .btn { border-radius: 8px; font-weight: 600; }
.qv-hero .btn-outline-light:hover { color: #0ea5e9; }

.qv-section       { background:#fff; border:1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04); }
.qv-section-head  { display:flex; align-items:center; padding-bottom: 12px; margin-bottom: 18px; border-bottom: 2px solid #f1f5f9; }
.qv-section-head h5 { margin: 0; font-weight: 700; color: #1e293b; font-size: 1rem; text-transform: uppercase; letter-spacing: .5px; }
.qv-section-icon  { width: 34px; height: 34px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color:#fff; margin-right: 12px; font-size: .95rem; }
.icon-info   { background: #0ea5e9; }
.icon-detail { background: #f59e0b; }
.icon-add    { background: #10b981; }
.icon-remark { background: #8b5cf6; }
.icon-count  { margin-left:auto; background:#eef2ff; color:#4338ca; padding: 3px 10px; border-radius: 999px; font-size:.78rem; font-weight: 600; }

.qv-field         { margin-bottom: 10px; }
.qv-field .lbl    { display:block; font-size: .72rem; font-weight: 600; letter-spacing: .6px; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
.qv-field .val    { font-size: .95rem; color: #0f172a; font-weight: 500; word-break: break-word; }
.qv-field .val.muted { color: #94a3b8; font-weight: 400; }

.qv-table         { width: 100%; border-collapse: separate; border-spacing: 0; }
.qv-table thead th{ background:#f1f5f9; color:#334155; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .5px; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; white-space: nowrap; }
.qv-table tbody td{ padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: .9rem; color: #1e293b; vertical-align: middle; }
.qv-table tbody tr:last-child td { border-bottom: 0; }
.qv-table tbody tr:hover { background: #f8fafc; }
.amt-cell         { font-weight: 600; color: #0f172a; }
.amt-cell .ccy    { color: #64748b; font-weight: 500; font-size: .82rem; margin-right: 4px; }
.ccy-chip         { display:inline-block; padding: 3px 10px; border-radius: 999px; background: #ecfeff; color: #0e7490; font-weight: 600; font-size: .78rem; letter-spacing: .3px; }
.meta-cell        { color:#64748b; font-size: .82rem; }

.empty-state      { padding: 26px 10px; text-align:center; color:#94a3b8; font-size: .9rem; }
</style>

<div class="qv-wrap">

    <!-- Hero ───────────────────────────────────────────────────────────── -->
    <div class="qv-hero d-flex flex-wrap justify-content-between align-items-start">
        <div>
            <div class="hero-sub">Quotation</div>
            <h2><?= Html::encode($model->unique_id) ?></h2>
            <div class="hero-meta">
                <span class="me-3"><i class="fas fa-user me-1"></i> <?= Html::encode($model->client_name) ?></span>
                <?php if (!empty($model->project_name)): ?>
                    <span class="me-3"><i class="fas fa-diagram-project me-1"></i> <?= Html::encode($model->project_name) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar-alt me-1"></i> <?= $fmtDate($model->created_date) ?></span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0">
            <button class="btn btn-sm btn-light" type="button"
                    data-bs-toggle="modal" data-bs-target="#remarkModal"
                    data-id="<?= $model->id ?>">
                <i class="fas fa-comment-dots me-1"></i> Add Remark
            </button>
            <a class="btn btn-sm btn-outline-light" href="<?= Url::to(['index']) ?>">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- Quotation Info ──────────────────────────────────────────────────── -->
    <div class="qv-section">
        <div class="qv-section-head">
            <span class="qv-section-icon icon-info"><i class="fas fa-id-card"></i></span>
            <h5>Quotation Info</h5>
        </div>
        <div class="row">
            <div class="col-md-3 qv-field">
                <span class="lbl">Client</span>
                <span class="val <?= empty($model->client_name) ? 'muted' : '' ?>"><?= Html::encode($model->client_name ?: '—') ?></span>
            </div>
            <div class="col-md-3 qv-field">
                <span class="lbl">Email</span>
                <span class="val <?= empty($model->email) ? 'muted' : '' ?>"><?= Html::encode($model->email ?: '—') ?></span>
            </div>
            <div class="col-md-2 qv-field">
                <span class="lbl">Mobile</span>
                <span class="val <?= empty($model->mobile) ? 'muted' : '' ?>"><?= Html::encode($model->mobile ?: '—') ?></span>
            </div>
            <div class="col-md-4 qv-field">
                <span class="lbl">Project</span>
                <span class="val <?= empty($model->project_name) ? 'muted' : '' ?>"><?= Html::encode($model->project_name ?: '—') ?></span>
            </div>
        </div>
    </div>

    <!-- Quotation Details ──────────────────────────────────────────────── -->
    <div class="qv-section">
        <div class="qv-section-head">
            <span class="qv-section-icon icon-detail"><i class="fas fa-list-ol"></i></span>
            <h5>Quotation Details</h5>
            <span class="icon-count"><?= count($details) ?> version<?= count($details) != 1 ? 's' : '' ?></span>
        </div>
        <div class="table-responsive">
            <table class="qv-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Currency</th>
                        <th class="text-end">Quote Amount</th>
                        <th>Proposal Send Date</th>
                        <th>Attachments</th>
                        <th>Added By</th>
                        <th>Added On</th>
                        <th class="text-end" style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($details)): ?>
                    <tr><td colspan="8" class="empty-state">No quotation details yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($details as $i => $d): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><span class="ccy-chip"><?= Html::encode($d->currency->title ?? '—') ?></span></td>
                        <td class="text-end amt-cell">
                            <?php if (!empty($d->currency->currency)): ?>
                                <span class="ccy"><?= Html::encode($d->currency->currency) ?></span>
                            <?php endif; ?>
                            <?= number_format((float)$d->quote_amount, 2) ?>
                        </td>
                        <td><?= $fmtDate($d->proposal_send_date) ?></td>
                        <td>
                            <?php if (!empty($d->attachments)): ?>
                                <?php foreach ($d->attachments as $att):
                                    $ext  = strtolower(pathinfo($att->path, PATHINFO_EXTENSION));
                                    $icon = in_array($ext, ['pdf']) ? '<i class="fas fa-file-alt"></i>' : (in_array($ext, ['doc','docx']) ? '<i class="fas fa-pen-to-square"></i>' : '<i class="fas fa-paperclip"></i>');
                                    ?>
                                    <a href="<?= Yii::$app->request->baseUrl . Html::encode($att->path) ?>"
                                       target="_blank" rel="noreferrer noopener"
                                       class="me-1 text-decoration-none small"
                                       title="<?= Html::encode(basename($att->path)) ?>">
                                        <?= $icon ?> <?= strtoupper($ext) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="meta-cell"><?= Html::encode($d->createdByUser->username ?? '—') ?></td>
                        <td class="meta-cell"><?= $fmtStamp($d->created_date) ?></td>
                        <td class="text-end">
                            <?= Html::a('<i class="fas fa-times"></i>', ['delete-detail', 'id' => $d->id], [
                                'class'        => 'btn btn-outline-danger btn-sm px-2 py-0',
                                'title'        => 'Remove this version',
                                'data-method'  => 'post',
                                'data-confirm' => 'Remove this quote version?',
                                'encode'       => false,
                                'style'        => 'font-size:.75rem;',
                            ]) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Quotation Detail ───────────────────────────────────────────── -->
    <div class="qv-section">
        <div class="qv-section-head">
            <span class="qv-section-icon icon-add"><i class="fas fa-plus"></i></span>
            <h5>Add Quotation Detail</h5>
        </div>

        <?php $form = ActiveForm::begin([
            'options' => ['enctype' => 'multipart/form-data'],
            'enableClientValidation' => false,
        ]); ?>
        <div class="row g-3">
            <div class="col-md-3">
                <?= $form->field($detail, 'currency_id')->widget(Select2::class, [
                    'data'          => ArrayHelper::map($currencies, 'id', 'title'),
                    'options'       => ['placeholder' => '— Select Currency —'],
                    'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                ])->label('Currency <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($detail, 'quote_amount')->textInput([
                    'class'       => 'form-control form-control-sm',
                    'placeholder' => '0.00',
                    'type'        => 'number',
                    'step'        => '0.01',
                    'min'         => '0.01',
                ])->label('Quote Amount <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($detail, 'proposal_send_date')->widget(DatePicker::class, [
                    'type'          => DatePicker::TYPE_INPUT,
                    'options'       => ['class' => 'form-control form-control-sm', 'required' => true],
                    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => '0d'],
                ])->label('Proposal Send Date <span class="text-danger">*</span>', ['encode' => false]) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($detail, 'attachmentFiles[]')->fileInput([
                    'multiple' => true,
                    'accept'   => '.pdf,.doc,.docx,.ppt,.pptx',
                    'class'    => 'form-control form-control-sm',
                ])->label('Attachments <small class="text-muted">(PDF/DOC/PPT, max 5)</small>', ['encode' => false]) ?>
            </div>
        </div>
        <div class="mt-3">
            <?= Html::submitButton('<i class="fas fa-plus me-1"></i> Add Quotation Detail', [
                'class'  => 'btn btn-primary btn-sm px-4',
                'encode' => false,
            ]) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>

    <!-- Remarks ────────────────────────────────────────────────────────── -->
    <div class="qv-section">
        <div class="qv-section-head">
            <span class="qv-section-icon icon-remark"><i class="fas fa-comments"></i></span>
            <h5>Quotation Remarks</h5>
            <span class="icon-count"><?= count($remarks) ?></span>
        </div>

        <?php if (empty($remarks)): ?>
            <div class="empty-state">No remarks yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="qv-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Remarks</th>
                            <th style="width:160px;">Added By</th>
                            <th style="width:180px;">Added On</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($remarks as $i => $r): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td style="white-space:pre-line;"><?= Html::encode($r->remarks) ?></td>
                            <td class="meta-cell"><?= Html::encode($r->createdByUser->username ?? '—') ?></td>
                            <td class="meta-cell"><?= $fmtStamp($r->created_date) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Add Remark Modal -->
<div class="modal fade" id="remarkModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold">Add Remark</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="remarkModalBody">
                <div class="text-center text-muted py-3 small">Loading…</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="saveRemarkBtn">Save Remark</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var remarkModal  = document.getElementById('remarkModal');
    var remarkBody   = document.getElementById('remarkModalBody');
    var saveBtn      = document.getElementById('saveRemarkBtn');
    var currentId    = null;

    // Load form partial when modal opens
    remarkModal.addEventListener('show.bs.modal', function (e) {
        currentId = (e.relatedTarget || document.querySelector('[data-bs-target="#remarkModal"]')).getAttribute('data-id');
        remarkBody.innerHTML = '<div class="text-center text-muted py-3 small">Loading…</div>';
        fetch('/quotation/add-remark?id=' + currentId, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function(r) { return r.text(); })
            .then(function(html) { remarkBody.innerHTML = html; });
    });

    // Save via AJAX
    saveBtn.addEventListener('click', function () {
        var form = document.getElementById('remarkForm');
        if (!form) return;
        var data = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: data,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(remarkModal).hide();
                window.location.reload();
            } else {
                var errDiv = document.getElementById('remarkErrors');
                if (errDiv) {
                    var msgs = Object.values(res.errors || {}).flat().join('<br>');
                    errDiv.innerHTML = '<div class="alert alert-danger alert-sm py-1 mt-2 small">' + msgs + '</div>';
                }
            }
        });
    });
})();
</script>
