<?php
/** @var \yii\web\View $this */
/** @var \backend\models\IotNonPurchase $model */
/** @var \backend\models\Project[] $projects */
/** @var \backend\models\IotProduct[] $products */
/** @var \backend\models\IotNonPurchaseProducts[] $existing */

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;

$isNew = $model->isNewRecord;
$this->title = $isNew ? 'Add Non-Purchase Entry' : 'Edit Non-Purchase Entry';

$existing = $existing ?? [];

// Build product options for JS
$productOptions = [];
foreach ($products as $p) {
    $productOptions[$p->id] = Html::encode($p->product_name);
}
$productOptionsJson = json_encode($productOptions);
?>
<div class="non-purchase-form">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['non-purchased'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php $form = ActiveForm::begin(['action' => $isNew ? ['create-non-purchase'] : ['update-non-purchase', 'id' => $model->id]]); ?>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <?= $form->field($model, 'project_id')->widget(Select2::class, [
                        'data'          => ArrayHelper::map($projects, 'id', 'title'),
                        'options'       => ['placeholder' => 'Select Project…'],
                        'pluginOptions' => ['allowClear' => true],
                    ]) ?>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 mb-2">
                <h6 class="fw-semibold mb-0">Products Required</h6>
                <?= Html::a('<b>Add Product</b>', ['create'], [
                    'style'  => 'color:blue; font-size:.85rem; cursor:pointer',
                    'target' => '_blank',
                    'title'  => 'Create a new IOT product',
                    'encode' => false,
                ]) ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleProducts">Hide Product</button>
            </div>
            <div class="table-responsive mb-3" id="product-table">
                <table class="table table-sm border">
                    <thead class="table-light">
                        <tr>
                            <th style="width:55%">Product</th>
                            <th style="width:25%">Quantity</th>
                            <th style="width:20%"></th>
                        </tr>
                    </thead>
                    <tbody id="itemBody">
                        <?php if (empty($existing)): ?>
                        <tr>
                            <td>
                                <select name="product_id[]" class="form-select form-select-sm" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p->id ?>"><?= Html::encode($p->product_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="quantity[]" class="form-control form-control-sm" min="1" value="1" required></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($existing as $ex): ?>
                            <tr>
                                <td>
                                    <select name="product_id[]" class="form-select form-select-sm" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= $p->id ?>" <?= $p->id == $ex->product_id ? 'selected' : '' ?>><?= Html::encode($p->product_name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="quantity[]" class="form-control form-control-sm" min="1" value="<?= $ex->quantity ?>" required></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fas fa-times"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addItem">+ Add Product</button>

            <div class="d-flex gap-2">
                <?= Html::submitButton($isNew ? 'Create Entry' : 'Save Changes', ['class' => 'btn btn-primary btn-sm px-4']) ?>
                <?= Html::a('Cancel', ['non-purchased'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
var productOptions = {$productOptionsJson};

function buildProductSelect(selectedId) {
    var html = '<select name="product_id[]" class="form-select form-select-sm" required><option value="">Select Product</option>';
    for (var id in productOptions) {
        var sel = (selectedId && id == selectedId) ? ' selected' : '';
        html += '<option value="' + id + '"' + sel + '>' + productOptions[id] + '</option>';
    }
    html += '</select>';
    return html;
}

document.getElementById('addItem').addEventListener('click', function() {
    document.getElementById('itemBody').insertAdjacentHTML('beforeend',
        '<tr>' +
        '<td>' + buildProductSelect(null) + '</td>' +
        '<td><input type="number" name="quantity[]" class="form-control form-control-sm" min="1" value="1" required></td>' +
        '<td><button type="button" class="btn btn-sm btn-outline-danger remove-item">\u2715</button></td>' +
        '</tr>'
    );
});

document.getElementById('itemBody').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-item')) {
        var rows = document.querySelectorAll('#itemBody tr');
        if (rows.length > 1) {
            if (confirm('Are you sure to delete this row?')) {
                e.target.closest('tr').remove();
            }
        }
    }
});

// Hide/Show product table toggle (matches Yii1)
document.getElementById('toggleProducts').addEventListener('click', function() {
    var table = document.getElementById('product-table');
    if (table.style.display === 'none') {
        table.style.display = '';
        this.textContent = 'Hide Product';
    } else {
        table.style.display = 'none';
        this.textContent = 'Show Product';
    }
});
JS, \yii\web\View::POS_READY);
?>
