<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Employee $employee */
/** @var \backend\models\User|null $user */

use yii\bootstrap5\Html;

$this->title = "Edit Employee: {$employee->employee_name}";
?>
<div class="ui-page employee-update">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', '#', [
            'class'   => 'btn btn-outline-secondary btn-sm',
            'encode'  => false,
            // Replace() so update.php drops out of history — otherwise the
            // view-page Back bounces back here. See memory: history_loop_replace_pattern.
            'onclick' => 'window.location.replace(' . \yii\helpers\Json::encode(\yii\helpers\Url::to(['index'])) . '); return false;',
        ]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['employee' => $employee, 'user' => $user ?? new \backend\models\User(), 'isUpdate' => true]) ?>
</div>
