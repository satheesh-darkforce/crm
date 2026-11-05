<?php
/**
 * Page head + collapsible search panel for list and report pages (work reports, invoices, …).
 * Filter values come from the query string; controllers clean them before use.
 *
 * @var \yii\web\View $this
 * @var string      $title
 * @var string|null $sub      plain text under the title
 * @var string|null $meta     ready-made HTML shown next to the actions (totals)
 * @var string|null $actions  ready-made HTML buttons
 * @var string      $route    form action, e.g. 'index'
 * @var array       $filters  [['type' => date|select|dropdown|text|html, 'name', 'label', 'data', 'placeholder', 'multiple', 'col', 'html']]
 * @var string[]|null $countKeys  query keys that count as an active search (default: the filter names)
 */

use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$req     = Yii::$app->request;
$filters = $filters ?? [];
$keys    = $countKeys ?? array_filter(array_map(fn($f) => $f['name'] ?? null, $filters));
$active  = 0;
foreach ($keys as $k) {
    $v = $req->get($k);
    if ($v !== null && $v !== '' && $v !== []) {
        $active++;
    }
}
$panelId = 'wr-search-' . preg_replace('/\W/', '', $route);
?>
<div class="ui-head">
    <div>
        <h1><?= Html::encode($title) ?></h1>
        <?php if (!empty($sub)): ?><div class="ui-sub"><?= Html::encode($sub) ?><?= $active ? ' · matching your search' : '' ?></div><?php endif; ?>
    </div>
    <div class="ui-actions">
        <?= $meta ?? '' ?>
        <?php if ($filters): ?>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $panelId ?>"
                    aria-expanded="<?= $active ? 'true' : 'false' ?>" aria-controls="<?= $panelId ?>">
                <i class="fas fa-search me-1"></i>Search<?php if ($active): ?> <span class="ui-count ms-1"><?= $active ?></span><?php endif; ?>
            </button>
        <?php endif; ?>
        <?= $actions ?? '' ?>
    </div>
</div>

<?php if ($filters): ?>
<div class="collapse <?= $active ? 'show' : '' ?>" id="<?= $panelId ?>">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="get" action="<?= Url::to([$route]) ?>" class="row g-2 align-items-end">
                <?php foreach ($filters as $f):
                    $name  = $f['name'] ?? '';
                    $id    = 'wf-' . preg_replace('/\W/', '', $name);
                    $col   = $f['col'] ?? 'col-lg-3 col-md-6';
                    $label = '<label class="form-label small mb-1" for="' . $id . '">' . Html::encode($f['label'] ?? '') . '</label>';
                    $value = $req->get(rtrim($name, '[]'));
                ?>
                    <div class="<?= $col ?>">
                    <?php switch ($f['type']):
                        case 'date': ?>
                            <?= $label . DatePicker::widget([
                                'name' => $name, 'value' => is_string($value) ? $value : '', 'type' => DatePicker::TYPE_INPUT,
                                'options' => ['id' => $id, 'class' => 'form-control form-control-sm', 'placeholder' => 'YYYY-MM-DD', 'autocomplete' => 'off'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true, 'clearBtn' => true],
                            ]) ?>
                            <?php break;
                        case 'select': ?>
                            <?= $label . Select2::widget([
                                'name' => $name, 'value' => ($value === '' ? null : $value), 'data' => $f['data'] ?? [],
                                'options' => ['id' => $id, 'placeholder' => $f['placeholder'] ?? 'All', 'multiple' => !empty($f['multiple'])],
                                'size' => Select2::SMALL,
                                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                            ]) ?>
                            <?php break;
                        case 'dropdown': // short fixed lists (≤ 5 options) ?>
                            <?= $label . Html::dropDownList($name, is_string($value) ? $value : '', $f['data'] ?? [],
                                ['id' => $id, 'class' => 'form-select form-select-sm', 'prompt' => $f['placeholder'] ?? 'All']) ?>
                            <?php break;
                        case 'text': ?>
                            <?= $label ?>
                            <input type="text" id="<?= $id ?>" name="<?= Html::encode($name) ?>" value="<?= Html::encode(is_string($value) ? $value : '') ?>"
                                   class="form-control form-control-sm" maxlength="100" placeholder="<?= Html::encode($f['placeholder'] ?? '') ?>">
                            <?php break;
                        default: ?>
                            <?= $f['html'] ?? '' ?>
                    <?php endswitch; ?>
                    </div>
                <?php endforeach; ?>
                <div class="col-lg-2 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                    <?= Html::a('Reset', [$route], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
