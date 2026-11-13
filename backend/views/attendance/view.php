<?php
use yii\bootstrap5\Html;
use yii\widgets\DetailView;
$this->title = 'Attendance #' . $model->id;
?>
<div class="ui-page attendance-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('Back', Yii::$app->request->referrer ?: \yii\helpers\Url::to(['index']), ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>
    <?= DetailView::widget(['model' => $model, 'attributes' => [
        'id',
        ['label' => 'Employee', 'value' => $model->developer->developer_name ?? '-'],
        'in_time', 'in_ip', 'out_time', 'out_ip',
        'in_latitude', 'in_longitude', 'out_latitude', 'out_longitude',
    ]]) ?>
</div>
