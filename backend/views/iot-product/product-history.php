<?php
/**
 * IoT Product Stock-Movement History (loaded into a Bootstrap modal via AJAX).
 *
 * @var \yii\web\View                $this
 * @var \backend\models\IotProduct    $model
 * @var array                        $history         rows: date, particular, in, out, balance, price_per_qty
 * @var array                        $purchaseStats   [max_qty, min_qty, avg_qty]
 * @var \backend\models\InwardInvoiceDetails|null $lastPurchase
 */

use yii\bootstrap5\Html;
use yii\helpers\HtmlPurifier;

$max = $purchaseStats['max_qty'] ?? null;
$min = $purchaseStats['min_qty'] ?? null;
$avg = $purchaseStats['avg_qty'] ?? null;
?>
<div class="iot-product-history">
    <div class="row g-2 mb-3 small">
        <div class="col-md-4"><strong>Product Name:</strong> <?= Html::encode($model->product_name ?: '-') ?></div>
        <div class="col-md-4"><strong>Part Number:</strong> <?= Html::encode($model->part_number ?: '-') ?></div>
        <div class="col-md-4"><strong>Product Code:</strong> <?= Html::encode($model->product_code ?: '-') ?></div>

        <div class="col-md-3"><strong>Minimum Qty:</strong> <?= $min !== null ? (int)$min : '-' ?></div>
        <div class="col-md-3"><strong>Maximum Qty:</strong> <?= $max !== null ? (int)$max : '-' ?></div>
        <div class="col-md-3"><strong>Average Qty:</strong> <?= $avg !== null ? number_format((float)$avg, 2, '.', '') : '-' ?></div>
        <div class="col-md-3"><strong>Last Purchase Qty:</strong> <?= $lastPurchase && $lastPurchase->qty !== null ? (int)$lastPurchase->qty : '-' ?></div>
    </div>

    <div class="table-responsive" style="max-height:420px;overflow-y:auto">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light" style="position:sticky;top:0;z-index:1">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Date</th>
                    <th>Particular</th>
                    <th class="text-center">IN</th>
                    <th class="text-center">OUT</th>
                    <th class="text-center">Balance</th>
                    <th class="text-end">Price / Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No stock movement recorded.</td></tr>
                <?php else: foreach ($history as $i => $row): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="small"><?= $row['date'] ? date('Y-m-d h:i A', strtotime($row['date'])) : '-' ?></td>
                        <td><?php
                            $particular = (string) ($row['particular'] ?? '');
                            // The DB stores anchor tags inside particular (e.g. "purchase (<a ...>SKE-1332/25-26</a>)").
                            // HtmlPurifier strips anything risky but keeps safe inline tags so the link renders.
                            echo $particular === ''
                                ? '-'
                                : HtmlPurifier::process($particular, [
                                    'HTML.Allowed' => 'a[href|title|target],br,b,strong,i,em',
                                    'HTML.Nofollow' => true,
                                ]);
                        ?></td>
                        <td class="text-center text-success fw-semibold"><?= $row['in']  !== null ? (int)$row['in']  : '-' ?></td>
                        <td class="text-center text-danger fw-semibold"><?= $row['out'] !== null ? (int)$row['out'] : '-' ?></td>
                        <td class="text-center"><?= (int)($row['balance'] ?? 0) ?></td>
                        <td class="text-end"><?= $row['price_per_qty'] !== null ? Html::encode($row['price_per_qty']) : '-' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
