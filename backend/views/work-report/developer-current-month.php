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

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Employee monthly work report';

$isRange = !empty($fromDate) || !empty($toDate);
$period  = $isRange
    ? (($fromDate ? date('d M Y', strtotime($fromDate)) : 'Start') . ' – ' . ($toDate ? date('d M Y', strtotime($toDate)) : 'today'))
    : $monthLabel;

// Month picker: ?year=MM-YYYY (Yii1 contract), last three years plus next month.
$monthOptions = [];
for ($t = strtotime('first day of next month'); $t >= strtotime('first day of -36 months'); $t = strtotime('-1 month', $t)) {
    $monthOptions[date('m-Y', $t)] = date('F Y', $t);
}
$current = sprintf('%02d-%d', $month, $year);
$monthOptions += [$current => $monthLabel];

$keep = array_filter(['developer_id' => $devFilter, 'project_id' => $projectFilter, 'client_id' => $clientFilter], fn($v) => $v !== null && $v !== '');
$step = fn(int $d) => ['developer-current-month', 'year' => date('m-Y', mktime(0, 0, 0, $month + $d, 1, $year))] + $keep;

$export = array_filter([
    '/work-report/export-developer-cumulative-report',
    'from_date' => $fromDate, 'to_date' => $toDate, 'developer_id' => $devFilter, 'project_id' => $projectFilter, 'client_id' => $clientFilter,
], fn($v) => $v !== null && $v !== '');

$actions = '';
if (!$isRange) {
    $actions .= '<div class="btn-group btn-group-sm" role="group" aria-label="Change month">'
        . Html::a('<i class="fas fa-chevron-left"></i><span class="visually-hidden">Previous month</span>', $step(-1), ['class' => 'btn btn-outline-secondary', 'encode' => false, 'title' => 'Previous month'])
        . Html::a('<i class="fas fa-chevron-right"></i><span class="visually-hidden">Next month</span>', $step(1), ['class' => 'btn btn-outline-secondary', 'encode' => false, 'title' => 'Next month'])
        . '</div>';
}
if (UserAccess::checkAccess('work-report/export-developer-cumulative-report')) {
    $actions .= Html::a('<i class="fas fa-file-excel me-1"></i>Export', $export, ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]);
}
$rows  = $dataProvider->getModels();
$total = $dataProvider->getTotalCount();
?>
<div class="ui-page work-report-current-month">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Monthly time by employee',
        'sub'     => $period . ' · ' . number_format($total) . ' employee / project pair' . ($total == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Total <strong>' . Task::hm($grandTotal) . '</strong> hrs</span>',
        'actions' => $actions,
        'route'   => 'developer-current-month',
        'countKeys' => ['developer_id', 'project_id', 'client_id', 'from_date', 'to_date'],
        'filters' => [
            ['type' => 'select', 'name' => 'year', 'label' => 'Month', 'data' => $monthOptions, 'placeholder' => 'This month', 'col' => 'col-lg-2 col-md-4'],
            ['type' => 'select', 'name' => 'developer_id', 'label' => 'Employee', 'data' => ArrayHelper::map($developers, 'id', 'developer_name'), 'placeholder' => 'All employees', 'col' => 'col-lg-2 col-md-4'],
            ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects', 'col' => 'col-lg-3 col-md-4'],
            ['type' => 'select', 'name' => 'client_id', 'label' => 'Client', 'data' => ArrayHelper::map($clients, 'id', 'client_name'), 'placeholder' => 'All clients', 'col' => 'col-lg-3 col-md-6'],
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-3 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-3 col-6'],
            ['type' => 'html', 'col' => 'col-12 order-last', 'html' => '<div class="form-text mt-0">A From / To range replaces the month.</div>'],
        ],
    ]) ?>

    <?= $this->render('_totals', [
        'rows'       => $rows,
        'columns'    => ['developer_name' => 'Employee', 'project_title' => 'Project', 'client_name' => 'Client'],
        'timeKey'    => 'groupbysum',
        'grandTotal' => $grandTotal,
        'pagination' => $dataProvider->getPagination(),
        'total'      => $total,
        'empty'      => ['icon' => 'calendar-alt', 'title' => 'No work logged for ' . $period, 'text' => $keep ? 'Try clearing the employee, project or client filter.' : 'Pick another month above.'],
    ]) ?>
</div>
