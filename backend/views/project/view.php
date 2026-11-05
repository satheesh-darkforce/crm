<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Project $model */
/** @var \backend\models\Project[] $phases */
/** @var bool $hasPhases */
/** @var array $phaseHours */
/** @var \yii\data\ArrayDataProvider $empHoursProvider */
/** @var int   $totalEmpSecsAll */
/** @var array $invoices */
/** @var string $fromDate */
/** @var string $toDate */

use backend\models\Amc;
use backend\models\Project;
use common\components\UserAccess;
use kartik\date\DatePicker;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use yii\widgets\Pjax;

$this->title = 'View Project';

// AMC: only completed projects may start AMC; show "View AMC" when a contract is active.
$isCompleted = (int)$model->project_status === Project::PROJECT_STATUS_COMPLETED;
$amcEnabled  = UserAccess::isAmcEnabled();
$activeAmc   = ($amcEnabled && $isCompleted)
    ? Amc::find()->where(['project_id' => $model->id, 'status' => 1, 'amc_status' => Amc::AMC_ACTIVE])->one()
    : null;
$canStartAmc = $amcEnabled && UserAccess::checkAccess('amc/start');
$canViewAmc  = $amcEnabled && UserAccess::checkAccess('amc/view');
$canUpdate   = UserAccess::checkAccess('project/update');

