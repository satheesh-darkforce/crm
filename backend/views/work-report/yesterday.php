<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[]   $projects */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $yesterday */
/** @var ?string $fromDate */
/** @var ?string $toDate */
/** @var ?string $devFilter */
/** @var ?string $projFilter */
/** @var ?string $cliFilter */
/** @var int $grandTotalSecs */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = "Employee yesterday's work report";

$total = $dataProvider->getTotalCount();
$isRange = !empty($fromDate) || !empty($toDate);
$period = $isRange
    ? (($fromDate ? date('d M Y', strtotime($fromDate)) : 'Start') . ' – ' . ($toDate ? date('d M Y', strtotime($toDate)) : 'today'))
    : date('l, d M Y', strtotime($yesterday));
$hasFilter = $isRange || $devFilter || $projFilter || $cliFilter;

// Time per employee on this page.
$devSummary = [];
foreach ($dataProvider->getModels() as $m) {
    $devName = $m->developer->developer_name ?? '—';
    $devSummary[$devName] = ($devSummary[$devName] ?? 0) + (int)$m->total_time;
}

// Export keeps the current filters (Yii1 parity).
$export = array_filter([
    '/work-report/export-developer-cumulative-report',
    'from_date' => $fromDate, 'to_date' => $toDate, 'developer_id' => $devFilter, 'project_id' => $projFilter, 'client_id' => $cliFilter,
], fn($v) => $v !== null && $v !== '');
?>
<div class="ui-page work-report-yesterday">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => $isRange ? 'Work reports by day' : "Yesterday's work",
        'sub'     => $period . ' · ' . number_format($total) . ' report' . ($total == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Total <strong>' . Task::hm($grandTotalSecs) . '</strong> hrs</span>',
        'actions' => UserAccess::checkAccess('work-report/export-developer-cumulative-report')
            ? Html::a('<i class="fas fa-file-excel me-1"></i>Export', $export, ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]) : '',
        'route'   => 'yesterday',
        'filters' => [
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'select', 'name' => 'developer_id', 'label' => 'Employee', 'data' => ArrayHelper::map($developers, 'id', 'developer_name'), 'placeholder' => 'All employees', 'col' => 'col-lg-2 col-md-4'],
            ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects', 'col' => 'col-lg-2 col-md-6'],
            ['type' => 'select', 'name' => 'client_id', 'label' => 'Client', 'data' => ArrayHelper::map($clients, 'id', 'client_name'), 'placeholder' => 'All clients', 'col' => 'col-lg-2 col-md-6'],
            ['type' => 'html', 'col' => 'col-12 order-last', 'html' => '<div class="form-text mt-0">Without From / To this page shows yesterday only.</div>'],
        ],
    ]) ?>

    <?php if ($devSummary): ?>
        <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Time per employee">
            <?php foreach ($devSummary as $devName => $secs): ?>
                <span class="ui-pill tone-neutral"><?= Html::encode($devName) ?> <strong class="num ms-1"><?= Task::hm($secs) ?></strong></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $this->render('_entries', [
        'dataProvider' => $dataProvider,
        'columns'      => $isRange ? ['developer', 'date', 'client', 'project'] : ['developer', 'client', 'project'],
        'empty'        => $hasFilter
            ? ['icon' => 'search', 'title' => 'No reports match your search', 'text' => 'Try a wider date range or fewer filters.',
               'action' => Html::a('Clear search', ['yesterday'], ['class' => 'btn btn-outline-secondary btn-sm'])]
            : ['icon' => 'moon', 'title' => 'Nobody logged work yesterday', 'text' => 'Reports for ' . date('d M Y', strtotime($yesterday)) . ' appear here once they are added.'],
    ]) ?>
</div>
