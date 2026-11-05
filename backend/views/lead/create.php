<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var array $users */

use yii\bootstrap5\Html;

$this->title = 'Add Lead';
?>
<div class="ui-page lead-create">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub">New leads start as Open with their first follow-up scheduled.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', Yii::$app->request->referrer ?: ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['lead' => $lead, 'users' => $users]) ?>
</div>
