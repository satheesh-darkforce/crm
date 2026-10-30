<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Vendor[] $vendors */

use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

$this->title = 'Select Vendor';

$createUrl = Url::to(['create']);
?>
<div class="inward-invoice-select-vendor">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0">Create Inward Invoice</h5>
        <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p class="text-muted small mb-3">Select a vendor to create an inward invoice.</p>
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Vendor <span class="text-danger">*</span></label>
                    <?= Select2::widget([
                        'name'          => 'vendor_id',
                        'data'          => ArrayHelper::map($vendors, 'id', 'company_name'),
                        'options'       => ['id' => 'vendor-select', 'placeholder' => '— Select Vendor —'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button id="btn-proceed" class="btn btn-primary btn-sm" disabled>Proceed</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$this->registerJs(<<<JS
jQuery('#vendor-select').on('change', function() {
    jQuery('#btn-proceed').prop('disabled', !this.value);
});
jQuery('#btn-proceed').on('click', function() {
    var vid = jQuery('#vendor-select').val();
    if (vid) window.location.href = '{$createUrl}?vendor_id=' + vid;
});
JS, \yii\web\View::POS_READY);
?>
