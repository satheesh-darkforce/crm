<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use backend\models\BankDetail;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Bank Details';

$banks = $dataProvider->getModels();
$total = $dataProvider->getTotalCount();
$can = [
    'create'  => UserAccess::checkAccess('bank-detail/create'),
    'view'    => UserAccess::checkAccess('bank-detail/view'),
    'update'  => UserAccess::checkAccess('bank-detail/update'),
    'default' => UserAccess::checkAccess('bank-detail/set-default'),
    'delete'  => UserAccess::checkAccess('bank-detail/delete'),
];
$hasDefault = (bool)array_filter($banks, fn(BankDetail $b) => (int)$b->is_default === 1);
?>
<div class="ui-page bank-detail-index">
    <div class="ui-head">
        <div>
            <h1>Payment bank accounts</h1>
            <div class="ui-sub"><?= $total ?> account<?= $total == 1 ? '' : 's' ?> · shown on invoices under "Payment bank info"</div>
        </div>
        <div class="ui-actions">
            <?php if ($can['create']): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add account', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($banks && !$hasDefault): ?>
        <div class="alert alert-warning small">No default account yet. Mark one as default so new invoices pick it automatically.</div>
    <?php endif; ?>

    <?php if (!$banks): ?>
        <div class="card border-0 shadow-sm">
            <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'university', 'title' => 'No bank accounts yet',
                'text' => 'Add the account clients should pay into; it prints on every invoice.',
                'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add account', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($banks as $b): /** @var BankDetail $b */ $isDefault = (int)$b->is_default === 1; ?>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm bank-card<?= $isDefault ? ' is-default' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <h2 class="h6 fw-bold mb-0"><?= Html::encode($b->bank_name) ?></h2>
                                    <div class="small text-muted"><?= Html::encode($b->branch) ?></div>
                                </div>
                                <?php if ($isDefault): ?><span class="ui-pill tone-accent">Default</span><?php endif; ?>
                            </div>
                            <dl>
                                <dt>Account name</dt><dd><?= Html::encode($b->acc_name) ?></dd>
                                <dt>Account number</dt><dd class="mono fw-semibold"><?= Html::encode($b->acc_no) ?></dd>
                                <dt>IFSC</dt><dd class="mono"><?= Html::encode($b->ifsc) ?></dd>
                                <dt>SWIFT</dt><dd class="mono"><?= Html::encode($b->swift) ?></dd>
                            </dl>
                            <div class="bank-actions">
                                <?php if ($can['view']): ?><?= Html::a('View', ['view', 'id' => $b->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?><?php endif; ?>
                                <?php if ($can['update']): ?><?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $b->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?><?php endif; ?>
                                <?php if ($can['default'] && !$isDefault): ?>
                                    <?= Html::a('Make default', ['set-default', 'id' => $b->id], ['class' => 'btn btn-outline-primary btn-sm',
                                        'data' => ['method' => 'post', 'confirm' => 'Make ' . $b->bank_name . ' the default account for new invoices?']]) ?>
                                <?php endif; ?>
                                <?php if ($can['delete']): $used = $b->getInvoiceCount(); ?>
                                    <?= Html::a('<i class="fas fa-trash me-1"></i>Remove', ['delete', 'id' => $b->id], ['class' => 'btn btn-outline-danger btn-sm ms-auto', 'encode' => false,
                                        'data' => ['method' => 'post', 'confirm' => 'Remove the ' . $b->bank_name . ' account (' . $b->acc_no . ')?'
                                            . ($used ? ' It stays on the ' . $used . ' invoice' . ($used == 1 ? '' : 's') . ' that already print it, but cannot be picked for new ones.' : '')]]) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $dataProvider->getPagination(), 'total' => $total, 'count' => count($banks), 'noun' => 'accounts']) ?>
    <?php endif; ?>
</div>
