<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Amc $model */
/** @var \backend\models\Project $project */
/** @var \backend\models\AmcRenew[] $renews */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'View AMC';

$canUpdate      = UserAccess::checkAccess('amc/update');
$canRenew       = UserAccess::checkAccess('amc/renew');
$canEnd         = UserAccess::checkAccess('amc/end');
$canUpdateRenew = UserAccess::checkAccess('amc/update-renew');

$d  = fn($v) => $v ? date('d M Y', strtotime($v)) : '—';

$currentEnd     = $model->getCurrentEndDate();
$warrantyStatus = $model->getWarrantyStatusLabel();
$underWarranty  = $warrantyStatus === 'Under Warranty';
$isActive       = (int)$model->amc_status === \backend\models\Amc::AMC_ACTIVE;
// Ended AMCs are view-only — no editing of the contract or its renewals.
$canUpdate      = $canUpdate && $isActive;
$canUpdateRenew = $canUpdateRenew && $isActive;
$isPercent      = (int)$model->charge_type === 1;
$noWarranty     = (int)$model->warranty_period === 0;

// Current AMC period.
//  - If a renewal exists, that is the current AMC period (its start → end).
//  - Otherwise the AMC has not been renewed yet: it begins right after the
//    warranty ends (or at the start date when there is no warranty) and has
//    no end date until it is renewed ("Need to renew").
$latestRenew = !empty($renews) ? $renews[count($renews) - 1] : null;
if ($latestRenew) {
    $curAmcStart = $latestRenew->start_date;
    $curAmcEnd   = $latestRenew->end_date;
    $needRenew   = false;
} else {
    $curAmcStart = $noWarranty
        ? $model->start_date
        : date('Y-m-d', strtotime($model->end_date . ' +1 day'));
    $curAmcEnd   = null;
    $needRenew   = true;
}

$daysLeft = $curAmcEnd ? (int) floor((strtotime($curAmcEnd) - strtotime(date('Y-m-d'))) / 86400) : null;

$chargeVal = function ($type, $pct, $amt): string {
    if ((int)$type === 1) {
        return rtrim(rtrim((string)$pct, '0'), '.') . '%';
    }
    return '₹' . number_format((float)$amt, 2);
};

$computedAmt = ($isPercent && $model->estimation_cost > 0)
    ? (float)$model->estimation_cost * (float)$model->percentage / 100
    : null;
