<?php
/**
 * Create Work Report — renders the shared _form partial.
 *
 * @var \yii\web\View              $this
 * @var \backend\models\WorkReport  $model
 * @var \backend\models\Project[]   $projects
 * @var \backend\models\Developer[] $developers
 * @var bool                       $isAdmin
 */

use yii\bootstrap5\Html;

$this->title = 'Add Work Report';
?>

<div class="work-report-create">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?= $this->render('_form', [
                'model'      => $model,
                'projects'   => $projects,
                'developers' => $developers,
                'isUpdate'   => false,
            ]) ?>
        </div>
    </div>
</div>
