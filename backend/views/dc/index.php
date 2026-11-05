<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $clientOptions   name => name */
/** @var array $companyOptions  name => name */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Delivery Challans';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$q          = Yii::$app->request->get();
$keys       = ['dc_no', 'client_name', 'company_name', 'dc_type', 'status'];
$hasFilter  = (bool)array_filter(array_intersect_key($q, array_flip($keys)), fn($v) => $v !== '' && $v !== 'all');
$types      = [1 => ['Returnable', 'warning'], 2 => ['Sale', 'accent'], 3 => ['Service', 'neutral']];
$can = [
    'create'  => UserAccess::checkAccess('dc/create'),
    'view'    => UserAccess::checkAccess('dc/view'),
    'update'  => UserAccess::checkAccess('dc/update'),
    'pdf'     => UserAccess::checkAccess('dc/challan-pdf'),
    'return'  => UserAccess::checkAccess('dc/save-return'),
    'cancel'  => UserAccess::checkAccess('dc/cancel'),
];
$tabs = array_filter([
    'quotation-dashboard/index' => UserAccess::checkAccess('quotation-dashboard/index') ? 'Dashboard' : null,
    'dc/index'                  => 'Outward DCs',
    'dc/inward-index'           => UserAccess::checkAccess('dc/inward-index') ? 'Inward DCs' : null,
    'delivery-product/index'    => UserAccess::checkAccess('delivery-product/index') ? 'Products' : null,
]);
?>
<div class="ui-page dc-index">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'     => 'Delivery challans',
        'sub'       => number_format($total) . ' outward DC' . ($total == 1 ? '' : 's'),
        'actions'   => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>New DC', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : '',
        'route'     => 'index',
        'countKeys' => ['dc_no', 'client_name', 'company_name', 'dc_type'],
        'filters'   => [
            ['type' => 'text', 'name' => 'dc_no', 'label' => 'DC no.', 'placeholder' => 'e.g. 1042', 'col' => 'col-lg-2 col-md-4 col-6'],
            ['type' => 'select', 'name' => 'client_name', 'label' => 'Client', 'data' => $clientOptions, 'placeholder' => 'All clients', 'col' => 'col-lg-3 col-md-8'],
            ['type' => 'select', 'name' => 'company_name', 'label' => 'Company', 'data' => $companyOptions, 'placeholder' => 'All companies', 'col' => 'col-lg-3 col-md-6'],
            ['type' => 'dropdown', 'name' => 'dc_type', 'label' => 'Type', 'data' => [1 => 'Returnable', 2 => 'Sale', 3 => 'Service'], 'col' => 'col-lg-2 col-md-3 col-6'],
            ['type' => 'dropdown', 'name' => 'status', 'label' => 'Status', 'data' => ['active' => 'Active', 'cancelled' => 'Cancelled'], 'col' => 'col-lg-2 col-md-3 col-6'],
        ],
    ]) ?>

    <?php if (count($tabs) > 1): ?>
    <nav class="ui-tabs" aria-label="Delivery">
        <?php foreach ($tabs as $r => $label): ?>
            <?= Html::a($label, ['/' . $r], ['class' => $r === 'dc/index' ? 'active' : '', 'aria-current' => $r === 'dc/index' ? 'page' : null]) ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>DC</th><th>Client</th><th>Type</th><th>Status</th><th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="6" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilter
                            ? ['icon' => 'search', 'title' => 'No delivery challans match your search', 'action' => Html::a('Clear search', ['index'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'truck', 'title' => 'No delivery challans yet', 'text' => 'Challans you raise appear here.',
                               'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>New DC', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $m):
                    $t          = (int)$m->dc_type;
                    $cancelled  = (int)$m->status === 2;
                    $returnable = $t === 1;
                    $returned   = !empty($m->dc_returned_date) && $m->dc_returned_date !== '0000-00-00';
                    $overdue    = $returnable && !$returned && !$cancelled && $m->dc_return_date && strtotime($m->dc_return_date) < strtotime('today');
                    $no         = $m->dc_no ?: 'Draft';
                ?>
                    <tr<?= $cancelled ? ' class="is-cancelled"' : '' ?>>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title"><span class="mono"><?= $can['view'] ? Html::a('DC ' . Html::encode($no), ['view', 'id' => $m->id], ['class' => 'text-reset']) : 'DC ' . Html::encode($no) ?></span>
                            <div class="small text-muted fw-normal"><?= $m->dc_date ? date('d M Y', strtotime($m->dc_date)) : '—' ?></div></td>
                        <td data-label="Client"><span class="cell-stack"><?= Html::encode($m->client->company_name ?? '—') ?><span class="d-block small text-muted"><?= Html::encode($m->client->client_name ?? '') ?></span></span></td>
                        <td data-label="Type"><span class="cell-stack">
                            <span class="ui-pill tone-<?= $types[$t][1] ?? 'neutral' ?> plain"><?= $types[$t][0] ?? '—' ?></span>
                            <?php if ($returnable): ?>
                                <span class="d-block small mt-1 <?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $returned ? 'Returned ' . date('d M Y', strtotime($m->dc_returned_date))
                                    : ($m->dc_return_date ? 'Due back ' . date('d M Y', strtotime($m->dc_return_date)) : 'Not returned') ?></span>
                            <?php endif; ?>
                        </span></td>
                        <td data-label="Status"><?= $cancelled ? '<span class="ui-pill tone-neutral" title="' . Html::encode($m->cancel_reason) . '">Cancelled</span>'
                            : ($overdue ? '<span class="ui-pill tone-danger">Return overdue</span>' : '<span class="ui-pill tone-success">Active</span>') ?></td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for DC <?= Html::encode($no) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye fa-fw me-1"></i>View', ['view', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['pdf'] && !$cancelled): ?><li><?= Html::a('<i class="fas fa-file-pdf fa-fw me-1"></i>Download PDF', ['challan-pdf', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['return'] && !$cancelled && $returnable && !$returned): ?>
                                        <li><a href="#" class="dropdown-item" data-dc-return data-id="<?= $m->id ?>" data-no="<?= Html::encode($no) ?>" data-dc-date="<?= Html::encode($m->dc_date) ?>"><i class="fas fa-undo fa-fw me-1"></i>Mark returned</a></li>
                                    <?php endif; ?>
                                    <?php if ($can['cancel'] && !$cancelled): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a href="#" class="dropdown-item text-danger" data-dc-cancel data-id="<?= $m->id ?>" data-no="<?= Html::encode($no) ?>"><i class="fas fa-ban fa-fw me-1"></i>Cancel DC</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'DCs']) ?>
    </div>

    <?= $this->render('_action-modals') ?>
</div>
