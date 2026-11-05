<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadMaster $model */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Lead Master';
?>
<div class="ui-page lead-master-view" style="max-width:680px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->getFullName() ?: 'Prospect #' . $model->id) ?></h1>
            <div class="ui-sub"><?= Html::encode($model->account_name) ?></div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('lead/update-lead-master')): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update-lead-master', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <?= $this->render('_lead-master-detail', ['model' => $model]) ?>
    </div>
</div>
