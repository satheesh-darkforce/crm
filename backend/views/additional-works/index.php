<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer[] $developers */

use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\LinkPager;
use kartik\select2\Select2;

$this->title = 'Additional Works';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="additional-works-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Additional Works</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Record', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => Yii::$app->request->get('developer_id'),
                        'data'          => ['' => 'All Employees'] + ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('developer_id')): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th class="text-center">Hours</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No additional work records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $m): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold small"><?= Html::encode($m->developer->developer_name ?? '—') ?></td>
                        <td class="text-muted small"><?= $m->date ? date('d M Y', strtotime($m->date)) : '—' ?></td>
                        <td class="small" style="max-width:300px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= Html::encode($m->description ?? '') ?>">
                                <?= Html::encode($m->description ?? '—') ?>
                            </div>
                        </td>
                        <td class="text-center fw-semibold small"><?= $m->hours ?? '—' ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:130px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $m->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-method' => 'post', 'data-confirm' => 'Delete this record?',
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
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
