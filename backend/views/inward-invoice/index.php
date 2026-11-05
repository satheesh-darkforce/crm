<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Vendor[] $vendors */
/** @var array $f        cleaned filters */
/** @var float $netTotal net total of every matching invoice */

use backend\models\InwardInvoice;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$this->title = 'Inward Invoices';

$rows       = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination ? $pagination->getOffset() : 0;
$hasFilter  = (bool)array_filter($f, fn($v) => $v !== '');
$inr        = fn($v) => '₹' . number_format((float)$v, 2);
$can = [
    'create'  => UserAccess::checkAccess('inward-invoice/create'),
    'view'    => UserAccess::checkAccess('inward-invoice/view'),
    'update'  => UserAccess::checkAccess('inward-invoice/update'),
    'delete'  => UserAccess::checkAccess('inward-invoice/delete'),
    'convert' => UserAccess::checkAccess('inward-invoice/convert-to-invoice'),
];
?>
<div class="ui-page inward-invoice-index">
    <?= $this->render('@backend/views/_ui/_head', [
        'title'   => 'Inward invoices',
        'sub'     => number_format($total) . ' invoice' . ($total == 1 ? '' : 's'),
        'meta'    => '<span class="ui-total">Net <strong>' . $inr($netTotal) . '</strong></span>',
        'actions' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add inward invoice', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : '',
        'route'   => 'index',
        'filters' => [
            ['type' => 'text', 'name' => 'q', 'label' => 'Invoice no. or title', 'placeholder' => 'Search', 'col' => 'col-lg-3 col-md-6'],
            ['type' => 'select', 'name' => 'vendor_id', 'label' => 'Vendor', 'data' => ArrayHelper::map($vendors, 'id', 'company_name'), 'placeholder' => 'All vendors', 'col' => 'col-lg-3 col-md-6'],
            ['type' => 'date', 'name' => 'from_date', 'label' => 'From', 'col' => 'col-lg-2 col-md-3 col-6'],
            ['type' => 'date', 'name' => 'to_date', 'label' => 'To', 'col' => 'col-lg-2 col-md-3 col-6'],
            ['type' => 'dropdown', 'name' => 'payment_status', 'label' => 'Payment', 'data' => InwardInvoice::paymentOptions() + ['none' => 'Not set'], 'col' => 'col-lg-2 col-md-3 col-6'],
            ['type' => 'dropdown', 'name' => 'invoice_role', 'label' => 'Type', 'data' => InwardInvoice::roleOptions(), 'col' => 'col-lg-2 col-md-3 col-6'],
        ],
    ]) ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Invoice</th>
                    <th>Vendor</th>
                    <th>Date</th>
                    <th class="text-end">Sub total</th>
                    <th class="text-end">GST</th>
                    <th class="text-end">Net total</th>
                    <th>Payment</th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="9" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilter
                            ? ['icon' => 'search', 'title' => 'No inward invoices match your search', 'text' => 'Try a wider date range or fewer filters.',
                               'action' => Html::a('Clear search', ['index'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'file-import', 'title' => 'No inward invoices yet', 'text' => 'Record bills from vendors here.',
                               'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>Add inward invoice', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $inv): /** @var InwardInvoice $inv */
                    $isProv = (int)$inv->invoice_role === InwardInvoice::ROLE_PROVISIONAL;
                ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title">
                            <?= $can['view'] ? Html::a(Html::encode($inv->invoice_number), ['view', 'id' => $inv->id], ['class' => 'text-reset']) : Html::encode($inv->invoice_number) ?>
                            <?php if ($isProv): ?><span class="ui-pill tone-warning plain ms-1">Provisional</span><?php endif; ?>
                            <?php if ($inv->title): ?><div class="small text-muted fw-normal text-truncate" style="max-width:260px" title="<?= Html::encode($inv->title) ?>"><?= Html::encode($inv->title) ?></div><?php endif; ?>
                        </td>
                        <td class="small" data-label="Vendor"><?= Html::encode($inv->vendor->company_name ?? '—') ?></td>
                        <td class="text-nowrap" data-label="Date"><?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?></td>
                        <td class="text-end num" data-label="Sub total"><?= $inr($inv->grand_total) ?></td>
                        <td class="text-end num text-muted" data-label="GST"><?= $inr((float)$inv->sgst_amount + (float)$inv->cgst_amount + (float)$inv->igst_amount) ?></td>
                        <td class="text-end num fw-semibold" data-label="Net total"><?= $inr($inv->net_total) ?></td>
                        <td data-label="Payment"><span class="ui-pill tone-<?= $inv->getPaymentTone() ?>"><?= Html::encode($inv->getPaymentLabel()) ?></span></td>
                        <td class="cell-actions text-end">
                            <?php if ($can['view'] || $can['update'] || $can['delete'] || ($isProv && $can['convert'])): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="Actions for invoice <?= Html::encode($inv->invoice_number) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye fa-fw me-1"></i>View', ['view', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($can['update']): ?><li><?= Html::a('<i class="fas fa-pen fa-fw me-1"></i>Edit', ['update', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php if ($isProv && $can['convert']): ?>
                                        <li><a href="#" class="dropdown-item" data-ii-convert="<?= $inv->id ?>" data-url="<?= Url::to(['convert-to-invoice', 'id' => $inv->id]) ?>"><i class="fas fa-check-double fa-fw me-1"></i>Mark actual</a></li>
                                    <?php endif; ?>
                                    <?php if ($can['delete']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-trash fa-fw me-1"></i>Delete', ['delete', 'id' => $inv->id], ['class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data' => ['method' => 'post', 'confirm' => 'Delete inward invoice ' . $inv->invoice_number . ' (' . $inr($inv->net_total) . ')?']]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows), 'noun' => 'invoices']) ?>
    </div>
</div>
