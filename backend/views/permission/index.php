<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeaveRequests[] $requests */
/** @var bool   $isAdmin */
/** @var bool   $canViewAll  admin, or developer with the "List" permission */
/** @var bool   $canDecide   admin, or developer with the "Approve / Reject" permission */
/** @var string $month */
/** @var \backend\models\Developer|null $selfDev */
/** @var \backend\models\Developer[] $developers  employee filter options (only when $canViewAll) */
/** @var int $developerId  currently filtered employee, 0 = all */

use backend\models\LeaveRequests;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\date\DatePicker;
use kartik\select2\Select2;

$this->title = 'Permission Requests';

$statusBadge = [0 => 'secondary', 1 => 'info', 2 => 'success', 3 => 'danger', 4 => 'warning'];
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.list-table tr.row-pending{background:#fffde7}
.act-btn{padding:.2rem .55rem;font-size:.78rem;line-height:1.3}
</style>

<div class="permission-index">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold">Permission Requests</h5>
            <small class="text-muted">Permissions for <?= Html::encode(date('F Y', strtotime($month . '-01'))) ?></small>
        </div>
        <div class="d-flex align-items-end gap-2">
            <form method="get" class="d-flex align-items-end gap-2">
                <?php if ($canViewAll): ?>
                <div style="min-width:230px">
                    <label class="form-label small mb-1">Employee</label>
                    <?= Select2::widget([
                        'name'          => 'developer_id',
                        'value'         => $developerId ?: '',
                        'data'          => ['' => 'All Employees'] + ArrayHelper::map($developers, 'id', 'developer_name'),
                        'options'       => ['placeholder' => 'All Employees'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <?php endif; ?>
                <div>
                    <label class="form-label small mb-1">Month</label>
                    <?= DatePicker::widget([
                        'name'          => 'month',
                        'type'          => DatePicker::TYPE_INPUT,
                        'value'         => $month,
                        'options'       => ['class' => 'form-control form-control-sm', 'style' => 'width:140px'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm', 'startView' => 'year', 'minViewMode' => 'months'],
                    ]) ?>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-filter"></i> Filter</button>
            </form>
            <?= Html::a('<i class="fas fa-plus"></i> Request Permission', ['create'], ['class' => 'btn btn-sm btn-primary', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <?php if ($canViewAll): ?><th>Employee</th><?php endif; ?>
                        <th>Date</th>
                        <th>Hours</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="<?= $canViewAll ? 7 : 6 ?>" class="text-center text-muted py-4">No permission requests for this month.</td></tr>
                <?php else: $i = 1; foreach ($requests as $r):
                    $pending = (int)$r->request_status === LeaveRequests::REQ_STATUS_REQUESTED;
                ?>
                    <tr class="<?= $pending ? 'row-pending' : '' ?>">
                        <td class="text-muted"><?= $i++ ?></td>
                        <?php if ($canViewAll): ?>
                        <td><?= Html::encode($r->developer->developer_name ?? '—') ?></td>
                        <?php endif; ?>
                        <td><?= Html::encode(date('d M Y', strtotime($r->from_date))) ?></td>
                        <td>
                            <?= rtrim(rtrim(number_format((float)$r->permission_hours, 1), '0'), '.') ?> hr
                        </td>
                        <td><?= Html::encode($r->reason) ?></td>
                        <td><span class="badge bg-<?= $statusBadge[(int)$r->request_status] ?? 'secondary' ?>"><?= Html::encode($r->getRequestStatusName()) ?></span></td>
                        <td class="text-end">
                            <?php
                            $isOwn      = $selfDev && (int)$r->developer_id === (int)$selfDev->id;
                            // Approve / Reject follows the permission alone — including
                            // on the approver's own requests.
                            $showDecide = $canDecide && $pending;
                            $showCancel = $pending && $isOwn;
                            ?>
                            <?php if ($showDecide): ?>
                                <form method="post" action="<?= Url::to(['decide', 'id' => $r->id]) ?>" class="d-inline">
                                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                                    <button type="submit" name="action" value="approve" class="btn btn-success act-btn"><i class="fas fa-check"></i> Approve</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger act-btn"
                                            onclick="return confirm('Reject this permission request?')"><i class="fas fa-times"></i> Reject</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($showCancel): ?>
                                <form method="post" action="<?= Url::to(['cancel', 'id' => $r->id]) ?>" class="d-inline"
                                      onsubmit="return confirm('Cancel this request?')">
                                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                                    <button type="submit" class="btn btn-outline-secondary act-btn"><i class="fas fa-ban"></i> Cancel</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$showDecide && !$showCancel): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
