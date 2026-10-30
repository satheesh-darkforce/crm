<?php
/**
 * Issued Products listing (ports Yii1 issued_product.php).
 *
 * @var \yii\web\View $this
 * @var \yii\data\ActiveDataProvider $dataProvider
 * @var \backend\models\Developer[] $developers
 * @var \backend\models\Project[]   $projects
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Issued Products';

// Status: 1=Requested, 2=Issued (matches Yii1)
$statusBadge = [1 => 'warning', 2 => 'success'];
$statusLabel = [1 => 'Requested', 2 => 'Issued'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$selStatus  = Yii::$app->request->get('consumption_status', '');
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
.status-sel{font-size:.78rem;padding:.15rem .35rem;min-width:120px}
</style>

<div class="iot-issued-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Manage Issued Products</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ ADD', ['create-issue'], ['class' => 'btn btn-danger btn-sm']) ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int)$p->id ?>" <?= Yii::$app->request->get('project_id') == $p->id ? 'selected' : '' ?>>
                                <?= Html::encode($p->title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="developer_id" class="form-select form-select-sm">
                        <option value="">All Developers</option>
                        <?php foreach ($developers as $d): ?>
                            <option value="<?= (int)$d->id ?>" <?= Yii::$app->request->get('developer_id') == $d->id ? 'selected' : '' ?>>
                                <?= Html::encode($d->developer_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="consumption_status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="1" <?= (string)$selStatus === '1' ? 'selected' : '' ?>>Requested</option>
                        <option value="2" <?= (string)$selStatus === '2' ? 'selected' : '' ?>>Issued</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('developer_id') || Yii::$app->request->get('project_id') || $selStatus !== ''): ?>
                        <?= Html::a('Reset', ['issued'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Project</th>
                        <th>Developer</th>
                        <th>Requested Date</th>
                        <th>Created At</th>
                        <th>Approved At</th>
                        <th>Status</th>
                        <th class="text-end" style="width:60px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No issued product records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $c):
                        $cs = (int)$c->consumption_status;
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="text-muted small"><?= Html::encode($c->project->title ?? '—') ?></td>
                        <td class="fw-semibold small"><?= Html::encode($c->developer->developer_name ?? '—') ?></td>
                        <td class="text-muted small"><?= $c->requested_date ? date('Y-m-d', strtotime($c->requested_date)) : '—' ?></td>
                        <td class="text-muted small"><?= $c->created_at    ? date('Y-m-d h:i A', strtotime($c->created_at))   : '—' ?></td>
                        <td class="text-muted small"><?= $c->approved_at   ? date('Y-m-d h:i A', strtotime($c->approved_at))  : '—' ?></td>
                        <td>
                            <?php if ($cs === 1): ?>
                                <select class="form-select form-select-sm status-sel"
                                        onchange="changeStatus(<?= (int)$c->id ?>, this.value, this)">
                                    <option value="1" selected>Requested</option>
                                    <option value="2">Issued</option>
                                </select>
                            <?php elseif ($cs === 2): ?>
                                <span class="badge bg-<?= $statusBadge[$cs] ?? 'secondary' ?>">
                                    <?= Html::encode($statusLabel[$cs] ?? '—') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:170px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view-issue', 'id' => $c->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php if ($cs === 1): ?>
                                        <li><?= Html::a('<i class="fas fa-pen"></i> Update', ['update-issue', 'id' => $c->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($cs === 2): ?>
                                        <li><?= Html::a('<i class="fas fa-exchange-alt"></i> Return Product', ['create-return', 'id' => $c->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <li><?= Html::a('<i class="fas fa-clipboard"></i> Copy as New Request', ['create-issue', 'copy' => $c->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$csrfParam       = Yii::$app->request->csrfParam;
$csrfToken       = Yii::$app->request->getCsrfToken();
$changeStatusUrl = Url::to(['/iot-product/change-status']);
$this->registerJs(<<<JS
window.changeStatus = function(id, status, selectEl) {
    status = parseInt(status, 10);
    if (status !== 2) return; // only Requested -> Issued is actionable
    Swal.fire({
        icon: 'question',
        title: 'Are you sure want to change the status?',
        showCancelButton: true,
        confirmButtonText: 'Yes, mark as Issued',
        cancelButtonText: 'Cancel'
    }).then(function (result) {
        if (!result.isConfirmed) {
            if (selectEl) selectEl.value = '1';
            return;
        }
        jQuery.ajax({
            url: '$changeStatusUrl',
            type: 'POST',
            data: { '$csrfParam': '$csrfToken', id: id, status: status, key: 1 },
            dataType: 'json'
        }).done(function (r) {
            if (r && r.status === 1) {
                Swal.fire({ icon: 'success', title: 'Success', text: r.msg, timer: 1400, showConfirmButton: false })
                    .then(function () { location.reload(); });
            } else {
                Swal.fire({ icon: 'error', title: 'Sorry', html: (r && r.msg) ? String(r.msg).replace(/\\n/g, '<br>') : 'Unable to change status.' });
                if (selectEl) selectEl.value = '1';
            }
        }).fail(function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' });
            if (selectEl) selectEl.value = '1';
        });
    });
};
JS, \yii\web\View::POS_READY);
?>
