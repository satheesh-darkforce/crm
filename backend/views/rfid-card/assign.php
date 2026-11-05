<?php
/** @var \yii\web\View $this */
/** @var \backend\models\RfidCard $model */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;

$this->title = 'Assign RFID Card';
?>
<div class="ui-page rfid-assign">
    <div class="ui-head">
        <div>
            <h1>Assign Card: <span class="font-monospace"><?= Html::encode($model->code) ?></span></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:480px">
        <div class="card-body">
            <?php if ($model->developer): ?>
            <div class="alert alert-info small mb-3">
                Currently assigned to: <strong><?= Html::encode($model->developer->developer_name ?? '—') ?></strong>
                since <?= $model->assigned_from ? date('d M Y', strtotime($model->assigned_from)) : '—' ?>.
            </div>
            <?php endif; ?>

            <form method="post">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Assign To</label>
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => $model->developer_id,
                        'data'          => ['' => '— Unassign Card —'] + ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'Select Employee'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-4">Save Assignment</button>
                    <?= Html::a('Cancel', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                </div>
            </form>
        </div>
    </div>
</div>
