<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer[] $developers */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$q = Yii::$app->request->get();

$this->title = 'Leave Requests';

$statusBadge = [0 => 'secondary', 1 => 'info', 2 => 'success', 3 => 'danger', 4 => 'warning'];
$statusLabel = [0 => 'Requested', 1 => 'Pending (L1)', 2 => 'Approved', 3 => 'Rejected', 4 => 'Canceled'];

$leaveTypeLabel = [1 => 'Leave', 2 => 'Permission', 3 => 'Absent'];
$leaveTypeBadge = [1 => 'info', 2 => 'primary', 3 => 'danger'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$pendingCount = 0;
foreach ($models as $m) { if ((int)$m->request_status < 2) $pendingCount++; }

$canDecide = UserAccess::checkAccess('leave/decide');
?>
<style>
.list-table tr.row-pending{background:#fffde7}
.list-table tr.row-approved td{opacity:.85}
.list-table tr.row-approved td:last-child{opacity:1}
</style>

<div class="ui-page leave-index">
    <div class="ui-head">
        <div>
            <h1>Leave Requests</h1>
            <div class="ui-sub"><?= $total ?> request<?= $total != 1 ? 's' : '' ?>
                &nbsp;<span id="pending-count" class="badge bg-warning text-dark<?= $pendingCount > 0 ? '' : ' d-none' ?>"><?= $pendingCount ?> pending</span></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => Yii::$app->request->get('developer_id'),
                        'data'          => ['' => 'All Developers'] + ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Developers'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <select name="request_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusLabel as $v => $l): ?>
                            <option value="<?= $v ?>" <?= Yii::$app->request->get('request_status') === (string)$v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => $q['from_date'] ?? '',
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'From Date'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => $q['to_date'] ?? '',
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'To Date'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (!empty($q['developer_id']) || (($q['request_status'] ?? '') !== '') || !empty($q['from_date']) || !empty($q['to_date'])): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
                <div class="col-auto ms-auto">
                    <?= Html::a('<i class="fas fa-clock"></i> Leave Settings', ['/attendance/leave-settings'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Developer</th>
                        <th>Leave Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th class="text-center">Days</th>
                        <th>Reason</th>
                        <th>Description</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th class="text-end" style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-5">No leave requests found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $leave):
                        $rs  = (int)$leave->request_status;
                        $lt  = (int)($leave->leave_type ?? 0);
                        $rowClass = match($rs) {
                            0, 1 => 'row-pending',
                            2    => 'row-approved',
                            default => '',
                        };
                    ?>
                    <tr class="<?= $rowClass ?>" data-leave-row="<?= $leave->id ?>">
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $leave->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($leave->developer->developer_name ?? '—') ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($leave->isHalfDayLeave()): ?>
                                <span class="badge bg-info-subtle text-info border" style="font-size:.72rem">Half Day</span>
                                <div class="small text-muted"><?= $leave->getSessionName() ?></div>
                            <?php elseif ($lt && isset($leaveTypeLabel[$lt])): ?>
                                <span class="badge bg-<?= $leaveTypeBadge[$lt] ?>-subtle text-<?= $leaveTypeBadge[$lt] ?> border" style="font-size:.72rem">
                                    <?= $leaveTypeLabel[$lt] ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small"><?= Html::encode($leave->getLeaveTypeName() ?? '—') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?= $leave->from_date ? date('d M Y', strtotime($leave->from_date)) : '—' ?>
                        </td>
                        <td class="text-muted small">
                            <?= $leave->to_date ? date('d M Y', strtotime($leave->to_date)) : '—' ?>
                        </td>
                        <td class="text-center fw-semibold">
                            <?= $leave->no_of_days !== null ? $leave->getDaysLabel() : '—' ?>
                        </td>
                        <td class="small text-muted" style="max-width:200px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                 title="<?= Html::encode($leave->reason ?? '') ?>">
                                <?= Html::encode($leave->reason ?? '—') ?>
                            </div>
                        </td>
                        <td class="small text-muted" style="max-width:180px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                 title="<?= Html::encode($leave->description ?? '') ?>">
                                <?= Html::encode($leave->description ?? '—') ?>
                            </div>
                        </td>
                        <td class="small text-muted">
                            <?= $leave->requested_date ? date('d M Y', strtotime($leave->requested_date)) : '—' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge[$rs] ?? 'secondary' ?>" data-status-badge>
                                <?= $statusLabel[$rs] ?? '—' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                            <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                    type="button" data-bs-toggle="dropdown">&#8942;</button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                <?php if (UserAccess::checkAccess('leave/view')): ?>
                                <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $leave->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                <?php endif; ?>
                                <?php if ($canDecide): ?>
                                    <?php
                                    /* Every decide action is rendered once and shown/hidden by the row's
                                       current status, so an inline status change can re-point the menu
                                       without re-rendering the page. data-for = statuses it applies to. */
                                    $decideItems = [
                                        ['for' => '0,1,2', 'sep' => true],
                                        ['for' => '0,1',   'action' => 'approve',         'icon' => 'fa-check',         'class' => 'text-success', 'label' => 'Approve',           'confirm' => 'Approve this leave request?'],
                                        ['for' => '0',     'action' => 'transfer',        'icon' => 'fa-exchange-alt',  'class' => '',             'label' => 'Transfer to Admin', 'confirm' => 'Transfer for admin approval?'],
                                        ['for' => '0,1',   'action' => 'reject',          'icon' => 'fa-times',         'class' => 'text-danger',  'label' => 'Reject',            'confirm' => 'Reject this leave request?'],
                                        ['for' => '2',     'action' => 'cancel-approved', 'icon' => 'fa-times',         'class' => 'text-danger',  'label' => 'Cancel Approved',   'confirm' => 'Cancel this approved leave?'],
                                    ];
                                    foreach ($decideItems as $it):
                                        $hidden = in_array((string)$rs, explode(',', $it['for']), true) ? '' : ' d-none';
                                        if (!empty($it['sep'])): ?>
                                            <li data-for="<?= $it['for'] ?>" class="<?= trim($hidden) ?>"><hr class="dropdown-divider my-1"></li>
                                        <?php else: ?>
                                            <li data-for="<?= $it['for'] ?>" class="<?= trim($hidden) ?>">
                                                <button type="button" class="dropdown-item <?= $it['class'] ?>"
                                                        onclick="leaveDecide(this, <?= $leave->id ?>, '<?= $it['action'] ?>', '<?= Html::encode($it['confirm']) ?>')">
                                                    <i class="fas <?= $it['icon'] ?>"></i> <?= $it['label'] ?>
                                                </button>
                                            </li>
                                        <?php endif;
                                    endforeach; ?>
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
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
/* Inline status change — posts to leave/decide over AJAX and repaints the row,
   so the active name/date filters survive (a full reload used to drop them).
   Handlers are inline onclick: delegated jQuery clicks never reach .dropdown-item
   in this layout. */
$decideUrl   = Url::to(['decide']);
$statusJs    = json_encode($statusLabel);
$badgeJs     = json_encode($statusBadge);
$csrfParam   = Yii::$app->request->csrfParam;
$csrfToken   = Yii::$app->request->csrfToken;

$this->registerJs(<<<JS
window.LEAVE_DECIDE_URL = '$decideUrl';
window.LEAVE_STATUS_LABEL = $statusJs;
window.LEAVE_STATUS_BADGE = $badgeJs;
window.LEAVE_CSRF = {param: '$csrfParam', token: '$csrfToken'};

window.leaveDecide = function (btn, id, action, confirmText) {
    Swal.fire({
        text: confirmText, icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Yes', cancelButtonText: 'Cancel',
        reverseButtons: true, heightAuto: false
    }).then(function (r) {
        if (!r.isConfirmed) return;

        var row = btn.closest('tr');
        row.style.opacity = '.5';
        row.querySelectorAll('.dropdown-item').forEach(function (el) { el.disabled = true; });

        var body = new URLSearchParams();
        body.append('action', action);
        body.append(window.LEAVE_CSRF.param, window.LEAVE_CSRF.token);

        fetch(window.LEAVE_DECIDE_URL + '?id=' + id, {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            body: body,
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            row.style.opacity = '';
            row.querySelectorAll('.dropdown-item').forEach(function (el) { el.disabled = false; });
            if (!data || !data.success) {
                Swal.fire({icon: 'error', title: 'Failed', text: (data && data.message) || 'Could not update the status.'});
                return;
            }
            leaveApplyStatus(row, data.request_status);
            Swal.fire({icon: 'success', title: data.message, timer: 1500, showConfirmButton: false, heightAuto: false});
        })
        .catch(function () {
            row.style.opacity = '';
            row.querySelectorAll('.dropdown-item').forEach(function (el) { el.disabled = false; });
            Swal.fire({icon: 'error', title: 'Network error', text: 'The status was not changed. Please try again.'});
        });
    });
};

/* Repaint one row for its new status: badge, row tint and which menu items apply. */
function leaveApplyStatus(row, status) {
    var s = String(status);

    var badge = row.querySelector('[data-status-badge]');
    if (badge) {
        badge.className = 'badge bg-' + (window.LEAVE_STATUS_BADGE[s] || 'secondary');
        badge.setAttribute('data-status-badge', '');
        badge.textContent = window.LEAVE_STATUS_LABEL[s] || '—';
    }

    row.classList.remove('row-pending', 'row-approved');
    if (s === '0' || s === '1') row.classList.add('row-pending');
    else if (s === '2')         row.classList.add('row-approved');

    row.querySelectorAll('li[data-for]').forEach(function (li) {
        var applies = li.getAttribute('data-for').split(',').indexOf(s) !== -1;
        li.classList.toggle('d-none', !applies);
    });

    leaveRefreshPendingCount();
}

/* Pending = every visible row still at Requested / Pending (L1). */
function leaveRefreshPendingCount() {
    var el = document.getElementById('pending-count');
    if (!el) return;
    var n = document.querySelectorAll('tr[data-leave-row].row-pending').length;
    el.textContent = n + ' pending';
    el.classList.toggle('d-none', n === 0);
}
JS, \yii\web\View::POS_END);
