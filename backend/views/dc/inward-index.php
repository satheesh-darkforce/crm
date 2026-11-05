<?php
/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $clientOptions */
/** @var array $companyOptions */

use backend\models\DcDetail;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Inward Delivery Challans';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$q          = Yii::$app->request->get();
$hasFilter  = (bool)array_filter(array_intersect_key($q, array_flip(['inward_dc_no', 'client_name', 'company_name'])), fn($v) => $v !== '');
$can = [
    'create' => UserAccess::checkAccess('dc/create-inward'),
    'view'   => UserAccess::checkAccess('dc/inward-view'),
    'return' => UserAccess::checkAccess('dc/add-return'),
];
// One query for the whole page instead of two per row.
$returnedIds = $rows ? array_map('intval', DcDetail::find()->select('inward_dc_id')
    ->where(['type' => 1, 'status' => 1, 'inward_dc_id' => array_map(fn($m) => $m->id, $rows)])->column()) : [];
$tabs = array_filter([
    'quotation-dashboard/index' => UserAccess::checkAccess('quotation-dashboard/index') ? 'Dashboard' : null,
    'dc/index'                  => UserAccess::checkAccess('dc/index') ? 'Outward DCs' : null,
    'dc/inward-index'           => 'Inward DCs',
    'delivery-product/index'    => UserAccess::checkAccess('delivery-product/index') ? 'Products' : null,
]);
?>
<div class="ui-page inward-dc-index">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Inward delivery challans',
        'sub'     => number_format($total) . ' item' . ($total == 1 ? '' : 's') . ' received from clients (for repair, service…)',
        'actions' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>New inward DC', ['create-inward'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : '',
        'route'   => 'inward-index',
        'filters' => [
            ['type' => 'text', 'name' => 'inward_dc_no', 'label' => 'Client DC no.', 'placeholder' => 'As on their challan', 'col' => 'col-lg-3 col-md-4'],
            ['type' => 'select', 'name' => 'client_name', 'label' => 'Client', 'data' => $clientOptions, 'placeholder' => 'All clients', 'col' => 'col-lg-3 col-md-8'],
            ['type' => 'select', 'name' => 'company_name', 'label' => 'Company', 'data' => $companyOptions, 'placeholder' => 'All companies', 'col' => 'col-lg-3 col-md-6'],
        ],
    ]) ?>

    <?php if (count($tabs) > 1): ?>
    <nav class="ui-tabs" aria-label="Delivery">
        <?php foreach ($tabs as $r => $label): ?>
            <?= Html::a($label, ['/' . $r], ['class' => $r === 'dc/inward-index' ? 'active' : '', 'aria-current' => $r === 'dc/inward-index' ? 'page' : null]) ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Inward DC</th><th>Client</th><th>Expected return</th><th>Status</th><th class="text-end" style="width:150px"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="6" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilter
                            ? ['icon' => 'search', 'title' => 'No inward DCs match your search', 'action' => Html::a('Clear search', ['inward-index'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'dolly', 'title' => 'No inward DCs yet', 'text' => 'Record items clients send in, then raise a return DC when they go back.',
                               'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>New inward DC', ['create-inward'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $m):
                    $returned = in_array((int)$m->id, $returnedIds, true);
                    $overdue  = !$returned && $m->dc_return_date && strtotime($m->dc_return_date) < strtotime('today');
                ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title"><span class="mono"><?= Html::encode($m->inward_dc_no ?: '—') ?></span>
                            <div class="small text-muted fw-normal">Received <?= $m->dc_date ? date('d M Y', strtotime($m->dc_date)) : '—' ?></div></td>
                        <td data-label="Client"><span class="cell-stack"><?= Html::encode($m->client->company_name ?? '—') ?><span class="d-block small text-muted"><?= Html::encode($m->client->client_name ?? '') ?></span></span></td>
                        <td class="text-nowrap<?= $overdue ? ' text-danger fw-semibold' : '' ?>" data-label="Expected return"><?= $m->dc_return_date ? date('d M Y', strtotime($m->dc_return_date)) : '—' ?></td>
                        <td data-label="Status"><?= $returned ? '<span class="ui-pill tone-success">Returned</span>' : ($overdue ? '<span class="ui-pill tone-danger">Overdue</span>' : '<span class="ui-pill tone-warning">With us</span>') ?></td>
                        <td class="text-end text-nowrap" data-label="">
                            <?php if ($can['view']): ?><?= Html::a('View', ['inward-view', 'id' => $m->id], ['class' => 'btn btn-outline-secondary btn-sm']) ?><?php endif; ?>
                            <?php if ($can['return'] && !$returned): ?><?= Html::a('<i class="fas fa-undo me-1"></i>Return', ['add-return', 'id' => $m->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'inward DCs']) ?>
    </div>
</div>
