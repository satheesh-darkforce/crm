<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var string|null $search */

use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;

$this->title = 'Salary Deductions';

$models = $dataProvider->getModels();
$total  = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="salary-deductions">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Manage Deductions</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('Earnings Split', ['earnings-split'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('+ Add Deduction', ['create-deduction'], ['class' => 'btn btn-primary btn-sm']) ?>
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
                <div class="col-md-3">
                    <select name="type2" class="form-select form-select-sm">
                        <option value="">— All Value Types —</option>
                        <option value="1" <?= ($type2 ?? '') == '1' ? 'selected' : '' ?>>Percentage (%)</option>
                        <option value="2" <?= ($type2 ?? '') == '2' ? 'selected' : '' ?>>Fixed Amount</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" type="submit">Search</button>
                    <?php if ($search || ($type2 ?? '')): ?>
                        <?= Html::a('Clear', ['deductions'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Value</th>
                        <th>Employee %</th>
                        <th>Employer %</th>
                        <th>Admin %</th>
                        <th>Ceiling (₹)</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No deductions found.</td></tr>
                <?php else: ?>
                    <?php
                    $page = $dataProvider->pagination->page;
                    $pageSize = $dataProvider->pagination->pageSize;
                    foreach ($models as $i => $item): ?>
                    <tr>
                        <td class="text-muted"><?= $page * $pageSize + $i + 1 ?></td>
                        <td class="fw-semibold"><?= Html::encode($item->title) ?></td>
                        <td><span class="badge bg-<?= $item->type2 == 1 ? 'warning' : 'secondary' ?> text-white"><?= $item->getValueTypeLabel() ?></span></td>
                        <td><?php
                            if ($item->employee_value !== null || $item->employer_value !== null) {
                                echo '<span class="text-muted">—</span>'; // PF/ESI use the split columns
                            } elseif ($item->default_value === null) {
                                echo '<span class="text-muted">—</span>';
                            } else {
                                echo $item->type2 == 1 ? Html::encode($item->default_value) . '%' : '&#8377; ' . number_format((float)$item->default_value, 2);
                            }
                        ?></td>
                        <td><?= $item->employee_value !== null ? Html::encode($item->employee_value) . '%' : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $item->employer_value !== null ? Html::encode($item->employer_value) . '%' : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $item->admin_charge !== null ? Html::encode($item->admin_charge) . '%' : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $item->wage_ceiling !== null ? '&#8377; ' . number_format((float)$item->wage_ceiling, 0) : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem">
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $item->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete', 'id' => $item->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-confirm' => 'Delete this deduction?', 'data-method' => 'post',
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
