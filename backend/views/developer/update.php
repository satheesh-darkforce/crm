<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer $developer */
/** @var \backend\models\User|null $user */

use yii\bootstrap5\Html;

$this->title = 'Edit Employee: ' . $developer->developer_name;
?>
<div class="developer-update">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= Html::encode($this->title) ?></h4>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', '#', [
            'class'   => 'btn btn-outline-secondary btn-sm',
            'encode'  => false,
            // Replace() so update.php drops out of history — otherwise the
            // view-page Back bounces back here. See memory: history_loop_replace_pattern.
            'onclick' => 'window.location.replace(' . \yii\helpers\Json::encode(\yii\helpers\Url::to(['/employee/index'])) . '); return false;',
        ]) ?>
    </div>
    <?= $this->render('_form', ['developer' => $developer, 'user' => $user ?? new \backend\models\User(), 'isUpdate' => true]) ?>
</div>
