<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\widgets\LinkPager;

$this->title = 'Bank Details';

$banks      = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="bank-detail-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Bank Accounts</h5>
            <small class="text-muted"><?= $total ?> account<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Account', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <?php if (empty($banks)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">No bank accounts added yet.</div>
        </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($banks as $b): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100 <?= $b->is_default ? 'border-primary border-2' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="fw-bold mb-0"><?= Html::encode($b->bank_name) ?></h6>
                            <small class="text-muted"><?= Html::encode($b->branch) ?></small>
                        </div>
                        <div class="d-flex gap-1 align-items-center">
                            <?php if ($b->is_default): ?>
                                <span class="badge bg-primary">Default</span>
                            <?php else: ?>
                                <?= Html::a('Set Default', ['set-default', 'id' => $b->id], [
                                    'class' => 'btn btn-outline-primary btn-sm',
                                    'data-method' => 'post', 'data-confirm' => 'Set as default account?',
                                ]) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <table class="table table-sm table-borderless mb-2">
                        <tr><th class="text-muted" style="width:40%">A/c Name</th><td><?= Html::encode($b->acc_name) ?></td></tr>
                        <tr><th class="text-muted">A/c Number</th><td class="fw-semibold"><?= Html::encode($b->acc_no) ?></td></tr>
                        <tr><th class="text-muted">IFSC</th><td><?= Html::encode($b->ifsc) ?></td></tr>
                        <?php if ($b->swift): ?>
                        <tr><th class="text-muted">SWIFT</th><td><?= Html::encode($b->swift) ?></td></tr>
                        <?php endif; ?>
                    </table>
                    <div class="d-flex gap-2">
                        <?= Html::a('View', ['view', 'id' => $b->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                        <?= Html::a('Edit', ['update', 'id' => $b->id], ['class' => 'btn btn-outline-warning btn-sm']) ?>
                        <?= Html::a('Delete', ['delete', 'id' => $b->id], [
                            'class' => 'btn btn-outline-danger btn-sm',
                            'data-method' => 'post', 'data-confirm' => 'Delete this bank account?',
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($pagination && $pagination->pageCount > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
        <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
            'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
