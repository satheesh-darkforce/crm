<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Developer $developer */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'My Leave Requests';

$statusBadge = [0 => 'secondary', 1 => 'info', 2 => 'success', 3 => 'danger', 4 => 'warning'];
$statusLabel = [0 => 'Requested', 1 => 'Pending (L1)', 2 => 'Approved', 3 => 'Rejected', 4 => 'Canceled'];
$typeBadge   = [1 => 'info', 2 => 'primary', 3 => 'danger'];
$typeLabel   = [1 => 'Leave', 2 => 'Permission', 3 => 'Absent'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$q = Yii::$app->request->get();
?>
<style>
.list-table tr.row-pending{background:#fffde7}
/* Approved / rejected rows: fade text/background, never the td itself —
   `opacity` on a td creates a new stacking context that clips the action
   dropdown menu, putting it behind the following rows. */
.list-table tr.row-approved td{color:#475569;background:#f6fbf9}
.list-table tr.row-approved td .badge{filter:saturate(.85)}
.list-table tr.row-rejected td{color:#94a3b8;background:#fbfbfd}
.list-table tr.row-rejected td .badge{filter:saturate(.6)}
/* Ensure the action dropdown floats above the table — without this the menu
   sits in the table cell's stacking context and is clipped by sibling rows. */
.leave-my-requests .table-responsive{overflow:visible!important}
.leave-my-requests table{position:relative}
.leave-my-requests td{position:relative}
.leave-my-requests td .dropdown.show{position:relative;z-index:1080}
.leave-my-requests td .dropdown-menu{z-index:1080}
</style>

<div class="ui-page leave-my-requests">
    <div class="ui-head">
        <div>
            <h1>My Leave Requests</h1>
            <div class="ui-sub"><?= $total ?> request<?= $total != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('+ Add Leave Request', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="request_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusLabel as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($q['request_status'] ?? '') === (string)$v ? 'selected' : '' ?>>
                                <?= Html::encode($l) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (!empty($q['request_status'])): ?>
                        <?= Html::a('Clear', ['my-requests'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
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
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th class="text-center">Days</th>
                        <th>Reason</th>
                        <th>Submitted On</th>
                        <th>Status</th>
                        <th class="text-end" style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No leave requests found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $leave):
                        $rs       = (int)$leave->request_status;
                        $lt       = (int)($leave->leave_type ?? 0);
                        $rowClass = match($rs) {
                            0, 1    => 'row-pending',
                            2       => 'row-approved',
                            3, 4    => 'row-rejected',
                            default => '',
                        };
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <?php if ($leave->isHalfDayLeave()): ?>
                                <span class="badge bg-info">Half Day</span>
                                <div class="small text-muted"><?= $leave->getSessionName() ?></div>
                            <?php else: ?>
                                <span class="badge bg-<?= $typeBadge[$lt] ?? 'secondary' ?>">
                                    <?= $typeLabel[$lt] ?? Html::encode($leave->leaveTypeName) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $leave->from_date ? date('d M Y', strtotime($leave->from_date)) : '—' ?></td>
                        <td><?= $leave->to_date   ? date('d M Y', strtotime($leave->to_date))   : '—' ?></td>
                        <td class="text-center fw-semibold"><?= $leave->getDaysLabel() ?></td>
                        <td class="small text-muted" style="max-width:220px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                 title="<?= Html::encode($leave->reason) ?>">
                                <?= Html::encode($leave->reason) ?>
                            </div>
                        </td>
                        <td class="small text-muted">
                            <?= $leave->requested_date ? date('d M Y', strtotime($leave->requested_date)) : '—' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge[$rs] ?? 'secondary' ?>">
                                <?= $statusLabel[$rs] ?? '—' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:130px;z-index:1080">
                                    <?php if ($rs === 0): ?>
                                        <?php if (\common\components\UserAccess::checkAccess('leave/update')): ?>
                                        <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $leave->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <?php endif; ?>
                                        <li>
                                            <?= Html::a('<i class="fas fa-times"></i> Cancel', ['cancel', 'id' => $leave->id], [
                                                'class'  => 'dropdown-item text-danger',
                                                'encode' => false,
                                                'data'   => ['method' => 'post', 'confirm' => 'Cancel this leave request?'],
                                            ]) ?>
                                        </li>
                                    <?php else: ?>
                                        <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $leave->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
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
            <small class="text-muted">
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?>
            </small>
            <?= LinkPager::widget([
                'pagination'    => $pagination,
                'options'       => ['class' => 'pagination mb-0'],
                'firstPageLabel'=> '«', 'lastPageLabel' => '»',
                'prevPageLabel' => '‹', 'nextPageLabel' => '›',
                'maxButtonCount'=> 7,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
