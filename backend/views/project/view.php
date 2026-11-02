<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Project $model */
/** @var \backend\models\Project[] $phases */
/** @var bool $hasPhases */
/** @var array $phaseHours */
/** @var array $empHours */
/** @var \yii\data\ArrayDataProvider $empHoursProvider */
/** @var int   $totalEmpSecsAll */
/** @var array $invoices */
/** @var string $fromDate */
/** @var string $toDate */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

$this->title = 'View Project';

// AMC: only completed projects (main `project` table status 3 = Completed)
// may start AMC. Show "View AMC" if an active contract already exists.
$isCompletedProject = (int)$model->project_status === 3;
$activeAmc = !UserAccess::isAmcEnabled() ? null : \backend\models\Amc::find()
    ->where(['project_id' => $model->id, 'status' => 1, 'amc_status' => \backend\models\Amc::AMC_ACTIVE])
    ->one();
$canStartAmc = UserAccess::checkAccess('amc/start');
$canViewAmc  = UserAccess::checkAccess('amc/view');

// Helper: seconds → HH:MM
$secToHM = fn(int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '00:00';

$typeLabel     = [1 => 'Productive', 2 => 'Non Productive'];
$industryLabel = [1 => 'IT', 2 => 'IOT'];
$statusLabel = [0 => 'Not started', 1 => 'In-progress', 2 => 'Delivered', 3 => 'Closed'];
$statusBadge = [0 => 'secondary', 1 => 'info', 2 => 'success', 3 => 'dark'];

$totalEmpSecs  = array_sum(array_column($empHours, 'total_secs'));
$totalPaid     = array_sum(array_column($invoices, 'paid_amount'));
$totalPending  = array_sum(array_column($invoices, 'pending_amount'));
?>
<style>
.info-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
.info-item { background:#fff; border:1px solid #eee; border-radius:6px; padding:12px 15px; }
.info-label { display:block; font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
.info-value { font-size:14px; font-weight:600; color:#222; }
.proj-table th { font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:#6c757d; font-weight:600; white-space:nowrap; }
.proj-table td { font-size:.875rem; vertical-align:middle; }
.proj-table tfoot td { font-weight:700; border-top:2px solid #dee2e6; }
</style>

<div class="project-view">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 fw-semibold">View Project</h4>
        <div class="d-flex gap-2">
            <?php if ($isCompletedProject && $activeAmc && $canViewAmc): ?>
                <?= Html::a('<i class="fas fa-eye"></i> View AMC', ['/amc/view', 'id' => $activeAmc->id],
                    ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
            <?php elseif ($isCompletedProject && $canStartAmc): ?>
                <button type="button" class="btn btn-primary btn-sm" id="proj-start-amc"
                        data-id="<?= $model->id ?>"><i class="fas fa-play"></i> Start AMC</button>
            <?php endif; ?>
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <!-- ── 1. General Info ────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h5 class="mb-0 fw-semibold">General Info</h5></div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Client</span>
                    <span class="info-value"><?= Html::encode($model->client->client_name ?? '—') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Project Title</span>
                    <span class="info-value"><?= Html::encode($model->title) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Project Type</span>
                    <span class="info-value"><?= $typeLabel[$model->project_type] ?? '—' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Industry Type</span>
                    <span class="info-value"><?= $industryLabel[$model->industry_type] ?? '—' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Estimation Hours</span>
                    <span class="info-value"><?= $model->total_estimation_hours ? $secToHM((int)$model->total_estimation_hours) : '—' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Estimation Cost</span>
                    <span class="info-value"><?= $model->total_estimation_cost ? '₹' . number_format((float)$model->total_estimation_cost, 2) : '—' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date Created</span>
                    <span class="info-value"><?= $model->date ? date('d-m-Y', strtotime($model->date)) : '—' ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Live / Updated Date</span>
                    <span class="info-value"><?= $model->live_updated_date ? date('d-m-Y', strtotime($model->live_updated_date)) : '—' ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($hasPhases): ?>
    <!-- ── 2. Project Phases ──────────────────────────────────────────── -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="mb-0 fw-semibold">Project Phases</h5></div>
                <div class="card-body table-responsive p-0">
                    <table class="table proj-table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Phase</th>
                                <th>Milestone</th>
                                <th class="text-end">Estimated Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($phases as $i => $ph): $ms = (int)$ph->milestone_status; ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td><?= (int)$ph->phase_value ?></td>
                                <td><?= (int)$ph->milestone_value ?></td>
                                <td class="text-end"><?= $ph->milestone_hours ? $secToHM((int)$ph->milestone_hours) : '—' ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusBadge[$ms] ?? 'secondary' ?>"><?= $statusLabel[$ms] ?? '—' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── 3. Phase Based Hours ───────────────────────────────────────── -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="mb-0 fw-semibold">Phase Based Hours</h5></div>
                <div class="card-body table-responsive p-0">
                    <table class="table proj-table table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Phase</th>
                                <th>Milestone</th>
                                <th class="text-end">Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($phaseHours)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No work reports yet.</td></tr>
                        <?php else: ?>
                            <?php $phTotalSecs = 0; foreach ($phaseHours as $i => $pr):
                                $phTotalSecs += (int)$pr['total_secs']; ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td><?= (int)$pr['phase_value'] ?></td>
                                <td><?= (int)$pr['milestone_value'] ?></td>
                                <td class="text-end fw-semibold"><?= $secToHM((int)$pr['total_secs']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                        <?php if (!empty($phaseHours)): ?>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Total Hours</td>
                                <td class="text-end fw-bold"><?= $secToHM($phTotalSecs) ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── 4. Employee Based Hours ────────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold">Employee Based Hours</h5>
                <form method="get" action="<?= Url::to(['view', 'id' => $model->id]) ?>"
                      class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="id" value="<?= $model->id ?>">
                    <?= \kartik\date\DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $fromDate,
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                        'options'       => ['id' => 'filter-from-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'From date'],
                    ]) ?>
                    <?= \kartik\date\DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $toDate,
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                        'options'       => ['id' => 'filter-to-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'To date'],
                    ]) ?>
                    <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-search"></i></button>
                    <?php if ($fromDate || $toDate): ?>
                        <?= Html::a('<i class="fas fa-times"></i>', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <?php Pjax::begin([
                'id'             => 'pjax-emp-hours',
                'timeout'        => 5000,
                'enablePushState' => false,
                'clientOptions'  => ['scrollTo' => false],
                'linkSelector'   => '#pjax-emp-hours .pagination a',
                'formSelector'   => false,
            ]); ?>
            <table class="table proj-table table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Developer Name</th>
                        <?php if ($hasPhases): ?>
                        <th>Phase</th>
                        <th>Milestone</th>
                        <?php endif; ?>
                        <th class="text-end">Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $colSpan = $hasPhases ? 5 : 3;
                    $empPageination = $empHoursProvider->getPagination();
                    $empPageRows  = $empHoursProvider->getModels();
                    $empOffset    = $empPageination ? $empPageination->getOffset() : 0;
                    ?>
                    <?php if (empty($empPageRows)): ?>
                        <tr><td colspan="<?= $colSpan ?>" class="text-center text-muted py-4">No work reports found.</td></tr>
                    <?php else: ?>
                        <?php $rowNum = 0; foreach ($empPageRows as $row): $rowNum++; ?>
                            <tr>
                                <td class="text-muted"><?= $empOffset + $rowNum ?></td>
                                <td><?= Html::encode($row['developer_name']) ?></td>
                                <?php if ($hasPhases): ?>
                                <td><?= (int)$row['phase_value'] ?></td>
                                <td><?= (int)$row['milestone_value'] ?></td>
                                <?php endif; ?>
                                <td class="text-end fw-semibold"><?= $secToHM((int)$row['total_secs']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if ($empHoursProvider->getTotalCount() > 0): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="<?= $colSpan - 1 ?>" class="fw-semibold text-end">Total Hours</td>
                        <td class="text-end fw-bold"><?= $secToHM((int)$totalEmpSecsAll) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            <?php if ($empPageination && $empPageination->pageCount > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= $empOffset + 1 ?>–<?= min($empOffset + $empPageination->pageSize, $empHoursProvider->getTotalCount()) ?>
                    of <?= $empHoursProvider->getTotalCount() ?>
                </small>
                <?= \yii\widgets\LinkPager::widget([
                    'pagination'     => $empPageination,
                    'options'        => ['class' => 'pagination pagination-sm mb-0'],
                    'firstPageLabel' => '«', 'lastPageLabel' => '»',
                    'prevPageLabel'  => '‹', 'nextPageLabel' => '›',
                    'maxButtonCount' => 5,
                ]) ?>
            </div>
            <?php endif; ?>
            <?php Pjax::end(); ?>
        </div>
    </div>

    <!-- ── 5. Project Payment Details ─────────────────────────────────── -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h5 class="mb-0 fw-semibold">Project Payment Details</h5></div>
        <div class="card-body table-responsive p-0">
            <table class="table proj-table table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Invoice No</th>
                        <th>Invoice Date</th>
                        <th>Currency</th>
                        <th class="text-end">Received Amount</th>
                        <th class="text-end">Pending Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $i => $inv): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td><?= Html::encode($inv['invoice_no']) ?></td>
                                <td><?= $inv['invoice_date'] ? date('d-m-Y', strtotime($inv['invoice_date'])) : '—' ?></td>
                                <td><?= Html::encode($inv['currency'] ?: '—') ?></td>
                                <td class="text-end"><?= number_format((float)$inv['paid_amount'], 2) ?></td>
                                <td class="text-end"><?= number_format((float)$inv['pending_amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($invoices)): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="fw-semibold text-end">Total Amount</td>
                        <td class="text-end fw-bold"><?= number_format($totalPaid, 2) ?></td>
                        <td class="text-end fw-bold"><?= number_format($totalPending, 2) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

</div>

<?php
if ($isCompletedProject && $canStartAmc && !$activeAmc):
    $setLiveDateUrl = Url::to(['set-live-date']);
    $amcStartUrl    = Url::to(['/amc/start']);
    $todayStr       = date('Y-m-d');
    $liveDefault    = $model->live_updated_date ?: $todayStr;
    $this->registerCss('.datepicker.datepicker-dropdown{z-index:20000 !important;}');
    $this->registerJs(<<<JS
jQuery('#proj-start-amc').on('click', function(){
    var projectId = jQuery(this).data('id');
    Swal.fire({
        title: 'Project Live / Updated Date',
        html: '<input type="text" id="proj-live-date" class="form-control form-control-sm text-center" '
            + 'placeholder="Select date" autocomplete="off" readonly '
            + 'style="background:#fff;cursor:pointer;max-width:220px;margin:0 auto;">',
        confirmButtonText: 'Save & Continue',
        showCancelButton: true,
        allowOutsideClick: false,
        didOpen: function(){
            jQuery('#proj-live-date').kvDatepicker({
                format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true, endDate: '0d'
            }).kvDatepicker('update', '$liveDefault');
        },
        preConfirm: function(){
            var v = jQuery('#proj-live-date').val();
            if (!v) { Swal.showValidationMessage('Please pick a date'); return false; }
            return v;
        }
    }).then(function(r){
        if (!r.isConfirmed || !r.value) { return; }
        jQuery.ajax({
            url: '$setLiveDateUrl', type: 'POST',
            data: { id: projectId, live_updated_date: r.value, _csrf: yii.getCsrfToken() },
            success: function(res){
                if (!res.success) { Swal.fire({icon:'error', title:'Error', text: res.message || 'Failed'}); return; }
                window.location.href = '$amcStartUrl' + '?project_id=' + projectId;
            },
            error: function(){ Swal.fire({icon:'error', title:'Error', text:'Request failed'}); }
        });
    });
});
JS, \yii\web\View::POS_READY);
endif;
?>
