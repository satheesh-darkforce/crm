<?php
use yii\bootstrap5\Html;
use yii\widgets\DetailView;
$this->title = 'User: ' . $model->username;
?>
<div class="user-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= Html::encode($this->title) ?></h4>
        <div class="d-flex gap-2">
            <?= Html::a('Reset Password', ['reset-password', 'id' => $model->user_id], ['class' => 'btn btn-warning btn-sm']) ?>
            <?= Html::a($model->status ? 'Disable' : 'Enable', ['toggle-status', 'id' => $model->user_id], [
                'class' => 'btn btn-' . ($model->status ? 'secondary' : 'success') . ' btn-sm',
                'data'  => ['confirm' => 'Toggle status?', 'method' => 'post'],
            ]) ?>
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>
    <?= DetailView::widget(['model' => $model, 'attributes' => [
        'user_id', 'username',
        ['attribute' => 'usertype', 'value' => $model->getTypeName()],
        ['attribute' => 'status', 'value' => $model->status ? 'Active' : 'Inactive'],
        'last_password_changed_date',
    ]]) ?>
</div>
