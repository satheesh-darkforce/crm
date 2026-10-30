<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Project[] $projects */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Non-Purchased Products';

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

<div class="iot-non-purchased-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Non-Purchased Products</h5>
            <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Non-Purchase Entry', ['create-non-purchase'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p->id ?>" <?= Yii::$app->request->get('project_id') == $p->id ? 'selected' : '' ?>><?= Html::encode($p->title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('project_id')): ?>
                        <?= Html::a('Clear', ['non-purchased'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th>Products</th>
                        <th>Created Date</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No non-purchased product records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $np): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td class="fw-semibold small"><?= Html::encode($np->project->title ?? '—') ?></td>
                        <td>
                            <?php foreach ($np->items as $item): ?>
                                <span class="badge bg-light text-dark border me-1" style="font-size:.72rem">
                                    <?= Html::encode($item->product->product_name ?? 'ID:'.$item->product_id) ?>
                                    <span class="text-muted">×<?= $item->quantity ?></span>
                                </span>
                            <?php endforeach; ?>
                            <?php if (empty($np->items)): ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?= $np->created_at ? date('d M Y', strtotime($np->created_at)) : '—' ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:140px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view-non-purchase', 'id' => $np->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update-non-purchase', 'id' => $np->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a href="#" class="dropdown-item text-danger np-delete"
                                           data-id="<?= (int)$np->id ?>">
                                           <i class="fas fa-trash-alt"></i> Delete
                                        </a>
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
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$deleteUrl = Url::to(['/iot-product/delete-non-purchase']);
$this->registerJs(<<<JS
(function () {
    var csrfData = { '$csrfParam': '$csrfToken' };

    document.querySelectorAll('.np-delete').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Are you sure to delete this record?',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#dc3545'
            }).then(function (res) {
                if (!res.isConfirmed) return;
                jQuery.post('$deleteUrl', Object.assign({}, csrfData, { id: el.dataset.id }), null, 'json')
                    .done(function (r) {
                        var msg = (r && r.msg) ? r.msg : 'Unknown response.';
                        if (r && r.status === 1) {
                            Swal.fire({ icon: 'success', title: 'Success', text: msg, timer: 1500, showConfirmButton: false })
                                .then(function () { location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Sorry', text: msg });
                        }
                    })
                    .fail(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' }); });
            });
        });
    });
}());
JS, \yii\web\View::POS_READY);
?>
