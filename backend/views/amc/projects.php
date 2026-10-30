<?php
/** @var \yii\web\View $this */
/** @var \yii\data\SqlDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $clientF */
/** @var string $titleF */

use common\components\UserAccess;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\LinkPager;

$this->title = 'AMC Projects';

$canView   = UserAccess::checkAccess('amc/view');
$canUpdate = UserAccess::checkAccess('amc/update');
$canStart  = UserAccess::checkAccess('amc/start');
$canRemove = UserAccess::checkAccess('amc/remove');

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="amc-projects">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">AMC Projects</h5>
            <small class="text-muted"><?= $total ?> completed project<?= $total != 1 ? 's' : '' ?> enrolled in AMC</small>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name' => 'client_id', 'value' => $clientF ?: null,
                        'data' => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options' => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <input type="text" name="title" value="<?= Html::encode($titleF) ?>" class="form-control form-control-sm" placeholder="Project title…">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm">Filter</button>
                    <?= Html::a('Reset', ['projects'], ['class' => 'btn btn-light btn-sm']) ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Client</th><th>Project</th>
                        <th class="text-end">Estimation Cost</th>
                        <th>AMC</th><th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No AMC projects found.</td></tr>
                <?php else: foreach ($rows as $i => $r):
                    $hasActive = (int)$r['active_amc'] > 0;
                ?>
                    <tr>
                        <td class="text-muted"><?= $pagination->getOffset() + $i + 1 ?></td>
                        <td><?= Html::encode($r['client_name'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= Html::encode($r['title']) ?></td>
                        <td class="text-end text-muted"><?= $r['total_estimation_cost'] ? '₹' . number_format((float)$r['total_estimation_cost'], 0) : '—' ?></td>
                        <td>
                            <?php if ($hasActive): ?>
                                <span class="badge bg-success-subtle text-success border">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Not started</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:170px">
                                    <?php if ($hasActive && $r['amc_id']): ?>
                                        <?php if ($canView): ?>
                                        <li><?= Html::a('<i class="fas fa-eye"></i> View AMC', ['view', 'id' => $r['amc_id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                        <?php endif; ?>
                                        <?php if ($canUpdate): ?>
                                        <li><?= Html::a('<i class="fas fa-pen"></i> Edit AMC', ['update', 'id' => $r['amc_id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                        <?php endif; ?>
                                    <?php elseif ($canStart): ?>
                                        <li><?= Html::a('<i class="fas fa-play"></i> Start AMC', ['start', 'project_id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canRemove): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-times"></i> Remove from AMC', ['remove', 'project_id' => $r['id']], ['class' => 'dropdown-item text-danger', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-end py-2">
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0']]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
