<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $model */
/** @var \yii\data\ArrayDataProvider $workProvider */
/** @var array $totals — keys cur, last, all (all in seconds) */
/** @var \backend\models\Domain[] $domains */
/** @var \backend\models\Invoice[] $invoices */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = $model->client_name;

// work_report.total_time is stored in SECONDS — render as HH:MM (Yii1 parity).
// Function name kept for backwards compatibility with downstream calls.
$fmtMins = function (int $secs): string {
    if ($secs <= 0) return '—';
    return sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
};

$domainStatusLabels = [1=>'Live',2=>'Expired',3=>'Need Follow',4=>'Pending Payment',5=>'Pending Renewal'];
$domainStatusColors = [1=>'success',2=>'danger',3=>'warning',4=>'secondary',5=>'info'];

$payLabels = [0=>'Unpaid',1=>'Partial',2=>'Paid'];
$payColors = [0=>'danger',1=>'warning',2=>'success'];

$typeLabels = [1=>'Inter-State', 2=>'Intra-State', 3=>'International'];
?>
<style>
.sec-card{margin-bottom:1.25rem}
.sec-card .card-header{font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;background:#f8f9fa;border-bottom:1px solid #dee2e6;padding:.55rem .85rem}
.tbl th{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.tbl td{vertical-align:middle;font-size:.865rem}
.tbl tfoot td{font-weight:700;border-top:2px solid #dee2e6;font-size:.865rem}
</style>

<div class="client-view">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($model->client_name) ?></h5>
            <small class="text-muted"><?= Html::encode($model->company_name) ?></small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-warning btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'],                     ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <!-- Profile -->
    <div class="card border-0 shadow-sm sec-card">
        <div class="card-header">Profile</div>
        <div class="card-body py-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><th class="text-muted fw-normal" style="width:160px">Client Name</th><td><?= Html::encode($model->client_name) ?></td></tr>
                        <tr><th class="text-muted fw-normal">Company</th><td><?= Html::encode($model->company_name) ?></td></tr>
                        <tr><th class="text-muted fw-normal">Contact Person</th><td><?= Html::encode($model->contact_person ?: '—') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Phone</th><td><?= Html::encode($model->phone) ?><?= $model->alt_phone ? ' / ' . Html::encode($model->alt_phone) : '' ?></td></tr>
                        <tr><th class="text-muted fw-normal">Email</th><td><?= Html::encode($model->email_id) ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><th class="text-muted fw-normal" style="width:160px">GST Registration</th><td><?= Html::encode($model->getRegistrationTypeName()) ?></td></tr>
                        <tr><th class="text-muted fw-normal">GST No</th><td><?= Html::encode($model->gst_no ?: '—') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Client Type</th><td><?= $typeLabels[$model->client_type] ?? '—' ?></td></tr>
                        <tr><th class="text-muted fw-normal">Tax Exemption</th><td><?= $model->tax_exemption ? 'Yes' : 'No' ?><?= ($model->tax_exemption && $model->tax_exemption_reason) ? ' — <em>' . Html::encode($model->tax_exemption_reason) . '</em>' : '' ?></td></tr>
                        <tr><th class="text-muted fw-normal">Username</th><td><?= Html::encode($model->user->username ?? '—') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Address</th><td><?= nl2br(Html::encode($model->address)) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Working Hours -->
    <div class="card border-0 shadow-sm sec-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Working Hours</span>
            <small class="text-muted fw-normal"><?= date('M Y') ?> / <?= date('M Y', strtotime('-1 month')) ?></small>
        </div>
        <div class="table-responsive">
            <table class="table tbl mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Project</th>
                        <th class="text-center">Current Month</th>
                        <th class="text-center">Last Month</th>
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $pageRows = $workProvider->getModels();
                $pagination = $workProvider->getPagination();
                ?>
                <?php if (empty($pageRows)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No work reports yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($pageRows as $wr): ?>
                    <tr>
                        <td><?= Html::encode($wr['project_title'] ?? '—') ?></td>
                        <td class="text-center"><?= $fmtMins((int)$wr['cur_secs']) ?></td>
                        <td class="text-center"><?= $fmtMins((int)$wr['last_secs']) ?></td>
                        <td class="text-center fw-semibold"><?= $fmtMins((int)$wr['total_secs']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if ($workProvider->getTotalCount() > 0): ?>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="text-center"><?= $fmtMins((int)$totals['cur']) ?></td>
                        <td class="text-center"><?= $fmtMins((int)$totals['last']) ?></td>
                        <td class="text-center"><?= $fmtMins((int)$totals['all']) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            <?php if ($pagination && $pagination->pageCount > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $workProvider->getTotalCount()) ?>
                    of <?= $workProvider->getTotalCount() ?>
                </small>
                <?= \yii\widgets\LinkPager::widget([
                    'pagination'     => $pagination,
                    'options'        => ['class' => 'pagination pagination-sm mb-0'],
                    'firstPageLabel' => '«', 'lastPageLabel' => '»',
                    'prevPageLabel'  => '‹', 'nextPageLabel' => '›',
                    'maxButtonCount' => 5,
                ]) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Domains -->
    <div class="card border-0 shadow-sm sec-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Domains</span>
            <small class="text-muted fw-normal"><?= count($domains) ?> domain<?= count($domains) != 1 ? 's' : '' ?></small>
        </div>
        <div class="table-responsive">
            <table class="table tbl mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Domain</th>
                        <th>Registration Date</th>
                        <th>Expiration Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($domains)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No domains found.</td></tr>
                <?php else: ?>
                    <?php foreach ($domains as $i => $dom): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-semibold">
                            <a href="<?= Url::to(['/domain/view', 'id' => $dom->id]) ?>" class="text-decoration-none">
                                <?= Html::encode($dom->domain_name) ?>
                            </a>
                        </td>
                        <td class="text-muted small"><?= $dom->date_of_purchase ? date('d-m-Y', strtotime($dom->date_of_purchase)) : '—' ?></td>
                        <td class="text-muted small"><?= $dom->expiry_date ? date('d-m-Y', strtotime($dom->expiry_date)) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Invoices -->
    <div class="card border-0 shadow-sm sec-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Invoices</span>
            <small class="text-muted fw-normal"><?= count($invoices) ?> invoice<?= count($invoices) != 1 ? 's' : '' ?></small>
        </div>
        <div class="table-responsive">
            <table class="table tbl mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Payment</th>
                        <th class="text-end" style="width:60px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No invoices found.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $i => $inv): $ps = (int)$inv->payment_status; ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td>
                            <a href="<?= Url::to(['/invoice/view', 'id' => $inv->id]) ?>" class="text-decoration-none fw-semibold">
                                <?= Html::encode($inv->invoice_no ?: 'INV-' . $inv->id) ?>
                            </a>
                        </td>
                        <td class="text-muted small"><?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?></td>
                        <td>
                            <?php if ($inv->invoice_type == 2): ?>
                                <span class="badge bg-info-subtle text-info border" style="font-size:.72rem">Proforma</span>
                            <?php elseif ($inv->invoice_type == 3): ?>
                                <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.72rem">Credit Note</span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary border" style="font-size:.72rem">Tax Invoice</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">&#8377;<?= number_format((float)$inv->net_total, 2) ?></td>
                        <td class="text-end text-success">&#8377;<?= number_format((float)$inv->paid_amount, 2) ?></td>
                        <td class="text-end <?= $inv->getBalanceAmount() > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            &#8377;<?= number_format($inv->getBalanceAmount(), 2) ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $payColors[$ps] ?? 'secondary' ?>-subtle text-<?= $payColors[$ps] ?? 'secondary' ?> border" style="font-size:.72rem">
                                <?= $payLabels[$ps] ?? '—' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?= Html::a('<i class="fas fa-eye"></i>', ['/invoice/view', 'id' => $inv->id], ['class' => 'btn btn-outline-secondary btn-sm py-0 px-1', 'encode' => false, 'title' => 'View']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
