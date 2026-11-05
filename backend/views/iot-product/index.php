<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\IotCategory[] $categories */
/** @var \backend\models\IotBrand[] $brands */
/** @var \backend\models\IotSubcategory[] $subcategories */
/** @var \backend\models\IotLocation[] $locations */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'IOT Products';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.stock-low{color:#dc3545;font-weight:700}
.stock-ok{color:#198754;font-weight:600}
</style>

<div class="ui-page iot-product-index">
    <div class="ui-head">
        <div>
            <h1>IoT Products</h1>
            <div class="ui-sub"><?= $total ?> product<?= $total != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <button type="button" id="iot-print-barcodes" class="btn btn-success btn-sm" style="display:none"
                    data-url="<?= Url::to(['print-multi-barcode']) ?>">
                <i class="fas fa-comment"></i> Print <span id="iot-print-count">0</span> Barcode<span id="iot-print-plural">s</span>
            </button>
            <?= Html::a('+ Add Product', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="search" value="<?= Html::encode(Yii::$app->request->get('search')) ?>"
                           class="form-control form-control-sm" placeholder="Name / Code / Part No…">
                </div>
                <div class="col-md-2">
                    <select name="cat_id" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c->id ?>" <?= Yii::$app->request->get('cat_id') == $c->id ? 'selected' : '' ?>><?= Html::encode($c->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="brand_id" class="form-select form-select-sm">
                        <option value="">All Brands</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= $b->id ?>" <?= Yii::$app->request->get('brand_id') == $b->id ? 'selected' : '' ?>><?= Html::encode($b->brand_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="subcat_id" class="form-select form-select-sm">
                        <option value="">All Sub-Categories</option>
                        <?php foreach ($subcategories as $sc): ?>
                            <option value="<?= $sc->id ?>" <?= Yii::$app->request->get('subcat_id') == $sc->id ? 'selected' : '' ?>><?= Html::encode($sc->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="location_id" class="form-select form-select-sm">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc->id ?>" <?= Yii::$app->request->get('location_id') == $loc->id ? 'selected' : '' ?>><?= Html::encode($loc->location) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('search') || Yii::$app->request->get('cat_id') || Yii::$app->request->get('brand_id') || Yii::$app->request->get('subcat_id') || Yii::$app->request->get('location_id')): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
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
                        <th style="width:34px" class="text-center">
                            <input type="checkbox" class="form-check-input" id="iot-select-all" title="Select all on this page">
                        </th>
                        <th style="width:40px">#</th>
                        <th>Product Name</th>
                        <th>Code</th>
                        <th>Part No</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Sub-Category</th>
                        <th>Location</th>
                        <th class="text-center">Stock Qty</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-5">No products found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $p): ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input iot-row-check" value="<?= (int)$p->id ?>">
                        </td>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <span class="fw-semibold"><?= Html::encode($p->product_name) ?></span>
                            <?php if ($p->description): ?>
                                <div class="text-muted" style="font-size:.72rem;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= Html::encode($p->description) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-light text-dark border font-monospace"><?= Html::encode($p->product_code) ?></span></td>
                        <td class="text-muted small"><?= Html::encode($p->part_number ?: '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($p->brand->brand_name ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($p->category->name ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($p->subcategory->name ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($p->location->location ?? '—') ?></td>
                        <td class="text-center">
                            <span class="<?= $p->stock_qty <= 0 ? 'stock-low' : ($p->stock_qty <= 5 ? 'text-warning fw-semibold' : 'stock-ok') ?>">
                                <?= (int)$p->stock_qty ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:170px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li>
                                        <a href="#" class="dropdown-item iot-history-btn"
                                           data-url="<?= Url::to(['product-history', 'id' => $p->id]) ?>"
                                           data-title="Product History — <?= Html::encode($p->product_name) ?>">
                                           <i class="fas fa-chart-bar"></i> Product History
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="dropdown-item iot-barcode-btn"
                                           data-url="<?= Url::to(['barcode', 'id' => $p->id]) ?>"
                                           data-title="Barcode — <?= Html::encode($p->product_name) ?>">
                                           <i class="fas fa-key"></i> Barcode
                                        </a>
                                    </li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $p->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $p->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-method' => 'post', 'data-confirm' => 'Delete ' . Html::encode($p->product_name) . '?',
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
            <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for Product History / Barcode (shared) -->
<div class="modal fade" id="iot-quick-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="iot-quick-modal-title">&nbsp;</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="iot-quick-modal-body">
                <div class="text-center text-muted py-4">Loading…</div>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<'JS'
(function () {
    var modalEl = document.getElementById('iot-quick-modal');
    if (!modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var titleEl = document.getElementById('iot-quick-modal-title');
    var bodyEl  = document.getElementById('iot-quick-modal-body');

    function openFrom(a) {
        var url   = a.getAttribute('data-url');
        var title = a.getAttribute('data-title') || '';
        titleEl.textContent = title;
        jQuery(bodyEl).html('<div class="text-center text-muted py-4">Loading…</div>');
        modal.show();
        jQuery.get(url)
            .done(function (html) { jQuery(bodyEl).html(html); })
            .fail(function ()    { jQuery(bodyEl).html('<div class="alert alert-danger mb-0">Failed to load content.</div>'); });
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest('.iot-history-btn, .iot-barcode-btn');
        if (!a) return;
        e.preventDefault();
        openFrom(a);
    });

    // ── Multi-select + Print Barcodes ────────────────────────────────────
    var printBtn   = document.getElementById('iot-print-barcodes');
    var printCount = document.getElementById('iot-print-count');
    var printPlur  = document.getElementById('iot-print-plural');
    var selectAll  = document.getElementById('iot-select-all');

    function refreshPrintBtn() {
        var checked = document.querySelectorAll('.iot-row-check:checked');
        var n = checked.length;
        if (n > 0) {
            printBtn.style.display = '';
            printCount.textContent = n;
            printPlur.textContent  = n === 1 ? '' : 's';
        } else {
            printBtn.style.display = 'none';
        }
        var rows = document.querySelectorAll('.iot-row-check');
        if (selectAll) {
            selectAll.checked = rows.length > 0 && n === rows.length;
            selectAll.indeterminate = n > 0 && n < rows.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.iot-row-check').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            refreshPrintBtn();
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('iot-row-check')) {
            refreshPrintBtn();
        }
    });

    if (printBtn) {
        printBtn.addEventListener('click', function () {
            var ids = Array.prototype.map.call(
                document.querySelectorAll('.iot-row-check:checked'),
                function (cb) { return cb.value; }
            );
            if (!ids.length) return;

            Swal.fire({
                title: 'Print barcodes?',
                text: 'Print barcodes for ' + ids.length + ' selected product' + (ids.length === 1 ? '' : 's') + '?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, print',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (!result.isConfirmed) return;
                titleEl.textContent = 'Print Barcodes';
                jQuery(bodyEl).html('<div class="text-center text-muted py-4">Loading…</div>');
                modal.show();
                jQuery.post(printBtn.getAttribute('data-url'), {
                    id: ids,
                    _csrf: yii.getCsrfToken()
                })
                .done(function (html) { jQuery(bodyEl).html(html); })
                .fail(function ()    { jQuery(bodyEl).html('<div class="alert alert-danger mb-0">Failed to load barcodes.</div>'); });
            });
        });
    }
}());
JS);
?>
