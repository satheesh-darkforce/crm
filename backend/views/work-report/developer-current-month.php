<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var string $monthLabel */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\ClientModel[] $clients */
/** @var int $year */
/** @var int $month */
/** @var int $grandTotal */
/** @var ?string $devFilter */
/** @var ?string $projectFilter */
/** @var ?string $clientFilter */
/** @var ?string $fromDate */
/** @var ?string $toDate */

use common\components\UserAccess;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Employee monthly work report';

// total_time is stored in seconds (Yii1 parity). Render as HH:MM.
$fmt = fn($s): string => (int)$s > 0 ? sprintf('%02d:%02d', intdiv((int)$s, 3600), intdiv((int)$s % 3600, 60)) : '00:00';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

// Month / year dropdown options
$months = [
    1 => 'January', 2 => 'February', 3 => 'March',     4 => 'April',
    5 => 'May',     6 => 'June',     7 => 'July',      8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
$currentYear = (int)date('Y');
$years = range($currentYear + 1, $currentYear - 6);

// Prev / Next month navigation
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Build export URL preserving the current filter set (Yii1 parity).
$exportUrl = Url::to(array_filter([
    '/work-report/export-developer-cumulative-report',
    'from_date'    => $fromDate,
    'to_date'      => $toDate,
    'developer_id' => $devFilter,
    'project_id'   => $projectFilter,
    'client_id'    => $clientFilter,
], fn($v) => $v !== null && $v !== ''));
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.work-report-current-month .table-responsive{overflow-x:auto!important}
</style>

<div class="work-report-current-month">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
            <small class="text-muted"><?= $total ?> entr<?= $total != 1 ? 'ies' : 'y' ?> &middot; <?= Html::encode($monthLabel) ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('&laquo; Prev', ['developer-current-month', 'year' => sprintf('%02d-%d', $prevMonth, $prevYear), 'developer_id' => $devFilter], ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
            <?= Html::a('Next &raquo;', ['developer-current-month', 'year' => sprintf('%02d-%d', $nextMonth, $nextYear), 'developer_id' => $devFilter], ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
            <?php if (UserAccess::checkAccess('work-report/export-developer-cumulative-report')): ?>
            <?= Html::a('<i class="fas fa-file-excel me-1"></i> Export to Excel', $exportUrl, ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters: month + year + employee + optional date range -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end" id="monthly-filter-form">
                <!-- Yii1 expects ?year=MM-YYYY. Build it from the two dropdowns via JS. -->
                <input type="hidden" name="year" id="year-combined" value="<?= sprintf('%02d-%d', $month, $year) ?>">

                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Month</label>
                    <select name="filter_month" id="filter_month" class="form-select form-select-sm">
                        <?php foreach ($months as $mNum => $mName): ?>
                            <option value="<?= $mNum ?>" <?= $mNum === $month ? 'selected' : '' ?>><?= $mName ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Year</label>
                    <select name="filter_year" id="filter_year" class="form-select form-select-sm">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Employee</label>
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => $devFilter,
                        'data'          => ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Project</label>
                    <?= Select2::widget([
                        'name'          => 'project_id',
                        'value'         => $projectFilter ?? '',
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'All Projects'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Client</label>
                    <?= Select2::widget([
                        'name'          => 'client_id',
                        'value'         => $clientFilter ?? '',
                        'data'          => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options'       => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">From Date</label>
                    <input type="date" name="from_date" value="<?= Html::encode($fromDate ?? '') ?>" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">To Date</label>
                    <input type="date" name="to_date" value="<?= Html::encode($toDate ?? '') ?>" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button class="btn btn-secondary btn-sm flex-fill">Filter</button>
                </div>
                <div class="col-12">
                    <small class="text-muted">Date range overrides Month/Year when set.</small>
                    <?= Html::a('Reset', ['developer-current-month'], ['class' => 'btn btn-link btn-sm py-0 px-1']) ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Employee</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th class="text-end">Total Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No work reports for <?= Html::encode($monthLabel) ?>.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold"><?= Html::encode($r['developer_name'] ?? '—') ?></td>
                        <td><?= Html::encode($r['project_title'] ?? '—') ?></td>
                        <td class="text-muted"><?= Html::encode($r['client_name'] ?? '—') ?></td>
                        <td class="text-end fw-semibold"><?= $fmt($r['groupbysum']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if ($grandTotal > 0): ?>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="4" class="text-end fw-semibold small">Grand Total</td>
                        <td class="text-end fw-bold"><?= $fmt($grandTotal) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 py-2 d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= count($rows) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$this->registerJs(<<<JS
function syncYearCombined() {
    var mm = jQuery('#filter_month').val();
    var yy = jQuery('#filter_year').val();
    if (mm && yy) {
        jQuery('#year-combined').val(('0' + mm).slice(-2) + '-' + yy);
    }
}
jQuery('#filter_month, #filter_year').on('change', syncYearCombined);
jQuery('#monthly-filter-form').on('submit', function() {
    syncYearCombined();
    // Strip the helper inputs so only ?year=MM-YYYY is sent (Yii1 contract)
    jQuery('#filter_month, #filter_year').prop('disabled', true);
});
JS, \yii\web\View::POS_READY);
?>
