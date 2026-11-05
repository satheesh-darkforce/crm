<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Amc $model */
/** @var \backend\models\Project $project */
/** @var \backend\models\AmcRenew[] $renews */

use backend\models\Amc;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'View AMC';

$isActive  = (int)$model->amc_status === Amc::AMC_ACTIVE;
$can = [
    'update'      => $isActive && UserAccess::checkAccess('amc/update'),
    'renew'       => $isActive && UserAccess::checkAccess('amc/renew'),
    'end'         => $isActive && UserAccess::checkAccess('amc/end'),
    'updateRenew' => $isActive && UserAccess::checkAccess('amc/update-renew'),
];

$d          = fn($v) => $v ? date('d M Y', strtotime($v)) : '—';
$isPercent  = (int)$model->charge_type === Amc::CHARGE_PERCENTAGE;
$noWarranty = (int)$model->warranty_period === 0;
$warranty   = $model->getWarrantyStatusLabel();
$title      = $project->title ?? 'Project #' . $model->project_id;

// Current AMC period: the latest renewal; otherwise it starts after the warranty
// (or at the start date with no warranty) and has no end until renewed.
$latest = $renews ? $renews[count($renews) - 1] : null;
if ($latest) {
    [$curStart, $curEnd] = [$latest->start_date, $latest->end_date];
} else {
    $curStart = $noWarranty ? $model->start_date : date('Y-m-d', strtotime($model->end_date . ' +1 day'));
    $curEnd   = null;
}
$daysLeft = $curEnd ? (int)floor((strtotime($curEnd) - strtotime(date('Y-m-d'))) / 86400) : null;

$charge = fn($type, $pct, $amt): string => (int)$type === Amc::CHARGE_PERCENTAGE
    ? rtrim(rtrim((string)$pct, '0'), '.') . '%'
    : '₹' . number_format((float)$amt, 2);
$computed = ($isPercent && (float)$model->estimation_cost > 0) ? (float)$model->estimation_cost * (float)$model->percentage / 100 : null;
?>
<div class="ui-page amc-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($title) ?></h1>
            <div class="ui-sub">
                AMC · <?= Html::encode($model->client->client_name ?? 'No client') ?> ·
                <span class="ui-pill tone-<?= $isActive ? 'success' : 'neutral' ?>"><?= $model->getAmcStatusLabel() ?></span>
            </div>
        </div>
        <div class="ui-actions">
            <?php if ($can['renew']): ?><?= Html::a('<i class="fas fa-sync me-1"></i>Renew', ['renew', 'id' => $model->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?><?php endif; ?>
            <?php if ($can['update']): ?><?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?><?php endif; ?>
            <?php if ($can['end']): ?>
                <?= Html::a('<i class="fas fa-stop me-1"></i>End AMC', ['end', 'id' => $model->id], [
                    'class' => 'btn btn-outline-danger btn-sm', 'encode' => false,
                    'data' => ['method' => 'post', 'confirm' => 'End the AMC for "' . $title . '"? It moves to Ended AMC and can no longer be renewed or edited.'],
                ]) ?>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', [$isActive ? 'active' : 'ended'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="ui-tiles">
        <div class="ui-tile is-accent">
            <div class="t-label">Current AMC period</div>
            <div class="t-value"><?= $d($curStart) ?> → <?= $curEnd ? $d($curEnd) : '<span class="ui-pill plain tone-warning">Needs renewal</span>' ?></div>
            <?php if ($daysLeft !== null): ?>
                <div class="t-sub <?= $daysLeft < 0 ? 'text-danger fw-semibold' : '' ?>"><?= $daysLeft >= 0 ? $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' : abs($daysLeft) . ' days overdue' ?></div>
            <?php endif; ?>
        </div>
        <div class="ui-tile">
            <div class="t-label">Warranty</div>
            <div class="t-value"><?= $noWarranty ? 'None' : $d($model->start_date) . ' → ' . $d($model->end_date) ?></div>
            <div class="t-sub"><span class="ui-pill plain tone-<?= $warranty === 'Under Warranty' ? 'success' : ($noWarranty ? 'neutral' : 'warning') ?>"><?= Html::encode($warranty) ?></span>
                <?= $noWarranty ? '' : ' · ' . (int)$model->warranty_period . ' month' . ((int)$model->warranty_period === 1 ? '' : 's') ?></div>
        </div>
        <div class="ui-tile">
            <div class="t-label">Charge</div>
            <div class="t-value"><?= $computed !== null ? '₹' . number_format($computed, 2) : $charge($model->charge_type, $model->percentage, $model->amount) ?></div>
            <div class="t-sub"><?= $isPercent ? rtrim(rtrim((string)$model->percentage, '0'), '.') . '% of estimation cost' : 'Fixed amount' ?></div>
        </div>
        <div class="ui-tile">
            <div class="t-label">Estimation cost</div>
            <div class="t-value"><?= (float)$model->estimation_cost > 0 ? '₹' . number_format((float)$model->estimation_cost, 2) : '—' ?></div>
            <div class="t-sub">Created <?= $d($model->created_date) ?></div>
        </div>
    </div>

    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="fas fa-info-circle mt-1"></i>
        <div><?= $noWarranty
            ? 'No warranty period — the AMC charge applies from the start date.'
            : 'The AMC charge applies after the warranty ends (' . $d($model->end_date) . ').' ?></div>
    </div>

    <?php if (trim((string)$model->description) !== '' || $project): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">Details</div>
        <table class="table ui-detail">
            <tr><th scope="row">Project</th><td><?= $project ? Html::a(Html::encode($project->title), ['/project/view', 'id' => $project->id]) : '—' ?></td></tr>
            <tr><th scope="row">Charge type</th><td><?= $model->getChargeTypeLabel() ?> · <?= $charge($model->charge_type, $model->percentage, $model->amount) ?></td></tr>
            <tr><th scope="row">Description</th><td><?= trim((string)$model->description) !== '' ? nl2br(Html::encode($model->description)) : '—' ?></td></tr>
        </table>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">
            <span>Renewals<?= $renews ? ' (' . count($renews) . ')' : '' ?></span>
            <?php if ($can['renew']): ?><?= Html::a('<i class="fas fa-sync me-1"></i>Renew', ['renew', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Period</th><th>Months</th><th>Charge</th><th>Description</th><?php if ($can['updateRenew']): ?><th class="text-end"><span class="visually-hidden">Action</span></th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php if (!$renews): ?>
                    <tr class="ui-empty-row"><td colspan="6" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'history', 'title' => 'No renewals yet', 'small' => true,
                            'text' => $can['renew'] ? 'Renew the AMC when the current period ends.' : null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($renews as $k => $rn): ?>
                    <tr>
                        <td class="cell-num muted"><?= $k + 1 ?></td>
                        <td class="cell-title">Renewal <?= $k + 1 ?><div class="sub"><?= $d($rn->start_date) ?> → <?= $d($rn->end_date) ?></div></td>
                        <td data-label="Months"><?= (int)$rn->months ?></td>
                        <td class="nowrap" data-label="Charge"><?= $charge($rn->charge_type, $rn->percentage, $rn->amount) ?></td>
                        <td class="small wrap" data-label="Description"><?= trim((string)$rn->description) !== '' ? Html::encode($rn->description) : '—' ?></td>
                        <?php if ($can['updateRenew']): ?>
                            <td class="text-end"><?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update-renew', 'id' => $rn->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
