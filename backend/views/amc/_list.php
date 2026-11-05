<?php
/** @var \yii\web\View $this */
/** @var \yii\data\SqlDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $clientF */
/** @var string $titleF */
/** @var string $typeF */
/** @var string $warrantyF */
/** @var string $stateF */
/** @var string $startF */
/** @var string $endF */
/** @var string $heading */
/** @var array  $resetUrl */
/** @var string $mode  'active' | 'ended' */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$can = [
    'view'   => UserAccess::checkAccess('amc/view'),
    'update' => $mode === 'active' && UserAccess::checkAccess('amc/update'),
    'renew'  => $mode === 'active' && UserAccess::checkAccess('amc/renew'),
    'end'    => $mode === 'active' && UserAccess::checkAccess('amc/end'),
];

$rows        = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination ? $pagination->getOffset() : 0;
$today       = date('Y-m-d');
$filters     = $mode === 'active' ? [$clientF, $titleF, $typeF, $warrantyF, $stateF, $startF, $endF] : [$clientF, $titleF];
$activeCount = count(array_filter($filters, fn($v) => $v !== ''));
$hasFilters  = $activeCount > 0;

$charge = fn(array $r): string => (int)$r['charge_type'] === 1
    ? rtrim(rtrim((string)$r['percentage'], '0'), '.') . '%'
    : '₹' . number_format((float)$r['amount'], 2);

// Warranty: No Warranty (period 0) / Under Warranty / Warranty Ended.
$warranty = function (array $r) use ($today): array {
    if ((int)$r['warranty_period'] === 0) { return ['No warranty', 'neutral']; }
    return (!empty($r['end_date']) && $r['end_date'] >= $today) ? ['Under warranty', 'success'] : ['Warranty ended', 'warning'];
};

