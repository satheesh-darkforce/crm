<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Employee $employee */
/** @var \backend\models\User $user */

use yii\bootstrap5\Html;

$this->title = 'Add Employee';
?>
<div class="employee-create">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <?= $this->render('_form', ['employee' => $employee, 'user' => $user, 'isUpdate' => false]) ?>
</div>
