<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Vendor[] $vendors */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Inward Invoices';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
</style>

<div class="inward-invoice-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Inward Invoices</h5>
            <small class="text-muted"><?= $total ?> invoice<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ Add Invoice', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="vendor_id" class="form-select form-select-sm">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v->id ?>" <?= Yii::$app->request->get('vendor_id') == $v->id ? 'selected' : '' ?>>
                                <?= Html::encode($v->company_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <?= \kartik\date\DatePicker::widget([
                        'name'          => 'from_date',
                        'value'         => Yii::$app->request->get('from_date', ''),
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                        'options'       => ['id' => 'filter-from-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'From'],
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <?= \kartik\date\DatePicker::widget([
                        'name'          => 'to_date',
                        'value'         => Yii::$app->request->get('to_date', ''),
                        'type'          => \kartik\date\DatePicker::TYPE_INPUT,
                        'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true],
                        'options'       => ['id' => 'filter-to-date', 'class' => 'form-control form-control-sm', 'placeholder' => 'To'],
                    ]) ?>
                    <div id="to-date-error" class="text-danger" style="font-size:.75rem;display:none">To date cannot be before From date.</div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" id="filter-btn">Filter</button>
                    <?php if (Yii::$app->request->get('vendor_id') || Yii::$app->request->get('from_date') || Yii::$app->request->get('to_date')): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
<script>
(function(){var $f=$('#filter-from-date'),$t=$('#filter-to-date'),$e=$('#to-date-error'),$b=$('#filter-btn');function p(v){var d=new Date(v);return v&&!isNaN(d)?d:null;}function v(){var f=p($f.val()),t=p($t.val());if(f&&t&&t<f){$e.show();$b.prop('disabled',true);return false;}$e.hide();$b.prop('disabled',false);return true;}$f.on('changeDate change',function(){var fv=$f.val();$t.datepicker('setStartDate',fv||null);if(p(fv)&&p($t.val())&&p($t.val())<p(fv)){$t.val('').datepicker('update');}v();});$t.on('changeDate change',v);$f.closest('form').on('submit',function(){return v();});}());
</script>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Invoice No</th>
                        <th>Vendor</th>
                        <th>Date</th>
                        <th>Title</th>
                        <th class="text-end">Sub Total</th>
                        <th class="text-end">GST</th>
                        <th class="text-end">Net Total</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No inward invoices found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $inv): ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $inv->id]) ?>" class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($inv->invoice_number) ?>
                            </a>
                        </td>
                        <td class="small"><?= Html::encode($inv->vendor->company_name ?? '—') ?></td>
                        <td class="small text-muted"><?= $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '—' ?></td>
                        <td class="small"><?= Html::encode($inv->title ?: '—') ?></td>
                        <td class="text-end small">₹<?= number_format((float)$inv->grand_total, 2) ?></td>
                        <td class="text-end small text-muted">₹<?= number_format((float)$inv->sgst_amount + (float)$inv->cgst_amount + (float)$inv->igst_amount, 2) ?></td>
                        <td class="text-end fw-semibold small">₹<?= number_format((float)$inv->net_total, 2) ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $inv->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-trash-alt"></i> Delete', ['delete', 'id' => $inv->id], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-confirm' => 'Delete this invoice?', 'data-method' => 'post',
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
        <div class="card-footer bg-white border-top-0 py-2">
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›']) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
