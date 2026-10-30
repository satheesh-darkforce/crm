<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Assigned to Me';

$statusBadge = [1 => 'secondary', 2 => 'info', 3 => 'warning', 4 => 'success', 5 => 'primary', 6 => 'danger'];
$statusLabel = [1 => 'Open', 2 => 'Follow-up', 3 => 'Quoted', 4 => 'Won', 5 => 'Converted', 6 => 'Dropped'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$now      = new DateTime();
$today    = $now->format('Y-m-d');
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

<div class="lead-assigned">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Assigned to Me</h5>
            <small class="text-muted"><?= $total ?> lead<?= $total != 1 ? 's' : '' ?> assigned to me</small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('All Leads', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('My Leads', ['my-leads'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        </div>
    </div>

    <!-- Legend -->
    <div class="d-flex gap-3 mb-2" style="font-size:.78rem">
        <span><span class="d-inline-block me-1" style="width:12px;height:12px;background:#fff8f0;border:1px solid #dee2e6"></span>Overdue</span>
        <span><span class="d-inline-block me-1" style="width:12px;height:12px;background:#fffde7;border:1px solid #dee2e6"></span>Today</span>
        <span><span class="d-inline-block me-1" style="width:12px;height:12px;background:#f0fff4;border:1px solid #dee2e6"></span>Tomorrow</span>
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
                        <th>Created By</th>
                        <th>Next Follow-up</th>
                        <th>Status</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No leads assigned to you.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $lead):
                        $followDate = $lead->meeting_reported_date ?? null;
                        $rowClass = '';
                        if ($followDate && $lead->lead_status < 5) {
                            $fd = substr($followDate, 0, 10);
                            if ($fd < $today)         $rowClass = 'row-overdue';
                            elseif ($fd === $today)    $rowClass = 'row-today';
                            elseif ($fd === $tomorrow) $rowClass = 'row-tomorrow';
                        }
                        $st = (int)$lead->lead_status;
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $lead->id]) ?>" class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($lead->client_name) ?>
                            </a>
                            <?php if ($lead->company_name): ?>
                                <div class="text-muted" style="font-size:.75rem"><?= Html::encode($lead->company_name) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= Html::encode($lead->subject ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($lead->createdBy->username ?? '—') ?></td>
                        <td>
                            <?php if ($followDate): ?>
                                <span class="small <?= $rowClass === 'row-overdue' ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <?= date('d M Y', strtotime($followDate)) ?>
                                </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>">
                                <?= $statusLabel[$st] ?? '—' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:165px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $lead->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-comment-dots"></i> Add Follow-up', ['view', 'id' => $lead->id, '#' => 'followup'], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
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
