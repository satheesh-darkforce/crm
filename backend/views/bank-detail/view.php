<?php
/** @var \yii\web\View $this */
/** @var \backend\models\BankDetail $model */

use yii\bootstrap5\Html;

$this->title = $model->bank_name . ' — ' . $model->acc_name;
?>
<div class="bank-detail-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">
            <?= Html::encode($model->bank_name) ?>
            <?php if ($model->is_default): ?>
                <span class="badge bg-success ms-2" style="font-size:.7rem">Default</span>
            <?php endif; ?>
        </h5>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0" style="max-width:600px">
                <tr>
                    <th class="text-muted" style="width:180px">Account Name</th>
                    <td><?= Html::encode($model->acc_name) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Account Number</th>
                    <td class="font-monospace"><?= Html::encode($model->acc_no) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Bank Name</th>
                    <td><?= Html::encode($model->bank_name) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Branch</th>
                    <td><?= Html::encode($model->branch) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">IFSC Code</th>
                    <td class="font-monospace"><?= Html::encode($model->ifsc) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">SWIFT Code</th>
                    <td class="font-monospace"><?= Html::encode($model->swift ?: '—') ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Default Account</th>
                    <td>
                        <?php if ($model->is_default): ?>
                            <span class="badge bg-success">Yes</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">No</span>
                            <?= Html::a('Set as Default', ['set-default', 'id' => $model->id], [
                                'class' => 'btn btn-xs btn-outline-success btn-sm ms-2',
                                'data-method' => 'post',
                                'data-confirm' => 'Set this as the default bank account?',
                            ]) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
