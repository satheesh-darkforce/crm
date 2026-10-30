<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $idF */
/** @var string $title */
/** @var string $statusF */
/** @var string $typeF */
/** @var string $clientF */
/** @var string $costF */
/** @var string $fromF */
/** @var string $toF */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$canCreate = UserAccess::checkAccess('project/create');
$canUpdate = UserAccess::checkAccess('project/update');
$canDelete = UserAccess::checkAccess('project/delete');
$canView   = UserAccess::checkAccess('project/view');
$canChangeStatus = UserAccess::checkAccess('project/change-status');
$showActions = $canView || $canUpdate || $canDelete;

$this->title = 'Projects';

$statusLabel = [1 => 'Inprogress', 2 => 'Waiting to assign', 3 => 'Completed', 4 => 'Hold'];
$statusBadge = [1 => 'info', 2 => 'warning', 3 => 'success', 4 => 'danger'];
$typeLabel   = [1 => 'Productive', 2 => 'Non Productive'];
$typeColor   = [1 => 'primary', 2 => 'secondary'];

// work_report.total_time is stored in seconds (Yii1 parity: hours*3600 + minutes*60).
// Render as HH:MM to match Yii1's Utility::convertSecondsToHours().
$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
/* keep the date picker dropdown above the SweetAlert modal */
.datepicker.datepicker-dropdown{z-index:20000 !important;}
</style>

<?php // Hidden DatePicker — loads the bootstrap (kvDatepicker) assets so the
      // live-date popup can use the same picker as the rest of the app. ?>
<div style="display:none">
    <?= DatePicker::widget([
        'name' => '_dp_assets_loader',
        'type' => DatePicker::TYPE_INPUT,
        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true],
    ]) ?>
</div>

<div class="project-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Projects</h5>
            <small class="text-muted"><?= $total ?> project<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?php if ($canCreate): ?>
            <?= Html::a('+ New Project', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-1">
                    <input type="text" name="id" value="<?= Html::encode($idF) ?>"
                           class="form-control form-control-sm" placeholder="Code">
                </div>
                <div class="col-md-2">
                    <?= Select2::widget([
                        'name'          => 'title',
                        'value'         => $title !== '' ? $title : null,
                        'data'          => $title !== '' ? [$title => $title] : [],
                        'options'       => ['placeholder' => 'Project title…'],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width'      => '100%',
                            'minimumInputLength' => 1,
                            'ajax' => [
                                'url'      => Url::to(['/project/search-title']),
                                'dataType' => 'json',
                                'delay'    => 250,
                                'data'     => new \yii\web\JsExpression('function(params){return {q: params.term};}'),
                                'cache'    => true,
                            ],
                            'escapeMarkup'         => new \yii\web\JsExpression('function(m){return m;}'),
                            'templateResult'       => new \yii\web\JsExpression('function(d){return d.text;}'),
                            'templateSelection'    => new \yii\web\JsExpression('function(d){return d.text;}'),
                        ],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= Select2::widget([
                        'name'          => 'client_id',
                        'value'         => $clientF ?: null,
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <select name="project_type" class="form-select form-select-sm" style="width:115px">
                        <option value="">All Types</option>
                        <?php foreach ($typeLabel as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $typeF == $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="cost_range" class="form-select form-select-sm" style="width:130px">
                        <option value="">All Costs</option>
                        <option value="1" <?= $costF === '1' ? 'selected' : '' ?>>10000 and below</option>
                        <option value="2" <?= $costF === '2' ? 'selected' : '' ?>>10000-50000</option>
                        <option value="3" <?= $costF === '3' ? 'selected' : '' ?>>50000-100000</option>
                        <option value="4" <?= $costF === '4' ? 'selected' : '' ?>>100000 and above</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select name="project_status" class="form-select form-select-sm" style="width:120px">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusLabel as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $statusF == $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $fromF,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'proj-from', 'placeholder' => 'Start date', 'class' => 'form-control form-control-sm', 'style' => 'width:130px'],
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $toF,
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['id' => 'proj-to', 'placeholder' => 'End date', 'class' => 'form-control form-control-sm', 'style' => 'width:130px'],
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if ($idF !== '' || $title || $statusF || $typeF || $clientF || $costF !== '' || $fromF !== '' || $toF !== ''): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:90px">Project Code</th>
                        <th>Client Name</th>
                        <th>Project Title</th>
                        <th>Project Type</th>
                        <th class="text-end">Total Estimation Cost</th>
                        <th class="text-center">Current Month Wrk Hrs</th>
                        <th>Project Status</th>
                        <?php if ($showActions): ?>
                        <th class="text-end" style="width:60px">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="<?= $showActions ? 9 : 8 ?>" class="text-center text-muted py-5">No projects found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $row):
                        $st = (int)($row['project_status'] ?? 1);
                        $tp = (int)($row['project_type'] ?? 0);
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="small"><?= Html::encode((string) $row['id']) ?></td>
                        <td><?= Html::encode($row['client_name'] ?? '—') ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $row['id']]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($row['title']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($tp && isset($typeLabel[$tp])): ?>
                                <span class="badge bg-<?= $typeColor[$tp] ?>-subtle text-<?= $typeColor[$tp] ?> border" style="font-size:.72rem">
                                    <?= $typeLabel[$tp] ?>
                                </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="text-end text-muted small">
                            <?= $row['total_estimation_cost'] ? '₹' . number_format((float)$row['total_estimation_cost'], 0) : '—' ?>
                        </td>
                        <td class="text-center fw-semibold <?= $row['cur_month_secs'] > 0 ? 'text-success' : 'text-muted' ?>">
                            <?= $fmt((int)$row['cur_month_secs']) ?>
                        </td>
                        <td>
                            <?php if ($canChangeStatus): ?>
                            <select class="form-select form-select-sm project-status-select" data-id="<?= $row['id'] ?>" style="width:auto;font-size:.78rem;padding:2px 28px 2px 6px;">
                                <?php foreach ($statusLabel as $sv => $sl): ?>
                                    <option value="<?= $sv ?>" <?= $st === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                                <span class="badge bg-light text-dark"><?= Html::encode($statusLabel[$st] ?? '—') ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if ($showActions): ?>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <?php if ($canView): ?>
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canUpdate): ?>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                    <?php if ($canView || $canUpdate): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $row['id']], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-method' => 'post', 'data-confirm' => 'Delete ' . Html::encode($row['title']) . '?',
                                    ]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?>
            </small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$changeStatusUrl = Url::to(['change-status']);
