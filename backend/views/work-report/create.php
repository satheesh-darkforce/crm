<?php
/**
 * @var \yii\web\View              $this
 * @var \backend\models\WorkReport  $model
 * @var \backend\models\Project[]   $projects
 * @var \backend\models\Developer[] $developers
 * @var bool                       $isAdmin
 */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Add Work Report';
?>
<div class="ui-page work-report-create" style="max-width:1100px">
    <div class="ui-head">
        <div>
            <h1>Add work report</h1>
            <div class="ui-sub">Log what you worked on and for how long.</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', [UserAccess::checkAccess('work-report/index') ? 'index' : 'my-work-reports'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['model' => $model, 'projects' => $projects, 'developers' => $isAdmin ? $developers : [], 'isUpdate' => false]) ?>
</div>
