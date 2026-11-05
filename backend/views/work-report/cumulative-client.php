<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var int   $grandTotal   (in seconds) */
/** @var string $fromDate */
/** @var string $toDate */
/** @var int|null $cliFilter */
/** @var int|null $projFilter */
/** @var \backend\models\ClientModel[] $clients */
/** @var \backend\models\Project[] $projects */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Client based cumulative report';

$total     = $dataProvider->getTotalCount();
$hasFilter = $fromDate || $toDate || $cliFilter || $projFilter;
$period    = ($fromDate || $toDate)
    ? (($fromDate ? date('d M Y', strtotime($fromDate)) : 'Start') . ' – ' . ($toDate ? date('d M Y', strtotime($toDate)) : 'today'))
    : 'All time';

$export = array_filter([
    '/work-report/export-client-cumulative-report',
    'from_date' => $fromDate, 'to_date' => $toDate, 'client_id' => $cliFilter, 'project_id' => $projFilter,
], fn($v) => $v !== null && $v !== '');
?>
<div class="ui-page work-report-cumulative-client">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Cumulative time by client',
        'sub'     => $period . ' · ' . number_format($total) . ' client / project pair' . ($total == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Total <strong>' . Task::hm($grandTotal) . '</strong> hrs</span>',
        'actions' => UserAccess::checkAccess('work-report/export-client-cumulative-report')
            ? Html::a('<i class="fas fa-file-excel me-1"></i>Export', $export, ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]) : '',
        'route'   => 'cumulative-client',
        'filters' => [
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'select', 'name' => 'client_id', 'label' => 'Client', 'data' => ArrayHelper::map($clients, 'id', 'client_name'), 'placeholder' => 'All clients'],
            ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects'],
        ],
    ]) ?>

    <?= $this->render('_totals', [
        'rows'       => $dataProvider->getModels(),
        'columns'    => ['client_name' => 'Client', 'title' => 'Project'],
        'timeKey'    => 'total_secs',
        'grandTotal' => $grandTotal,
        'pagination' => $dataProvider->getPagination(),
        'total'      => $total,
        'empty'      => $hasFilter
            ? ['icon' => 'search', 'title' => 'No work matches your search', 'text' => 'Try a wider date range or fewer filters.',
               'action' => Html::a('Clear search', ['cumulative-client'], ['class' => 'btn btn-outline-secondary btn-sm'])]
            : ['icon' => 'chart-bar', 'title' => 'No work logged yet', 'text' => 'Totals per client and project appear here.'],
    ]) ?>
</div>
