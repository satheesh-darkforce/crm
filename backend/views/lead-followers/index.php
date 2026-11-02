<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var int $openFollowUps */

use backend\models\Lead;
use backend\models\LeadFollowers;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Follow Ups';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$offset     = $pagination->getOffset();
$isClosed   = in_array((int)$lead->lead_status, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true);
$canClose   = !$isClosed && UserAccess::checkAccess('lead/change-followup-status');
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.85rem}
.list-table tbody tr:hover{background:#f8f9ff}
.list-table tr.row-overdue td{background:#fff3e6}
.list-table tr.row-today td{background:#fff9db}
.list-table tr.row-tomorrow td{background:#ebfbee}
.list-table .wrap{white-space:normal;min-width:140px;max-width:300px}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="lead-followers-index">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Follow Ups <small class="text-muted fw-normal">— <?= Html::encode($lead->title ?: $lead->client_name) ?></small></h5>
            <small class="text-muted"><?= Html::encode($lead->client_name) ?> · <?= $lead->getLeadStatusLabel() ?></small>
        </div>
        <div class="d-flex gap-2">
            <?php if (UserAccess::checkAccess('lead/view')): ?>
                <?= Html::a('<i class="fas fa-eye me-1"></i>View Lead', ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if (!$isClosed && UserAccess::checkAccess('lead-followers/create')): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Follow Up', ['create', 'id' => $lead->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Subject</th>
                    <th>Follow Up</th>
                    <th>Assigned To</th>
                    <th>Created By</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th class="text-end" style="width:60px">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No follow-ups yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $f): /** @var LeadFollowers $f */ ?>
                    <?php $open = (int)$f->followup_status === LeadFollowers::FOLLOWUP_OPEN; ?>
                    <tr class="<?= $open ? Lead::followUpRowClass($f->follow_up_date) : '' ?>">
                        <td class="text-muted"><?= $offset + $i + 1 ?></td>
                        <td class="wrap"><?= Html::encode($f->subject ?: '-') ?></td>
                        <td class="small" style="white-space:nowrap"><?= Html::encode($f->getScheduledLabel()) ?></td>
                        <td class="small"><?= Html::encode(Lead::userLabel($f->assignedTo)) ?></td>
                        <td class="small"><?= Html::encode(Lead::userLabel($f->createdBy)) ?></td>
                        <td class="small"><?= Html::encode(Lead::modeLabel($f->mode)) ?></td>
                        <td><span class="badge bg-<?= $open ? 'warning text-dark' : 'secondary' ?>"><?= $f->getFollowupStatusLabel() ?></span></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:190px">
                                    <?php if (UserAccess::checkAccess('lead-followers/view')): ?>
                                        <li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['view', 'id' => $f->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (!$isClosed && UserAccess::checkAccess('lead-followers/update')): ?>
                                        <li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['update', 'id' => $f->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($open && $canClose): ?>
                                        <li><a href="#" class="dropdown-item js-close-followup" data-id="<?= $f->id ?>" data-lead="<?= $lead->id ?>"
                                               data-only-open="<?= $openFollowUps <= 1 ? 1 : 0 ?>"><i class="fas fa-check me-2"></i>Close Follow-up</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination->getPageCount() > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-end">
            <?= LinkPager::widget([
                'pagination' => $pagination,
                'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›',
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->render('@backend/views/lead/_status-js') ?>
