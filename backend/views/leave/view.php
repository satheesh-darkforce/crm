<?php
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\widgets\DetailView;

$currentUser = Yii::$app->user->identity;
$isAdmin = $currentUser && ($currentUser->isAdmin() || $currentUser->isSubAdmin());
$backUrl = $isAdmin ? ['index'] : ['my-requests'];
$backLabel = $isAdmin ? 'Leave Requests' : 'My Leave Requests';

$this->title = 'Leave Request';
$isPending = in_array($model->request_status, [
    \backend\models\LeaveRequests::REQ_STATUS_REQUESTED,
    \backend\models\LeaveRequests::REQ_STATUS_L1,
]);
?>
<div class="ui-page leave-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('Back', $backUrl, ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <?= DetailView::widget(['model' => $model, 'attributes' => [
                ['label' => 'Employee', 'value' => $model->developer->developer_name ?? '-'],
                ['attribute' => 'leave_type', 'value' => $model->getLeaveTypeName()],
                'from_date', 'to_date',
                ['attribute' => 'no_of_days', 'value' => $model->getDaysLabel()],
                'reason:ntext',
                ['attribute' => 'request_status', 'value' => $model->getRequestStatusName()],
                [
                    'attribute' => 'requested_date',
                    'value'     => $model->requested_date
                        ? Yii::$app->formatter->asDatetime($model->requested_date, 'php:d M Y, h:i A')
                        : '-',
                ],
                [
                    'attribute' => 'approved_date',
                    'value'     => $model->approved_date
                        ? Yii::$app->formatter->asDatetime($model->approved_date, 'php:d M Y, h:i A')
                        : '-',
                    'visible'   => (int)$model->request_status === \backend\models\LeaveRequests::REQ_STATUS_APPROVED,
                ],
            ]]) ?>
        </div>
        <?php if ($isAdmin && $isPending): ?>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header py-2 fw-bold">Decision</div>
                <div class="card-body">
                    <?php $form = ActiveForm::begin(['action' => ['decide', 'id' => $model->id]]); ?>
                    <div class="d-flex gap-2">
                        <?= Html::submitButton('Approve', ['class' => 'btn btn-success btn-sm', 'name' => 'action', 'value' => 'approve']) ?>
                        <?= Html::submitButton('Reject', ['class' => 'btn btn-danger btn-sm', 'name' => 'action', 'value' => 'reject']) ?>
                    </div>
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
