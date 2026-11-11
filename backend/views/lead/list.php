<?php
/** @var \yii\web\View $this */
/** @var string $listType  index | my-leads | assigned | converted | dropped */
/** @var string $title */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var array $filters */
/** @var array $users  user_id => username */

use backend\components\LeadAccess;
use backend\models\Lead;
use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = $title;

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination->getOffset();

$isOpenList = in_array($listType, ['index', 'my-leads', 'assigned'], true);
$canChange  = UserAccess::checkAccess('lead/change-status');
$canToggle  = UserAccess::checkAccess('lead/change-lead-status');
$canCreate  = UserAccess::checkAccess('lead/create');
$canReopen  = LeadAccess::canReopen();
$viewRoute  = $listType === 'assigned' ? 'view-assigned' : 'view';
$hasFilters = count(array_filter($filters, fn($v) => $v !== '')) > 0;
$dateLabel  = $listType === 'my-leads' ? 'Follow Up' : 'Attended';

$tabs = [
    'index'     => 'Manage Leads',
    'my-leads'  => 'My Leads',
    'assigned'  => 'Assigned Leads',
    'converted' => 'Converted Leads',
    'dropped'   => 'Dropped Leads',
];
$emptyText = [
    'index'     => 'New leads you add will appear here.',
    'my-leads'  => 'Leads you created or that are assigned to you will appear here.',
    'assigned'  => 'Follow-ups other people assign to you will appear here.',
    'converted' => 'Leads move here once they are marked Converted.',
    'dropped'   => 'Leads move here once they are marked Dropped.',
][$listType] ?? '';

