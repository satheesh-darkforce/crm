<?php
/** @var \yii\web\View $this */
/** @var \backend\models\IotConsumption $model */
/** @var \backend\models\IotConsumptionProducts[] $items */

use yii\bootstrap5\Html;

$this->title = 'Issue Request #' . $model->id;

$statusBadge = [0 => 'warning', 1 => 'success', 2 => 'danger'];
$statusLabel = [0 => 'Pending', 1 => 'Approved', 2 => 'Rejected'];
$cs = (int)$model->consumption_status;
?>
<div class="issue-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Issue Request #<?= $model->id ?></h5>
        <div class="d-flex gap-2">
            <?php if ($cs == 0): ?>
                <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update-issue', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
                <button class="btn btn-success btn-sm" onclick="changeStatus(<?= $model->id ?>, 1)"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-danger btn-sm"  onclick="changeStatus(<?= $model->id ?>, 2)"><i class="fas fa-times"></i> Reject</button>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['issued'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <dl class="row mb-0" style="font-size:.875rem">
                        <dt class="col-sm-4 text-muted">Developer</dt>
                        <dd class="col-sm-8 fw-semibold"><?= Html::encode($model->developer->developer_name ?? '—') ?></dd>
                        <dt class="col-sm-4 text-muted">Project</dt>
                        <dd class="col-sm-8"><?= Html::encode($model->project->title ?? '—') ?></dd>
                        <dt class="col-sm-4 text-muted">Requested</dt>
                        <dd class="col-sm-8"><?= $model->requested_date ? date('d M Y h:i A', strtotime($model->requested_date)) : '—' ?></dd>
                        <dt class="col-sm-4 text-muted">Approved</dt>
                        <dd class="col-sm-8"><?= $model->approved_at ? date('d M Y h:i A', strtotime($model->approved_at)) : '—' ?></dd>
                        <dt class="col-sm-4 text-muted">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= $statusBadge[$cs] ?? 'secondary' ?>"><?= $statusLabel[$cs] ?? '—' ?></span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold py-2">Products</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>Product Code</th>
                        <th class="text-center">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td><?= Html::encode($item->product->product_name ?? '—') ?></td>
                    <td><span class="badge bg-light text-dark border font-monospace"><?= Html::encode($item->product->product_code ?? '—') ?></span></td>
                    <td class="text-center fw-semibold"><?= $item->quantity ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4" class="text-muted text-center py-3">No products.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$changeStatusUrl = \yii\helpers\Url::to(['/iot-product/change-status']);
$this->registerJs(<<<JS
window.changeStatus = function(id, status) {
    var label = status == 1 ? 'approve' : 'reject';
    Swal.fire({
        icon: 'question',
        title: 'Confirm',
        text: 'Are you sure you want to ' + label + ' this request?',
        showCancelButton: true,
        confirmButtonText: 'Yes, ' + label,
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        jQuery.ajax({
            url: '{$changeStatusUrl}',
            type: 'POST',
            data: {'{$csrfParam}': '{$csrfToken}', id: id, status: status},
            dataType: 'json',
            success: function(data) {
                if (data.status == 1) {
                    Swal.fire({icon: 'success', title: 'Success', text: data.msg, timer: 1500, showConfirmButton: false}).then(function() { location.reload(); });
                } else {
                    Swal.fire({icon: 'error', title: 'Error', text: data.msg});
                }
            },
            error: function() {
                Swal.fire({icon: 'error', title: 'Error', text: 'Request failed. Please try again.'});
            }
        });
    });
};
JS, \yii\web\View::POS_READY);
?>
