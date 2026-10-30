<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */
/** @var string $searchName */
/** @var string $clientName */
/** @var string $companyName */
/** @var string $phone */
/** @var string $email */
/** @var string $gstNo */
/** @var string $clientType */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Clients';

// work_report.total_time is stored in SECONDS (Yii1 parity).
// Render as HH:MM, matching Yii1's Utility::convertSecondsToHours().
$fmt = fn(?int $secs): string => ($secs > 0)
    ? sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60))
    : '—';

$typeLabels = [1 => 'Inter-State', 2 => 'Intra-State', 3 => 'International'];
$typeColors = [1 => 'primary', 2 => 'info', 3 => 'success'];

$models = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
?>

<style>
.client-table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; font-weight: 600; border-bottom: 2px solid #dee2e6; white-space: nowrap; }
.client-table td { vertical-align: middle; font-size: .875rem; }
.client-table tbody tr:hover { background: #f8f9ff; }
.client-table td.client-name-cell { min-width: 160px; max-width: 260px; }
.client-table td.client-name-cell a.fw-semibold { display: inline-block; word-break: break-word; overflow-wrap: anywhere; white-space: normal; line-height: 1.25; }
.action-btn { padding: .2rem .55rem; font-size: .78rem; }
.pagination { --bs-pagination-font-size: .825rem; }
.pagination .page-link { border-radius: 6px !important; margin: 0 2px; }
/* Keep global layout's overflow:visible on desktop so the action dropdown
   can escape the table; only enable horizontal scroll on narrow screens
   where the 9-column table actually overflows (overflow-x:auto would
   otherwise force overflow-y to clip the dropdown). */
@media (max-width: 767.98px) {
    .client-index .table-responsive { overflow-x: auto !important; }
    .client-table { min-width: 720px; }
    .client-table td { font-size: .82rem; }
}
</style>

<div class="client-index">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Clients</h5>
            <small class="text-muted"><?= count($models) ?> of <?= $dataProvider->getTotalCount() ?> clients</small>
        </div>
        <?= Html::a('+ Add Client', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Filters (Yii1 parity: client_name, company_name, phone, email, gst, type) -->
    <?php
    $hasAnyFilter = ($searchName !== '' || $clientName !== '' || $companyName !== ''
        || $phone !== '' || $email !== '' || $gstNo !== '' || $clientType !== '');
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" action="<?= Url::to(['index']) ?>" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Client Name</label>
                    <input type="text" name="client_name" value="<?= Html::encode($clientName) ?>"
                           class="form-control form-control-sm" placeholder="Client name…">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Company</label>
                    <input type="text" name="company_name" value="<?= Html::encode($companyName) ?>"
                           class="form-control form-control-sm" placeholder="Company…">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Phone</label>
                    <input type="text" name="phone" value="<?= Html::encode($phone) ?>"
                           class="form-control form-control-sm" placeholder="Phone…">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Email</label>
                    <input type="text" name="email_id" value="<?= Html::encode($email) ?>"
                           class="form-control form-control-sm" placeholder="Email…">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">GST No.</label>
                    <input type="text" name="gst_no" value="<?= Html::encode($gstNo) ?>"
                           class="form-control form-control-sm" placeholder="GST…">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Type</label>
                    <select name="client_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="1" <?= $clientType === '1' ? 'selected' : '' ?>>Inter-State</option>
                        <option value="2" <?= $clientType === '2' ? 'selected' : '' ?>>Intra-State</option>
                        <option value="3" <?= $clientType === '3' ? 'selected' : '' ?>>International</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 align-items-center">
                    <button class="btn btn-secondary btn-sm">Search</button>
                    <?php if ($hasAnyFilter): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table card -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table client-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Client</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th class="text-center">Last Work</th>
                        <th class="text-center">Cur Month</th>
                        <th class="text-center">Prev Month</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            No clients found.
                            <?= $searchName ? Html::a('Clear search', ['index']) : '' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    foreach ($models as $i => $row):
                        $type = (int)($row['client_type'] ?? 0);
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>

                        <!-- Client name -->
                        <td class="client-name-cell">
                            <a href="<?= Url::to(['view', 'id' => $row['id']]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($row['client_name']) ?>
                            </a>
                        </td>

                        <td><?= Html::encode($row['company_name']) ?></td>

                        <!-- Contact -->
                        <td>
                            <?php if ($row['phone']): ?>
                                <div class="text-muted small"><?= Html::encode($row['phone']) ?></div>
                            <?php endif; ?>
                            <?php if ($row['email_id']): ?>
                                <div class="text-muted small"><?= Html::encode($row['email_id']) ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Type badge -->
                        <td>
                            <?php if ($type && isset($typeLabels[$type])): ?>
                                <span class="badge bg-<?= $typeColors[$type] ?? 'secondary' ?> bg-opacity-10 text-<?= $typeColors[$type] ?? 'secondary' ?> border border-<?= $typeColors[$type] ?? 'secondary' ?>" style="font-size:.72rem">
                                    <?= $typeLabels[$type] ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Work stats -->
                        <td class="text-center">
                            <?php if ($row['last_work_date']): ?>
                                <span class="badge bg-light text-dark border"><?= date('d M Y', strtotime($row['last_work_date'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-semibold <?= $row['cur_month_secs'] > 0 ? 'text-success' : 'text-muted' ?>">
                            <?= $fmt((int)$row['cur_month_secs']) ?>
                        </td>
                        <td class="text-center text-muted">
                            <?= $fmt((int)$row['last_month_secs']) ?>
                        </td>

                        <!-- Actions dropdown -->
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary action-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    &#8942;
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:180px;font-size:.825rem">
                                    <li>
                                        <?= Html::a('<i class="fas fa-eye"></i> View Details', ['view', 'id' => $row['id']],
                                            ['class' => 'dropdown-item', 'encode' => false]) ?>
                                    </li>
                                    <li>
                                        <?= Html::a('<i class="fas fa-pen"></i> Edit Client', ['update', 'id' => $row['id']],
                                            ['class' => 'dropdown-item', 'encode' => false]) ?>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><span class="dropdown-header" style="font-size:.7rem">INVOICES</span></li>
                                    <li>
                                        <?= Html::a('<i class="fas fa-file-alt"></i> Add Tax Invoice',
                                            ['/invoice/create', 'client_id' => $row['id'], 'invoice_role' => 1],
                                            ['class' => 'dropdown-item', 'encode' => false]) ?>
                                    </li>
                                    <li>
                                        <?= Html::a('<i class="fas fa-clipboard"></i> Add Proforma Invoice',
                                            ['/invoice/create', 'client_id' => $row['id'], 'invoice_role' => 2],
                                            ['class' => 'dropdown-item', 'encode' => false]) ?>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><span class="dropdown-header" style="font-size:.7rem">ACCOUNT</span></li>
                                    <li>
                                        <a class="dropdown-item" href="#"
                                           data-bs-toggle="modal"
                                           data-bs-target="#resetModal"
                                           data-id="<?= $row['id'] ?>"
                                           data-name="<?= Html::encode($row['client_name']) ?>">
                                            <i class="fas fa-key"></i> Reset Password
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><span class="dropdown-header" style="font-size:.7rem">PERMISSIONS</span></li>
                                    <li>
                                        <?= Html::a('<i class="fas fa-key"></i> Portal Permissions',
                                            ['access-permissions', 'id' => $row['id']],
                                            ['class' => 'dropdown-item', 'encode' => false]) ?>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <?= Html::a('<i class="fas fa-ban"></i> Deactivate',
                                            ['delete', 'id' => $row['id']],
                                            [
                                                'class'       => 'dropdown-item text-danger',
                                                'encode'      => false,
                                                'data-method' => 'post',
                                                'data-confirm'=> 'Deactivate ' . Html::encode($row['client_name']) . '?',
                                            ]
                                        ) ?>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination footer -->
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 py-2 d-flex justify-content-between align-items-center">
            <small class="text-muted">
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $dataProvider->getTotalCount()) ?>
                of <?= $dataProvider->getTotalCount() ?>
            </small>
            <?php echo LinkPager::widget([
                'pagination'        => $pagination,
                'options'           => ['class' => 'pagination mb-0'],
                'linkOptions'       => ['class' => 'page-link'],
                'linkContainerOptions' => ['class' => 'page-item'],
                'disabledListItemSubTagOptions' => ['tag' => 'a', 'class' => 'page-link'],
                'activePageCssClass'  => 'active',
                'disabledPageCssClass'=> 'disabled',
                'maxButtonCount'    => 7,
                'firstPageLabel'    => '«',
                'lastPageLabel'     => '»',
                'prevPageLabel'     => '‹',
                'nextPageLabel'     => '›',
            ]);
            ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post" id="resetForm" action="">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Reset Password</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Client: <strong id="resetClientName"></strong></p>
                    <input type="password" name="new_password" class="form-control form-control-sm"
                           placeholder="New password" required minlength="6" autocomplete="new-password">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('resetModal').addEventListener('show.bs.modal', function(e) {
    var btn    = e.relatedTarget;
    var id     = btn.getAttribute('data-id');
    var name   = btn.getAttribute('data-name');
    var url    = <?= json_encode(Url::to(['/client/reset-password'])) ?>;
    document.getElementById('resetClientName').textContent = name;
    document.getElementById('resetForm').action = url + '?id=' + id;
});
</script>
