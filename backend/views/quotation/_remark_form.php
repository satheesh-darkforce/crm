<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Quotation $model */
/** @var \backend\models\QuotationRemark $remark */

use yii\bootstrap5\Html;
use yii\helpers\Url;
?>
<form id="remarkForm" method="post" action="<?= Url::to(['add-remark', 'id' => $model->id]) ?>">
    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
    <div class="mb-3">
        <label class="form-label form-label-sm text-muted fw-semibold">
            Quotation: <strong><?= Html::encode($model->unique_id) ?></strong> — <?= Html::encode($model->client_name) ?>
        </label>
        <textarea name="QuotationRemark[remarks]" class="form-control form-control-sm" rows="5"
                  placeholder="Enter remark…" required><?= Html::encode($remark->remarks ?? '') ?></textarea>
        <?php if ($remark->hasErrors('remarks')): ?>
            <div class="invalid-feedback d-block"><?= Html::encode($remark->getFirstError('remarks')) ?></div>
        <?php endif; ?>
    </div>
    <div id="remarkErrors"></div>
</form>
