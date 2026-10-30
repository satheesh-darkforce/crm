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
use yii\widgets\LinkPager;

$canView   = UserAccess::checkAccess('amc/view');
$canUpdate = UserAccess::checkAccess('amc/update');
$canRenew  = UserAccess::checkAccess('amc/renew');
$canEnd    = UserAccess::checkAccess('amc/end');

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$chargeVal = function (array $r): string {
    if ((int)$r['charge_type'] === 1) {
        return rtrim(rtrim((string)$r['percentage'], '0'), '.') . ' %';
    }
    return '₹' . number_format((float)$r['amount'], 2);
};

$today = strtotime(date('Y-m-d'));

// Warranty status for a row: No Warranty (period 0) / Under Warranty / Warranty Ended.
$warrantyStatus = function (array $r) use ($today): array {
    if ((int)$r['warranty_period'] === 0) {
        return ['No Warranty', 'bg-secondary-subtle text-secondary'];
    }
    if (!empty($r['end_date']) && strtotime($r['end_date']) >= $today) {
        return ['Under Warranty', 'bg-success-subtle text-success'];
    }
    return ['Warranty Ended', 'bg-warning-subtle text-warning'];
};

// Current AMC period: the latest renewal's dates if renewed; otherwise it
// begins after the warranty (or at the start date when there's no warranty)
// and has no end date yet.
$amcDates = function (array $r): array {
    if (!empty($r['renew_end'])) {
        return [$r['renew_start'], $r['renew_end']];
    }
    $start = (int)$r['warranty_period'] === 0
        ? $r['start_date']
        : ($r['end_date'] ? date('Y-m-d', strtotime($r['end_date'] . ' +1 day')) : null);
    return [$start, null];   // null end → still under warranty
};
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="amc-list">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($heading) ?></h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Client</label>
                    <?= Select2::widget([
                        'name' => 'client_id', 'value' => $clientF ?: null,
                        'data' => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options' => ['placeholder' => 'All Clients'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Project</label>
                    <input type="text" name="title" value="<?= Html::encode($titleF) ?>" class="form-control form-control-sm" placeholder="Project title…">
                </div>
                <?php if ($mode === 'active'): // extra filters apply only to the Active AMC list ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Charge Type</label>
                    <select name="charge_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="1" <?= $typeF === '1' ? 'selected' : '' ?>>Percentage</option>
                        <option value="2" <?= $typeF === '2' ? 'selected' : '' ?>>Amount</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Warranty</label>
                    <select name="warranty" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="under" <?= $warrantyF === 'under' ? 'selected' : '' ?>>Under Warranty</option>
                        <option value="ended" <?= $warrantyF === 'ended' ? 'selected' : '' ?>>Warranty Ended</option>
                        <option value="none"  <?= $warrantyF === 'none'  ? 'selected' : '' ?>>No Warranty</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">AMC State</label>
                    <select name="state" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="active"  <?= $stateF === 'active'  ? 'selected' : '' ?>>Active</option>
                        <option value="overdue" <?= $stateF === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">AMC Start Date (from)</label>
                    <?= DatePicker::widget([
                        'name' => 'amc_start', 'value' => $startF,
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['id' => 'amc-f-start', 'class' => 'form-control form-control-sm', 'placeholder' => 'From', 'autocomplete' => 'off'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'clearBtn' => true, 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">AMC End Date (to)</label>
                    <?= DatePicker::widget([
                        'name' => 'amc_end', 'value' => $endF,
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['id' => 'amc-f-end', 'class' => 'form-control form-control-sm', 'placeholder' => 'To', 'autocomplete' => 'off'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'clearBtn' => true, 'todayHighlight' => true],
                    ]) ?>
                </div>
                <?php endif; ?>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary btn-sm">Filter</button>
                    <?= Html::a('Reset', $resetUrl, ['class' => 'btn btn-light btn-sm']) ?>
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
                        <th>Warranty Status</th>
                        <th>AMC Start Date</th><th>AMC End Date</th>
                        <th>Charge</th><th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No records found.</td></tr>
                <?php else: foreach ($rows as $i => $r):
                    [$wLabel, $wClass]      = $warrantyStatus($r);
                    [$amcStart, $amcEnd]    = $amcDates($r);
                ?>
                    <tr>
                        <td class="text-muted"><?= $pagination->getOffset() + $i + 1 ?></td>
                        <td><?= Html::encode($r['client_name'] ?? '—') ?></td>
                        <td class="fw-semibold"><?= Html::encode($r['title'] ?? '—') ?></td>
                        <td><span class="badge rounded-pill border <?= $wClass ?>"><?= $wLabel ?></span></td>
                        <td><?= $amcStart ? date('d-m-Y', strtotime($amcStart)) : '—' ?></td>
                        <td>
                            <?php if ($amcEnd): ?>
                                <?= date('d-m-Y', strtotime($amcEnd)) ?>
                            <?php elseif ($wLabel === 'Under Warranty'): ?>
                                <span class="badge rounded-pill bg-success-subtle text-success border">Under warranty</span>
                            <?php else: ?>
                                <span class="badge rounded-pill bg-warning-subtle text-warning border">Need to renew</span>
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
                                    <?php if ($canView): ?>
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($mode === 'active' && $canUpdate): ?>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($mode === 'active' && $canRenew): ?>
                                        <li><?= Html::a('<i class="fas fa-sync"></i> Renew', ['renew', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($mode === 'active' && $canEnd): ?>
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

<?php
// Keep the date window valid: the End Date can't be earlier than the Start
// Date (and vice-versa). Uses the kvDatepicker (bootstrap-datepicker) API.
$this->registerJs(<<<JS
(function(){
    var s = jQuery('#amc-f-start'), e = jQuery('#amc-f-end');
    if (!s.length || !e.length) { return; }
    function sync(){
        var sv = s.val(), ev = e.val();
        if (sv) { e.kvDatepicker('setStartDate', sv); }
        if (ev) { s.kvDatepicker('setEndDate', ev); }
    }
    s.on('changeDate', function(){ e.kvDatepicker('setStartDate', s.val() || null); });
    e.on('changeDate', function(){ s.kvDatepicker('setEndDate', e.val() || null); });
    sync();
})();
JS, \yii\web\View::POS_READY);
?>