// Current AMC period: the latest renewal if renewed; otherwise it starts after the
// warranty (or at the start date with no warranty) and has no end until renewed.
$period = function (array $r): array {
    if (!empty($r['renew_end'])) { return [$r['renew_start'], $r['renew_end']]; }
    $start = (int)$r['warranty_period'] === 0 ? $r['start_date'] : ($r['end_date'] ? date('Y-m-d', strtotime($r['end_date'] . ' +1 day')) : null);
    return [$start, null];
};
$d = fn($v) => $v ? date('d M Y', strtotime($v)) : '—';
?>
<div class="ui-page amc-list">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($heading) ?></h1>
            <div class="ui-sub"><?= number_format($total) ?> contract<?= $total == 1 ? '' : 's' ?><?= $hasFilters ? ' matching your search' : '' ?></div>
        </div>
        <div class="ui-actions">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#amc-search"
                    aria-expanded="<?= $hasFilters ? 'true' : 'false' ?>" aria-controls="amc-search">
                <i class="fas fa-search me-1"></i>Search<?php if ($hasFilters): ?> <span class="ui-count ms-1"><?= $activeCount ?></span><?php endif; ?>
            </button>
        </div>
    </div>

    <?= $this->render('_tabs', ['active' => $mode]) ?>

    <div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="amc-search">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small mb-1" for="amc-f-client">Client</label>
                        <?= Select2::widget([
                            'name' => 'client_id', 'value' => $clientF ?: null,
                            'data' => ArrayHelper::map($clients, 'id', 'client_name'),
                            'options' => ['placeholder' => 'All clients', 'id' => 'amc-f-client'],
                            'size' => Select2::SMALL,
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small mb-1" for="amc-f-title">Project</label>
                        <input type="text" id="amc-f-title" name="title" value="<?= Html::encode($titleF) ?>" class="form-control form-control-sm" maxlength="100" placeholder="Project title">
                    </div>
                    <?php if ($mode === 'active'): ?>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small mb-1" for="amc-f-type">Charge type</label>
                        <?= Html::dropDownList('charge_type', $typeF, [1 => 'Percentage', 2 => 'Amount'], ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'amc-f-type']) ?>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small mb-1" for="amc-f-warranty">Warranty</label>
                        <?= Html::dropDownList('warranty', $warrantyF, ['under' => 'Under warranty', 'ended' => 'Warranty ended', 'none' => 'No warranty'], ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'amc-f-warranty']) ?>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small mb-1" for="amc-f-state">State</label>
                        <?= Html::dropDownList('state', $stateF, ['active' => 'Running', 'overdue' => 'Overdue'], ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'amc-f-state']) ?>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small mb-1" for="amc-f-start">Period starts from</label>
                        <?= DatePicker::widget([
                            'name' => 'amc_start', 'value' => $startF, 'type' => DatePicker::TYPE_INPUT,
                            'options' => ['id' => 'amc-f-start', 'class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off', 'data-range-from' => '#amc-f-end'],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'clearBtn' => true, 'todayHighlight' => true],
                        ]) ?>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small mb-1" for="amc-f-end">Period ends by</label>
                        <?= DatePicker::widget([
                            'name' => 'amc_end', 'value' => $endF, 'type' => DatePicker::TYPE_INPUT,
                            'options' => ['id' => 'amc-f-end', 'class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off', 'data-range-to' => '#amc-f-start'],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'clearBtn' => true, 'todayHighlight' => true],
                        ]) ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-lg-2 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                        <?= Html::a('Reset', $resetUrl, ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Project</th><th>Warranty</th><th>Current period</th><th>Charge</th><th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="6" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilters
                            ? ['icon' => 'search', 'title' => 'No contracts match your search', 'text' => 'Try fewer or different search terms.',
                               'action' => Html::a('Clear search', $resetUrl, ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'file-contract', 'title' => $mode === 'active' ? 'No active AMC contracts' : 'No ended AMC contracts',
                               'text' => $mode === 'active' ? 'Start an AMC from a completed project.' : 'Contracts you end are listed here.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $r): ?>
                    <?php
                    [$wLabel, $wTone] = $warranty($r);
                    [$pStart, $pEnd]  = $period($r);
                    $overdue = $pEnd && $pEnd < $today;
                    $title   = (string)($r['title'] ?? '—');
                    ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap">
                            <?= $can['view'] ? Html::a(Html::encode($title), ['view', 'id' => $r['id']], ['class' => 'text-reset text-decoration-none']) : Html::encode($title) ?>
                            <div class="sub"><?= Html::encode($r['client_name'] ?? 'No client') ?></div>
                        </td>
                        <td data-label="Warranty"><span class="ui-pill tone-<?= $wTone ?>"><?= $wLabel ?></span></td>
                        <td class="small" data-label="Current period">
                            <?= $d($pStart) ?> →
                            <?php if ($pEnd): ?>
                                <?= $d($pEnd) ?><?php if ($overdue && $mode === 'active'): ?> <span class="ui-pill plain tone-danger ms-1">Overdue</span><?php endif; ?>
                            <?php elseif ($wLabel === 'Under warranty'): ?>
                                <span class="ui-pill plain tone-success">After warranty</span>
                            <?php else: ?>
                                <span class="ui-pill plain tone-warning">Needs renewal</span>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap" data-label="Charge"><?= $charge($r) ?> <span class="small muted"><?= (int)$r['charge_type'] === 1 ? 'of cost' : 'fixed' ?></span></td>
                        <td class="cell-actions text-end">
                            <?php if (array_filter($can)): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                        data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions for <?= Html::encode($title) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:170px">
                                    <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['view', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['update']): ?><li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['update', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['renew']): ?><li><?= Html::a('<i class="fas fa-sync me-2"></i>Renew', ['renew', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['end']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-stop me-2"></i>End AMC', ['end', 'id' => $r['id']], [
                                            'class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data' => ['method' => 'post', 'confirm' => 'End the AMC for "' . $title . '"? It moves to Ended AMC and can no longer be renewed or edited.'],
                                        ]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows)]) ?>
    </div>
</div>
