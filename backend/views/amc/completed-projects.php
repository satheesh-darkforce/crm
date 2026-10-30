<?php
/** @var \yii\web\View $this */
/** @var \yii\data\SqlDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $clientF */
/** @var string $titleF */
/** @var string $amcF */

use common\components\UserAccess;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\LinkPager;

$this->title = 'Completed Projects';

$canView  = UserAccess::checkAccess('amc/view');
$canStart = UserAccess::checkAccess('amc/start');

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

<div class="amc-completed-projects">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Completed Projects</h5>
            <small class="text-muted"><?= $total ?> completed project<?= $total != 1 ? 's' : '' ?></small>
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
                <div class="col-md-2">
                    <select name="amc" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="1" <?= $amcF === '1' ? 'selected' : '' ?>>Under AMC</option>
                        <option value="0" <?= $amcF === '0' ? 'selected' : '' ?>>Not under AMC</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm">Filter</button>
                    <?= Html::a('Reset', ['completed-projects'], ['class' => 'btn btn-light btn-sm']) ?>
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
                    <tr><td colspan="6" class="text-center text-muted py-5">No completed projects found.</td></tr>
                <?php else: foreach ($rows as $i => $r):
                    $hasActive = (int)$r['active_amc'] > 0;
                    $enrolled  = (int)$r['amc'] === 1;
                ?>
                    <tr>
                        <td class="text-muted"><?= $pagination->getOffset() + $i + 1 ?></td>
                        <td><?= Html::encode($r['client_name'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= Html::encode($r['title']) ?></td>
                        <td class="text-end text-muted"><?= $r['total_estimation_cost'] ? '₹' . number_format((float)$r['total_estimation_cost'], 0) : '—' ?></td>
                        <td>
                            <?php if ($hasActive): ?>
                                <span class="badge bg-success-subtle text-success border">Under AMC</span>
                            <?php elseif ($enrolled): ?>
                                <span class="badge bg-warning-subtle text-warning border">Enrolled</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($hasActive && $r['amc_id']): ?>
                                <?php if ($canView): ?>
                                <?= Html::a('<i class="fas fa-eye"></i> View AMC', ['view', 'id' => $r['amc_id']], ['class' => 'btn btn-outline-success btn-sm', 'encode' => false]) ?>
                                <?php endif; ?>
                            <?php elseif ($canStart): ?>
                                <?= Html::a('<i class="fas fa-play"></i> Start AMC', ['start', 'project_id' => $r['id']], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
                            <?php endif; ?>
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
