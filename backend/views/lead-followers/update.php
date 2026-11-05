<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadFollowers $model */
/** @var \backend\models\Lead $lead */
/** @var array $users */

use yii\bootstrap5\Html;

$this->title = 'Update Follow Up';
?>
<div class="ui-page lead-followers-update" style="max-width:1000px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub"><?= Html::encode($lead->client_name) ?><?= $lead->title ? ' · ' . Html::encode($lead->title) : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model, 'lead' => $lead, 'users' => $users]) ?>
</div>