$datePicker = fn(string $name) => DatePicker::widget([
    'name' => $name, 'value' => $filters[$name], 'type' => DatePicker::TYPE_INPUT,
    'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off', 'id' => 'lead-filter-' . $name],
    'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
]);
?>
<div class="ui-page lead-list">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($title) ?></h1>
            <div class="ui-sub"><?= number_format($total) ?> lead<?= $total == 1 ? '' : 's' ?><?= $hasFilters ? ' matching your search' : '' ?></div>
        </div>
        <div class="ui-actions">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#lead-search"
                    aria-expanded="<?= $hasFilters ? 'true' : 'false' ?>" aria-controls="lead-search">
                <i class="fas fa-search me-1"></i>Search<?php if ($hasFilters): ?> <span class="ui-count ms-1"><?= count(array_filter($filters, fn($v) => $v !== '')) ?></span><?php endif; ?>
            </button>
            <?php if ($canCreate): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Lead', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <nav class="ui-tabs" aria-label="Lead lists">
        <?php foreach ($tabs as $route => $label): ?>
            <?php if (UserAccess::checkAccess('lead/' . $route)): ?>
                <?= Html::a($label, [$route], ['class' => $route === $listType ? 'active' : '', 'aria-current' => $route === $listType ? 'page' : null]) ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="lead-search">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="<?= Url::to([$listType]) ?>" class="row g-2 align-items-end">
                    <?php foreach (['client_name' => ['Client Name', 'Name'], 'contact_no' => ['Contact No', 'Phone number'],
                                    'email' => ['Email', 'name@company.com'], 'country' => ['City', 'City'],
                                    'contact_person_name' => ['Contact Person', 'Name'], 'title' => ['Project', 'Project title']] as $field => [$label, $ph]):
                        if ($field === 'contact_person_name' && \backend\components\Crm::simple()) { continue; } ?>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lead-filter-<?= $field ?>"><?= $label ?></label>
                        <input type="text" id="lead-filter-<?= $field ?>" name="<?= $field ?>" value="<?= Html::encode($filters[$field]) ?>"
                               class="form-control form-control-sm" maxlength="100" placeholder="<?= $ph ?>">
                    </div>
                    <?php endforeach; ?>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lead-filter-from_date"><?= $dateLabel ?> From</label>
                        <?= $datePicker('from_date') ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lead-filter-to_date"><?= $dateLabel ?> To</label>
                        <?= $datePicker('to_date') ?>
                    </div>
                    <?php if ($isOpenList): ?>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lead-filter-lead_status">Lead Status</label>
                        <?= Html::dropDownList('lead_status', $filters['lead_status'], Lead::openLeadStatusOptions(),
                            ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'lead-filter-lead_status']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lead-filter-assigned_to">Assigned To</label>
                        <?= Select2::widget([
                            'name' => 'assigned_to', 'value' => $filters['assigned_to'], 'data' => $users,
                            'options' => ['placeholder' => 'All', 'id' => 'lead-filter-assigned_to'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                            'size' => Select2::SMALL,
                        ]) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lead-filter-lead_source">Source</label>
                        <?= Select2::widget([
                            'name' => 'lead_source', 'value' => $filters['lead_source'], 'data' => Lead::sourceOptions(),
                            'options' => ['placeholder' => 'All', 'id' => 'lead-filter-lead_source'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                            'size' => Select2::SMALL,
                        ]) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lead-filter-priority">Temperature</label>
                        <?= Html::dropDownList('priority', $filters['priority'], Lead::priorityOptions(),
                            ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'lead-filter-priority']) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lead-filter-interest">Interested In</label>
                        <?= Select2::widget([
                            'name' => 'interest', 'value' => $filters['interest'], 'data' => \backend\models\ProductCategory::options(),
                            'options' => ['placeholder' => 'All', 'id' => 'lead-filter-interest'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                            'size' => Select2::SMALL,
                        ]) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                        <?= Html::a('Reset', [$listType], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($isOpenList && $models): ?>
    <div class="ui-legend" aria-label="Follow-up legend">
        <span>Next follow-up:</span>
        <span class="fu-chip is-overdue">Overdue</span>
        <span class="fu-chip is-today">Today</span>
        <span class="fu-chip is-tomorrow">Tomorrow</span>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Client Name</th>
                    <th>Project</th>
                    <th>Subject</th>
                    <?php if ($listType === 'index'): ?>
                        <th>Email &amp; Phone</th>
                    <?php elseif ($listType === 'converted'): ?>
                        <th>Converted On</th>
                    <?php elseif ($listType === 'dropped'): ?>
                        <th>Dropped On</th>
                        <th>Reason</th>
                    <?php endif; ?>
                    <?php if ($listType !== 'index'): ?>
                        <th>Attended</th>
                    <?php endif; ?>
                    <th>Next Follow Up</th>
                    <?php if ($listType === 'assigned'): ?>
                        <th>Created By</th>
                    <?php elseif (in_array($listType, ['index', 'my-leads'], true)): ?>
                        <th>Owner</th>
                    <?php endif; ?>
                    <th>Lead Status</th>
                    <?php if ($listType === 'index'): ?>
                        <th>Status</th>
                    <?php endif; ?>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr class="ui-empty-row"><td colspan="13" class="ui-empty-cell">
                        <?php if ($hasFilters): ?>
                            <?= $this->render('@backend/views/_ui/_empty', [
                                'icon' => 'search', 'title' => 'No leads match your search',
                                'text' => 'Try fewer or different search terms.',
                                'action' => Html::a('Clear search', [$listType], ['class' => 'btn btn-outline-secondary btn-sm']),
                            ]) ?>
                        <?php else: ?>
                            <?= $this->render('@backend/views/_ui/_empty', [
                                'icon' => 'bullseye', 'title' => 'No ' . strtolower($title) . ' yet', 'text' => $emptyText,
                                'action' => $canCreate && $listType === 'index'
                                    ? Html::a('<i class="fas fa-plus me-1"></i>Add Lead', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false])
                                    : null,
                            ]) ?>
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $lead): /** @var Lead $lead */ ?>
                    <?php
                    $ls       = (int)$lead->lead_status;
                    $options  = $canChange ? $lead->nextLeadStatusOptions($canReopen) : [];
                    $isClosed = in_array($ls, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true);
                    $state    = $isOpenList ? Lead::followUpState($lead->meeting_reported_date) : '';
                    ?>
                    <tr class="<?= $state ? 'fu-' . $state : '' ?>">
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap">
                            <?= Html::encode($lead->client_name ?: '-') ?>
                            <?php if ($isOpenList): $sc = \backend\components\LeadInsight::score($lead); [$scBand, $scTone] = \backend\components\LeadInsight::band($sc); ?>
                                <span class="ui-pill tone-<?= $scTone ?> ms-1" title="Lead score <?= $sc ?>/100 (<?= $scBand ?>)"><?= $sc ?></span>
                            <?php endif; ?>
                            <?php if ($lead->company_name): ?><div class="sub"><?= Html::encode($lead->company_name) ?></div><?php endif; ?>
                            <?php
                            $meta = [];
                            if ($lead->lead_source) { $meta[] = Html::encode(Lead::sourceOptions()[(int)$lead->lead_source] ?? ''); }
                            if ($lead->interests) { $meta[] = Html::encode(implode(', ', array_map(fn($c) => $c->name, $lead->interests))); }
                            ?>
                            <?php if ($meta || (int)$lead->priority > Lead::PRIORITY_LOW): ?>
                                <div class="sub">
                                    <?php if ((int)$lead->priority === Lead::PRIORITY_HIGH): ?><span class="ui-pill tone-danger">Hot</span>
                                    <?php elseif ((int)$lead->priority === Lead::PRIORITY_MEDIUM): ?><span class="ui-pill tone-warning">Warm</span><?php endif; ?>
                                    <?= implode(' · ', $meta) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="wrap" data-label="Project"><?= Html::encode($lead->title ?: '-') ?></td>
                        <td class="wrap" data-label="Subject"><?= Html::encode($lead->getNextSubject() ?: '-') ?></td>
                        <?php if ($listType === 'index'): ?>
                            <td class="small wrap" data-label="Contact">
                                <?php $contact = array_filter([$lead->email, $lead->contact_no]); ?>
                                <?= $contact ? implode('<br>', array_map([Html::class, 'encode'], $contact)) : '-' ?>
                            </td>
                        <?php elseif ($listType === 'converted'): ?>
                            <td class="nowrap small" data-label="Converted On"><?= Lead::formatDateTime($lead->converted_date_time) ?></td>
                        <?php elseif ($listType === 'dropped'): ?>
                            <td class="nowrap small" data-label="Dropped On"><?= Lead::formatDateTime($lead->dropped_date_time) ?></td>
                            <td class="wrap small" data-label="Reason"><?= Html::encode($lead->reason ?: '-') ?></td>
                        <?php endif; ?>
                        <?php if ($listType !== 'index'): ?>
                            <td class="nowrap small" data-label="Attended"><?= Lead::formatDateTime($lead->meeting_attended_date) ?></td>
                        <?php endif; ?>
                        <td class="small" data-label="Next Follow Up">
                            <?= $this->render('_due', ['label' => Lead::formatDateTime($lead->meeting_reported_date), 'state' => $state]) ?>
                        </td>
                        <?php if ($listType === 'assigned'): ?>
                            <td class="small nowrap" data-label="Created By"><?= Html::encode(Lead::userLabel($lead->createdBy)) ?></td>
                        <?php elseif (in_array($listType, ['index', 'my-leads'], true)): ?>
                            <td class="small owner" data-label="Owner">
                                <div><span class="muted"><?= $listType === 'my-leads' ? 'Assigned by' : 'By' ?></span> <?= Html::encode(Lead::userLabel($lead->createdBy)) ?></div>
                                <div><span class="muted">To</span> <?= Html::encode(Lead::userLabel($lead->assignedTo)) ?></div>
                            </td>
                        <?php endif; ?>
                        <td data-label="Lead Status">
                            <?php if (count($options) > 1): ?>
                                <?= Html::dropDownList('lead_status', $ls, $options, [
                                    'class'      => 'form-select form-select-sm js-lead-status',
                                    'data-id'    => $lead->id,
                                    'data-prev'  => $ls,
                                    'aria-label' => 'Lead status for ' . $lead->client_name,
                                ]) ?>
                            <?php else: ?>
                                <span class="ui-pill tone-<?= Lead::leadStatusTone($ls) ?>"><?= Html::encode($lead->getLeadStatusLabel()) ?></span>
                                <?php if ($ls === Lead::LEAD_STATUS_CONVERTED && (int)$lead->is_client === 1): ?>
                                    <span class="ui-pill plain">Client</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php if ($listType === 'index'): ?>
                            <td data-label="Status">
                                <?php if ($canToggle): ?>
                                    <?= Html::dropDownList('status', (int)$lead->status, Lead::statusOptions(), [
                                        'class'      => 'form-select form-select-sm js-lead-active',
                                        'data-id'    => $lead->id,
                                        'data-prev'  => (int)$lead->status,
                                        'aria-label' => 'Active status for ' . $lead->client_name,
                                    ]) ?>
                                <?php else: ?>
                                    <span class="ui-pill tone-<?= (int)$lead->status === Lead::STATUS_ACTIVE ? 'success' : 'neutral' ?>">
                                        <?= Lead::statusOptions()[(int)$lead->status] ?? '-' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false"
                                        aria-label="Actions for <?= Html::encode($lead->client_name) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:180px">
                                    <?php if (UserAccess::checkAccess('lead/' . $viewRoute)): ?>
                                        <li><?= Html::a('<i class="fas fa-eye me-2"></i>View', [$viewRoute, 'id' => $lead->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (!$isClosed && UserAccess::checkAccess('lead/update')): ?>
                                        <li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['update', 'id' => $lead->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('lead-followers/index')): ?>
                                        <li><?= Html::a('<i class="fas fa-share me-2"></i>Follow Ups', ['/lead-followers/index', 'id' => $lead->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (!$isClosed && UserAccess::checkAccess('lead-followers/create')): ?>
                                        <li><?= Html::a('<i class="fas fa-plus me-2"></i>Add Follow Up', ['/lead-followers/create', 'id' => $lead->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($ls === Lead::LEAD_STATUS_CONVERTED && (int)$lead->is_client === 0 && UserAccess::checkAccess('lead/add-to-client')): ?>
                                        <li><?= Html::a('<i class="fas fa-user-plus me-2"></i>Add To Client', ['add-to-client', 'id' => $lead->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('lead/delete')): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-trash me-2"></i>Delete', ['delete', 'id' => $lead->id], [
                                            'class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data'  => ['method' => 'post', 'confirm' => 'Delete the lead "' . $lead->client_name . '"? It will be removed from all lead lists.'],
                                        ]) ?></li>
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