// seconds → HH:MM (hours can exceed 24)
$hm = fn(int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '00:00';
$d  = fn($v) => $v ? date('d M Y', strtotime($v)) : '—';

$st         = (int)$model->project_status;
$totalPaid  = array_sum(array_column($invoices, 'paid_amount'));
$totalPend  = array_sum(array_column($invoices, 'pending_amount'));
$estSecs    = (int)$model->total_estimation_hours;
$hasRange   = $fromDate !== '' || $toDate !== '';
?>
<div class="ui-page project-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->title) ?></h1>
            <div class="ui-sub">
                #<?= (int)$model->id ?> · <?= Html::encode($model->client->client_name ?? 'No client') ?> ·
                <span class="ui-pill tone-<?= Project::statusTone($st) ?>"><?= Html::encode($model->getStatusLabel()) ?></span>
            </div>
        </div>
        <div class="ui-actions">
            <?php if ($canUpdate): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if ($activeAmc && $canViewAmc): ?>
                <?= Html::a('<i class="fas fa-file-contract me-1"></i>View AMC', ['/amc/view', 'id' => $activeAmc->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php elseif ($isCompleted && !$activeAmc && $canStartAmc): ?>
                <button type="button" class="btn btn-primary btn-sm js-start-amc" data-id="<?= $model->id ?>" data-live-date="<?= Html::encode((string)$model->live_updated_date) ?>">
                    <i class="fas fa-play me-1"></i>Start AMC
                </button>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="ui-tiles">
        <div class="ui-tile"><div class="t-label">Type</div><div class="t-value"><?= Html::encode(Project::typeOptions()[(int)$model->project_type] ?? '—') ?></div>
            <div class="t-sub"><?= Html::encode(Project::industryOptions()[(int)$model->industry_type] ?? 'Industry not set') ?></div></div>
        <div class="ui-tile"><div class="t-label">Estimated hours</div><div class="t-value"><?= $estSecs > 0 ? $hm($estSecs) : '—' ?></div>
            <div class="t-sub">Logged <?= $hm($totalEmpSecsAll) ?><?= $hasRange ? ' (in range)' : '' ?></div></div>
        <div class="ui-tile"><div class="t-label">Estimated cost</div><div class="t-value"><?= (float)$model->total_estimation_cost > 0 ? '₹' . number_format((float)$model->total_estimation_cost, 2) : '—' ?></div></div>
        <div class="ui-tile"><div class="t-label">Received / pending</div><div class="t-value text-success"><?= number_format($totalPaid, 2) ?></div>
            <div class="t-sub <?= $totalPend > 0 ? 'text-danger' : '' ?>"><?= number_format($totalPend, 2) ?> pending</div></div>
        <div class="ui-tile"><div class="t-label">Created</div><div class="t-value"><?= $d($model->date) ?></div>
            <div class="t-sub">Live: <?= $d($model->live_updated_date) ?></div></div>
    </div>

    <?php if (trim((string)$model->description) !== ''): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Description</div>
        <div class="card-body small"><?= nl2br(Html::encode($model->description)) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($hasPhases): ?>
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">Phases &amp; milestones</div>
                <div class="table-responsive">
                    <table class="table list-table ui-table stack mb-0">
                        <thead class="table-light"><tr><th>Milestone</th><th class="text-end">Estimated</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($phases as $ph): $ms = (int)$ph->milestone_status; ?>
                            <tr>
                                <td class="cell-title">Phase <?= (int)$ph->phase_value ?> · M<?= (int)$ph->milestone_value ?></td>
                                <td class="text-end" data-label="Estimated"><?= $ph->milestone_hours ? $hm((int)$ph->milestone_hours) : '—' ?></td>
                                <td data-label="Status"><span class="ui-pill tone-<?= Project::milestoneStatusTone($ms) ?>"><?= Html::encode(Project::milestoneStatusOptions()[$ms] ?? '—') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">
                    <span>Hours by milestone</span>
                    <?php if ($phaseHours): ?><span class="ui-section-meta">Total <?= $hm((int)array_sum(array_column($phaseHours, 'total_secs'))) ?></span><?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table list-table ui-table stack mb-0">
                        <thead class="table-light"><tr><th>Milestone</th><th class="text-end">Logged</th></tr></thead>
                        <tbody>
                        <?php if (!$phaseHours): ?>
                            <tr class="ui-empty-row"><td colspan="2" class="ui-empty-cell">
                                <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'clock', 'title' => 'No work logged' . ($hasRange ? ' in this range' : ' yet'), 'small' => true]) ?>
                            </td></tr>
                        <?php endif; ?>
                        <?php foreach ($phaseHours as $pr): ?>
                            <tr>
                                <td class="cell-title">Phase <?= (int)$pr['phase_value'] ?> · M<?= (int)$pr['milestone_value'] ?></td>
                                <td class="text-end fw-semibold" data-label="Logged"><?= $hm((int)$pr['total_secs']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if (count($phaseHours) > 1): ?>
                        <tfoot><tr><td>Total</td><td class="text-end"><?= $hm((int)array_sum(array_column($phaseHours, 'total_secs'))) ?></td></tr></tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hours by employee (date range applies to both hour tables) -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section flex-wrap">
            <span>Hours by employee<?= $hasRange ? ' · ' . Html::encode(($fromDate ?: '…') . ' to ' . ($toDate ?: '…')) : '' ?></span>
            <form method="get" action="<?= Url::to(['view', 'id' => $model->id]) ?>" class="d-flex flex-wrap gap-2 align-items-center" style="text-transform:none;letter-spacing:0">
                <input type="hidden" name="id" value="<?= (int)$model->id ?>">
                <div style="width:140px"><?= DatePicker::widget([
                    'name' => 'from_date', 'value' => $fromDate, 'type' => DatePicker::TYPE_INPUT,
                    'options' => ['id' => 'filter-from-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'From', 'aria-label' => 'From date', 'autocomplete' => 'off', 'data-range-from' => '#filter-to-date'],
                    'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                ]) ?></div>
                <div style="width:140px"><?= DatePicker::widget([
                    'name' => 'to_date', 'value' => $toDate, 'type' => DatePicker::TYPE_INPUT,
                    'options' => ['id' => 'filter-to-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'To', 'aria-label' => 'To date', 'autocomplete' => 'off', 'data-range-to' => '#filter-from-date'],
                    'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                ]) ?></div>
                <button type="submit" class="btn btn-outline-primary btn-sm" aria-label="Apply date range"><i class="fas fa-filter"></i></button>
                <?php if ($hasRange): ?>
                    <?= Html::a('Clear', ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                <?php endif; ?>
            </form>
        </div>
        <?php Pjax::begin([
            'id' => 'pjax-emp-hours', 'timeout' => 8000, 'enablePushState' => false,
            'clientOptions' => ['scrollTo' => false], 'linkSelector' => '#pjax-emp-hours .pagination a', 'formSelector' => false,
        ]); ?>
        <?php
        $rows   = $empHoursProvider->getModels();
        $pg     = $empHoursProvider->getPagination();
        $offset = $pg ? $pg->getOffset() : 0;
        ?>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Employee</th>
                    <?php if ($hasPhases): ?><th>Milestone</th><?php endif; ?>
                    <th class="text-end">Hours</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="<?= $hasPhases ? 4 : 3 ?>" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'user-clock', 'small' => true,
                            'title' => 'No work logged' . ($hasRange ? ' in this range' : ' yet'),
                            'text' => $hasRange ? 'Try a wider date range.' : 'Hours appear once work reports are filed.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title"><?= Html::encode($row['developer_name'] ?: '—') ?></td>
                        <?php if ($hasPhases): ?>
                            <td data-label="Milestone">Phase <?= (int)$row['phase_value'] ?> · M<?= (int)$row['milestone_value'] ?></td>
                        <?php endif; ?>
                        <td class="text-end fw-semibold" data-label="Hours"><?= $hm((int)$row['total_secs']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($empHoursProvider->getTotalCount() > 1): ?>
                <tfoot><tr><td colspan="<?= $hasPhases ? 3 : 2 ?>" class="text-end">Total (all employees)</td><td class="text-end"><?= $hm($totalEmpSecsAll) ?></td></tr></tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php if ($pg && $pg->getPageCount() > 1): ?>
        <div class="card-footer bg-white ui-pager">
            <small class="text-muted">Showing <?= $offset + 1 ?>–<?= $offset + count($rows) ?> of <?= $empHoursProvider->getTotalCount() ?></small>
            <?= LinkPager::widget([
                'pagination' => $pg,
                'options' => ['class' => 'pagination pagination-sm mb-0'],
                'linkContainerOptions' => ['class' => 'page-item'],
                'linkOptions' => ['class' => 'page-link'],
                'disabledListItemSubTagOptions' => ['tag' => 'span', 'class' => 'page-link'],
                'maxButtonCount' => 5,
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›',
            ]) ?>
        </div>
        <?php endif; ?>
        <?php Pjax::end(); ?>
    </div>

    <!-- Payments -->
    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section"><span>Payments<?= $invoices ? ' (' . count($invoices) . ' invoices)' : '' ?></span></div>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Invoice No</th><th>Date</th><th>Currency</th><th class="text-end">Received</th><th class="text-end">Pending</th></tr>
                </thead>
                <tbody>
                <?php if (!$invoices): ?>
                    <tr class="ui-empty-row"><td colspan="6" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'file-invoice', 'title' => 'No invoices for this project', 'small' => true]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($invoices as $i => $inv): ?>
                    <tr>
                        <td class="cell-num muted"><?= $i + 1 ?></td>
                        <td class="cell-title"><?= Html::a(Html::encode($inv['invoice_no'] ?: 'INV-' . $inv['id']), ['/invoice/view', 'id' => $inv['id']], ['class' => 'text-reset text-decoration-none', 'target' => '_blank', 'rel' => 'noopener']) ?></td>
                        <td class="small nowrap" data-label="Date"><?= $d($inv['invoice_date']) ?></td>
                        <td class="small" data-label="Currency"><?= Html::encode($inv['currency'] ?: '—') ?></td>
                        <td class="text-end nowrap text-success" data-label="Received"><?= number_format((float)$inv['paid_amount'], 2) ?></td>
                        <td class="text-end nowrap <?= (float)$inv['pending_amount'] > 0 ? 'text-danger fw-semibold' : 'muted' ?>" data-label="Pending"><?= number_format((float)$inv['pending_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if (count($invoices) > 1): ?>
                <tfoot><tr><td colspan="4" class="text-end">Total</td><td class="text-end"><?= number_format($totalPaid, 2) ?></td><td class="text-end"><?= number_format($totalPend, 2) ?></td></tr></tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <?php if ($isCompleted && !$activeAmc && $canStartAmc): ?>
    <div class="d-none" aria-hidden="true">
        <?= DatePicker::widget(['name' => '_live_date_assets', 'type' => DatePicker::TYPE_INPUT, 'pluginOptions' => ['format' => 'yyyy-mm-dd']]) ?>
    </div>
    <?php endif; ?>
</div>
