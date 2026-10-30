<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var string|null $search */

use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\helpers\Url;

$this->title = 'Salary Allowances';

$models = $dataProvider->getModels();
$total  = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="salary-allowances">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Manage Allowances</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('Deductions', ['deductions'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('+ Add Allowance', ['create-allowance'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <!-- Search -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="title" class="form-control form-control-sm"
                           placeholder="Search by title..." value="<?= Html::encode($search ?? '') ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" type="submit">Search</button>
                    <?php if ($search): ?>
                        <?= Html::a('Clear', ['allowances'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Grid -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Title</th>
                        <th>Value Type</th>
                        <th>Default Value</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No allowances found.</td></tr>
                <?php else: ?>
                    <?php
                    $page = $dataProvider->pagination->page;
                    $pageSize = $dataProvider->pagination->pageSize;
                    foreach ($models as $i => $item): ?>
                    <tr>
                        <td class="text-muted"><?= $page * $pageSize + $i + 1 ?></td>
                        <td class="fw-semibold"><?= Html::encode($item->title) ?></td>
                        <td><span class="badge bg-<?= $item->type2 == 1 ? 'info' : 'secondary' ?> text-white"><?= $item->getValueTypeLabel() ?></span></td>
                        <td><?= $item->type2 == 1 ? Html::encode($item->default_value) . '%' : '&#8377; ' . number_format((float)$item->default_value, 2) ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem">
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $item->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete', 'id' => $item->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-confirm' => 'Delete this allowance?', 'data-method' => 'post',
                                    ]) ?></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($dataProvider->totalCount > $dataProvider->pagination->pageSize): ?>
        <div class="card-footer d-flex justify-content-end py-2">
            <?= LinkPager::widget([
                'pagination' => $dataProvider->pagination,
                'options' => ['class' => 'pagination pagination-sm mb-0'],
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