?>
<style>
.amc-page .amc-hero{background:linear-gradient(120deg,#1e40af 0%,#1d4ed8 55%,#2563eb 100%);color:#fff;border-radius:.75rem;padding:1.4rem 1.5rem;}
.amc-page .amc-hero h4{font-weight:700;margin:.25rem 0;}
.amc-page .amc-eyebrow{font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;opacity:.85;}
.amc-page .amc-tile{border:1px solid #e9ecef;border-radius:.6rem;padding:.85rem 1rem;height:100%;background:#fff;}
.amc-page .amc-tile .t-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#8a93a6;font-weight:600;margin-bottom:.35rem;}
.amc-page .amc-tile .t-value{font-size:1.05rem;font-weight:700;color:#222;line-height:1.2;}
.amc-page .amc-tile .t-sub{font-size:.72rem;color:#9aa1ad;margin-top:.15rem;}
.amc-page .amc-dl{margin:0;}
.amc-page .amc-dl dt{font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#8a93a6;font-weight:600;}
.amc-page .amc-dl dd{font-size:.95rem;font-weight:600;color:#222;margin-bottom:.9rem;}
.amc-page .amc-icbox{width:30px;height:30px;border-radius:.45rem;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;}
</style>

<div class="amc-page">

    <!-- Hero header -->
    <div class="amc-hero mb-3 d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="amc-eyebrow"><i class="fas fa-tools me-1"></i> Annual Maintenance Charge</div>
            <h4 class="mb-1"><?= Html::encode($project->title ?? '—') ?></h4>
            <div class="opacity-75"><i class="fas fa-building me-1"></i> <?= Html::encode($model->client->client_name ?? '—') ?></div>
        </div>
        <div class="text-end">
            <span class="badge rounded-pill <?= $isActive ? 'bg-light text-success' : 'bg-light text-secondary' ?> mb-2">
                <i class="fas <?= $isActive ? 'fa-check-circle' : 'fa-stop-circle' ?> me-1"></i><?= $model->getAmcStatusLabel() ?>
            </span>
            <div class="d-flex gap-2 justify-content-end">
                <?php if ($isActive && $canUpdate): ?>
                    <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-light btn-sm fw-semibold', 'encode' => false]) ?>
                <?php endif; ?>
                <?php if ($isActive && $canRenew): ?>
                    <?= Html::a('<i class="fas fa-sync"></i> Renew', ['renew', 'id' => $model->id], ['class' => 'btn btn-light btn-sm fw-semibold', 'encode' => false]) ?>
                <?php endif; ?>
                <?php if ($isActive && $canEnd): ?>
                    <?= Html::a('<i class="fas fa-stop"></i> End', ['end', 'id' => $model->id], ['class' => 'btn btn-outline-light btn-sm fw-semibold', 'encode' => false]) ?>
                <?php endif; ?>
                <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['active'], ['class' => 'btn btn-outline-light btn-sm fw-semibold', 'encode' => false]) ?>
            </div>
        </div>
    </div>

    <!-- KPI tiles -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg">
            <div class="amc-tile">
                <div class="t-label"><span class="amc-icbox bg-primary-subtle text-primary me-1"><i class="fas fa-flag"></i></span> Warranty Start Date</div>
                <div class="t-value"><?= $noWarranty ? '<span class="text-muted">No Warranty</span>' : $d($model->start_date) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="amc-tile">
                <div class="t-label"><span class="amc-icbox bg-primary-subtle text-primary me-1"><i class="fas fa-shield-alt"></i></span> Warranty End Date</div>
                <div class="t-value"><?= $noWarranty ? '<span class="text-muted">No Warranty</span>' : $d($model->end_date) ?></div>
                <?php if (!$noWarranty): ?><div class="t-sub"><?= (int)$model->warranty_period ?> month(s) warranty</div><?php endif; ?>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="amc-tile">
                <div class="t-label"><span class="amc-icbox bg-info-subtle text-info me-1"><i class="fas fa-play"></i></span> Current AMC Start Date</div>
                <div class="t-value text-primary"><?= $d($curAmcStart) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="amc-tile">
                <div class="t-label"><span class="amc-icbox bg-info-subtle text-info me-1"><i class="fas fa-calendar-check"></i></span> Current AMC End Date</div>
                <?php if ($needRenew): ?>
                    <div class="t-value"><span class="badge rounded-pill bg-warning-subtle text-warning border">Need to renew</span></div>
                <?php else: ?>
                    <div class="t-value text-primary"><?= $d($curAmcEnd) ?></div>
                    <?php if ($daysLeft !== null): ?>
                        <div class="t-sub"><?= $daysLeft >= 0 ? $daysLeft . ' days left' : abs($daysLeft) . ' days overdue' ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="amc-tile">
                <div class="t-label"><span class="amc-icbox <?= $underWarranty ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> me-1"><i class="fas fa-heartbeat"></i></span> Warranty Status</div>
                <div class="t-value">
                    <span class="badge rounded-pill <?= $underWarranty ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> border">
                        <?= Html::encode($warrantyStatus) ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="amc-tile">
                <div class="t-label"><span class="amc-icbox bg-success-subtle text-success me-1"><i class="fas fa-rupee-sign"></i></span> Charge</div>
                <div class="t-value">
                    <?php if ($isPercent && $computedAmt !== null): ?>
                        ₹<?= number_format($computedAmt, 2) ?>
                    <?php else: ?>
                        <?= $chargeVal($model->charge_type, $model->percentage, $model->amount) ?>
                    <?php endif; ?>
                </div>
                <div class="t-sub">
                    <?php if ($isPercent): ?>
                        <?= rtrim(rtrim((string)$model->percentage, '0'), '.') ?>% of estimation cost
                    <?php else: ?>
                        fixed amount
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info py-2 px-3 small mb-3">
        <i class="fas fa-circle-info me-1"></i>
        <?php if ($noWarranty): ?>
            No warranty period — the AMC charge applies from the start date.
        <?php else: ?>
            The AMC charge is applied <strong>after the warranty period ends</strong> (from <?= $d($model->end_date) ?>).
        <?php endif; ?>
    </div>

    <!-- AMC details -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="fas fa-info-circle text-primary me-1"></i> AMC Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><dl class="amc-dl"><dt>Charge Type</dt><dd><?= $model->getChargeTypeLabel() ?></dd></dl></div>
                <div class="col-md-3"><dl class="amc-dl"><dt><?= $isPercent ? 'Percentage' : 'Amount' ?></dt><dd><?= $chargeVal($model->charge_type, $model->percentage, $model->amount) ?></dd></dl></div>
                <div class="col-md-3"><dl class="amc-dl"><dt>Total Estimation Cost</dt><dd><?= $model->estimation_cost ? '₹' . number_format((float)$model->estimation_cost, 2) : '—' ?></dd></dl></div>
                <?php if ($computedAmt !== null): ?>
                <div class="col-md-3"><dl class="amc-dl"><dt>Computed AMC Amount</dt><dd class="text-success">₹<?= number_format($computedAmt, 2) ?></dd></dl></div>
                <?php endif; ?>
                <div class="col-md-3"><dl class="amc-dl"><dt>Created</dt><dd><?= $d($model->created_date) ?></dd></dl></div>
            </div>
            <?php if (trim((string)$model->description) !== ''): ?>
            <dl class="amc-dl mb-0"><dt>Description</dt><dd class="fw-normal text-secondary"><?= nl2br(Html::encode($model->description)) ?></dd></dl>
            <?php endif; ?>
        </div>
    </div>

    <!-- Renewal history -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="fas fa-history text-primary me-1"></i> Renewal History</span>
            <span class="badge bg-secondary-subtle text-secondary border"><?= count($renews) ?> renewal<?= count($renews) != 1 ? 's' : '' ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th><th>Period</th><th>Start</th><th>End</th><th>Months</th><th>Charge</th><th>Description</th><?php if ($canUpdateRenew): ?><th class="text-end">Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $colspan = $canUpdateRenew ? 8 : 7; ?>
                    <?php if (empty($renews)): ?>
                        <tr><td colspan="<?= $colspan ?>" class="text-center text-muted py-4">No renewals yet.</td></tr>
                    <?php else: foreach ($renews as $k => $rn): ?>
                    <tr>
                        <td class="text-muted"><?= $k + 1 ?></td>
                        <td class="fw-semibold">Renewal #<?= $k + 1 ?></td>
                        <td><?= $d($rn->start_date) ?></td>
                        <td><?= $d($rn->end_date) ?></td>
                        <td><?= (int)$rn->months ?></td>
                        <td><?= $chargeVal($rn->charge_type, $rn->percentage, $rn->amount) ?></td>
                        <td class="text-muted small"><?= Html::encode((string)$rn->description) ?: '—' ?></td>
                        <?php if ($canUpdateRenew): ?>
                        <td class="text-end"><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update-renew', 'id' => $rn->id], ['class' => 'btn btn-outline-secondary btn-sm py-0', 'encode' => false]) ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
