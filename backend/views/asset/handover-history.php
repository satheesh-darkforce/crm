<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Developer $developer */
/** @var \backend\models\AssetHandover[] $handovers */
/** @var int $key 1=Active Assets, 2=Handovered Assets */

use backend\models\Asset;
use yii\bootstrap5\Html;
use kartik\select2\Select2;

$this->title = 'Handover: ' . $developer->developer_name;
$devId = $developer->id;
?>

<div class="ui-page asset-handover-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($developer->developer_name) ?></h1>
            <div class="ui-sub">Assets handed over to this employee</div>
        </div>
        <div class="ui-actions">
            <div style="min-width:200px">
                <?= Select2::widget([
                    'name' => 'filter',
                    'value' => $key,
                    'data' => [1 => 'Active assets', 2 => 'Returned assets'],
                    'options' => ['id' => 'handover-filter', 'class' => 'form-select form-select-sm'],
                    'pluginOptions' => ['allowClear' => false, 'width' => '100%', 'minimumResultsForSearch' => -1],
                ]) ?>
            </div>
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['handover'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table list-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>S.no</th>
                            <th>Asset Name</th>
                            <th>Asset Id</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Category</th>
                            <th>Sub Category</th>
                            <th>Asset Location</th>
                            <th>Asset Given Date</th>
                            <?php if ($key == 2): ?>
                                <th>Asset Return Date</th>
                            <?php endif; ?>
                            <th>Update</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    if (!empty($handovers)):
                        foreach ($handovers as $h):
                            $assetIds = $h->getDecodedAssetIds();
                            if (!empty($assetIds)):
                                $assetModels = Asset::find()
                                    ->where(['id' => $assetIds, 'status' => 1])
                                    ->with(['brand', 'category', 'subcategory', 'location'])
                                    ->all();
                                $count = count($assetModels);
                                if (!empty($assetModels)):
                                    $j = 1;
                                    foreach ($assetModels as $asset):
                    ?>
                                <tr>
                                    <td><?= $i ?></td>
                                    <td><?= Html::encode($asset->product_name ?? '') ?></td>
                                    <td><?= Html::encode($asset->barcode ?? '') ?></td>
                                    <td><?= Html::encode($asset->brand->brand_name ?? '') ?></td>
                                    <td><?= Html::encode($asset->model ?? '') ?></td>
                                    <td><?= Html::encode($asset->category->name ?? '') ?></td>
                                    <td><?= Html::encode($asset->subcategory->name ?? '') ?></td>
                                    <td><?= Html::encode($asset->location->loc ?? '') ?></td>
                                    <td><?= $h->in_date ? date('Y-m-d', strtotime($h->in_date)) : '' ?></td>
                                    <?php if ($key == 2): ?>
                                        <td><?= $h->out_date ? date('Y-m-d', strtotime($h->out_date)) : '' ?></td>
                                    <?php endif; ?>
                                    <?php if ($count > 1 && $j == 1): ?>
                                        <td rowspan="<?= $count ?>">
                                            <a href="<?= \yii\helpers\Url::to(['update-handover', 'id' => $h->id, 'dev_id' => $h->dev_id, 'key' => $key]) ?>"><i class="fa fa-edit" style="margin-top:30%"></i></a>
                                        </td>
                                        <td rowspan="<?= $count ?>">
                                            <i class="fa fa-trash" style="margin-top:30%;color:lightcoral;cursor:pointer" onclick="deletehandover('<?= $h->id ?>', '<?= $h->dev_id ?>', '<?= $key ?>')"></i>
                                        </td>
                                    <?php elseif ($count == 1): ?>
                                        <td>
                                            <a href="<?= \yii\helpers\Url::to(['update-handover', 'id' => $h->id, 'dev_id' => $h->dev_id, 'key' => $key]) ?>"><i class="fa fa-edit"></i></a>
                                        </td>
                                        <td>
                                            <i class="fa fa-trash" style="color:lightcoral;cursor:pointer" onclick="deletehandover('<?= $h->id ?>', '<?= $h->dev_id ?>', '<?= $key ?>')"></i>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                    <?php
                                    $i++;
                                    $j++;
                                    endforeach;
                                endif;
                            endif;
                        endforeach;
                    else:
                    ?>
                        <tr><td colspan="<?= $key == 2 ? 12 : 11 ?>" class="text-center text-muted py-5">No handover records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$filterUrl = \yii\helpers\Url::to(['handover-history', 'dev_id' => $devId]);
$deleteUrl = \yii\helpers\Url::to(['delete-handover']);
$js = <<<JS
$('#handover-filter').on('change', function() {
    var key = $(this).val();
    window.location.href = '{$filterUrl}&key=' + key;
});

window.deletehandover = function(id, dev_id, key) {
    Swal.fire({icon: 'warning', title: 'Delete this handover record?', showCancelButton: true, confirmButtonText: 'Yes, delete', customClass: {confirmButton: 'is-danger'}}).then(function (r) {
        if (!r.isConfirmed) { return; }
        $.ajax({
            url: '{$deleteUrl}?id=' + encodeURIComponent(id) + '&dev_id=' + encodeURIComponent(dev_id) + '&key=' + encodeURIComponent(key),
            type: 'post',
            success: function(data) {
                if (data.status == 1) {
                    Swal.fire({icon:'success', title:'Success', text: data.msg}).then(function(){ location.reload(); });
                } else {
                    Swal.fire({icon:'error', title:'Error', text: data.msg}).then(function(){ location.reload(); });
                }
            }
        });
    });
};
JS;
$this->registerJs($js);
?>
