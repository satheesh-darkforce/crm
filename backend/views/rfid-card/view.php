<?php
/** @var \yii\web\View $this */
/** @var \backend\models\RfidCard $model */

use yii\bootstrap5\Html;

$this->title = 'RFID Card — ' . $model->code;
?>
<div class="rfid-card-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold font-monospace"><?= Html::encode($model->code) ?></h5>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-users"></i> Assign', ['assign', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-warning', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0" style="max-width:500px">
                <tr>
                    <th class="text-muted" style="width:160px">RFID Code</th>
                    <td class="font-monospace fw-semibold"><?= Html::encode($model->code) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Status</th>
                    <td>
                        <span class="badge bg-<?= $model->status ? 'success' : 'danger' ?>">
                            <?= $model->status ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th class="text-muted">Assigned To</th>
                    <td>
                        <?php if ($model->developer): ?>
                            <span class="badge bg-success"><?= Html::encode($model->developer->developer_name ?? '—') ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Unassigned</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th class="text-muted">Assigned From</th>
                    <td class="text-muted"><?= $model->assigned_from ? date('d M Y H:i', strtotime($model->assigned_from)) : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Created At</th>
                    <td class="text-muted"><?= $model->created_at ? date('d M Y H:i', strtotime($model->created_at)) : '—' ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
