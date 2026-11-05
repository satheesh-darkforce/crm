<?php
/** @var \yii\web\View $this */
/** @var \backend\models\IotConsumption $model */
/** @var \backend\models\IotConsumptionProducts[] $items */

use backend\models\IotConsumption;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Issue Request #' . $model->id;

$cs        = (int)$model->consumption_status;
$requested = $cs === IotConsumption::STATUS_REQUESTED;
$issued    = $cs === IotConsumption::STATUS_ISSUED;
$fmt       = fn($v) => $v ? date('d M Y', strtotime($v)) : '—';
$fmtTime   = fn($v) => $v ? date('d M Y, h:i A', strtotime($v)) : '—';
?>
<div class="ui-page issue-view" style="max-width:960px">
    <div class="ui-head">
        <div>
            <h1>Issue Request #<?= (int)$model->id ?>
                <span class="ui-pill <?= $issued ? 'tone-success' : 'tone-warning' ?> align-middle"><?= $issued ? 'Issued' : 'Requested' ?></span>
            </h1>
            <div class="ui-sub"><?= Html::encode($model->developer->developer_name ?? '—') ?> · <?= Html::encode($model->project->title ?? 'No project') ?></div>
        </div>
        <div class="ui-actions">
            <?php if ($requested && UserAccess::checkAccess('iot-product/update-issue')): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update-issue', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if ($requested && UserAccess::checkAccess('iot-product/change-status')): ?>
                <button type="button" class="btn btn-primary btn-sm js-issue-now"><i class="fas fa-check me-1"></i>Mark as Issued</button>
            <?php endif; ?>
            <?php if ($issued && UserAccess::checkAccess('iot-product/create-return')): ?>
                <?= Html::a('<i class="fas fa-undo me-1"></i>Return products', ['create-return', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['issued'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Request</div>
        <table class="table ui-detail mb-0">
            <tr><th scope="row">Employee</th><td class="fw-semibold"><?= Html::encode($model->developer->developer_name ?? '—') ?></td></tr>
            <tr><th scope="row">Project</th><td><?= Html::encode($model->project->title ?? '—') ?></td></tr>
            <tr><th scope="row">Required on</th><td><?= $fmt($model->requested_date) ?></td></tr>
            <tr><th scope="row">Requested at</th><td><?= $fmtTime($model->created_at) ?></td></tr>
            <tr><th scope="row">Issued at</th><td><?= $fmtTime($model->approved_at) ?></td></tr>
        </table>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">Products</div>
        <table class="table ui-table stack mb-0">
            <thead>
                <tr>
                    <th class="cell-num">#</th>
                    <th>Product</th>
                    <th>Code</th>
                    <th class="text-end">Quantity</th>
                    <?php if ($issued): ?><th class="text-end">Returned</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td class="cell-num"><?= $i + 1 ?></td>
                    <td class="cell-title"><?= Html::encode($item->product->product_name ?? '—') ?></td>
                    <td data-label="Code" class="mono"><?= Html::encode($item->product->product_code ?? '—') ?></td>
                    <td data-label="Quantity" class="text-end fw-semibold"><?= (int)$item->quantity ?></td>
                    <?php if ($issued): ?><td data-label="Returned" class="text-end"><?= (int)($item->returned_quantity ?? 0) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr><td colspan="5" class="text-muted text-center py-4">No products on this request.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$changeStatusUrl = Url::to(['/iot-product/change-status']);
$id = (int)$model->id;
$this->registerJs(<<<JS
$(document).on('click', '.js-issue-now', function () {
    var btn = this;
    Swal.fire({
        icon: 'question',
        title: 'Mark as Issued?',
        text: 'The quantities will be taken out of stock.',
        showCancelButton: true,
        confirmButtonText: 'Yes, issue',
        cancelButtonText: 'Cancel'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        btn.disabled = true;
        $.post('{$changeStatusUrl}', { id: {$id}, status: 2 }, null, 'json').done(function (r) {
            if (r && r.status == 1) {
                Swal.fire({ icon: 'success', title: 'Issued', text: r.msg, timer: 1400, showConfirmButton: false })
                    .then(function () { location.reload(); });
            } else {
                btn.disabled = false;
                Swal.fire({ icon: 'error', title: 'Not issued', html: $('<div>').text((r && r.msg) || 'Unable to change status.').html().replace(/\\n/g, '<br>') });
            }
        }).fail(function () {
            btn.disabled = false;
            Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed. Please try again.' });
        });
    });
});
JS);
?>
