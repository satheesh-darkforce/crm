<?php
use yii\bootstrap5\Html;
use yii\grid\GridView;
$this->title = 'Users';
$typeLabel = [1 => 'Admin', 2 => 'Employee', 3 => 'Client', 4 => 'Sub-Admin'];
?>
<div class="user-index">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <div class="card mb-3"><div class="card-body py-2">
        <form method="get" action="<?= \yii\helpers\Url::to(['index']) ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" name="username" value="<?= Html::encode(Yii::$app->request->get('username')) ?>"
                           class="form-control form-control-sm" placeholder="Username…">
                </div>
                <div class="col-md-2">
                    <select name="usertype" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <?php foreach ($typeLabel as $v => $l): ?>
                            <option value="<?= $v ?>" <?= Yii::$app->request->get('usertype') == $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="1" <?= Yii::$app->request->get('status') == 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= Yii::$app->request->get('status') === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
            </div>
        </form>
    </div></div>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'table table-striped table-hover table-sm'],
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            'user_id',
            'username',
            ['attribute' => 'usertype', 'value' => fn($m) => $typeLabel[$m->usertype] ?? 'Unknown'],
            [
                'attribute' => 'status',
                'format'    => 'raw',
                'value'     => fn($m) => $m->status
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>',
            ],
            [
                'class'    => 'yii\grid\ActionColumn',
                'template' => '{view} {toggle} {reset}',
                'buttons'  => [
                    'view'   => fn($url, $m) => Html::a('View', ['view', 'id' => $m->user_id], ['class' => 'btn btn-info btn-sm']),
                    'toggle' => fn($url, $m) => Html::a($m->status ? 'Disable' : 'Enable', ['toggle-status', 'id' => $m->user_id], [
                        'class' => 'btn btn-' . ($m->status ? 'warning' : 'success') . ' btn-sm',
                        'data'  => ['confirm' => 'Toggle status?', 'method' => 'post'],
                    ]),
                    'reset'  => fn($url, $m) => Html::a('Reset Pwd', ['reset-password', 'id' => $m->user_id], ['class' => 'btn btn-secondary btn-sm']),
                ],
            ],
        ],
    ]) ?>
</div>
