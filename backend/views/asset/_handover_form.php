<?php
/** @var \yii\web\View $this */
/** @var \backend\models\AssetHandover $model */
/** @var \backend\models\Asset[] $assets */
/** @var \backend\models\Developer[] $developers */
/** @var \backend\models\AssetLocation[] $locations */

use backend\models\AssetHandover;
use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Create' : 'Update';

$selectedAssetIds = $isNew ? [] : $model->getDecodedAssetIds();
$assetMap    = ArrayHelper::map($assets, 'id', fn($a) => $a->barcode ?: $a->product_name);
$devMap      = ArrayHelper::map($developers, 'id', 'developer_name');
$locMap      = ArrayHelper::map($locations, 'id', 'loc');
$req = '<span class="text-danger">*</span>';
?>
<div class="ui-page asset-handover-form">
    <div class="ui-head">
        <div>
            <h1><?= $this->title ?> Asset Handover</h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['handover'], ['class' => 'btn btn-outline-danger btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <p class="mb-2"><strong>Fields with <span class="text-danger">*</span> are required.</strong></p>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['id' => 'handover-form']); ?>
            <div class="row g-3">

                <!-- Row 1: Employee + Given Date + Return Date (edit only) + Condition (edit only) -->
                <div class="col-md-3">
                    <?= $form->field($model, 'dev_id')->widget(Select2::class, [
                        'data'          => $devMap,
                        'options'       => ['placeholder' => 'Select Employee'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Employee ' . $req, ['encode' => false]) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'in_date')->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'value'         => $model->in_date ? date('Y-m-d', strtotime($model->in_date)) : date('Y-m-d'),
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => '0d'],
                    ])->label('Asset Given Date ' . $req, ['encode' => false]) ?>
                </div>
                <?php if (!$isNew): ?>
                <div class="col-md-3">
                    <?= $form->field($model, 'out_date')->widget(DatePicker::class, [
                        'type'          => DatePicker::TYPE_INPUT,
                        'value'         => $model->out_date ? date('Y-m-d', strtotime($model->out_date)) : '',
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'endDate' => '0d'],
                    ])->label('Asset Returned Date ' . $req, ['encode' => false]) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'asset_condition')->widget(Select2::class, [
                        'data'          => AssetHandover::conditionLabels(),
                        'options'       => ['placeholder' => 'Select Condition'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Asset Condition ' . $req, ['encode' => false]) ?>
                </div>
                <?php endif; ?>

                <!-- Row 2: Location + Assets (multi-select) + Description -->
                <div class="col-md-3">
                    <?= $form->field($model, 'asset_location')->widget(Select2::class, [
                        'data'          => $locMap,
                        'options'       => ['placeholder' => 'Select Assets Location'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Assets Location ' . $req, ['encode' => false]) ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Asset <?= $req ?></label>
                    <?= Select2::widget([
                        'name'          => 'AssetHandover[asset_id][]',
                        'value'         => $selectedAssetIds,
                        'data'          => $assetMap,
                        'options'       => [
                            'placeholder' => 'Select Assets…',
                            'multiple'    => true,
                            'class'       => 'form-control form-control-sm',
                        ],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                    <?php if ($model->hasErrors('asset_id')): ?>
                        <div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('asset_id')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'description')->textarea([
                        'rows'        => 3,
                        'class'       => 'form-control form-control-sm',
                        'maxlength'   => 250,
                        'placeholder' => 'Description…',
                    ])->label('Description') ?>
                </div>
            </div>

            <input type="hidden" id="sub_key" name="sub_key" value="1">
            <div class="d-flex gap-2 mt-3">
                <?= Html::submitButton('Submit', ['class' => 'btn btn-primary btn-sm px-4', 'id' => 'btn-submit']) ?>
                <?php if ($isNew): ?>
                    <?= Html::submitButton('Save & Add New', ['class' => 'btn btn-outline-primary btn-sm px-4', 'id' => 'btn-submit-new']) ?>
                <?php endif; ?>
                <?= Html::a('Cancel', ['handover'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$js = <<<JS
$('#btn-submit').on('click', function() { $('#sub_key').val('1'); });
$('#btn-submit-new').on('click', function() { $('#sub_key').val('0'); });
JS;
$this->registerJs($js);
?>
