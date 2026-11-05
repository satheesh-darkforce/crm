<?php
/** @var \yii\web\View $this */
/** @var \backend\models\IotProduct $model */

use yii\bootstrap5\Html;
use backend\models\IotUploads;

$this->title = $model->product_name;
?>
<div class="ui-page iot-product-view">
    <div class="ui-head">
        <div>
            <h1><?= Html::encode($model->product_name) ?></h1>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0" style="max-width:700px">
                <tr>
                    <th class="text-muted" style="width:180px">Product Code</th>
                    <td class="font-monospace fw-semibold"><?= Html::encode($model->product_code) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Product Name</th>
                    <td><?= Html::encode($model->product_name) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Part Number</th>
                    <td><?= Html::encode($model->part_number ?: '—') ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Category</th>
                    <td><?= $model->category ? Html::encode($model->category->name) : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Sub Category</th>
                    <td><?= $model->subcategory ? Html::encode($model->subcategory->name) : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Brand</th>
                    <td><?= $model->brand ? Html::encode($model->brand->brand_name) : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Vendor</th>
                    <td><?= $model->vendor ? Html::encode($model->vendor->company_name) : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Location</th>
                    <td><?= $model->location ? Html::encode($model->location->location) : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Stock Qty</th>
                    <td>
                        <span class="<?= $model->stock_qty <= 0 ? 'text-danger fw-bold' : ($model->stock_qty <= 5 ? 'text-warning fw-semibold' : 'text-success fw-semibold') ?>">
                            <?= (int)$model->stock_qty ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th class="text-muted">Service Contact</th>
                    <td><?= Html::encode($model->service_contact ?: '—') ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Tags</th>
                    <td>
                        <?php
                        $tagStr = $model->getTagNames();
                        if ($tagStr) {
                            foreach (explode(', ', $tagStr) as $t):
                        ?>
                            <span class="badge bg-secondary-subtle text-secondary border me-1"><?= Html::encode($t) ?></span>
                        <?php endforeach; } else { echo '—'; } ?>
                    </td>
                </tr>
                <?php if ($model->description): ?>
                <tr>
                    <th class="text-muted">Description</th>
                    <td><?= nl2br(Html::encode($model->description)) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th class="text-muted">Created At</th>
                    <td><?= $model->created_at ? Yii::$app->formatter->asDate($model->created_at, 'php:Y-m-d') : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Status</th>
                    <td>
                        <span class="badge <?= $model->status == 1 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                            <?= $model->status == 1 ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <?php
    $uploads = IotUploads::find()->where(['product_id' => $model->id, 'status' => 1])->orderBy('id')->all();
    if (!empty($uploads)):
    ?>
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold py-2" style="font-size:.9rem">
            <i class="fas fa-save"></i> Attachments <span class="badge bg-secondary ms-1"><?= count($uploads) ?></span>
        </div>
        <div class="card-body py-2">
            <div class="d-flex flex-column gap-2">
                <?php foreach ($uploads as $up):
                    $ext  = strtolower($up->type ?? '');
                    $icon = in_array($ext, ['jpg','jpeg','png','gif']) ? '<i class="fas fa-image"></i>️'
                          : ($ext === 'pdf' ? '<i class="fas fa-file-alt"></i>'
                          : (in_array($ext, ['doc','docx']) ? '<i class="fas fa-pen-to-square"></i>'
                          : (in_array($ext, ['xls','xlsx','csv']) ? '<i class="fas fa-chart-bar"></i>' : '<i class="fas fa-paperclip"></i>')));
                ?>
                <div class="d-flex align-items-center gap-2" style="font-size:.875rem">
                    <span style="font-size:1.1rem"><?= $icon ?></span>
                    <a href="<?= Yii::$app->request->baseUrl . Html::encode($up->path) ?>" target="_blank" rel="noreferrer noopener" class="text-decoration-none">
                        <?= Html::encode(basename($up->path)) ?>
                    </a>
                    <span class="text-muted" style="font-size:.75rem"><?= strtoupper($ext) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
