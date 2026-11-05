<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var int   $grandTotal */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int|null $devFilter */
/** @var int|null $projFilter */
/** @var int|null $cliFilter */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[]   $projects */
/** @var \backend\models\ClientModel[] $clients */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Employee based cumulative report';

$total     = $dataProvider->getTotalCount();
$hasFilter = $fromDate || $toDate || $devFilter || $projFilter || $cliFilter;
$period    = ($fromDate || $toDate)
    ? (($fromDate ? date('d M Y', strtotime($fromDate)) : 'Start') . ' – ' . ($toDate ? date('d M Y', strtotime($toDate)) : 'today'))
    : 'All time';

$export = array_filter([
    '/work-report/export-developer-cumulative-report',
    'from_date' => $fromDate, 'to_date' => $toDate, 'developer_id' => $devFilter, 'project_id' => $projFilter, 'client_id' => $cliFilter,
], fn($v) => $v !== null && $v !== '');
?>
<div class="ui-page work-report-cumulative-employee">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Cumulative time by employee',
        'sub'     => $period . ' · ' . number_format($total) . ' employee / project pair' . ($total == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Total <strong>' . Task::hm($grandTotal) . '</strong> hrs</span>',
        'actions' => UserAccess::checkAccess('work-report/export-developer-cumulative-report')
            ? Html::a('<i class="fas fa-file-excel me-1"></i>Export', $export, ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]) : '',
        'route'   => 'cumulative-employee',
        'filters' => [
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'select', 'name' => 'developer_id', 'label' => 'Employee', 'data' => ArrayHelper::map($developers, 'id', 'developer_name'), 'placeholder' => 'All employees', 'col' => 'col-lg-2 col-md-4'],
            ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects', 'col' => 'col-lg-2 col-md-6'],
            ['type' => 'select', 'name' => 'client_id', 'label' => 'Client', 'data' => ArrayHelper::map($clients, 'id', 'client_name'), 'placeholder' => 'All clients', 'col' => 'col-lg-2 col-md-6'],
        ],
    ]) ?>

    <?= $this->render('_totals', [
        'rows'       => $dataProvider->getModels(),
        'columns'    => ['developer_name' => 'Employee', 'project_title' => 'Project', 'client_name' => 'Client'],
        'timeKey'    => 'total_secs',
        'grandTotal' => $grandTotal,
        'pagination' => $dataProvider->getPagination(),
        'total'      => $total,
        'empty'      => $hasFilter
            ? ['icon' => 'search', 'title' => 'No work matches your search', 'text' => 'Try a wider date range or fewer filters.',
               'action' => Html::a('Clear search', ['cumulative-employee'], ['class' => 'btn btn-outline-secondary btn-sm'])]
            : ['icon' => 'chart-bar', 'title' => 'No work logged yet', 'text' => 'Totals per employee and project appear here.'],
    ]) ?>
</div>
