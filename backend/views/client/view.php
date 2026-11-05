<?php
/** @var \yii\web\View $this */
/** @var \backend\models\ClientModel $model */
/** @var \yii\data\ArrayDataProvider $workProvider */
/** @var array $totals — keys cur, last, all (all in seconds) */
/** @var \backend\models\Domain[] $domains */
/** @var \backend\models\Invoice[] $invoices */

use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = $model->client_name;

// work_report.total_time is stored in SECONDS — render as HH:MM (Yii1 parity).
$fmt = fn(int $secs): string => $secs > 0 ? sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60)) : '—';
$val = fn($v) => trim((string)$v) === '' ? '<span class="text-muted">—</span>' : Html::encode($v);

$payLabels = [0 => 'Unpaid', 1 => 'Partial', 2 => 'Paid'];
$payTones  = [0 => 'danger', 1 => 'warning', 2 => 'success'];
$invTypes  = [1 => ['Tax Invoice', 'accent'], 2 => ['Proforma', 'neutral'], 3 => ['Credit Note', 'warning']];

$can = [
    'update'      => UserAccess::checkAccess('client/update'),
    'reset'       => UserAccess::checkAccess('client/reset-password'),
    'permissions' => UserAccess::checkAccess('client/access-permissions'),
    'delete'      => UserAccess::checkAccess('client/delete'),
    'invoice'     => UserAccess::checkAccess('invoice/create'),
];
$granted = array_filter(explode(',', (string)$model->access_permissions));
$curLabel  = date('M Y');
$lastLabel = date('M Y', strtotime(date('Y-m-01') . ' -1 month'));
?>
<div class="ui-page client-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->client_name) ?></h1>
            <div class="ui-sub">
                <?= Html::encode($model->company_name) ?>
                <?php if (isset(\backend\models\ClientModel::typeOptions()[(int)$model->client_type])): ?> · <?= $model->getTypeName() ?><?php endif; ?>
            </div>
        </div>
        <div class="ui-actions">
            <?php if ($can['update']): ?>
                <?= Html::a('<i class="fas fa-pen me-1"></i>Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if ($can['invoice'] || $can['reset'] || $can['permissions'] || $can['delete']): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More</button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:210px">
                    <?php if ($can['invoice']): ?>
                        <li><?= Html::a('<i class="fas fa-file-alt me-2"></i>Add Tax Invoice', ['/invoice/create', 'client_id' => $model->id, 'invoice_role' => 1], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                        <li><?= Html::a('<i class="fas fa-clipboard me-2"></i>Add Proforma Invoice', ['/invoice/create', 'client_id' => $model->id, 'invoice_role' => 2], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                    <?php endif; ?>
                    <?php if ($can['reset']): ?>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#resetModal"
                               data-url="<?= Url::to(['reset-password', 'id' => $model->id]) ?>" data-name="<?= Html::encode($model->client_name) ?>" data-return="view">
                            <i class="fas fa-key me-2"></i>Reset Password</a></li>
                    <?php endif; ?>
                    <?php if ($can['permissions']): ?>
                        <li><?= Html::a('<i class="fas fa-user-shield me-2"></i>Portal Permissions', ['access-permissions', 'id' => $model->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                    <?php endif; ?>
                    <?php if ($can['delete']): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><?= Html::a('<i class="fas fa-ban me-2"></i>Deactivate', ['delete', 'id' => $model->id], [
                            'class' => 'dropdown-item text-danger', 'encode' => false,
                            'data'  => ['method' => 'post', 'confirm' => 'Deactivate "' . $model->client_name . '"? The client is hidden from lists and can no longer log in to the portal.'],
                        ]) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">Contact</div>
                <table class="table ui-detail">
                    <tr><th scope="row">Client Name</th><td><?= $val($model->client_name) ?></td></tr>
                    <tr><th scope="row">Company</th><td><?= $val($model->company_name) ?></td></tr>
                    <tr><th scope="row">Contact Person</th><td><?= $val($model->contact_person) ?></td></tr>
                    <tr><th scope="row">Phone</th><td><?= $val($model->phone) ?></td></tr>
                    <tr><th scope="row">Alt Phone</th><td><?= $val($model->alt_phone) ?></td></tr>
                    <tr><th scope="row">Email</th><td><?= $val($model->email_id) ?></td></tr>
                    <tr><th scope="row">Address</th><td><?= trim((string)$model->address) !== '' ? nl2br(Html::encode($model->address)) : '<span class="text-muted">—</span>' ?></td></tr>
                </table>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header ui-section">Tax &amp; portal</div>
                <table class="table ui-detail">
                    <tr><th scope="row">Client Type</th><td><?= isset(\backend\models\ClientModel::typeOptions()[(int)$model->client_type]) ? Html::encode($model->getTypeName()) : '<span class="text-muted">Not set</span>' ?></td></tr>
                    <tr><th scope="row">GST Registration</th><td><?= $val($model->getRegistrationTypeName() === '—' ? '' : $model->getRegistrationTypeName()) ?></td></tr>
                    <tr><th scope="row">GST No</th><td><?= $val($model->gst_no) ?></td></tr>
                    <tr><th scope="row">Tax Exemption</th><td>
                        <?= (int)$model->tax_exemption === 1 ? 'Yes' : 'No' ?>
                        <?php if ((int)$model->tax_exemption === 1 && trim((string)$model->tax_exemption_reason) !== ''): ?>
                            <div class="small text-muted"><?= Html::encode($model->tax_exemption_reason) ?></div>
                        <?php endif; ?>
                    </td></tr>
                    <tr><th scope="row">Portal Username</th><td><?= $val($model->user->username ?? '') ?></td></tr>
                    <tr><th scope="row">Portal Access</th><td>
                        <?php if ($granted): ?>
                            <span class="ui-pill tone-success"><?= count($granted) ?> page<?= count($granted) == 1 ? '' : 's' ?></span>
                        <?php else: ?>
                            <span class="ui-pill">No access</span>
                        <?php endif; ?>
                        <?php if ($can['permissions']): ?>
                            <?= Html::a('Manage', ['access-permissions', 'id' => $model->id], ['class' => 'small ms-2']) ?>
                        <?php endif; ?>
                    </td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Working hours -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section">
            <span>Working hours</span>
            <span class="ui-section-meta">All time <?= $fmt((int)$totals['all']) ?></span>
        </div>
        <?php $pageRows = $workProvider->getModels(); $pagination = $workProvider->getPagination(); ?>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th>Project</th>
                    <th class="text-center"><?= $curLabel ?></th>
                    <th class="text-center"><?= $lastLabel ?></th>
                    <th class="text-center">All time</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$pageRows): ?>
                    <tr class="ui-empty-row"><td colspan="4" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'clock', 'title' => 'No work logged yet', 'small' => true,
                            'text' => 'Hours appear here once work reports are filed against this client.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($pageRows as $wr): ?>
                    <tr>
                        <td class="cell-title wrap"><?= Html::encode($wr['project_title'] ?: '—') ?></td>
                        <td class="text-center" data-label="<?= $curLabel ?>"><?= $fmt((int)$wr['cur_secs']) ?></td>
                        <td class="text-center" data-label="<?= $lastLabel ?>"><?= $fmt((int)$wr['last_secs']) ?></td>
                        <td class="text-center fw-semibold" data-label="All time"><?= $fmt((int)$wr['total_secs']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($workProvider->getTotalCount() > 1): ?>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Total (all projects)</td>
                        <td class="text-center"><?= $fmt((int)$totals['cur']) ?></td>
                        <td class="text-center"><?= $fmt((int)$totals['last']) ?></td>
                        <td class="text-center"><?= $fmt((int)$totals['all']) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php if ($pagination && $pagination->getPageCount() > 1): ?>
        <div class="card-footer bg-white ui-pager">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= $pagination->getOffset() + count($pageRows) ?> of <?= $workProvider->getTotalCount() ?> projects</small>
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

    <!-- Domains -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header ui-section"><span>Domains<?= $domains ? ' (' . count($domains) . ')' : '' ?></span></div>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Domain</th><th>Registered</th><th>Expires</th></tr>
                </thead>
                <tbody>
                <?php if (!$domains): ?>
                    <tr class="ui-empty-row"><td colspan="4" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'globe', 'title' => 'No domains', 'small' => true]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($domains as $i => $dom): ?>
                    <?php $expired = $dom->expiry_date && strtotime($dom->expiry_date) < strtotime('today'); ?>
                    <tr>
                        <td class="cell-num muted"><?= $i + 1 ?></td>
                        <td class="cell-title">
                            <?= Html::a(Html::encode($dom->domain_name ?: '—'), ['/domain/view', 'id' => $dom->id], ['class' => 'text-reset text-decoration-none']) ?>
                        </td>
                        <td class="small" data-label="Registered"><?= $dom->date_of_purchase ? date('d M Y', strtotime($dom->date_of_purchase)) : '—' ?></td>
                        <td class="small" data-label="Expires">
                            <?= $dom->expiry_date ? date('d M Y', strtotime($dom->expiry_date)) : '—' ?>
                            <?php if ($expired): ?><span class="ui-pill plain tone-danger ms-1">Expired</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Invoices -->
    <div class="card border-0 shadow-sm">
        <div class="card-header ui-section">
            <span>Invoices<?= $invoices ? ' (' . count($invoices) . ')' : '' ?></span>
            <?php if ($can['invoice']): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Tax Invoice', ['/invoice/create', 'client_id' => $model->id, 'invoice_role' => 1], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Invoice No</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th>Payment</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$invoices): ?>
                    <tr class="ui-empty-row"><td colspan="8" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'file-invoice', 'title' => 'No invoices yet', 'small' => true]) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($invoices as $i => $inv): ?>
                    <?php
                    $ps      = (int)$inv->payment_status;
                    $type    = $invTypes[(int)$inv->invoice_type] ?? $invTypes[1];
                    $balance = $inv->getBalanceAmount();
                    ?>
                    <tr>
                        <td class="cell-num muted"><?= $i + 1 ?></td>
                        <td class="cell-title">
                            <?= Html::a(Html::encode($inv->invoice_no ?: 'INV-' . $inv->id), ['/invoice/view', 'id' => $inv->id], ['class' => 'text-reset text-decoration-none', 'target' => '_blank', 'rel' => 'noopener']) ?>
                        </td>
                        <td class="small nowrap" data-label="Date"><?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?></td>
                        <td data-label="Type"><span class="ui-pill plain tone-<?= $type[1] ?>"><?= $type[0] ?></span></td>
                        <td class="text-end nowrap" data-label="Amount">&#8377;<?= number_format((float)$inv->net_total, 2) ?></td>
                        <td class="text-end nowrap text-success" data-label="Paid">&#8377;<?= number_format((float)$inv->paid_amount, 2) ?></td>
                        <td class="text-end nowrap <?= $balance > 0 ? 'text-danger fw-semibold' : 'muted' ?>" data-label="Balance">&#8377;<?= number_format($balance, 2) ?></td>
                        <td data-label="Payment"><span class="ui-pill tone-<?= $payTones[$ps] ?? 'neutral' ?>"><?= $payLabels[$ps] ?? '—' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($can['reset']): ?>
        <?= $this->render('_reset-password-modal') ?>
    <?php endif; ?>
</div>
