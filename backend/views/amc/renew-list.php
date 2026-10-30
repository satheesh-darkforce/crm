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

$this->title = 'Current Month AMC Renew';

$canView  = UserAccess::checkAccess('amc/view');
$canRenew = UserAccess::checkAccess('amc/renew');
$canEnd   = UserAccess::checkAccess('amc/end');

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$today      = strtotime(date('Y-m-d'));

$chargeVal = function (array $r): string {
    if ((int)$r['charge_type'] === 1) {
        return rtrim(rtrim((string)$r['percentage'], '0'), '.') . ' %';
    }
    return '₹' . number_format((float)$r['amount'], 2);
};
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="amc-renew-list">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Current Month AMC Renew</h5>
            <small class="text-muted"><?= $total ?> renewal<?= $total != 1 ? 's' : '' ?> due in <?= date('F Y') ?> (nearest first)</small>
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
                    <?= Html::a('Reset', ['renew-list'], ['class' => 'btn btn-light btn-sm']) ?>
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
                        <th>Start Date</th><th>Renew Due (End Date)</th>
                        <th>Charge</th><th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No renewals due this month.</td></tr>
                <?php else: foreach ($rows as $i => $r):
                    $end = !empty($r['current_end_date']) ? strtotime($r['current_end_date']) : null;
                    $overdue = $end !== null && $end < $today;
                ?>
                    <tr>
                        <td class="text-muted"><?= $pagination->getOffset() + $i + 1 ?></td>
                        <td><?= Html::encode($r['client_name'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= Html::encode($r['title'] ?? '—') ?></td>
                        <td><?= $r['start_date'] ? date('d-m-Y', strtotime($r['start_date'])) : '—' ?></td>
                        <td>
                            <?= $end ? date('d-m-Y', $end) : '—' ?>
                            <?php if ($overdue): ?>
                                <span class="badge bg-danger-subtle text-danger border ms-1">Overdue</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= (int)$r['charge_type'] === 1 ? 'Percentage' : 'Amount' ?></span>
                            <span class="ms-1"><?= $chargeVal($r) ?></span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <?php if ($canRenew): ?>
                                    <li><?= Html::a('<i class="fas fa-sync"></i> Renew', ['renew', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canView): ?>
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canEnd): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-stop"></i> End AMC', ['end', 'id' => $r['id']], ['class' => 'dropdown-item text-danger', 'encode' => false]) ?></li>
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
