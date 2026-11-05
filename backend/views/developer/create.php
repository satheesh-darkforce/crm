<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer $developer */
/** @var \backend\models\User $user */
/** @var string $suggestedEmpCode */

use yii\bootstrap5\Html;

$this->title = 'Add Employee';
?>
<div class="ui-page developer-create">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <?= $this->render('_form', [
        'developer'         => $developer,
        'user'              => $user,
        'suggestedEmpCode'  => $suggestedEmpCode ?? '',
    ]) ?>
</div>
