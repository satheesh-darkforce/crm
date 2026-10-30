<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\ClientModel $client */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'My Invoices';

$paymentBadge = [0 => 'danger', 1 => 'warning', 2 => 'success'];
$paymentLabel = [0 => 'Unpaid', 1 => 'Partial', 2 => 'Paid'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="invoice-client">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">My Invoices</h5>
            <small class="text-muted"><?= $total ?> invoice<?= $total != 1 ? 's' : '' ?></small>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover list-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Invoice No</th>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Currency</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Pending</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($models)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($models as $i => $inv): ?>
                        <?php
                            $offset = $pagination ? $pagination->getOffset() : 0;
                            $rowNo  = $offset + $i + 1;
                            $ps     = (int)$inv->payment_status;
                        ?>
                        <tr>
                            <td><?= $rowNo ?></td>
                            <td><strong><?= Html::encode($inv->invoice_no ?: ('#' . $inv->id)) ?></strong></td>
                            <td><?= Html::encode($inv->invoice_date) ?></td>
                            <td><?= Html::encode($inv->title) ?></td>
                            <td><?= Html::encode($inv->currency) ?></td>
                            <td class="text-end"><?= number_format((float)$inv->net_total, 2) ?></td>
                            <td class="text-end"><?= number_format((float)$inv->paid_amount, 2) ?></td>
                            <td class="text-end"><?= number_format($inv->balanceAmount, 2) ?></td>
                            <td>
                                <span class="badge bg-<?= $paymentBadge[$ps] ?? 'secondary' ?>">
                                    <?= $paymentLabel[$ps] ?? 'Unknown' ?>
                                </span>
                            </td>
                            <td>
                                <?= Html::a('View', ['view', 'id' => $inv->id], [
                                    'class'  => 'btn btn-outline-primary btn-sm act-btn',
                                    'target' => '_blank',
                                ]) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($pagination && $pagination->pageCount > 1): ?>
    <div class="d-flex justify-content-center mt-3">
        <?= LinkPager::widget(['pagination' => $pagination]) ?>
    </div>
    <?php endif; ?>
</div>
