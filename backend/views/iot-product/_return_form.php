<?php
/**
 * Return Product form — ports Yii1 _return_form.php.
 *
 * @var \yii\web\View                        $this
 * @var \backend\models\IotConsumption        $model
 * @var \backend\models\IotConsumptionProducts[] $items
 * @var \backend\models\Project[]             $projects
 * @var \backend\models\Developer[]           $developers
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Return Product #' . $model->id;

$reqDate = $model->requested_date ? date('Y-m-d', strtotime($model->requested_date)) : date('Y-m-d');
?>

<div class="ui-page iot-return-form">
    <div class="ui-head">
        <div>
            <h1>Return Product</h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['issued'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="post" id="iot-return-form">
                <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->getCsrfToken() ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Project</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= Html::encode($model->project->title ?? '—') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Developer</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= Html::encode($model->developer->developer_name ?? '—') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Requested Date</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= Html::encode($reqDate) ?>">
                    </div>
                </div>

                <h6 class="fw-semibold mt-2 mb-2">Return Products</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 return-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Product</th>
                                <th class="text-center" style="width:140px">Issued Qty</th>
                                <th class="text-center" style="width:170px">Previously Returned</th>
                                <th class="text-center" style="width:150px">Return Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No products in this request.</td></tr>
                        <?php else: foreach ($items as $i => $row):
                            $issued    = (int)$row->quantity;
                            $returned  = (int)($row->returned_quantity ?? 0);
                            $remaining = max(0, $issued - $returned);
                        ?>
                            <tr data-remaining="<?= $remaining ?>">
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <?= Html::encode($row->product->product_name ?? ('ID:' . $row->product_id)) ?>
                                    <input type="hidden" name="product_id[]" value="<?= (int)$row->product_id ?>">
                                </td>
                                <td class="text-center"><?= $issued ?></td>
                                <td class="text-center"><?= $returned ?></td>
                                <td class="text-center">
                                    <input type="number" class="form-control form-control-sm text-center return-qty"
                                           name="quantity[]" min="0" max="<?= $remaining ?>"
                                           value="0" <?= $remaining <= 0 ? 'readonly' : '' ?>>
                                    <?php if ($remaining <= 0): ?>
                                        <small class="text-muted">Fully returned</small>
                                    <?php else: ?>
                                        <small class="text-muted">Max <?= $remaining ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                    <?= Html::a('Cancel', ['issued'], ['class' => 'btn btn-secondary btn-sm ms-1']) ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<'JS'
(function () {
    var form = document.getElementById('iot-return-form');
    if (!form) return;

    form.querySelectorAll('.return-qty').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var row = inp.closest('tr');
            var max = parseInt(row.getAttribute('data-remaining'), 10) || 0;
            var val = parseInt(inp.value, 10);
            if (isNaN(val) || val < 0) { inp.value = 0; return; }
            if (val > max) {
                Swal.fire({ icon: 'warning', title: 'Too many', text: 'Return quantity cannot exceed issued (max ' + max + ').' });
                inp.value = max;
            }
        });
    });

    form.addEventListener('submit', function (e) {
        var any = false;
        form.querySelectorAll('.return-qty').forEach(function (inp) {
            if ((parseInt(inp.value, 10) || 0) > 0) any = true;
        });
        if (!any) {
            e.preventDefault();
            Swal.fire({icon:'warning', title:'Required', text:'Please enter a valid quantity'});
        }
    });
}());
JS, \yii\web\View::POS_READY);
?>
