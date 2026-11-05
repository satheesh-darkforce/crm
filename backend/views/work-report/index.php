<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\ClientModel[] $clients */
/** @var int $grandTotalSecs */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Daily Work Report';

$g     = Yii::$app->request->get();
$total = $dataProvider->getTotalCount();
$keys  = ['from_date', 'to_date', 'date', 'developer_id', 'project_id', 'client_id', 'task'];
$hasFilter = (bool)array_filter(array_intersect_key($g, array_flip($keys)), fn($v) => $v !== '' && $v !== null);

// Export keeps the current filters (Yii1 parity).
$export = array_filter(['/work-report/export-daily-work-report'] + array_intersect_key($g, array_flip(['from_date', 'to_date', 'developer_id', 'project_id', 'client_id'])),
    fn($v) => $v !== null && $v !== '');

$actions = '';
if (UserAccess::checkAccess('work-report/export-daily-work-report')) {
    $actions .= Html::a('<i class="fas fa-file-excel me-1"></i>Export', $export, ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]);
}
if (UserAccess::checkAccess('work-report/create')) {
    $actions .= Html::a('<i class="fas fa-plus me-1"></i>Add Work Report', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]);
}
?>
<div class="ui-page work-report-index">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Work reports',
        'sub'     => number_format($total) . ' report' . ($total == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Total <strong>' . Task::hm($grandTotalSecs) . '</strong> hrs</span>',
        'actions' => $actions,
        'route'   => 'index',
        'countKeys' => $keys,
        'filters' => [
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'select', 'name' => 'developer_id', 'label' => 'Employee', 'data' => ArrayHelper::map($developers, 'id', 'developer_name'), 'placeholder' => 'All employees'],
            ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects'],
            ['type' => 'select', 'name' => 'client_id', 'label' => 'Client', 'data' => ArrayHelper::map($clients, 'id', 'client_name'), 'placeholder' => 'All clients'],
            ['type' => 'text', 'name' => 'task', 'label' => 'Task', 'placeholder' => 'Search task text', 'col' => 'col-lg-4 col-md-6'],
        ],
    ]) ?>

    <?= $this->render('_entries', [
        'dataProvider' => $dataProvider,
        'columns'      => ['developer', 'date', 'client', 'project'],
        'empty'        => $hasFilter
            ? ['icon' => 'search', 'title' => 'No work reports match your search', 'text' => 'Try a wider date range or fewer filters.',
               'action' => Html::a('Clear search', ['index'], ['class' => 'btn btn-outline-secondary btn-sm'])]
            : ['icon' => 'clipboard-list', 'title' => 'No work reports yet', 'text' => 'Reports logged by the team appear here.'],
    ]) ?>
</div>
