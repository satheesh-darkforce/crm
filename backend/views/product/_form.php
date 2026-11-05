<?php
/** @var yii\web\View $this */
/** @var backend\models\Product $model */
/** @var array<int,string> $categories */

use backend\models\Product;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$req = '<span class="text-danger">*</span>';
$features = $model->featureList ?: [''];
$specs    = $model->specList ?: [['name' => '', 'value' => '']];
?>
<?php $form = ActiveForm::begin([
    'id'      => 'product-form',
    'options' => ['class' => 'ui-form', 'enctype' => 'multipart/form-data'],
]); ?>

<?php if ($model->hasErrors()): ?>
    <div class="alert alert-danger py-2 small">Please fix the highlighted fields below.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <section class="p-section">
            <h2>Basics</h2>
            <div class="row g-2">
                <div class="col-md-8">
                    <?= $form->field($model, 'item_name')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255, 'placeholder' => 'e.g. Premium Balcony Artificial Grass Mat'])
                         ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'product_code')->textInput(['class' => 'form-control form-control-sm mono', 'maxlength' => 50])
                        ->hint('Unique. Suggested automatically.') ?>
                </div>
                <div class="col-md-5">
                    <?= $form->field($model, 'category_id')->widget(Select2::class, [
                        'data'          => $categories,
                        'size'          => Select2::SMALL,
                        'options'       => ['placeholder' => $categories ? 'Select category' : 'Add a category first'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])
                      ->hint($categories ? '' : Html::a('Add categories', ['categories'], ['target' => '_blank'])) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'item_type')->dropDownList(Product::typeOptions(), ['class' => 'form-select form-select-sm'])
                         ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'hsn_code')->textInput(['class' => 'form-control form-control-sm mono', 'maxlength' => 8, 'inputmode' => 'numeric'])
                        ->label('HSN code') ?>
                </div>
                <div class="col-12">
                    <?= $form->field($model, 'short_description')->textInput(['class' => 'form-control form-control-sm', 'maxlength' => 255,
                        'placeholder' => 'One line shown in lists and on quotations']) ?>
                </div>
                <div class="col-12">
                    <?= $form->field($model, 'description')->textarea(['class' => 'form-control form-control-sm', 'rows' => 4, 'maxlength' => 10000]) ?>
                </div>
            </div>
        </section>

        <section class="p-section">
            <h2>Unit &amp; pricing</h2>
            <?php
            $per    = $model->units ? 'per ' . Html::encode($model->units) : 'per unit';
            $groups = [];
            foreach (Product::UNIT_GROUPS as $key => $g) {
                foreach (array_keys($g['units']) as $u) {
                    $groups[$u] = ['data-group' => $g['label'], 'data-decimals' => $g['decimals'] ? '1' : '0'];
                }
            }
            ?>
            <div class="row g-2">
                <div class="col-md-5">
                    <?= $form->field($model, 'units')->dropDownList(Product::unitDropdown(), [
                        'class' => 'form-select form-select-sm', 'prompt' => 'How is it sold?', 'options' => $groups,
                    ])->hint('<span id="unit-hint">Measured by area, length, weight, volume, count or as a service.</span>') ?>
                </div>
                <div class="col-md-4 col-7">
                    <?= $form->field($model, 'sale_price', ['inputTemplate' => '<div class="input-group input-group-sm"><span class="input-group-text">₹</span>{input}<span class="input-group-text p-per">' . $per . '</span></div>'])
                        ->textInput(['class' => 'form-control form-control-sm num', 'inputmode' => 'decimal', 'placeholder' => '0.00'])
                        ->label('Unit price <span class="p-req">' . $req . '</span>', ['encode' => false])
                        ->hint('Price for one unit, before GST.') ?>
                </div>
                <div class="col-md-3 col-5">
                    <?= $form->field($model, 'gst_rate')->dropDownList(Product::gstOptions(), ['class' => 'form-select form-select-sm', 'prompt' => 'Select GST']) ?>
                </div>
                <div class="col-md-4 col-7">
                    <?= $form->field($model, 'mrp', ['inputTemplate' => '<div class="input-group input-group-sm"><span class="input-group-text">₹</span>{input}<span class="input-group-text p-per">' . $per . '</span></div>'])
                        ->textInput(['class' => 'form-control form-control-sm num', 'inputmode' => 'decimal', 'placeholder' => 'Optional'])
                        ->label('MRP / list price')->hint('Optional. Shows the discount.') ?>
                </div>
                <div class="col-md-8 d-flex align-items-center">
                    <?= $form->field($model, 'price_on_request', ['options' => ['class' => 'mb-0']])->checkbox(['class' => 'form-check-input'])
                        ->label('Price on request — quote the rate per site (custom work, packages)') ?>
                </div>
                <div class="col-12">
                    <div class="p-preview" id="price-preview" aria-live="polite"></div>
                </div>
            </div>
        </section>

        <section class="p-section">
            <h2>Features</h2>
            <div data-repeat data-max="20">
                <div data-repeat-list>
                    <?php foreach ($features as $i => $f): ?>
                        <div class="p-repeat-row" data-repeat-row>
                            <input type="text" class="form-control form-control-sm" name="Product[featureList][<?= $i ?>]" value="<?= Html::encode($f) ?>" maxlength="150" placeholder="e.g. UV-stable, fade resistant">
                            <button type="button" class="btn btn-outline-secondary btn-sm p-repeat-remove" data-repeat-remove aria-label="Remove feature"><i class="fas fa-times"></i></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeat-template>
                    <div class="p-repeat-row" data-repeat-row>
                        <input type="text" class="form-control form-control-sm" name="Product[featureList][__i__]" maxlength="150" placeholder="Feature">
                        <button type="button" class="btn btn-outline-secondary btn-sm p-repeat-remove" data-repeat-remove aria-label="Remove feature"><i class="fas fa-times"></i></button>
                    </div>
                </template>
                <?php if ($model->hasErrors('featureList')): ?><div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('featureList')) ?></div><?php endif; ?>
                <button type="button" class="btn btn-outline-primary btn-sm mt-1" data-repeat-add><i class="fas fa-plus me-1"></i>Add feature</button>
            </div>
        </section>

        <section class="p-section">
            <h2>Specifications</h2>
            <div data-repeat data-max="30">
                <div data-repeat-list>
                    <?php foreach ($specs as $i => $s): ?>
                        <div class="p-repeat-row" data-repeat-row>
                            <input type="text" class="form-control form-control-sm p-spec-name" name="Product[specList][<?= $i ?>][name]" value="<?= Html::encode($s['name']) ?>" maxlength="60" placeholder="e.g. Pile height">
                            <input type="text" class="form-control form-control-sm" name="Product[specList][<?= $i ?>][value]" value="<?= Html::encode($s['value']) ?>" maxlength="200" placeholder="e.g. 40 mm">
                            <button type="button" class="btn btn-outline-secondary btn-sm p-repeat-remove" data-repeat-remove aria-label="Remove specification"><i class="fas fa-times"></i></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeat-template>
                    <div class="p-repeat-row" data-repeat-row>
                        <input type="text" class="form-control form-control-sm p-spec-name" name="Product[specList][__i__][name]" maxlength="60" placeholder="Name">
                        <input type="text" class="form-control form-control-sm" name="Product[specList][__i__][value]" maxlength="200" placeholder="Value">
                        <button type="button" class="btn btn-outline-secondary btn-sm p-repeat-remove" data-repeat-remove aria-label="Remove specification"><i class="fas fa-times"></i></button>
                    </div>
                </template>
                <?php if ($model->hasErrors('specList')): ?><div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('specList')) ?></div><?php endif; ?>
                <button type="button" class="btn btn-outline-primary btn-sm mt-1" data-repeat-add><i class="fas fa-plus me-1"></i>Add specification</button>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="p-section">
            <h2>Image</h2>
            <?php if ($model->image): ?>
                <img class="p-image mb-2" src="<?= Html::encode(Yii::getAlias('@web') . $model->image) ?>" alt="<?= Html::encode($model->item_name) ?>">
                <?php if (!$model->isNewRecord): ?>
                    <div class="mb-2">
                        <?= Html::a('<i class="fas fa-trash me-1"></i>Remove image', ['remove-image', 'id' => $model->id], ['class' => 'btn btn-link btn-sm text-danger p-0', 'encode' => false,
                            'data' => ['method' => 'post', 'confirm' => 'Remove this image?']]) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <input type="file" name="image_file" id="product-image-file" accept=".jpg,.jpeg,.png,.webp"
                   class="form-control form-control-sm<?= $model->hasErrors('image') ? ' is-invalid' : '' ?>" aria-label="Product image">
            <?php if ($model->hasErrors('image')): ?><div class="invalid-feedback d-block"><?= Html::encode($model->getFirstError('image')) ?></div><?php endif; ?>
            <div class="p-hint mt-1">JPG, PNG or WebP, up to 5 MB.<?= $model->image ? ' A new file replaces the current image.' : '' ?></div>
        </section>

        <section class="p-section">
            <h2>Availability</h2>
            <?= $form->field($model, 'is_active')->checkbox(['class' => 'form-check-input'])->label('Active — can be picked on new quotations and invoices') ?>
        </section>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <?= Html::submitButton($model->isNewRecord ? 'Save product' : 'Save changes', ['class' => 'btn btn-primary btn-sm px-4', 'name' => 'after', 'value' => 'view']) ?>
    <?php if ($model->isNewRecord): ?>
        <?= Html::submitButton('Save &amp; add another', ['class' => 'btn btn-outline-primary btn-sm', 'name' => 'after', 'value' => 'new', 'encode' => false]) ?>
    <?php endif; ?>
    <?= Html::a('Cancel', $model->isNewRecord ? ['index'] : ['view', 'id' => $model->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>

<?php ActiveForm::end(); ?>
