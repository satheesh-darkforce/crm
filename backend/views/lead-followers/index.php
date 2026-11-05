<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var int $openFollowUps */

use backend\models\Lead;
use backend\models\LeadFollowers;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\widgets\LinkPager;

$this->title = 'Follow Ups';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$offset     = $pagination->getOffset();
$total      = $dataProvider->getTotalCount();
$isClosed   = in_array((int)$lead->lead_status, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true);
$canClose   = !$isClosed && UserAccess::checkAccess('lead/change-followup-status');
$canCreate  = !$isClosed && UserAccess::checkAccess('lead-followers/create');
?>
<div class="ui-page lead-followers-index">
    <div class="ui-head">
        <div>
            <h1>Follow Ups <small>— <?= Html::encode($lead->title ?: $lead->client_name) ?></small></h1>
            <div class="ui-sub">
                <?= Html::encode($lead->client_name) ?> ·
                <span class="ui-pill tone-<?= Lead::leadStatusTone((int)$lead->lead_status) ?>"><?= Html::encode($lead->getLeadStatusLabel()) ?></span>
                · <?= number_format($total) ?> follow-up<?= $total == 1 ? '' : 's' ?>
            </div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('lead/view')): ?>
                <?= Html::a('<i class="fas fa-eye me-1"></i>View Lead', ['/lead/view', 'id' => $lead->id], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if ($canCreate): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Follow Up', ['create', 'id' => $lead->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Subject</th>
                    <th>Follow Up</th>
                    <th>Assigned To</th>
                    <th>Created By</th>
                    <th>Mode</th>
                    <th>Status</th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr class="ui-empty-row"><td colspan="8" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', [
                            'icon' => 'calendar-check', 'title' => 'No follow-ups yet',
                            'text' => $canCreate ? 'Schedule the next conversation with this lead.' : null,
                            'action' => $canCreate
                                ? Html::a('<i class="fas fa-plus me-1"></i>Add Follow Up', ['create', 'id' => $lead->id], ['class' => 'btn btn-primary btn-sm', 'encode' => false])
                                : null,
                        ]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $f): /** @var LeadFollowers $f */ ?>
                    <?php
                    $open  = (int)$f->followup_status === LeadFollowers::FOLLOWUP_OPEN;
                    $state = $open && !$isClosed ? Lead::followUpState($f->follow_up_date) : '';
                    ?>
                    <tr class="<?= $state ? 'fu-' . $state : '' ?>">
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap"><?= Html::encode($f->subject ?: '-') ?></td>
                        <td class="small" data-label="Follow Up"><?= $this->render('@backend/views/lead/_due', ['label' => $f->getScheduledLabel(), 'state' => $state]) ?></td>
                        <td class="small" data-label="Assigned To"><?= Html::encode(Lead::userLabel($f->assignedTo)) ?></td>
                        <td class="small" data-label="Created By"><?= Html::encode(Lead::userLabel($f->createdBy)) ?></td>
                        <td class="small" data-label="Mode"><?= Html::encode(Lead::modeLabel($f->mode)) ?></td>
                        <td data-label="Status"><span class="ui-pill tone-<?= $open ? 'accent' : 'neutral' ?>"><?= Html::encode($f->getFollowupStatusLabel()) ?></span></td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Follow-up actions">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:190px">
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
        <div class="card-footer bg-white ui-pager">
            <small class="text-muted">Showing <?= $offset + 1 ?>–<?= $offset + count($models) ?> of <?= number_format($total) ?></small>
            <?= LinkPager::widget([
                'pagination' => $pagination,
                'options' => ['class' => 'pagination pagination-sm mb-0'],
                'linkContainerOptions' => ['class' => 'page-item'],
                'linkOptions' => ['class' => 'page-link'],
                'disabledListItemSubTagOptions' => ['tag' => 'span', 'class' => 'page-link'],
                'maxButtonCount' => 5,
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›',
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
