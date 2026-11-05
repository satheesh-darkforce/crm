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

$this->title = 'AMC Due This Month';

$can = [
    'view'  => UserAccess::checkAccess('amc/view'),
    'renew' => UserAccess::checkAccess('amc/renew'),
    'end'   => UserAccess::checkAccess('amc/end'),
];
$rows        = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination ? $pagination->getOffset() : 0;
$today       = date('Y-m-d');
$activeCount = count(array_filter([$clientF, $titleF], fn($v) => $v !== ''));
$charge = fn(array $r): string => (int)$r['charge_type'] === 1
    ? rtrim(rtrim((string)$r['percentage'], '0'), '.') . '% of cost'
    : '₹' . number_format((float)$r['amount'], 2) . ' fixed';
?>
<div class="ui-page amc-renew-list">
    <div class="ui-head">
        <div>
            <h1>AMC due this month</h1>
            <div class="ui-sub"><?= number_format($total) ?> contract<?= $total == 1 ? '' : 's' ?> ending in <?= date('F Y') ?> · soonest first</div>
        </div>
    </div>

    <?= $this->render('_tabs', ['active' => 'renew-list']) ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" for="rl-client">Client</label>
                    <?= Select2::widget([
                        'name' => 'client_id', 'value' => $clientF ?: null,
                        'data' => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options' => ['placeholder' => 'All clients', 'id' => 'rl-client'],
                        'size' => Select2::SMALL,
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" for="rl-title">Project</label>
                    <input type="text" id="rl-title" name="title" value="<?= Html::encode($titleF) ?>" class="form-control form-control-sm" maxlength="100" placeholder="Project title">
                </div>
                <div class="col-lg-2 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                    <?php if ($activeCount): ?><?= Html::a('Reset', ['renew-list'], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Project</th><th>Contract start</th><th>Renewal due</th><th>Charge</th><th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="6" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $activeCount
                            ? ['icon' => 'search', 'title' => 'No renewals match your search', 'action' => Html::a('Clear search', ['renew-list'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'calendar-check', 'title' => 'Nothing due this month', 'text' => 'Contracts ending in ' . date('F') . ' appear here.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $r): ?>
                    <?php $end = $r['current_end_date'] ?: null; $overdue = $end && $end < $today; $title = (string)($r['title'] ?? '—'); ?>
                    <tr class="<?= $overdue ? 'is-overdue' : '' ?>">
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap"><?= Html::encode($title) ?><div class="sub"><?= Html::encode($r['client_name'] ?? 'No client') ?></div></td>
                        <td class="small nowrap" data-label="Contract start"><?= $r['start_date'] ? date('d M Y', strtotime($r['start_date'])) : '—' ?></td>
                        <td class="small nowrap" data-label="Renewal due">
                            <?= $end ? date('d M Y', strtotime($end)) : '—' ?>
                            <?php if ($overdue): ?><span class="ui-pill plain tone-danger ms-1">Overdue</span><?php elseif ($end === $today): ?><span class="ui-pill plain tone-warning ms-1">Today</span><?php endif; ?>
                        </td>
                        <td class="small" data-label="Charge"><?= $charge($r) ?></td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                        data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions for <?= Html::encode($title) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:170px">
                                    <?php if ($can['renew']): ?><li><?= Html::a('<i class="fas fa-sync me-2"></i>Renew', ['renew', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['view', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['end']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-stop me-2"></i>End AMC', ['end', 'id' => $r['id']], [
                                            'class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data' => ['method' => 'post', 'confirm' => 'End the AMC for "' . $title . '"? It moves to Ended AMC and can no longer be renewed or edited.'],
                                        ]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows)]) ?>
    </div>
</div>
