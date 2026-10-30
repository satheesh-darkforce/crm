<?php
/**
 * Update Work Report.
 *
 * @var \yii\web\View              $this
 * @var \backend\models\WorkReport  $model
 * @var \backend\models\Project[]   $projects
 * @var \backend\models\Developer[] $developers
 */

use yii\bootstrap5\Html;

$this->title = 'Update Work Report #' . $model->work_id;
?>

<div class="work-report-update">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
            <small class="text-muted">
                Developer: <strong><?= Html::encode($model->developer->developer_name ?? '—') ?></strong>
            </small>
        </div>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?= $this->render('_form', ['model' => $model, 'projects' => $projects, 'developers' => $developers, 'isUpdate' => true]) ?>
        </div>
    </div>
</div>
