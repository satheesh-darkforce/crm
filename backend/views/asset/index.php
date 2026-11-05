<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\AssetCategory[] $categories */
/** @var \backend\models\AssetBrand[] $brands */
/** @var \backend\models\AssetLocation[] $locations */
/** @var \backend\models\Vendor[] $vendors */
/** @var \backend\models\AssetTag[] $tags */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use kartik\select2\Select2;
use kartik\date\DatePicker;

$this->title = 'Assets';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$catMap     = array_column($categories, 'name', 'id');
$brandMap   = array_column($brands, 'brand_name', 'id');
$locMap     = array_column($locations, 'loc', 'id');
$vendorMap  = array_column($vendors, 'company_name', 'id');
$tagMap     = array_column($tags, 'name', 'id');
$req        = Yii::$app->request;
?>

<div class="ui-page asset-index">
    <div class="ui-head">
        <div>
            <h1>Assets</h1>
            <div class="ui-sub"><?= $total ?> record<?= $total != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('+ Add Asset', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('<i class="fas fa-tag"></i> Lookup Tables', ['categories'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-end">
                <!-- Row 1: Main filters -->
                <div class="col-md-3">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Name / Barcode / Model"
                           value="<?= Html::encode($req->get('search')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Category</label>
                    <?= Select2::widget([
                        'name'          => 'cat_id',
                        'value'         => $req->get('cat_id'),
                        'data'          => $catMap,
                        'options'       => ['placeholder' => 'All Categories', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Brand</label>
                    <?= Select2::widget([
                        'name'          => 'brand_id',
                        'value'         => $req->get('brand_id'),
                        'data'          => $brandMap,
                        'options'       => ['placeholder' => 'All Brands', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Location</label>
                    <?= Select2::widget([
                        'name'          => 'location_id',
                        'value'         => $req->get('location_id'),
                        'data'          => $locMap,
                        'options'       => ['placeholder' => 'All Locations', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Vendor</label>
                    <?= Select2::widget([
                        'name'          => 'vendor_id',
                        'value'         => $req->get('vendor_id'),
                        'data'          => $vendorMap,
                        'options'       => ['placeholder' => 'All Vendors', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Warranty</label>
                    <select name="warranty" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="1" <?= $req->get('warranty') === '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= $req->get('warranty') === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>

                <!-- Row 2: Collapsible advanced filters -->
                <?php
                $hasAdvanced = $req->get('amc') !== null && $req->get('amc') !== ''
                    || $req->get('purchase_from') || $req->get('purchase_to')
                    || $req->get('warranty_from') || $req->get('warranty_to')
                    || $req->get('amc_from') || $req->get('amc_to')
                    || $req->get('service_contact') || $req->get('description')
                    || $req->get('tags') || $req->get('vendor_id');
                ?>
                <div class="col-12">
                    <a class="small text-decoration-none" data-bs-toggle="collapse" href="#adv-filters" role="button"
                       <?= $hasAdvanced ? 'aria-expanded="true"' : '' ?>>
                        Advanced Filters <i class="fas fa-caret-down"></i>
                    </a>
                </div>
                <div class="collapse <?= $hasAdvanced ? 'show' : '' ?>" id="adv-filters">
                    <div class="row g-2 mt-1">
                        <div class="col-md-1">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">AMC</label>
                            <select name="amc" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="1" <?= $req->get('amc') === '1' ? 'selected' : '' ?>>Yes</option>
                                <option value="0" <?= $req->get('amc') === '0' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Purchase From</label>
                            <?= DatePicker::widget([
                                'name'          => 'purchase_from',
                                'value'         => $req->get('purchase_from'),
                                'type'          => DatePicker::TYPE_INPUT,
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'From'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Purchase To</label>
                            <?= DatePicker::widget([
                                'name'          => 'purchase_to',
                                'value'         => $req->get('purchase_to'),
                                'type'          => DatePicker::TYPE_INPUT,
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'To'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Warranty From</label>
                            <?= DatePicker::widget([
                                'name'          => 'warranty_from',
                                'value'         => $req->get('warranty_from'),
                                'type'          => DatePicker::TYPE_INPUT,
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'From'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Warranty To</label>
                            <?= DatePicker::widget([
                                'name'          => 'warranty_to',
                                'value'         => $req->get('warranty_to'),
                                'type'          => DatePicker::TYPE_INPUT,
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'To'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">AMC From</label>
                            <?= DatePicker::widget([
                                'name'          => 'amc_from',
                                'value'         => $req->get('amc_from'),
                                'type'          => DatePicker::TYPE_INPUT,
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'From'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">AMC To</label>
                            <?= DatePicker::widget([
                                'name'          => 'amc_to',
                                'value'         => $req->get('amc_to'),
                                'type'          => DatePicker::TYPE_INPUT,
                                'options'       => ['class' => 'form-control form-control-sm', 'placeholder' => 'To'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd'],
                            ]) ?>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Service Contact</label>
                            <input type="text" name="service_contact" class="form-control form-control-sm"
                                   placeholder="Service contact" value="<?= Html::encode($req->get('service_contact')) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Description</label>
                            <input type="text" name="description" class="form-control form-control-sm"
                                   placeholder="Search description" value="<?= Html::encode($req->get('description')) ?>">
                        </div>
                        <?php if (!empty($tagMap)): ?>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Tags</label>
                            <?= Select2::widget([
                                'name'          => 'tags',
                                'value'         => $req->get('tags'),
                                'data'          => $tagMap,
                                'options'       => ['placeholder' => 'All Tags', 'class' => 'form-control form-control-sm'],
                                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                            ]) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-auto d-flex gap-1">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php
                    $hasFilter = $req->get('search') || $req->get('cat_id')
                        || $req->get('brand_id') || $req->get('location_id') || $req->get('vendor_id')
                        || ($req->get('warranty') !== null && $req->get('warranty') !== '')
                        || ($req->get('amc') !== null && $req->get('amc') !== '')
                        || $req->get('purchase_from') || $req->get('purchase_to')
                        || $req->get('warranty_from') || $req->get('warranty_to')
                        || $req->get('amc_from') || $req->get('amc_to')
                        || $req->get('service_contact') || $req->get('description')
                        || $req->get('tags');
                    ?>
                    <?php if ($hasFilter): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <form method="post" action="<?= Url::to(['print-multi-barcode']) ?>" id="bulk-barcode-form" target="_blank">
    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="check-all" class="form-check-input"></th>
                        <th style="width:40px">#</th>
                        <th>Product Name</th>
                        <th>Barcode</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Vendor</th>
                        <th>Warranty</th>
                        <th>AMC</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-5">No asset records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $a): ?>
                    <tr>
                        <td><input type="checkbox" name="id[]" value="<?= $a->id ?>" class="form-check-input asset-chk"></td>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <div class="fw-semibold small"><?= Html::encode($a->product_name ?? '—') ?></div>
                            <?php if ($a->subcategory): ?>
                                <small class="text-muted"><?= Html::encode($a->subcategory->name) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><span class="badge bg-light text-dark border" style="font-family:monospace;font-size:.75rem"><?= Html::encode($a->barcode ?? '—') ?></span></td>
                        <td class="text-muted small"><?= Html::encode($a->brand->brand_name ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($a->model ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($a->category->name ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($a->location->loc ?? '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($a->vendor->company_name ?? '—') ?></td>
                        <td>
                            <?php if ($a->warranty): ?>
                                <?php
                                    $wExpired = $a->warranty_end_date && $a->warranty_end_date < date('Y-m-d');
                                ?>
                                <span class="badge bg-<?= $wExpired ? 'danger' : 'success' ?>-subtle text-<?= $wExpired ? 'danger' : 'success' ?> border" style="font-size:.7rem">
                                    <?= $wExpired ? 'Expired' : 'Active' ?>
                                    <?php if ($a->warranty_end_date): ?>
                                        <span class="text-muted"> til <?= date('d M Y', strtotime($a->warranty_end_date)) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a->amc): ?>
                                <?php $amcExpired = $a->amc_end_date && $a->amc_end_date < date('Y-m-d'); ?>
                                <span class="badge bg-<?= $amcExpired ? 'danger' : 'info' ?>-subtle text-<?= $amcExpired ? 'danger' : 'info' ?> border" style="font-size:.7rem">
                                    <?= $amcExpired ? 'Expired' : 'Active' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">No</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $a->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $a->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-tag"></i> Barcode', ['barcode', 'id' => $a->id], ['class' => 'dropdown-item', 'encode' => false, 'target' => '_blank']) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete', 'id' => $a->id], [
                                        'class' => 'dropdown-item text-danger',
                                        'encode' => false,
                                        'data-confirm' => 'Delete this asset?',
                                        'data-method' => 'post',
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
            <div class="d-flex align-items-center gap-3">
                <small class="text-muted">Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?></small>
                <button type="submit" class="btn btn-outline-dark btn-sm" id="print-barcodes-btn" style="display:none"><i class="fas fa-tag"></i> Print Selected Barcodes</button>
            </div>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php else: ?>
        <div class="card-footer bg-white border-top-0 py-2">
            <button type="submit" class="btn btn-outline-dark btn-sm" id="print-barcodes-btn" style="display:none"><i class="fas fa-tag"></i> Print Selected Barcodes</button>
        </div>
        <?php endif; ?>
    </div>
    </form>
</div>

<script>
document.getElementById('check-all').addEventListener('change', function () {
    document.querySelectorAll('.asset-chk').forEach(c => c.checked = this.checked);
    togglePrintBtn();
});
document.querySelectorAll('.asset-chk').forEach(c => c.addEventListener('change', togglePrintBtn));
function togglePrintBtn() {
    var any = document.querySelectorAll('.asset-chk:checked').length > 0;
    document.getElementById('print-barcodes-btn').style.display = any ? '' : 'none';
}
</script>
