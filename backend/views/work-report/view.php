<?php
use common\components\UserAccess;
use backend\models\User;
use yii\bootstrap5\Html;
use yii\widgets\DetailView;
$this->title = 'Work Report #' . $model->work_id;

// Show Edit / Delete only when the current user actually has permission —
// matches Yii1's per-action `UserAccess::checkaccess(...)` visibility check.
$canUpdate = UserAccess::checkAccess('work-report/update');
$canDelete = UserAccess::checkAccess('work-report/delete');

// Back button → previous page (referrer). Falls back to whichever list page
// the current user can actually access; finally to the dashboard.
$backUrl = Yii::$app->request->referrer;
if (!$backUrl || rtrim($backUrl, '/') === rtrim(Yii::$app->request->absoluteUrl, '/')) {
    if (UserAccess::checkAccess('work-report/index')) {
        $backUrl = \yii\helpers\Url::to(['index']);
    } elseif (UserAccess::checkAccess('work-report/my-work-reports')) {
        $backUrl = \yii\helpers\Url::to(['my-work-reports']);
    } else {
        $backUrl = \yii\helpers\Url::to(['/site/index']);
    }
}
?>
<div class="work-report-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= Html::encode($this->title) ?></h4>
        <div class="d-flex gap-2">
            <?php if ($canUpdate): ?>
                <?= Html::a('Edit', ['update', 'id' => $model->work_id], ['class' => 'btn btn-warning btn-sm']) ?>
            <?php endif; ?>
            <?php if ($canDelete): ?>
                <?= Html::a('Delete', ['delete', 'id' => $model->work_id], ['class' => 'btn btn-danger btn-sm', 'data' => ['confirm' => 'Remove?', 'method' => 'post']]) ?>
            <?php endif; ?>
            <?= Html::a('Back', $backUrl, ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>
    <?php
    // Resolve created/updated user names — Yii1 view shows them via ->createdby and
    // ->updatedby relations; the Yii2 model doesn't expose those, so resolve directly.
    $createdByName = $model->created_by
        ? (User::findOne(['user_id' => $model->created_by])->username ?? '-') : '-';
    $updatedByName = $model->updated_by
        ? (User::findOne(['user_id' => $model->updated_by])->username ?? '-') : '-';
    $fmtTs = fn($v) => ($v && $v !== '0000-00-00 00:00:00') ? date('Y-m-d h:i A', strtotime($v)) : '-';
    ?>
    <?= DetailView::widget(['model' => $model, 'attributes' => [
        'work_id',
        ['label' => 'Developer', 'value' => $model->developer->developer_name ?? '-'],
        ['label' => 'Project',   'value' => $model->project->title ?? '-'],
        ['label' => 'Client',    'value' => $model->client->client_name ?? '-'],
        'date',
        ['label' => 'From Time', 'value' => $model->from_time ?: '-'],
        ['label' => 'To Time',   'value' => $model->to_time ?: '-'],
        ['label' => 'Total Time', 'value' => $model->getTotalTimeFormatted()],
        'task',
        'description:ntext',
        ['label' => 'Created By',  'value' => $createdByName],
        ['label' => 'Created At',  'value' => $fmtTs($model->created_time)],
        ['label' => 'Updated By',  'value' => $updatedByName],
        ['label' => 'Last Update', 'value' => $fmtTs($model->last_updated_time)],
    ]]) ?>
</div>
