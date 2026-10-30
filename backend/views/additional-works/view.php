<?php
/** @var \yii\web\View $this */
/** @var \backend\models\AdditionalWorks $model */

use yii\bootstrap5\Html;
use yii\widgets\DetailView;

$this->title = 'Additional Work — ' . ($model->developer->developer_name ?? '#' . $model->id);
?>
<div class="additional-works-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
        <div class="d-flex gap-2">
            <?= Html::a('Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-warning btn-sm']) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger btn-sm',
                'data'  => ['confirm' => 'Delete this record?', 'method' => 'post'],
            ]) ?>
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <?= DetailView::widget([
        'model'      => $model,
        'attributes' => [
            ['label' => 'Employee', 'value' => $model->developer->developer_name ?? '—'],
            ['attribute' => 'date', 'value' => $model->date ? date('d M Y', strtotime($model->date)) : '—'],
            'hours',
            'description:ntext',
            'created_date',
        ],
    ]) ?>
</div>
