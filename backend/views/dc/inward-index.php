<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $clientOptions */
/** @var array $companyOptions */

use common\components\UserAccess;
use backend\models\DcDetail;
use kartik\select2\Select2;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Inward Delivery Challans';

$totalCount = $dataProvider->getTotalCount();

$inwardNo = (string) Yii::$app->request->get('inward_dc_no', '');
$clientNm = (string) Yii::$app->request->get('client_name', '');
$compNm   = (string) Yii::$app->request->get('company_name', '');
$hasFilter = $inwardNo !== '' || $clientNm !== '' || $compNm !== '';
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#fff8ec}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="inward-dc-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Inward Delivery Challans</h5>
            <small class="text-muted"><?= $totalCount ?> record<?= $totalCount != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?php if (UserAccess::checkAccess('quotation-dashboard/index')): ?>
                <?= Html::a('Dashboard', ['/quotation-dashboard/index'], ['class' => 'btn btn-outline-dark btn-sm']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" action="<?= Url::to(['inward-index']) ?>" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Inward DC No</label>
                    <input type="text" name="inward_dc_no" class="form-control form-control-sm" placeholder="e.g. INW0001" value="<?= Html::encode($inwardNo) ?>">
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Client Name</label>
                    <?= Select2::widget([
                        'name' => 'client_name',
                        'value' => $clientNm,
                        'data' => $clientOptions,
                        'options' => ['placeholder' => 'All Clients', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">Company Name</label>
                    <?= Select2::widget([
                        'name' => 'company_name',
                        'value' => $compNm,
                        'data' => $companyOptions,
                        'options' => ['placeholder' => 'All Companies', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-12 d-flex flex-wrap gap-1">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if ($hasFilter): ?>
                        <?= Html::a('Clear', ['inward-index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div style="overflow-x:auto;overflow-y:visible">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'layout' => "{items}<div class='d-flex justify-content-between align-items-center px-3 py-2'><div class='d-none d-sm-block small text-muted'>{summary}</div>{pager}</div>",
                'tableOptions' => ['class' => 'table list-table mb-0', 'style' => 'min-width:900px;'],
                'emptyTextOptions' => ['class' => 'text-center text-muted py-5'],
                'emptyText' => 'No inward delivery challans found.',
                'pager' => [
                    'class' => LinkPager::class,
                    'maxButtonCount' => 3,
                    'options' => ['class' => 'pagination pagination-sm mb-0'],
                    'linkOptions' => ['class' => 'page-link'],
                    'disabledListItemSubTagOptions' => ['tag' => 'a', 'class' => 'page-link'],
                ],
                'columns' => [
                    [
                        'class' => 'yii\grid\SerialColumn',
                        'header' => '#',
                        'headerOptions' => ['style' => 'width:40px'],
                        'contentOptions' => ['class' => 'text-muted'],
                    ],
                    [
                        'label' => 'Inward DC No',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width:140px'],
                        'value' => fn($m) => '<span class="fw-semibold">' . Html::encode($m->inward_dc_no ?? '—') . '</span>',
                    ],
                    [
                        'label' => 'Client Name',
                        'value' => fn($m) => $m->client->client_name ?? '—',
                        'contentOptions' => ['class' => 'fw-semibold small'],
                    ],
                    [
                        'label' => 'Company Name',
                        'value' => fn($m) => $m->client->company_name ?? '—',
                        'contentOptions' => ['class' => 'small text-muted'],
                    ],
                    [
                        'label' => 'DC Date',
                        'headerOptions' => ['style' => 'width:120px'],
                        'value' => fn($m) => $m->dc_date ? date('d M Y', strtotime($m->dc_date)) : '—',
                    ],
                    [
                        'label' => 'Expected Return',
                        'headerOptions' => ['style' => 'width:140px'],
                        'value' => fn($m) => $m->dc_return_date ? date('d M Y', strtotime($m->dc_return_date)) : '—',
                    ],
                    [
                        'label' => 'Return Status',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width:130px'],
                        'value' => function ($m) {
                            $returned = DcDetail::find()
                                ->where(['type' => 1, 'inward_dc_id' => $m->id, 'status' => 1])
                                ->exists();
                            return $returned
                                ? '<span class="badge bg-success">Returned</span>'
                                : '<span class="badge bg-secondary">Not Returned</span>';
                        },
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'header' => 'Actions',
                        'headerOptions' => ['class' => 'text-end', 'style' => 'width:220px'],
                        'contentOptions' => ['class' => 'text-end'],
                        'template' => '{view} {addReturn}',
                        'buttons' => [
                            'view' => fn($url, $m) => UserAccess::checkAccess('dc/inward-view')
                                ? Html::a('<i class="fas fa-eye"></i> View', ['inward-view', 'id' => $m->id], [
                                    'class' => 'btn btn-outline-primary act-btn me-1', 'encode' => false,
                                ]) : '',
                            'addReturn' => function ($url, $m) {
                                if (!UserAccess::checkAccess('dc/add-return')) return '';
                                $returned = DcDetail::find()
                                    ->where(['type' => 1, 'inward_dc_id' => $m->id, 'status' => 1])
                                    ->exists();
                                if ($returned) {
                                    return Html::tag('button', '<i class="fas fa-check"></i> Returned', [
                                        'class' => 'btn btn-secondary act-btn', 'disabled' => true, 'encode' => false, 'type' => 'button',
                                    ]);
                                }
                                return Html::a('<i class="fas fa-undo"></i> Add Return', ['add-return', 'id' => $m->id], [
                                    'class' => 'btn btn-success act-btn', 'encode' => false,
                                ]);
                            },
                        ],
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>
