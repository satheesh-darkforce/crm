<?php
/**
 * @var \yii\web\View              $this
 * @var \backend\models\WorkReport  $model
 * @var \backend\models\Project[]   $projects
 * @var \backend\models\Developer[] $developers
 */

use yii\bootstrap5\Html;

$this->title = 'Edit Work Report';
?>
<div class="ui-page work-report-update" style="max-width:1100px">
    <div class="ui-head">
        <div>
            <h1>Edit work report</h1>
            <div class="ui-sub">#<?= (int)$model->work_id ?> · <?= Html::encode($model->developer->developer_name ?? '—') ?> · <?= $model->getOldAttribute('date') ? date('d M Y', strtotime($model->getOldAttribute('date'))) : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['view', 'id' => $model->work_id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model, 'projects' => $projects, 'developers' => $developers, 'isUpdate' => true]) ?>
</div>
