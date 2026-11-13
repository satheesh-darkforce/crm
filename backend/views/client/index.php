<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */
/** @var array $filters  name, client_name, company_name, phone, email_id, gst_no, client_type */

use backend\models\ClientModel;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Clients';

// work_report.total_time is stored in SECONDS — render as HH:MM (Yii1 Utility::convertSecondsToHours()).
$fmt = fn(int $secs): string => $secs > 0 ? sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60)) : '—';

$simple     = \backend\components\Crm::simple(); // Green Divine: sales columns instead of timesheet hours
$inr        = fn($v): string => (float)$v > 0 ? '&#8377;' . number_format((float)$v, 0) : '<span class="muted">—</span>';
$typeLabels = ClientModel::typeOptions();
$typeTones  = [ClientModel::TYPE_INTERSTATE => 'accent', ClientModel::TYPE_INTRASTATE => 'success', ClientModel::TYPE_INTERNATIONAL => 'warning'];

$models      = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination ? $pagination->getOffset() : 0;
$activeCount = count(array_filter($filters, fn($v) => $v !== ''));
$hasFilters  = $activeCount > 0;

$can = [
    'create'      => UserAccess::checkAccess('client/create'),
    'view'        => UserAccess::checkAccess('client/view'),
    'update'      => UserAccess::checkAccess('client/update'),
    // Portal actions only while portal logins are on (params 'client.portalLogin').
    'reset'       => ClientModel::portalEnabled() && UserAccess::checkAccess('client/reset-password'),
    'permissions' => ClientModel::portalEnabled() && UserAccess::checkAccess('client/access-permissions'),
    'delete'      => UserAccess::checkAccess('client/delete'),
    'invoice'     => UserAccess::checkAccess('invoice/create'),
];
?>
<div class="ui-page client-index">
    <div class="ui-head">
        <div>
            <h1>Clients</h1>
            <div class="ui-sub"><?= number_format($total) ?> active client<?= $total == 1 ? '' : 's' ?><?= $hasFilters ? ' matching your search' : '' ?><?= $simple ? ' · latest quotation or invoice first' : ' · most recent work first' ?></div>
        </div>
        <div class="ui-actions">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#client-search"
                    aria-expanded="<?= $hasFilters ? 'true' : 'false' ?>" aria-controls="client-search">
                <i class="fas fa-search me-1"></i>Search<?php if ($hasFilters): ?> <span class="ui-count ms-1"><?= $activeCount ?></span><?php endif; ?>
            </button>
            <?php if ($can['create']): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Client', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="client-search">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="<?= Url::to(['index']) ?>" class="row g-2 align-items-end">
                    <?php foreach (['client_name' => ['Client Name', 'Client name'], 'company_name' => ['Company', 'Company name'],
                                    'phone' => ['Phone', 'Phone number'], 'email_id' => ['Email', 'name@company.com'],
                                    'gst_no' => ['GST No.', 'e.g. 33ABCDE1234F1Z5']] as $field => [$label, $ph]): ?>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label small mb-1" for="client-filter-<?= $field ?>"><?= $label ?></label>
                        <input type="text" id="client-filter-<?= $field ?>" name="<?= $field ?>" value="<?= Html::encode($filters[$field]) ?>"
                               class="form-control form-control-sm" maxlength="100" placeholder="<?= $ph ?>">
                    </div>
                    <?php endforeach; ?>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <label class="form-label small mb-1" for="client-filter-type">Type</label>
                        <?= Html::dropDownList('client_type', $filters['client_type'], $typeLabels, ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'client-filter-type']) ?>
                    </div>
                    <?php if ($filters['name'] !== ''): ?>
                        <?= Html::hiddenInput('name', $filters['name']) ?>
                    <?php endif; ?>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Search</button>
                        <?= Html::a('Reset', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Client</th>
                    <th>Contact</th>
                    <th>Type</th>
                    <?php if ($simple): ?>
                    <th class="text-center">Quotations</th>
                    <th class="text-end">Invoiced</th>
                    <th class="text-end">To collect</th>
                    <th class="text-center">Last activity</th>
                    <?php else: ?>
                    <th class="text-center">Last Work</th>
                    <th class="text-center" title="Hours logged this month"><?= date('M') ?> Hrs</th>
                    <th class="text-center" title="Hours logged last month"><?= date('M', strtotime(date('Y-m-01') . ' -1 month')) ?> Hrs</th>
                    <?php endif; ?>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr class="ui-empty-row"><td colspan="8" class="ui-empty-cell">
                        <?php if ($hasFilters): ?>
                            <?= $this->render('@backend/views/_ui/_empty', [
                                'icon' => 'search', 'title' => 'No clients match your search', 'text' => 'Try fewer or different search terms.',
                                'action' => Html::a('Clear search', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']),
                            ]) ?>
                        <?php else: ?>
                            <?= $this->render('@backend/views/_ui/_empty', [
                                'icon' => 'building', 'title' => 'No clients yet', 'text' => $simple ? 'Add a client, or convert a lead into a client.' : 'Clients you add get a portal login and appear here.',
                                'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add Client', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null,
                            ]) ?>
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $row): ?>
                    <?php
                    $type     = (int)($row['client_type'] ?? 0);
                    $name     = (string)($row['client_name'] ?? '');
                    $curSecs  = (int)($row['cur_month_secs'] ?? 0);
                    $lastSecs = (int)($row['last_month_secs'] ?? 0);
                    ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap">
                            <?php if ($can['view']): ?>
                                <?= Html::a(Html::encode($name ?: '-'), ['view', 'id' => $row['id']], ['class' => 'text-reset text-decoration-none']) ?>
                            <?php else: ?>
                                <?= Html::encode($name ?: '-') ?>
                            <?php endif; ?>
                            <?php if ($row['company_name'] && strcasecmp(trim($row['company_name']), trim($name)) !== 0): ?>
                                <div class="sub"><?= Html::encode($row['company_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small wrap" data-label="Contact">
                            <?php $contact = array_filter([trim((string)$row['phone']), trim((string)$row['email_id'])]); ?>
                            <?= $contact ? implode('<br>', array_map([Html::class, 'encode'], $contact)) : '<span class="muted">—</span>' ?>
                        </td>
                        <td data-label="Type">
                            <?php if (isset($typeLabels[$type])): ?>
                                <span class="ui-pill plain tone-<?= $typeTones[$type] ?>"><?= $typeLabels[$type] ?></span>
                            <?php else: ?>
                                <span class="ui-pill plain" title="Not set — edit the client to choose a type">Not set</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($simple): ?>
                        <td class="text-center nowrap" data-label="Quotations">
                            <?php if ((int)$row['quote_count'] > 0): ?>
                                <?= (int)$row['quote_count'] ?><?php if ((int)$row['open_quotes'] > 0): ?> <span class="ui-pill plain tone-accent ms-1" title="Draft or sent"><?= (int)$row['open_quotes'] ?> open</span><?php endif; ?>
                            <?php else: ?><span class="muted">—</span><?php endif; ?>
                        </td>
                        <td class="text-end nowrap" data-label="Invoiced"><?= $inr($row['billed']) ?></td>
                        <td class="text-end nowrap <?= (float)$row['pending'] > 0 ? 'text-danger fw-semibold' : '' ?>" data-label="To collect"><?= $inr($row['pending']) ?></td>
                        <td class="text-center small nowrap" data-label="Last activity"><?= $row['last_activity'] ? date('d M Y', strtotime($row['last_activity'])) : '<span class="muted">—</span>' ?></td>
                        <?php else: ?>
                        <td class="text-center small nowrap" data-label="Last Work">
                            <?= $row['last_work_date'] ? date('d M Y', strtotime($row['last_work_date'])) : '<span class="muted">—</span>' ?>
                        </td>
                        <td class="text-center nowrap <?= $curSecs > 0 ? 'fw-semibold text-success' : 'muted' ?>" data-label="<?= date('M') ?> Hrs"><?= $fmt($curSecs) ?></td>
                        <td class="text-center nowrap muted" data-label="<?= date('M', strtotime(date('Y-m-01') . ' -1 month')) ?> Hrs"><?= $fmt($lastSecs) ?></td>
                        <?php endif; ?>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false"
                                        aria-label="Actions for <?= Html::encode($name) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:200px">
                                    <?php if ($can['view']): ?>
                                        <li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['view', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($can['update']): ?>
                                        <li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['update', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($can['invoice']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header">Invoices</h6></li>
                                        <li><?= Html::a('<i class="fas fa-file-alt me-2"></i>Add Tax Invoice', ['/invoice/create', 'client_id' => $row['id'], 'invoice_role' => 1], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                        <li><?= Html::a('<i class="fas fa-clipboard me-2"></i>Add Proforma Invoice', ['/invoice/create', 'client_id' => $row['id'], 'invoice_role' => 2], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (($can['reset'] || $can['permissions']) && !empty($row['user_id'])): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header">Portal account</h6></li>
                                    <?php endif; ?>
                                    <?php if ($can['reset'] && !empty($row['user_id'])): ?>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#resetModal"
                                               data-url="<?= Url::to(['reset-password', 'id' => $row['id']]) ?>" data-name="<?= Html::encode($name) ?>" data-return="index">
                                            <i class="fas fa-key me-2"></i>Reset Password</a></li>
                                    <?php endif; ?>
                                    <?php if ($can['permissions'] && !empty($row['user_id'])): ?>
                                        <li><?= Html::a('<i class="fas fa-user-shield me-2"></i>Portal Permissions', ['access-permissions', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($can['delete']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-ban me-2"></i>Deactivate', ['delete', 'id' => $row['id']], [
                                            'class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data'  => ['method' => 'post', 'confirm' => 'Deactivate "' . $name . '"? The client is hidden from lists and can no longer log in to the portal.'],
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
        <?php if ($pagination && $pagination->getPageCount() > 1): ?>
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

    <?php if ($can['reset']): ?>
        <?= $this->render('_reset-password-modal') ?>
    <?php endif; ?>
</div>
