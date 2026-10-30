<?php
use yii\bootstrap5\Html;
use yii\widgets\DetailView;
$this->title = 'Attendance #' . $model->id;
?>
<div class="attendance-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= Html::encode($this->title) ?></h4>
        <?= Html::a('Back', Yii::$app->request->referrer ?: \yii\helpers\Url::to(['index']), ['class' => 'btn btn-secondary btn-sm']) ?>
    </div>
    <?= DetailView::widget(['model' => $model, 'attributes' => [
        'id',
        ['label' => 'Developer', 'value' => $model->developer->developer_name ?? '-'],
        'in_time', 'in_ip', 'out_time', 'out_ip',
        'in_latitude', 'in_longitude', 'out_latitude', 'out_longitude',
    ]]) ?>
</div>
