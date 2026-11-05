<?php
/**
 * @var \yii\web\View                $this
 * @var \yii\data\ActiveDataProvider $dataProvider
 * @var \backend\models\Project[]     $projects
 * @var int                          $totalSecs  total seconds for the current filter
 */

use backend\models\Task;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'My Work Reports';

$total     = $dataProvider->getTotalCount();
$req       = Yii::$app->request;
$hasFilter = ($req->get('project_id') ?? '') !== '' || ($req->get('from_date') ?? '') !== '' || ($req->get('to_date') ?? '') !== '';
$canCreate = UserAccess::checkAccess('work-report/create');
$noEmployee = !Yii::$app->user->identity->getDeveloperId();
?>
<div class="ui-page work-report-mine">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'My work reports',
        'sub'     => number_format($total) . ' report' . ($total == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Total <strong>' . Task::hm($totalSecs) . '</strong> hrs</span>',
        'actions' => $canCreate && !$noEmployee ? Html::a('<i class="fas fa-plus me-1"></i>Add Work Report', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : '',
        'route'   => 'my-work-reports',
        'filters' => [
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'select', 'name' => 'project_id', 'label' => 'Project', 'data' => ArrayHelper::map($projects, 'id', 'title'), 'placeholder' => 'All projects', 'col' => 'col-lg-4 col-md-4'],
        ],
    ]) ?>

    <?php if ($noEmployee): ?>
        <div class="alert alert-warning">Your login is not linked to an employee record, so you cannot log work reports. Ask an admin to link it.</div>
    <?php endif; ?>

    <?= $this->render('_entries', [
        'dataProvider' => $dataProvider,
        'columns'      => ['date', 'project', 'client'],
        'empty'        => $hasFilter
            ? ['icon' => 'search', 'title' => 'No reports match your search', 'text' => 'Try a wider date range.',
               'action' => Html::a('Clear search', ['my-work-reports'], ['class' => 'btn btn-outline-secondary btn-sm'])]
            : ['icon' => 'clipboard-list', 'title' => 'You have not logged any work yet', 'text' => 'Add a report at the end of each day.',
               'action' => $canCreate && !$noEmployee ? Html::a('<i class="fas fa-plus me-1"></i>Add Work Report', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null],
    ]) ?>
</div>
