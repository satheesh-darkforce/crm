<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\widgets\LinkPager;

$this->title = 'Quotations';

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

<div class="quotation-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Quotations</h5>
            <small class="text-muted"><?= $total ?> quotation<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Quotation', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Search -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="search"
                           value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm"
                           placeholder="Search client name, project, email or quotation ID…">
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Search</button>
                    <?php if (Yii::$app->request->get('search')): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Quotation ID</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Project</th>
                        <th>Latest Amount</th>
                        <th>Created</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">No quotations found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $q): ?>
                    <?php $latest = $q->latestDetail; ?>
                    <tr>
                        <td class="text-muted">
                            <?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?>
                        </td>
                        <td>
                            <a href="<?= \yii\helpers\Url::to(['view', 'id' => $q->id]) ?>"
                               class="text-decoration-none">
                                <span class="badge bg-light text-dark border"><?= Html::encode($q->unique_id ?: '—') ?></span>
                            </a>
                        </td>
                        <td class="fw-semibold"><?= Html::encode($q->client_name) ?></td>
                        <td class="small text-muted"><?= Html::encode($q->email) ?></td>
                        <td class="small"><?= Html::encode($q->project_name) ?></td>
                        <td class="small fw-semibold">
                            <?php if ($latest): ?>
                                <span class="text-muted"><?= Html::encode($latest->currency->currency ?? '') ?></span>
                                <?= number_format((float)$latest->quote_amount, 2) ?>
                                <div class="small text-muted fw-normal">
                                    <?= Html::encode($latest->currency->title ?? '') ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= $q->created_date ? date('d M Y', strtotime($q->created_date)) : '—' ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:170px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $q->id],
                                        ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $q->id],
                                        ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="#"
                                           data-bs-toggle="modal" data-bs-target="#remarkModal"
                                           data-id="<?= $q->id ?>">
                                            <i class="fas fa-comment-dots"></i> Add Remark
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete', 'id' => $q->id], [
                                        'class'        => 'dropdown-item text-danger',
                                        'encode'       => false,
                                        'data-confirm' => 'Delete this quotation?',
                                        'data-method'  => 'post',
                                    ]) ?></li>
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
            <small class="text-muted">
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?>
                of <?= $total ?>
            </small>
            <?= LinkPager::widget([
                'pagination'    => $pagination,
                'options'       => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»',
                'prevPageLabel'  => '‹', 'nextPageLabel'  => '›',
                'maxButtonCount' => 7,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Remark Modal -->
<div class="modal fade" id="remarkModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold">Add Remark</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="remarkModalBody">
                <div class="text-center text-muted py-3 small">Loading…</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="saveRemarkBtn">Save Remark</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var remarkModal = document.getElementById('remarkModal');
    var remarkBody  = document.getElementById('remarkModalBody');
    var saveBtn     = document.getElementById('saveRemarkBtn');
    var currentId   = null;

    remarkModal.addEventListener('show.bs.modal', function (e) {
        currentId = e.relatedTarget.getAttribute('data-id');
        remarkBody.innerHTML = '<div class="text-center text-muted py-3 small">Loading…</div>';
        fetch('/quotation/add-remark?id=' + currentId, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function(r) { return r.text(); })
            .then(function(html) { remarkBody.innerHTML = html; });
    });

    saveBtn.addEventListener('click', function () {
        var form = document.getElementById('remarkForm');
        if (!form) return;
        var data = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: data,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                bootstrap.Modal.getInstance(remarkModal).hide();
                var q = '<?= Yii::$app->request->get('search') ? '?search=' . urlencode(Yii::$app->request->get('search')) : '' ?>';
                window.location.href = '/quotation' + q;
            } else {
                var errDiv = document.getElementById('remarkErrors');
                if (errDiv) {
                    var msgs = Object.values(res.errors || {}).flat().join('<br>');
                    errDiv.innerHTML = '<div class="alert alert-danger py-1 mt-2 small">' + msgs + '</div>';
                }
            }
        });
    });
})();
</script>
