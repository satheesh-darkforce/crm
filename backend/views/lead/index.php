<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\User[] $users */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Leads';

$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'warning', 4 => 'success', 5 => 'primary', 6 => 'danger'];
$statusLabel = [1 => 'Open', 2 => 'Follow-up', 3 => 'Quoted', 4 => 'Won', 5 => 'Converted', 6 => 'Dropped'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$now   = new DateTime();
$today = $now->format('Y-m-d');
$tomorrow = (new DateTime('+1 day'))->format('Y-m-d');
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.list-table tr.row-overdue{background:#fff8f0}
.list-table tr.row-today{background:#fffde7}
.list-table tr.row-tomorrow{background:#f0fff4}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="lead-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Leads</h5>
            <small class="text-muted"><?= $total ?> lead<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Lead', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Legend -->
    <div class="d-flex gap-3 mb-2" style="font-size:.78rem">
        <span><span class="d-inline-block me-1" style="width:12px;height:12px;background:#fff8f0;border:1px solid #dee2e6"></span>Overdue follow-up</span>
        <span><span class="d-inline-block me-1" style="width:12px;height:12px;background:#fffde7;border:1px solid #dee2e6"></span>Follow-up today</span>
        <span><span class="d-inline-block me-1" style="width:12px;height:12px;background:#f0fff4;border:1px solid #dee2e6"></span>Follow-up tomorrow</span>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="search" value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm" placeholder="Client / Subject / Company…">
                </div>
                <div class="col-md-2">
                    <select name="lead_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusLabel as $v => $l): ?>
                            <option value="<?= $v ?>" <?= Yii::$app->request->get('lead_status') == $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="assigned_to" class="form-select form-select-sm">
                        <option value="">All Assignees</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u->user_id ?>" <?= Yii::$app->request->get('assigned_to') == $u->user_id ? 'selected' : '' ?>>
                                <?= Html::encode($u->username) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('search') || Yii::$app->request->get('lead_status') || Yii::$app->request->get('assigned_to')): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Client / Company</th>
                        <th>Subject</th>
                        <th>Assigned To</th>
                        <th>Created By</th>
                        <th>Next Follow-up</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No leads found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $lead):
                        $followDate = $lead->meeting_reported_date ?? null;
                        $rowClass = '';
                        if ($followDate && $lead->lead_status < 5) {
                            $fd = substr($followDate, 0, 10);
                            if ($fd < $today)        $rowClass = 'row-overdue';
                            elseif ($fd === $today)   $rowClass = 'row-today';
                            elseif ($fd === $tomorrow) $rowClass = 'row-tomorrow';
                        }
                        $st = (int)$lead->lead_status;
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $lead->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($lead->client_name) ?>
                            </a>
                            <?php if ($lead->company_name): ?>
                                <div class="text-muted" style="font-size:.75rem"><?= Html::encode($lead->company_name) ?></div>
                            <?php endif; ?>
                            <?php if ($lead->email || $lead->contact_no): ?>
                                <div class="text-muted" style="font-size:.72rem">
                                    <?= Html::encode($lead->email ?? '') ?>
                                    <?= ($lead->email && $lead->contact_no) ? ' · ' : '' ?>
                                    <?= Html::encode($lead->contact_no ?? '') ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= Html::encode($lead->subject ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($lead->assignedTo->username ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($lead->createdBy->username ?? '—') ?></td>
                        <td>
                            <?php if ($followDate): ?>
                                <span class="small <?= $rowClass === 'row-overdue' ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <?= date('d M Y', strtotime($followDate)) ?>
                                    <?php if (strlen($followDate) > 10): ?>
                                        <br><span style="font-size:.72rem"><?= date('h:i A', strtotime($followDate)) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>">
                                <?= $statusLabel[$st] ?? '—' ?>
                            </span>
                        </td>
                        <td class="text-muted small">
                            <?= $lead->created_date ? date('d M Y', strtotime($lead->created_date)) : '—' ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:165px">
                                    <?php if (UserAccess::checkAccess('lead/view')): ?>
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $lead->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('lead/update')): ?>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $lead->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('lead/view')): ?>
                                    <li><?= Html::a('<i class="fas fa-comment-dots"></i> Add Follow-up', ['view', 'id' => $lead->id, '#' => 'followup'], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($st < 5): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><span class="dropdown-header" style="font-size:.7rem">CHANGE STATUS</span></li>
                                    <?php foreach ($statusLabel as $sv => $sl):
                                        if ($sv == $st || $sv == 1) continue; ?>
                                        <?php if (!UserAccess::checkAccess('lead/change-status')) continue; ?>
                                        <li><?= Html::a($sl, ['change-status', 'id' => $lead->id, 'status' => $sv], [
                                            'class' => 'dropdown-item small', 'encode' => false,
                                            'data-method' => 'post', 'data-confirm' => "Mark as $sl?",
                                        ]) ?></li>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('lead/delete')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $lead->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-method' => 'post', 'data-confirm' => 'Delete this lead?',
                                    ]) ?></li>
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
