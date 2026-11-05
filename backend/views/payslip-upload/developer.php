<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\widgets\LinkPager;
use common\components\UserAccess;

$this->title = 'My Payslips';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>

<div class="ui-page payslip-developer-index">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Month</th>
                        <th class="text-end" style="width:120px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-5">No payslips found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $m): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold small">
                            <?= !empty($m->month_year) ? date('M - Y', strtotime($m->month_year)) : '-' ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <?php if (UserAccess::checkAccess('payslip-upload/view-payslip')): ?>
                                    <?= Html::a('<i class="fas fa-eye"></i> View', ['view-payslip', 'id' => $m->id], ['class' => 'btn btn-outline-primary act-btn', 'encode' => false, 'target' => '_blank']) ?>
                                <?php endif; ?>
                                <?php if (UserAccess::checkAccess('payslip-upload/download-payslip')): ?>
                                    <?= Html::a('<i class="fas fa-arrow-right"></i> Download', ['download-payslip', 'id' => $m->id], ['class' => 'btn btn-outline-success act-btn', 'encode' => false]) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>-<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '<<', 'lastPageLabel' => '>>', 'prevPageLabel' => '<', 'nextPageLabel' => '>', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
