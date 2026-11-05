<?php
/** @var \yii\web\View $this */
/** @var \backend\models\BankDetail $model */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Bank Account';
$isDefault = (int)$model->is_default === 1;
$used      = $model->getInvoiceCount();
?>
<div class="ui-page bank-detail-view" style="max-width:860px">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->bank_name) ?> <?php if ($isDefault): ?><span class="ui-pill tone-accent align-middle">Default</span><?php endif; ?></h1>
            <div class="ui-sub"><?= Html::encode($model->branch) ?> · used on <?= number_format($used) ?> invoice<?= $used == 1 ? '' : 's' ?></div>
        </div>
        <div class="ui-actions">
            <?php if (!$isDefault && UserAccess::checkAccess('bank-detail/set-default')): ?>
                <?= Html::a('Make default', ['set-default', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm',
                    'data' => ['method' => 'post', 'confirm' => 'Make ' . $model->bank_name . ' the default account for new invoices?']]) ?>
            <?php endif; ?>
            <?php if (UserAccess::checkAccess('bank-detail/update')): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">Account details</div>
        <table class="table ui-detail mb-0">
            <tr><th scope="row">Account name</th><td><?= Html::encode($model->acc_name) ?></td></tr>
            <tr><th scope="row">Account number</th><td class="mono fw-semibold"><?= Html::encode($model->acc_no) ?></td></tr>
            <tr><th scope="row">Bank</th><td><?= Html::encode($model->bank_name) ?></td></tr>
            <tr><th scope="row">Branch</th><td><?= Html::encode($model->branch) ?></td></tr>
            <tr><th scope="row">IFSC</th><td class="mono"><?= Html::encode($model->ifsc) ?></td></tr>
            <tr><th scope="row">SWIFT</th><td class="mono"><?= Html::encode($model->swift) ?></td></tr>
        </table>
    </div>

    <?php if (UserAccess::checkAccess('bank-detail/delete')): ?>
        <div class="mt-3 text-end">
            <?= Html::a('<i class="fas fa-trash me-1"></i>Remove account', ['delete', 'id' => $model->id], ['class' => 'btn btn-link btn-sm text-danger', 'encode' => false,
                'data' => ['method' => 'post', 'confirm' => 'Remove the ' . $model->bank_name . ' account (' . $model->acc_no . ')?'
                    . ($used ? ' It stays on the ' . $used . ' invoice' . ($used == 1 ? '' : 's') . ' that already print it, but cannot be picked for new ones.' : '')]]) ?>
        </div>
    <?php endif; ?>
</div>
