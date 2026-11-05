<?php
/**
 * Client Work Reports — work reports filtered for the logged-in client.
 *
 * @var \yii\web\View                $this
 * @var \yii\data\ActiveDataProvider $dataProvider
 * @var \backend\models\Project[]     $projects
 * @var \backend\models\Developer[]   $developers
 * @var int                          $totalMins   sum of total_time (seconds, despite the name) for the current filter
 */

use backend\models\Task;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Work Reports';

$total   = $dataProvider->getTotalCount();
$req     = Yii::$app->request;
$keys    = ['from_date', 'to_date', 'project_id', 'developer_id'];
$hasFilter = (bool)array_filter(array_map(fn($k) => $req->get($k), $keys), fn($v) => $v !== null && $v !== '');

$filters = [
    ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
    ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
];
if ($projects) {
    $filters[] = ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects'];
}
if ($developers) {
    $filters[] = ['type' => 'select', 'name' => 'developer_id', 'label' => 'Team member', 'data' => ArrayHelper::map($developers, 'id', 'developer_name'), 'placeholder' => 'Everyone'];
}
?>
<div class="ui-page client-work-reports">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'     => 'Work on your projects',
        'sub'       => number_format($total) . ' report' . ($total == 1 ? '' : 's'),
        'meta'      => '<span class="ui-total">Total <strong>' . Task::hm($totalMins) . '</strong> hrs</span>',
        'route'     => 'client-work-reports',
        'countKeys' => $keys,
        'filters'   => $filters,
    ]) ?>

    <?= $this->render('_entries', [
        'dataProvider' => $dataProvider,
        'columns'      => ['date', 'developer', 'project'],
        'readOnly'     => true,
        'empty'        => $hasFilter
            ? ['icon' => 'search', 'title' => 'No reports match your search', 'text' => 'Try a wider date range.',
               'action' => Html::a('Clear search', ['client-work-reports'], ['class' => 'btn btn-outline-secondary btn-sm'])]
            : ['icon' => 'clipboard-list', 'title' => 'No work reported yet', 'text' => 'Daily work logged on your projects appears here.'],
    ]) ?>
</div>
