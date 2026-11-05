<?php
/** @var \yii\web\View $this */
/** @var string $tab */
/** @var string $search */
/** @var int $catFilter */
/** @var array $models */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var int $pageSize */
/** @var \backend\models\AssetCategory[] $allCategories */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use common\components\UserAccess;

$this->title = 'Assets Categories';

$tabs = [
    'cat'    => 'Manage Category',
    'subcat' => 'Manage Sub Category',
    'brand'  => 'Manage Brand',
    'loc'    => 'Manage Locations',
    'tags'   => 'Manage Tags',
];

$canAdd    = UserAccess::checkAccess('asset/save-master');
$canDelete = UserAccess::checkAccess('asset/delete-master');

$req = '<span class="text-danger">*</span>';
?>
<style>
.cat-sidebar .nav-link{color:#333;padding:.45rem .75rem;font-size:.85rem;border-radius:.25rem;margin-bottom:2px}
.cat-sidebar .nav-link:hover{background:#e9ecef}
.cat-sidebar .nav-link.active{background:#0d6efd;color:#fff;font-weight:600}
</style>

<div class="ui-page asset-categories-index">
    <div class="ui-head"><div><h1>Asset Lookup Tables</h1><div class="ui-sub">Categories, brands, locations and tags used on assets</div></div></div>

    <div class="row">
        <!-- Left Sidebar: Tabs -->
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-2 cat-sidebar">
                    <?php foreach ($tabs as $key => $label): ?>
                        <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
                           href="<?= Url::to(['categories', 'tab' => $key]) ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right Panel: Table -->
        <div class="col-md-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <!-- Search Form -->
                    <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
                        <input type="hidden" name="tab" value="<?= Html::encode($tab) ?>">
                        <?php if ($tab === 'subcat'): ?>
                            <div>
                                <label class="form-label form-label-sm mb-0 text-muted" style="font-size:.7rem">Category</label>
                                <select name="cat_id" class="form-select form-select-sm" style="min-width:140px">
                                    <option value="">All</option>
                                    <?php foreach ($allCategories as $c): ?>
                                        <option value="<?= $c->id ?>" <?= $catFilter == $c->id ? 'selected' : '' ?>><?= Html::encode($c->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label class="form-label form-label-sm mb-0 text-muted" style="font-size:.7rem">Name</label>
                            <input type="text" name="search" value="<?= Html::encode($search) ?>" class="form-control form-control-sm" placeholder="Search…" style="min-width:160px">
                        </div>
                        <button class="btn btn-secondary btn-sm">Search</button>
                        <?php if ($search || $catFilter): ?>
                            <?= Html::a('Reset', ['categories', 'tab' => $tab], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                        <?php endif; ?>
                    </form>
                    <?php if ($canAdd): ?>
                        <button class="btn btn-primary btn-sm" onclick="openModal()">+ Add</button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px">Sno</th>
                                <?php if ($tab === 'subcat'): ?>
                                    <th>Category</th>
                                <?php endif; ?>
                                <th>Name</th>
                                <?php if ($canAdd || $canDelete): ?>
                                    <th class="text-end" style="width:100px">Action</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($models)): ?>
                            <tr><td colspan="<?= ($tab === 'subcat' ? 4 : 3) ?>" class="text-center text-muted py-4">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($models as $i => $m): ?>
                            <tr>
                                <td class="text-muted"><?= ($page - 1) * $pageSize + $i + 1 ?></td>
                                <?php if ($tab === 'subcat'): ?>
                                    <td><?= Html::encode($m->category->name ?? '—') ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php
                                    $displayName = match($tab) {
                                        'brand' => $m->brand_name ?? '',
                                        'loc'   => $m->loc ?? '',
                                        default => $m->name ?? '',
                                    };
                                    echo Html::encode($displayName);
                                    ?>
                                </td>
                                <?php if ($canAdd || $canDelete): ?>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if ($canAdd): ?>
                                            <?php
                                            $editData = match($tab) {
                                                'brand' => ['id' => $m->id, 'name' => $m->brand_name],
                                                'loc'   => ['id' => $m->id, 'name' => $m->loc],
                                                'subcat' => ['id' => $m->id, 'name' => $m->name, 'cat_id' => $m->category_id],
                                                default => ['id' => $m->id, 'name' => $m->name],
                                            };
                                            ?>
                                            <button class="btn btn-outline-primary btn-sm act-btn" title="Edit"
                                                    onclick='openModal(<?= json_encode($editData, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fa fa-pencil"></i></button>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                            <button class="btn btn-outline-danger btn-sm act-btn" title="Delete"
                                                    onclick="deleteMaster(<?= $m->id ?>)"><i class="fa fa-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>
                <div class="card-footer bg-white border-top-0 py-2 d-flex justify-content-between align-items-center">
                    <small class="text-muted"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $baseParams = ['categories', 'tab' => $tab];
                            if ($search) $baseParams['search'] = $search;
                            if ($catFilter) $baseParams['cat_id'] = $catFilter;
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= Url::to(array_merge($baseParams, ['page' => $page - 1])) ?>">‹</a>
                            </li>
                            <?php for ($p = 1; $p <= $pages; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= Url::to(array_merge($baseParams, ['page' => $p])) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= Url::to(array_merge($baseParams, ['page' => $page + 1])) ?>">›</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="masterModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modalTitle">Add</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <input type="hidden" id="editId" value="0">
                <?php if ($tab === 'subcat'): ?>
                    <div class="mb-2">
                        <label class="form-label form-label-sm mb-1">Category <?= $req ?></label>
                        <select id="modalCatId" class="form-select form-select-sm">
                            <option value="">Select Category</option>
                            <?php foreach ($allCategories as $c): ?>
                                <option value="<?= $c->id ?>"><?= Html::encode($c->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">
                        <?= match($tab) {
                            'cat' => 'Category Name',
                            'subcat' => 'Subcategory Name',
                            'brand' => 'Brand Name',
                            'loc' => 'Location Name',
                            'tags' => 'Tag Name',
                            default => 'Name',
                        } ?> <?= $req ?>
                    </label>
                    <input type="text" id="modalName" class="form-control form-control-sm" maxlength="255" placeholder="Enter name">
                </div>
                <div id="modalError" class="text-danger small d-none"></div>
            </div>
            <div class="modal-footer py-1">
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-sm btn-primary" onclick="saveMaster()">Submit</button>
            </div>
        </div>
    </div>
</div>

<?php
$saveUrl   = Url::to(['/asset/save-master']);
$deleteUrl = Url::to(['/asset/delete-master']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$tabJs     = json_encode($tab);
$tabLabels = json_encode([
    'cat' => 'Category', 'subcat' => 'Sub Category', 'brand' => 'Brand', 'loc' => 'Location', 'tags' => 'Tag',
]);

$js = <<<JS
var tabType = {$tabJs};
var tabLabels = {$tabLabels};

function openModal(data) {
    var isEdit = data && data.id;
    document.getElementById('modalTitle').textContent = (isEdit ? 'Edit ' : 'Add ') + (tabLabels[tabType] || 'Item');
    document.getElementById('editId').value = isEdit ? data.id : 0;
    document.getElementById('modalName').value = isEdit ? data.name : '';
    document.getElementById('modalError').classList.add('d-none');
    var catSel = document.getElementById('modalCatId');
    if (catSel) catSel.value = (isEdit && data.cat_id) ? data.cat_id : '';
    var modal = new bootstrap.Modal(document.getElementById('masterModal'));
    modal.show();
    setTimeout(function(){ document.getElementById('modalName').focus(); }, 300);
}

function saveMaster() {
    var name = document.getElementById('modalName').value.trim();
    var id   = document.getElementById('editId').value;
    var errEl = document.getElementById('modalError');
    if (!name) { errEl.textContent = 'Name is required.'; errEl.classList.remove('d-none'); return; }
    var catSel = document.getElementById('modalCatId');
    if (tabType === 'subcat' && catSel && !catSel.value) {
        errEl.textContent = 'Please select a category.'; errEl.classList.remove('d-none'); return;
    }
    var body = 'type=' + tabType + '&name=' + encodeURIComponent(name) + '&id=' + id
        + '&' + encodeURIComponent('{$csrfParam}') + '=' + encodeURIComponent('{$csrfToken}');
    if (tabType === 'subcat' && catSel) body += '&cat_id=' + catSel.value;

    fetch('{$saveUrl}', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) { location.reload(); }
            else { errEl.textContent = d.message || 'Save failed.'; errEl.classList.remove('d-none'); }
        });
}

function deleteMaster(id) {
    if (!confirm('Are you sure you want to delete this item?')) return;
    fetch('{$deleteUrl}', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'type=' + tabType + '&id=' + id
            + '&' + encodeURIComponent('{$csrfParam}') + '=' + encodeURIComponent('{$csrfToken}')
    }).then(function(r){ return r.json(); })
      .then(function(d){
        if (d.success) { location.reload(); }
        else { Swal.fire({icon:'error', title:'Error', text: d.message || 'Delete failed.'}); }
    });
}

document.getElementById('modalName').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); saveMaster(); }
});
JS;
// POS_END (not POS_READY) — POS_READY wraps the snippet in jQuery(function(){ … }),
// which would make openModal/saveMaster/deleteMaster local to that closure and
// invisible to the inline onclick="openModal()" handlers in the HTML, leaving
// the ADD button silently inert.
$this->registerJs($js, \yii\web\View::POS_END);
?>
