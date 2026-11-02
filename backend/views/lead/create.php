<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var array $users */

use yii\bootstrap5\Html;

$this->title = 'Add Lead';
?>
<div class="lead-create">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <?= $this->render('_form', ['lead' => $lead, 'users' => $users]) ?>
</div>
