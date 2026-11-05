<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Employee $employee */
/** @var \backend\models\User $user */

use yii\bootstrap5\Html;

$this->title = 'Add Employee';
?>
<div class="ui-page employee-create">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', ['employee' => $employee, 'user' => $user, 'isUpdate' => false]) ?>
</div>
