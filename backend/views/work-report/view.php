<?php
/**
 * @var \yii\web\View              $this
 * @var \backend\models\WorkReport  $model
 */

use backend\components\TaskAccess;
use backend\models\Task;
use backend\models\User;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Work Report';

// Edit / delete: managers, the company-wide list holders, or the report's own employee.
$user    = Yii::$app->user->identity;
$own     = (int)$model->developer_id > 0 && (int)$model->developer_id === (int)$user->getDeveloperId();
$mayEdit = TaskAccess::isManager() || UserAccess::checkAccess('work-report/index') || $own;
$canUpdate = $mayEdit && UserAccess::checkAccess('work-report/update');
$canDelete = $mayEdit && UserAccess::checkAccess('work-report/delete');

$back = Yii::$app->request->referrer;
if (!$back || rtrim($back, '/') === rtrim(Yii::$app->request->absoluteUrl, '/')) {
    $back = Url::to([UserAccess::checkAccess('work-report/index') ? 'index' : (UserAccess::checkAccess('work-report/my-work-reports') ? 'my-work-reports' : '/site/index')]);
}
$name = fn($uid) => $uid ? (User::find()->select('username')->where(['user_id' => $uid])->scalar() ?: '—') : '—';
$ts   = fn($v) => ($v && $v !== '0000-00-00 00:00:00') ? date('d M Y, h:i a', strtotime($v)) : '—';
$val  = fn($v) => trim((string)$v) !== '' ? Html::encode($v) : '<span class="text-muted">—</span>';
?>
<div class="ui-page work-report-view" style="max-width:960px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->developer->developer_name ?? 'Work report') ?> · <?= date('d M Y', strtotime($model->date)) ?></h1>
            <div class="ui-sub">#<?= (int)$model->work_id ?> · <?= Html::encode($model->project->title ?? 'No project') ?></div>
        </div>
        <div class="ui-actions">
            <?php if ($canUpdate): ?><?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->work_id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?><?php endif; ?>
            <?php if ($canDelete): ?>
                <?= Html::a('<i class="fas fa-trash me-1"></i>Delete', ['delete', 'id' => $model->work_id], ['class' => 'btn btn-outline-danger btn-sm', 'encode' => false,
                    'data' => ['method' => 'post', 'confirm' => 'Remove this work report of ' . date('d M Y', strtotime($model->date)) . '? Its hours leave every report.']]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', $back, ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="ui-tiles">
        <div class="ui-tile is-accent"><div class="t-label">Time worked</div><div class="t-value num"><?= Task::hm((int)$model->total_time) ?></div>
            <div class="t-sub"><?= $model->from_time && $model->to_time ? Html::encode($model->from_time . ' – ' . $model->to_time) : 'No from / to time' ?></div></div>
        <div class="ui-tile"><div class="t-label">Project</div><div class="t-value"><?= Html::encode($model->project->title ?? '—') ?></div>
            <div class="t-sub"><?= Html::encode($model->client->client_name ?? 'No client') ?></div></div>
        <div class="ui-tile"><div class="t-label">Source</div><div class="t-value"><?= [1 => 'Web', 2 => 'Mobile app', 3 => 'API'][(int)$model->data_source] ?? '—' ?></div>
            <div class="t-sub"><?= $model->task_id ? 'Linked to task #' . (int)$model->task_id : 'Not linked to a task' ?></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">Details</div>
        <table class="table ui-detail">
            <tr><th scope="row">Task</th><td><?= $val($model->task) ?></td></tr>
            <tr><th scope="row">Description</th><td><?= trim((string)$model->description) !== '' ? nl2br(Html::encode($model->description)) : '<span class="text-muted">—</span>' ?></td></tr>
            <tr><th scope="row">Created</th><td><?= Html::encode($name($model->created_by)) ?> · <?= $ts($model->created_time) ?></td></tr>
            <tr><th scope="row">Last updated</th><td><?= $model->updated_by ? Html::encode($name($model->updated_by)) . ' · ' . $ts($model->last_updated_time) : '<span class="text-muted">Never</span>' ?></td></tr>
        </table>
    </div>
</div>
