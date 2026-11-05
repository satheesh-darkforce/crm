<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var string|null $monthYear */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use kartik\date\DatePicker;
use common\components\UserAccess;

$this->title = 'Manage Payslips';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>

<div class="ui-page payslip-upload-index">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ui-sub"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('payslip-upload/create')): ?>
            <?= Html::a('+ Upload Payslip', ['create'], ['class' => 'btn btn-danger btn-sm']) ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- Month Filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1 small">Month &amp; Year</label>
                    <?= DatePicker::widget([
                        'name'          => 'month_year',
                        'value'         => $monthYear ?? '',
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'Select Month & Year', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => [
                            'autoclose'      => true,
                            'format'         => 'yyyy-mm',
                            'startView'      => 'months',
                            'minViewMode'    => 'months',
                            'endDate'        => date('Y-m', strtotime('-1 month')),
                        ],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm">Search</button>
                    <?php if (!empty($monthYear)): ?>
                        <?= Html::a('Reset', ['index'], ['class' => 'btn btn-danger btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Month</th>
                        <th class="text-end" style="width:90px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-5">No payslip uploads found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $m): ?>
                    <?php $rowIndex = $pagination ? ($pagination->getOffset() + $i) : $i; ?>
                    <tr>
                        <td class="text-muted"><?= $rowIndex + 1 ?></td>
                        <td class="fw-semibold small">
                            <?= !empty($m->month_year) ? date('M - Y', strtotime($m->month_year)) : '-' ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <?php if (UserAccess::checkAccess('payslip-upload/view')): ?>
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>

                                    <?php if ($m->file_path): ?>
                                    <li><?= Html::a('<i class="fas fa-arrow-right"></i> Download', ['download', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>

                                    <?php if ($rowIndex < 2 && UserAccess::checkAccess('payslip-upload/delete')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $m->id], [
                                        'class'        => 'dropdown-item text-danger',
                                        'encode'       => false,
                                        'data-confirm' => 'Are you sure you want to delete this payslip?',
                                        'data-method'  => 'post',
                                    ]) ?></li>
                                    <?php endif; ?>
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
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>-<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '<<', 'lastPageLabel' => '>>', 'prevPageLabel' => '<', 'nextPageLabel' => '>', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
