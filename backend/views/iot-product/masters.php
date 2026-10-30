<?php
/**
 * IoT Masters — Manage Dropdowns.
 * Mirrors Yii1 managedropdown.php + dropdowntable.php + adddropdownvalue.php.
 *
 * @var \yii\web\View                 $this
 * @var string                        $table         cat|subcat|brand|loc|tags
 * @var \yii\data\ActiveDataProvider  $dataProvider
 * @var string                        $search
 * @var int                           $categoryId
 * @var \backend\models\IotCategory[]  $categories
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'IOT Masters';

$tabs = [
    'cat'    => ['label' => 'Manage Category',     'title' => 'Category',     'col' => 'Category Name'],
    'subcat' => ['label' => 'Manage Sub Category', 'title' => 'Sub Category', 'col' => 'Sub-Category Name'],
    'brand'  => ['label' => 'Manage Brand',        'title' => 'Brand',        'col' => 'Brand Name'],
    'loc'    => ['label' => 'Manage Locations',    'title' => 'Location',     'col' => 'Location Name'],
    'tags'   => ['label' => 'Manage Tags',         'title' => 'Tag',          'col' => 'Tag Name'],
];
$active    = $tabs[$table];
$models    = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total     = $dataProvider->getTotalCount();

// Helper to get the "name" string per master row for display
$nameOf = static function ($row) use ($table): string {
    return match ($table) {
        'cat', 'subcat', 'tags' => (string)($row->name ?? ''),
        'brand'                 => (string)($row->brand_name ?? ''),
        'loc'                   => (string)($row->location ?? ''),
        default                 => '',
    };
};
?>
<style>
.iot-masters-side .nav-link{color:#333;border-radius:0;padding:.55rem .9rem;font-size:.85rem;border-bottom:1px solid #eee}
.iot-masters-side .nav-link.active{background:#e8f4fd;color:#0076B7;font-weight:600;border-left:3px solid #0076B7}
.iot-masters-side .nav-link:hover:not(.active){background:#f2f6ff}
.iot-masters-head{background:#0076B7;color:#fff;padding:.5rem .9rem;font-size:.85rem;font-weight:600;border-radius:6px 6px 0 0}
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="iot-masters">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">IOT Masters</h5>
        <small class="text-muted">Manage brands, categories, sub-categories, locations and tags</small>
    </div>

    <div class="row g-3">
        <!-- Left sidebar tabs -->
        <div class="col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm iot-masters-side">
                <div class="iot-masters-head">Masters</div>
                <div class="list-group list-group-flush">
                    <?php foreach ($tabs as $code => $t): ?>
                        <a class="nav-link <?= $code === $table ? 'active' : '' ?>"
                           href="<?= Url::to(['masters', 'table' => $code]) ?>">
                            <?= Html::encode($t['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right content -->
        <div class="col-md-9 col-lg-10">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-2">
                    <form method="get" class="row g-2 align-items-center" action="<?= Url::to(['masters']) ?>">
                        <input type="hidden" name="table" value="<?= Html::encode($table) ?>">
                        <?php if ($table === 'subcat'): ?>
                            <div class="col-md-3">
                                <select name="category_id" class="form-select form-select-sm">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= (int)$c->id ?>" <?= $categoryId === (int)$c->id ? 'selected' : '' ?>>
                                            <?= Html::encode($c->name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <input type="text" name="search" value="<?= Html::encode($search) ?>"
                                   class="form-control form-control-sm" placeholder="Search by name…">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary btn-sm">Search</button>
                            <?php if ($search !== '' || $categoryId > 0): ?>
                                <?= Html::a('Reset', ['masters', 'table' => $table], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="col text-end">
                            <button type="button" class="btn btn-danger btn-sm iot-master-add"
                                    data-table="<?= Html::encode($table) ?>" data-title="<?= Html::encode($active['title']) ?>">
                                + Add <?= Html::encode($active['title']) ?>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">#</th>
                                <?php if ($table === 'subcat'): ?><th>Category</th><?php endif; ?>
                                <th><?= Html::encode($active['col']) ?></th>
                                <th class="text-end" style="width:130px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($models)): ?>
                            <tr><td colspan="<?= $table === 'subcat' ? 4 : 3 ?>" class="text-center text-muted py-5">No records found.</td></tr>
                        <?php else: foreach ($models as $i => $row): ?>
                            <tr>
                                <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                                <?php if ($table === 'subcat'): ?>
                                    <td class="text-muted small"><?= Html::encode($row->category->name ?? '—') ?></td>
                                <?php endif; ?>
                                <td class="fw-semibold"><?= Html::encode($nameOf($row)) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm iot-master-edit"
                                            data-id="<?= (int)$row->id ?>"
                                            data-table="<?= Html::encode($table) ?>"
                                            data-title="<?= Html::encode($active['title']) ?>">
                                        Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm iot-master-delete"
                                            data-id="<?= (int)$row->id ?>"
                                            data-table="<?= Html::encode($table) ?>">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pagination && $pagination->pageCount > 1): ?>
                <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
                    <small class="text-muted">
                        Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?>
                    </small>
                    <?= LinkPager::widget([
                        'pagination'    => $pagination,
                        'options'       => ['class' => 'pagination mb-0'],
                        'firstPageLabel'=> '«', 'lastPageLabel'=> '»',
                        'prevPageLabel' => '‹', 'nextPageLabel'=> '›',
                        'maxButtonCount'=> 7,
                    ]) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="iot-master-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="iot-master-form">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold" id="iot-master-modal-title">&nbsp;</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="table" id="iot-master-table">
                    <input type="hidden" name="id" id="iot-master-id" value="0">

                    <div class="mb-3" id="iot-master-cat-wrap" style="display:none">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select name="category_id" id="iot-master-cat" class="form-select form-select-sm">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c->id ?>"><?= Html::encode($c->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-semibold" id="iot-master-name-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="iot-master-name" class="form-control form-control-sm" maxlength="250" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="iot-master-submit">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$saveUrl   = Url::to(['save-master']);
$getUrl    = Url::to(['get-master']);
$deleteUrl = Url::to(['delete-master']);
$csrfParam = Yii::$app->request->csrfParam;
$csrfToken = Yii::$app->request->getCsrfToken();
$labelMap  = json_encode(array_map(fn($t) => $t['title'], $tabs));
$this->registerJs(<<<JS
(function () {
    var labels   = $labelMap;
    var saveUrl  = '$saveUrl';
    var getUrl   = '$getUrl';
    var delUrl   = '$deleteUrl';
    var csrfData = { '$csrfParam': '$csrfToken' };

    var modalEl  = document.getElementById('iot-master-modal');
    var modal    = bootstrap.Modal.getOrCreateInstance(modalEl);
    var titleEl  = document.getElementById('iot-master-modal-title');
    var form     = document.getElementById('iot-master-form');
    var tableEl  = document.getElementById('iot-master-table');
    var idEl     = document.getElementById('iot-master-id');
    var nameEl   = document.getElementById('iot-master-name');
    var nameLbl  = document.getElementById('iot-master-name-label');
    var catWrap  = document.getElementById('iot-master-cat-wrap');
    var catEl    = document.getElementById('iot-master-cat');

    function openModal(table, id) {
        tableEl.value = table;
        idEl.value    = id || 0;
        nameEl.value  = '';
        catEl.value   = '';
        catWrap.style.display = (table === 'subcat') ? '' : 'none';
        nameLbl.innerHTML = (labels[table] || 'Name') + ' <span class="text-danger">*</span>';
        titleEl.textContent = (id ? 'Edit ' : 'Add ') + (labels[table] || 'Record');

        if (id) {
            jQuery.get(getUrl, { table: table, id: id })
                .done(function (r) {
                    if (!r || !r.success) {
                        Swal.fire({ icon: 'error', title: 'Error', text: (r && r.message) || 'Failed to load.' });
                        return;
                    }
                    nameEl.value = r.data.name || '';
                    if (table === 'subcat') catEl.value = r.data.category_id || '';
                    modal.show();
                })
                .fail(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load record.' }); });
        } else {
            modal.show();
        }
    }

    // Add button
    document.querySelectorAll('.iot-master-add').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn.dataset.table, 0); });
    });
    // Edit buttons
    document.querySelectorAll('.iot-master-edit').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn.dataset.table, btn.dataset.id); });
    });
    // Delete buttons
    document.querySelectorAll('.iot-master-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: 'Delete this record?',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#dc3545',
            }).then(function (res) {
                if (!res.isConfirmed) return;
                jQuery.get(delUrl, { id: btn.dataset.id, table: btn.dataset.table })
                    .done(function (r) {
                        if (r && r.status === 1) {
                            Swal.fire({ icon: 'success', title: 'Deleted', text: r.msg, timer: 1500, showConfirmButton: false })
                                .then(function () { location.reload(); });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Cannot Delete', text: (r && r.msg) || 'Delete failed.' });
                        }
                    })
                    .fail(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' }); });
            });
        });
    });

    // Form submit (add/edit)
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = nameEl.value.trim();
        if (!name) { nameEl.focus(); return; }
        if (tableEl.value === 'subcat' && !catEl.value) { catEl.focus(); return; }

        var payload = Object.assign({}, csrfData, {
            table: tableEl.value,
            id:    idEl.value,
            name:  name,
            category_id: catEl.value || 0,
        });

        jQuery.post(saveUrl, payload, null, 'json')
            .done(function (r) {
                if (r && r.success) {
                    modal.hide();
                    Swal.fire({ icon: 'success', title: 'Saved', text: r.message || 'Record saved.', timer: 1400, showConfirmButton: false })
                        .then(function () { location.reload(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', html: (r && r.message) || 'Save failed.' });
                }
            })
            .fail(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' }); });
    });
}());
JS, \yii\web\View::POS_READY);
?>