$setLiveDateUrl  = Url::to(['set-live-date']);
$amcStartUrl     = Url::to(['/amc/start']);
$todayStr        = date('Y-m-d');
$this->registerJs(<<<JS
// Flow shown after a project is marked Completed (status 3):
//   1) ask for the project's live / updated date and save it
//   2) ask whether to start AMC -> opens the Start AMC form
window.amcAfterComplete = function(projectId) {
    Swal.fire({
        title: 'Project Live / Updated Date',
        html: '<input type="text" id="amc-live-date" class="form-control form-control-sm text-center" '
            + 'placeholder="Select date" autocomplete="off" readonly '
            + 'style="background:#fff;cursor:pointer;max-width:220px;margin:0 auto;">',
        confirmButtonText: 'Save',
        showCancelButton: true,
        allowOutsideClick: false,
        didOpen: function() {
            jQuery('#amc-live-date').kvDatepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true,
                endDate: '0d'
            }).kvDatepicker('update', '$todayStr');
        },
        preConfirm: function() {
            var v = jQuery('#amc-live-date').val();
            if (!v) { Swal.showValidationMessage('Please pick a date'); return false; }
            return v;
        }
    }).then(function(r1) {
        if (!r1.isConfirmed || !r1.value) { return; }
        jQuery.ajax({
            url: '$setLiveDateUrl', type: 'POST',
            data: { id: projectId, live_updated_date: r1.value, _csrf: yii.getCsrfToken() },
            success: function(res) {
                if (!res.success) {
                    Swal.fire({icon:'error', title:'Error', text: res.message || 'Failed to save date'});
                    return;
                }
                Swal.fire({
                    title: 'Do you want to start AMC?',
                    showCancelButton: true, confirmButtonText: 'Yes', cancelButtonText: 'No'
                }).then(function(r2) {
                    if (r2.isConfirmed) {
                        window.location.href = '$amcStartUrl' + '?project_id=' + projectId;
                    }
                });
            },
            error: function() { Swal.fire({icon:'error', title:'Error', text:'Request failed'}); }
        });
    });
};
JS, \yii\web\View::POS_READY);

$this->registerJs(<<<JS
// Remember the value before a change so we can revert on cancel/error
// without a jarring full-page reload.
jQuery('.project-status-select').on('focus', function() {
    jQuery(this).data('prev', jQuery(this).val());
}).on('change', function() {
    var el = jQuery(this);
    var projectId  = el.data('id');
    var newStatus  = el.val();
    var prevStatus = el.data('prev');

    // No "are you sure" confirm — apply the change directly. When the new
    // status is Completed (3), ask for the live/updated date right after.
    jQuery.ajax({
        url: '$changeStatusUrl',
        type: 'GET',
        data: { id: projectId, status: newStatus },
        success: function(res) {
            if (res.success) {
                el.data('prev', newStatus);
                if (String(newStatus) === '3') {
                    amcAfterComplete(projectId);   // ask live date -> start AMC
                } else {
                    Swal.fire({icon:'success', title:'Updated successfully!', timer:1200, showConfirmButton:false});
                }
            } else {
                el.val(prevStatus);
                Swal.fire({icon:'error', title:'Error', text: res.message || 'Failed to update'});
            }
        },
        error: function() {
            el.val(prevStatus);
            Swal.fire({icon:'error', title:'Error', text:'Request failed'});
        }
    });
});
JS, \yii\web\View::POS_READY);

// ── Date-range filter: keep End ≥ Start, both in the picker and on submit ──
$this->registerJs(<<<JS
(function () {
    var \$from = jQuery('#proj-from');
    var \$to   = jQuery('#proj-to');
    if (!\$from.length || !\$to.length) { return; }

    // The End picker can never open on a day before the chosen Start (and vice-versa).
    function syncLimits() {
        var f = \$from.val();
        var t = \$to.val();
        \$to.kvDatepicker('setStartDate', f || null);
        \$from.kvDatepicker('setEndDate', t || null);
    }
    \$from.on('changeDate change', function () {
        // If End is now earlier than Start, clear it so the user re-picks a valid day.
        if (\$to.val() && \$from.val() && \$to.val() < \$from.val()) {
            \$to.val('');
        }
        syncLimits();
    });
    \$to.on('changeDate change', function () {
        if (\$to.val() && \$from.val() && \$to.val() < \$from.val()) {
            Swal.fire({icon:'warning', title:'Invalid range', text:'End date cannot be earlier than start date.'});
            \$to.val('');
        }
        syncLimits();
    });
    syncLimits();

    // Final guard on submit — block the request if the range is inverted.
    \$from.closest('form').on('submit', function (e) {
        if (\$from.val() && \$to.val() && \$to.val() < \$from.val()) {
            e.preventDefault();
            Swal.fire({icon:'warning', title:'Invalid range', text:'End date cannot be earlier than start date.'});
        }
    });
})();
JS, \yii\web\View::POS_READY);
?>
