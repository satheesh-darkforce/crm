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

$isOpenList     = in_array($listType, ['index', 'my-leads', 'assigned'], true);
$canChange      = UserAccess::checkAccess('lead/change-status');
$canToggle      = UserAccess::checkAccess('lead/change-lead-status');
$canReopen      = LeadAccess::canReopen();
$viewRoute      = $listType === 'assigned' ? 'view-assigned' : 'view';
$hasFilters     = count(array_filter($filters, fn($v) => $v !== '')) > 0;

$tabs = [
    'index'     => 'Manage Leads',
    'my-leads'  => 'My Leads',
    'assigned'  => 'Assigned Leads',
    'converted' => 'Converted Leads',
    'dropped'   => 'Dropped Leads',
];
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.85rem}
.list-table tbody tr:hover{background:#f8f9ff}
.list-table tr.row-overdue td{background:#fff3e6}
.list-table tr.row-today td{background:#fff9db}
.list-table tr.row-tomorrow td{background:#ebfbee}
.list-table .wrap{white-space:normal;min-width:100px;max-width:170px;word-break:break-word}
.list-table .owner{white-space:normal;min-width:110px;max-width:140px}
.list-table .nowrap{white-space:nowrap}
.list-table .form-select-sm{font-size:.78rem;padding-top:.2rem;padding-bottom:.2rem}
.list-table .js-lead-status{width:138px}
.list-table .js-lead-active{width:92px}
.lead-list .list-table th,.lead-list .list-table td{padding:.45rem .4rem}
/* The layout lets .table-responsive overflow on desktop (so dropdowns are not
   clipped); keep this wide table scrolling inside its card instead, and give
   the action menus a fixed Popper strategy so they still escape the scroller. */
.lead-list .table-responsive{overflow-x:auto!important}
.lead-list .dropdown-menu.show{z-index:2050}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.legend-dot{display:inline-block;width:12px;height:12px;border:1px solid #dee2e6;margin-right:4px;vertical-align:-1px}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="lead-list">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($title) ?></h5>
            <small class="text-muted"><?= $total ?> lead<?= $total == 1 ? '' : 's' ?></small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#lead-search">
                <i class="fas fa-search me-1"></i>Search
            </button>
            <?php if (UserAccess::checkAccess('lead/create')): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Lead', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <ul class="nav nav-pills nav-sm mb-3" style="font-size:.85rem">
        <?php foreach ($tabs as $route => $label): ?>
            <?php if (UserAccess::checkAccess('lead/' . $route)): ?>
            <li class="nav-item">
                <?= Html::a($label, [$route], ['class' => 'nav-link py-1 px-3' . ($route === $listType ? ' active' : '')]) ?>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <!-- Search -->
    <div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="lead-search">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="<?= Url::to([$listType]) ?>" class="row g-2 align-items-end">
                    <?php foreach (['client_name' => 'Client Name', 'contact_no' => 'Contact No', 'email' => 'Email',
                                    'country' => 'City', 'contact_person_name' => 'Contact Person', 'title' => 'Project'] as $field => $label): ?>
                    <div class="col-md-2 col-sm-4">
                        <label class="form-label small mb-1"><?= $label ?></label>
                        <input type="text" name="<?= $field ?>" value="<?= Html::encode($filters[$field]) ?>" class="form-control form-control-sm">
                    </div>
                    <?php endforeach; ?>
                    <div class="col-md-2 col-sm-4">
                        <label class="form-label small mb-1"><?= $listType === 'my-leads' ? 'Follow Up From' : 'Attended From' ?></label>
                        <?= DatePicker::widget([
                            'name' => 'from_date', 'value' => $filters['from_date'], 'type' => DatePicker::TYPE_INPUT,
                            'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                        ]) ?>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <label class="form-label small mb-1"><?= $listType === 'my-leads' ? 'Follow Up To' : 'Attended To' ?></label>
                        <?= DatePicker::widget([
                            'name' => 'to_date', 'value' => $filters['to_date'], 'type' => DatePicker::TYPE_INPUT,
                            'options' => ['class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                            'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                        ]) ?>
                    </div>
                    <?php if ($isOpenList): ?>
                    <div class="col-md-2 col-sm-4">
                        <label class="form-label small mb-1">Lead Status</label>
                        <?= Html::dropDownList('lead_status', $filters['lead_status'], Lead::openLeadStatusOptions(),
                            ['class' => 'form-select form-select-sm', 'prompt' => 'All']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-2 col-sm-4">
                        <label class="form-label small mb-1">Assigned To</label>
                        <?= Select2::widget([
                            'name' => 'assigned_to', 'value' => $filters['assigned_to'], 'data' => $users,
                            'options' => ['placeholder' => 'All'],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                            'size' => Select2::SMALL,
                        ]) ?>
                    </div>
                    <div class="col-md-2 col-sm-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        <?= Html::a('Reset', [$listType], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($isOpenList): ?>
    <div class="d-flex flex-wrap gap-3 mb-2 text-muted" style="font-size:.78rem">
        <span><span class="legend-dot" style="background:#fff9db"></span>Follow-up today</span>
        <span><span class="legend-dot" style="background:#ebfbee"></span>Follow-up tomorrow</span>
        <span><span class="legend-dot" style="background:#fff3e6"></span>Follow-up overdue</span>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
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
                    <th class="text-end" style="width:60px">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr><td colspan="13" class="text-center text-muted py-5">No leads found.</td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $lead): /** @var Lead $lead */ ?>
                    <?php
                    $ls       = (int)$lead->lead_status;
                    $options  = $canChange ? $lead->nextLeadStatusOptions($canReopen) : [];
                    $isClosed = in_array($ls, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true);
                    ?>
                    <tr class="<?= $isOpenList ? Lead::followUpRowClass($lead->meeting_reported_date) : '' ?>">
                        <td class="text-muted"><?= $offset + $i + 1 ?></td>
                        <td class="wrap fw-semibold">
                            <?= Html::encode($lead->client_name) ?>
                            <?php if ($lead->company_name): ?><div class="small text-muted fw-normal"><?= Html::encode($lead->company_name) ?></div><?php endif; ?>
                        </td>
                        <td class="wrap"><?= Html::encode($lead->title ?: '-') ?></td>
                        <td class="wrap"><?= Html::encode($lead->getNextSubject() ?: '-') ?></td>
                        <?php if ($listType === 'index'): ?>
                            <td class="small wrap">
                                <?php $contact = array_filter([$lead->email, $lead->contact_no]); ?>
                                <?= $contact ? implode('<br>', array_map([Html::class, 'encode'], $contact)) : '-' ?>
                            </td>
                        <?php elseif ($listType === 'converted'): ?>
                            <td class="nowrap small"><?= Lead::formatDateTime($lead->converted_date_time) ?></td>
                        <?php elseif ($listType === 'dropped'): ?>
                            <td class="nowrap small"><?= Lead::formatDateTime($lead->dropped_date_time) ?></td>
                            <td class="wrap small"><?= Html::encode($lead->reason ?: '-') ?></td>
                        <?php endif; ?>
                        <?php if ($listType !== 'index'): ?>
                            <td class="nowrap small"><?= Lead::formatDateTime($lead->meeting_attended_date) ?></td>
                        <?php endif; ?>
                        <td class="nowrap small"><?= Lead::formatDateTime($lead->meeting_reported_date) ?></td>
                        <?php if ($listType === 'assigned'): ?>
                            <td class="small nowrap"><?= Html::encode(Lead::userLabel($lead->createdBy)) ?></td>
                        <?php elseif (in_array($listType, ['index', 'my-leads'], true)): ?>
                            <td class="small owner">
                                <span class="text-muted"><?= $listType === 'my-leads' ? 'Assigned by' : 'By' ?></span> <?= Html::encode(Lead::userLabel($lead->createdBy)) ?><br>
                                <span class="text-muted">To</span> <?= Html::encode(Lead::userLabel($lead->assignedTo)) ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <?php if (count($options) > 1): ?>
                                <?= Html::dropDownList('lead_status', $ls, $options, [
                                    'class'     => 'form-select form-select-sm js-lead-status',
                                    'data-id'   => $lead->id,
                                    'data-prev' => $ls,
                                ]) ?>
                            <?php else: ?>
                                <span class="badge bg-<?= Lead::leadStatusBadge($ls) ?>"><?= $lead->getLeadStatusLabel() ?></span>
                                <?php if ($ls === Lead::LEAD_STATUS_CONVERTED && (int)$lead->is_client === 1): ?>
                                    <span class="badge bg-light text-dark border">Client</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php if ($listType === 'index'): ?>
                            <td>
                                <?php if ($canToggle): ?>
                                    <?= Html::dropDownList('status', (int)$lead->status, Lead::statusOptions(), [
                                        'class'     => 'form-select form-select-sm js-lead-active',
                                        'style'     => 'min-width:100px',
                                        'data-id'   => $lead->id,
                                        'data-prev' => (int)$lead->status,
                                    ]) ?>
                                <?php else: ?>
                                    <?= Lead::statusOptions()[(int)$lead->status] ?? '-' ?>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:180px">
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
                                            'data'  => ['method' => 'post', 'confirm' => 'Delete this lead?'],
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
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= $offset + 1 ?>–<?= $offset + count($models) ?> of <?= $total ?></small>
            <?= LinkPager::widget([
                'pagination' => $pagination,
                'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›',
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->render('_status-js') ?>
