<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $clientOptions */
/** @var array $filters */

use backend\models\PoDetail;
use common\components\UserAccess;
use yii\bootstrap5\Html;

$this->title = 'Purchase Orders';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$hasFilter  = $filters['search'] !== '' || $filters['status'] !== '' || $filters['client_id'] > 0;
$tone       = [PoDetail::STATUS_PENDING => 'warning', PoDetail::STATUS_IN_PROGRESS => 'accent', PoDetail::STATUS_DELIVERED => 'success', PoDetail::STATUS_CANCELLED => 'neutral'];
$can = [
    'create' => UserAccess::checkAccess('po/create'),
    'view'   => UserAccess::checkAccess('po/view'),
    'update' => UserAccess::checkAccess('po/update'),
];
$value = 0.0;
foreach ($rows as $po) {
    $value += (int)$po->po_status !== PoDetail::STATUS_CANCELLED ? (float)$po->po_cost : 0;
}
?>
<div class="ui-page po-index">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'     => 'Purchase orders',
        'sub'       => number_format($total) . ' PO' . ($total == 1 ? '' : 's') . ' received from clients',
        'meta'      => $rows ? '<span class="ui-total">This page <strong>₹' . number_format($value, 0) . '</strong></span>' : '',
        'actions'   => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add PO', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : '',
        'route'     => 'index',
        'countKeys' => ['search', 'status', 'client_id'],
        'filters'   => [
            ['type' => 'text', 'name' => 'search', 'label' => 'PO no., company or particulars', 'placeholder' => 'Search', 'col' => 'col-lg-4 col-md-6'],
            ['type' => 'select', 'name' => 'client_id', 'label' => 'Client', 'data' => $clientOptions, 'placeholder' => 'All clients', 'col' => 'col-lg-3 col-md-6'],
            ['type' => 'dropdown', 'name' => 'status', 'label' => 'Status', 'data' => PoDetail::statusOptions(), 'col' => 'col-lg-2 col-md-4'],
        ],
    ]) ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th><th>PO</th><th>Client</th><th>Particulars</th>
                    <th class="text-end">Value (₹)</th><th>Delivery</th><th>Status</th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="8" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilter
                            ? ['icon' => 'search', 'title' => 'No purchase orders match your search', 'action' => Html::a('Clear search', ['index'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'file-signature', 'title' => 'No purchase orders yet', 'text' => 'Record POs as clients send them.',
                               'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add PO', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $po): /** @var PoDetail $po */
                    $client = $po->getEffectiveClient();
                    $late   = $po->delivery_date && (int)$po->po_status < PoDetail::STATUS_DELIVERED && strtotime($po->delivery_date) < strtotime('today');
                ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title"><span class="mono"><?= $can['view'] ? Html::a(Html::encode($po->po_number), ['view', 'id' => $po->id], ['class' => 'text-reset']) : Html::encode($po->po_number) ?></span>
                            <div class="small text-muted fw-normal"><?= $po->date ? date('d M Y', strtotime($po->date)) : '—' ?><?= $po->quote_id ? ' · from quotation' : '' ?></div></td>
                        <td data-label="Client"><span class="cell-stack"><?= Html::encode($client->company_name ?? '') ?: '<span class="text-muted">—</span>' ?>
                            <?php if (!empty($client->client_name)): ?><span class="d-block small text-muted"><?= Html::encode($client->client_name) ?></span><?php endif; ?></span></td>
                        <td class="small" data-label="Particulars"><span class="d-inline-block text-truncate" style="max-width:240px" title="<?= Html::encode($po->particulars) ?>"><?= Html::encode($po->particulars) ?></span></td>
                        <td class="text-end num fw-semibold" data-label="Value (₹)"><?= number_format((float)$po->po_cost, 0) ?></td>
                        <td class="text-nowrap<?= $late ? ' text-danger fw-semibold' : '' ?>" data-label="Delivery"><?= $po->delivery_date ? date('d M Y', strtotime($po->delivery_date)) : '—' ?><?= $late ? ' <span class="ui-pill tone-danger plain">Late</span>' : '' ?></td>
                        <td data-label="Status"><span class="ui-pill tone-<?= $tone[(int)$po->po_status] ?? 'neutral' ?>"><?= Html::encode(PoDetail::statusOptions()[(int)$po->po_status] ?? '—') ?></span></td>
                        <td class="cell-actions text-end">
                            <?php if ($can['view'] || $can['update']): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for PO <?= Html::encode($po->po_number) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye fa-fw me-1"></i>View', ['view', 'id' => $po->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['update']): ?><li><?= Html::a('<i class="fas fa-pen fa-fw me-1"></i>Edit', ['update', 'id' => $po->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($po->file): ?><li><?= Html::a('<i class="fas fa-paperclip fa-fw me-1"></i>PO file', \yii\helpers\Url::to('@web/' . ltrim($po->file, '/')), ['class' => 'dropdown-item', 'encode' => false, 'target' => '_blank', 'rel' => 'noopener']) ?></li><?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'POs']) ?>
    </div>
</div>
