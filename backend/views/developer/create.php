<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer $developer */
/** @var \backend\models\User $user */
/** @var string $suggestedEmpCode */

use yii\bootstrap5\Html;

$this->title = 'Add Employee';
?>
<div class="developer-create">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= Html::encode($this->title) ?></h4>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <?= $this->render('_form', [
        'developer'         => $developer,
        'user'              => $user,
        'suggestedEmpCode'  => $suggestedEmpCode ?? '',
    ]) ?>
</div>
