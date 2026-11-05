<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var int    $modelOpen */
/** @var string $companyName */
/** @var string $clientName */
/** @var array  $dc  ['total_dc','returnable_dc','sale_dc','exceeded_dc','returned_dc','cancelled_dc'] */

use yii\bootstrap5\LinkPager;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Delivery Dashboard';
$this->registerCssFile('@web/css/quotation-dashboard.css');

$tiles = [
    ['key' => 'total_dc',      'label' => 'Total DC',        'type' => 'alldc',      'class' => 'card-home-total',      'icon' => 'fa-solid fa-file-invoice'],
    ['key' => 'returnable_dc', 'label' => 'Returnable DC',   'type' => 'returnable', 'class' => 'card-home-returnable', 'icon' => 'fa-solid fa-rotate-left'],
    ['key' => 'sale_dc',       'label' => 'Sale DC',         'type' => 'sale',       'class' => 'card-home-sale',       'icon' => 'fa-solid fa-tag'],
    ['key' => 'service_dc',    'label' => 'Service DC',      'type' => 'service',    'class' => 'card-home-service',    'icon' => 'fa-solid fa-screwdriver-wrench'],
    ['key' => 'exceeded_dc',   'label' => 'DC Date Overdue', 'type' => 'overdue',    'class' => 'card-home-overdue',    'icon' => 'fa-solid fa-triangle-exclamation'],
    ['key' => 'returned_dc',   'label' => 'Returned DC',     'type' => 'returned',   'class' => 'card-home-returned',   'icon' => 'fa-solid fa-check-double'],
    ['key' => 'cancelled_dc',  'label' => 'Cancelled DC',    'type' => 'cancelled',  'class' => 'card-home-cancelled',  'icon' => 'fa-solid fa-ban'],
    [
        'key'   => 'inward_dc',
        'label' => 'Inward DC',
        'class' => 'card-home-inward',
        'icon'  => 'fa-solid fa-truck-ramp-box',
        'url'   => ['/dc/inward-index'],
        'gate'  => 'dc/inward-index',
    ],
];
?>

<div class="ui-page quotation-dashboard">
    <div class="ui-head">
        <div>
            <h1>Delivery dashboard</h1>
            <div class="ui-sub">Delivery challans at a glance — pick a tile to see the list.</div>
        </div>
        <div class="ui-actions">
            <?php if (\common\components\UserAccess::checkAccess('dc/create')): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>New DC', ['/dc/create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard row g-3 mb-4">
        <?php foreach ($tiles as $t): ?>
            <?php if (!empty($t['gate']) && !\common\components\UserAccess::checkAccess($t['gate'])) continue; ?>
            <?php $href = !empty($t['url'])
                ? Url::to($t['url'])
                : Url::to(['/quotation-dashboard/list-dc', 'type' => $t['type']]); ?>
            <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                <div class="card-home <?= $t['class'] ?>">
                    <a href="<?= $href ?>">
                        <span class="card-home-icon"><i class="<?= Html::encode($t['icon'] ?? 'fa-solid fa-circle') ?>"></i></span>
                        <div class="card-home-body">
                            <p class="card-home-label"><?= Html::encode($t['label']) ?></p>
                            <p class="card-home-value"><?= (int)($dc[$t['key']] ?? 0) ?></p>
                        </div>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center my-3">
        <h2 class="h6 fw-semibold mb-0">Clients</h2>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-secondary search-btn">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            <?php if (\common\components\UserAccess::checkAccess('client/create')): ?>
                <a href="<?= Url::to(['/client/create']) ?>" class="btn btn-sm btn-success fw-bolder">Add Client</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-3 search-toggle" style="<?= $modelOpen ? '' : 'display:none;' ?>">
        <form action="<?= Url::to(['/quotation-dashboard/index']) ?>" method="post">
            <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
            <div class="row g-2">
                <div class="col-md-6 col-xl-4">
                    <input type="text" name="client_name" class="form-control form-control-sm"
                           placeholder="Client Name" value="<?= Html::encode($clientName) ?>">
                </div>
                <div class="col-md-6 col-xl-4">
                    <input type="text" name="company_name" class="form-control form-control-sm"
                           placeholder="Company Name" value="<?= Html::encode($companyName) ?>">
                </div>
                <div class="col-xl-4 text-md-start">
                    <button class="btn btn-sm btn-success" type="submit" name="action" value="submit">Submit</button>
                    <button class="btn btn-sm btn-dark"    type="submit" name="action" value="reset">Reset</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'layout' => "{items}<div class='d-flex justify-content-between align-items-center px-3 py-2'><div class='d-none d-sm-block small text-muted'>{summary}</div>{pager}</div>",
                'tableOptions' => ['class' => 'table list-table mb-0'],
                'pager' => [
                    'class' => LinkPager::class,
                    'firstPageLabel' => 'First', 'lastPageLabel' => 'Last',
                    'prevPageLabel' => '‹',      'nextPageLabel' => '›',
                    'maxButtonCount' => 3,
                    'options' => ['class' => 'pagination pagination-sm mb-0'],
                    'linkOptions' => ['class' => 'page-link'],
                    'disabledListItemSubTagOptions' => ['tag' => 'a', 'class' => 'page-link'],
                ],
                'options' => ['tag' => 'div'],
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn', 'header' => '#', 'headerOptions' => ['style' => 'width:40px']],
                    'client_name',
                    'company_name',
                    'phone',
                    'email_id',
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{addDc} {addInwardDc} {addQuote}',
                        'header' => 'Actions',
                        'headerOptions' => ['class' => 'text-end'],
                        'contentOptions' => ['class' => 'text-end'],
                        'buttons' => [
                            'addDc' => function ($url, $model) {
                                if (!\common\components\UserAccess::checkAccess('dc/create')) return '';
                                return Html::a('Add DC', ['/dc/create', 'id' => $model->id],
                                    ['class' => 'btn btn-sm btn-success act-btn', 'data-pjax' => '0']);
                            },
                            'addInwardDc' => function ($url, $model) {
                                if (!\common\components\UserAccess::checkAccess('dc/create-inward')) return '';
                                return Html::a('Add Inward DC', ['/dc/create-inward', 'id' => $model->id],
                                    ['class' => 'btn btn-sm btn-warning act-btn', 'data-pjax' => '0']);
                            },
                            'addQuote' => function ($url, $model) {
                                if (!\common\components\UserAccess::checkAccess('client-quotation/create')) return '';
                                return Html::a('Add Quote', ['/client-quotation/create', 'id' => $model->id, 'returnUrl' => Url::current()],
                                    ['class' => 'btn btn-sm act-btn btn-indigo', 'data-pjax' => '0']);
                            },
                        ],
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>

<?php
$this->registerJs("
    $(document).on('click', '.search-btn', function () {
        $('.search-toggle').toggle();
    });
");
