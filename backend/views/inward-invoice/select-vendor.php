<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Vendor[] $vendors */

use backend\models\Vendor;
use common\components\UserAccess;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'Add Inward Invoice';

// Vendors without a type cannot be billed yet (it decides which GST applies).
$data = [];
foreach ($vendors as $v) {
    $label = Vendor::typeLabels()[(int)$v->vendor_type] ?? null;
    $data[$v->id] = $v->company_name . ($label ? ' · ' . $label : ' · type not set');
}
?>
<div class="ui-page inward-invoice-select-vendor" style="max-width:760px">
    <div class="ui-head">
        <div>
            <h1>Add inward invoice</h1>
            <div class="ui-sub">Step 1 of 2 — who sent the bill?</div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['index'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if (!$vendors): ?>
                <?= $this->render('@backend/views/_ui/_empty', ['icon' => 'truck', 'title' => 'No vendors yet', 'text' => 'Add the vendor first, then record their invoice.',
                    'action' => UserAccess::checkAccess('vendor/create') ? Html::a('<i class="fas fa-plus me-1"></i>Add vendor', ['/vendor/create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null]) ?>
            <?php else: ?>
                <form method="get" action="<?= Url::to(['create']) ?>" class="ui-form js-busy-form">
                    <div class="required mb-3">
                        <label class="form-label" for="vendor-select">Vendor</label>
                        <?= Select2::widget([
                            'name' => 'vendor_id', 'data' => $data,
                            'options' => ['id' => 'vendor-select', 'placeholder' => 'Search vendors', 'required' => true],
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                        <div class="form-text">The vendor type (intra-state, inter-state or international) decides which GST applies.</div>
                    </div>
                    <div class="ui-form-actions">
                        <button type="submit" class="btn btn-primary btn-sm px-4">Continue<i class="fas fa-arrow-right ms-1"></i></button>
                        <?php if (UserAccess::checkAccess('vendor/create')): ?>
                            <?= Html::a('New vendor', ['/vendor/create'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
