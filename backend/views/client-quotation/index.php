<?php

/** @var yii\web\View $this */
/** @var backend\models\ClientQuotationSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array $clientOptions */
/** @var array $companyOptions */
/** @var array $userOptions */
/** @var int $total */
/** @var int $cancelledTotal */
/** @var int $completedTotal */

use common\components\UserAccess;
use backend\models\ClientQuotation;
use backend\models\ClientQuotationFile;
use kartik\select2\Select2;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\LinkPager;
use yii\bootstrap5\Modal;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

$this->title = 'Quotations';

$quoteForLabels  = [1 => 'IT', 2 => 'IOT', 3 => 'Hardware + Software'];
$quoteForBadges  = [1 => 'bg-primary', 2 => 'bg-info text-dark', 3 => 'bg-success'];
$statusOptions   = [1 => 'Active', 2 => 'Cancelled', 3 => 'Completed'];
$totalCount      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<?php
$currentStatus = $searchModel->status;
$activeTotal = max(0, (int) $total - (int) $cancelledTotal - (int) $completedTotal);
$tabs = [
    ''  => ['label' => 'All',       'count' => (int) $total],
    '1' => ['label' => 'Active',    'count' => $activeTotal],
    '3' => ['label' => 'Completed', 'count' => (int) $completedTotal],
    '2' => ['label' => 'Cancelled', 'count' => (int) $cancelledTotal],
];
$tabHref = static function ($key) {
    $params = ['index'];
    if ($key !== '') {
        $params['ClientQuotationSearch'] = ['status' => $key];
    }
    return Url::to($params);
};
$isActiveTab = static function ($key) use ($currentStatus) {
    return (string) $currentStatus === (string) $key;
};
?>
<style>
.cq-tabs { display:flex; gap:4px; border-bottom:1px solid #e2e8f0; margin-bottom:14px; }
.cq-tabs a { padding:8px 16px; font-size:.85rem; font-weight:500; color:#64748b; text-decoration:none; border-bottom:2px solid transparent; transition:all .15s; }
.cq-tabs a:hover { color:#0f172a; }
.cq-tabs a.active { color:var(--app-accent); border-bottom-color:var(--app-accent); font-weight:600; }
.cq-tabs .badge { font-size:.7rem; padding:2px 7px; margin-left:4px; }
</style>

<div class="ui-page quotation-index">
    <div class="ui-head">
        <div>
            <h1>Quotations</h1>
            <div class="ui-sub"><?= (int) $total ?> total · <?= (int) $completedTotal ?> completed · <?= (int) $cancelledTotal ?> cancelled</div>
        </div>
        <div class="ui-actions">
            <?php if (UserAccess::checkAccess('client-quotation/create')): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add quotation', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php Pjax::begin([
        'id'              => 'cq-list-pjax',
        'timeout'         => 5000,
        'enablePushState' => true,
        // Intercept the tab anchors, the pagination, and the filter Clear
        // button. Action-menu items (View/Download/Cancel) opt out via
        // data-pjax="0" so file downloads and full navigations work normally.
        'linkSelector'    => '#cq-list-pjax .cq-tabs a, #cq-list-pjax .pagination a, #cq-list-pjax .btn-clear-filters',
        'formSelector'    => '#cq-filter-form',
    ]); ?>

    <!-- Status tabs -->
    <div class="cq-tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <a class="<?= $isActiveTab($key) ? 'active' : '' ?>" href="<?= $tabHref($key) ?>">
                <?= Html::encode($tab['label']) ?>
                <span class="badge bg-light text-secondary"><?= (int) $tab['count'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <?php $form = ActiveForm::begin(['method' => 'get', 'action' => ['index'], 'id' => 'cq-filter-form', 'fieldConfig' => [
                'options' => ['class' => 'mb-0'],
                'labelOptions' => ['class' => 'form-label form-label-sm mb-1 text-muted', 'style' => 'font-size:.75rem'],
                'inputOptions' => ['class' => 'form-control form-control-sm'],
            ]]); ?>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2">
                    <?= $form->field($searchModel, 'client_id')->widget(Select2::class, [
                        'data' => $clientOptions,
                        'options' => ['placeholder' => 'All Clients', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Client') ?>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2">
                    <?= $form->field($searchModel, 'company')->widget(Select2::class, [
                        'data' => $companyOptions,
                        'options' => ['placeholder' => 'All Companies', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Company') ?>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2">
                    <?= $form->field($searchModel, 'quote_no')->textInput(['placeholder' => 'e.g. QIT/25-26/001'])->label('Quote No') ?>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2">
                    <?= $form->field($searchModel, 'project_name')->textInput(['placeholder' => 'Project'])->label('Project') ?>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2">
                    <?= $form->field($searchModel, 'quoted_by')->widget(Select2::class, [
                        'data' => $userOptions,
                        'options' => ['placeholder' => 'Any', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ])->label('Quote By') ?>
                </div>
                <div class="col-12 col-sm-12 col-md-12 col-lg-12 col-xl-12 d-flex flex-wrap gap-1">
                    <?= Html::submitButton('Filter', ['class' => 'btn btn-secondary btn-sm']) ?>
                    <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm btn-clear-filters']) ?>
                </div>
            </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div style="overflow-x:auto;overflow-y:visible">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'layout' => "{items}<div class='d-flex justify-content-between align-items-center px-3 py-2'><div class='d-none d-sm-block small text-muted'>{summary}</div>{pager}</div>",
        'tableOptions' => ['class' => 'table list-table mb-0', 'style' => 'min-width:770px;'],
        'pager' => [
            'class' => LinkPager::class,
            'firstPageLabel' => 'First',
            'lastPageLabel' => 'Last',
            'prevPageLabel' => '‹',
            'nextPageLabel' => '›',
            'maxButtonCount' => 3,
            'options' => ['class' => 'pagination pagination-sm mb-0'],
            'linkOptions' => ['class' => 'page-link'],
            'disabledListItemSubTagOptions' => ['tag' => 'a', 'class' => 'page-link'],
        ],
        'options' => ['tag' => 'div'],
        'emptyTextOptions' => ['class' => 'text-center text-muted py-5'],
        'emptyText' => 'No quotations found.',
        'columns' => [
            [
                'class' => 'yii\grid\SerialColumn',
                'header' => '#',
                'headerOptions' => ['style' => 'width:40px'],
                'contentOptions' => ['class' => 'text-muted'],
            ],
            [
                'label' => 'Quote No',
                'format' => 'raw',
                'value' => function ($m) {
                    return Html::encode($m->quote_no ?: '-');
                },
            ],
            [
                'label' => 'Status',
                'format' => 'raw',
                'headerOptions' => ['style' => 'width:100px', 'class' => 'text-center'],
                'contentOptions' => ['class' => 'text-center'],
                'value' => function ($m) {
                    if ((int) $m->status === 2) {
                        return '<span class="badge bg-danger">Cancelled</span>';
                    }
                    if ((int) $m->completed_status === 1) {
                        return '<span class="badge bg-success">Completed</span>';
                    }
                    return '<span class="badge bg-primary">Active</span>';
                },
            ],
            [
                'label' => 'Client / Company',
                'format' => 'raw',
                'value' => function ($m) {
                    $clientName  = $m->client->client_name  ?? '-';
                    $companyName = $m->client->company_name ?? '-';
                    return '<div class="fw-semibold">' . Html::encode($clientName) . '</div>'
                        . '<div class="small text-muted">' . Html::encode($companyName) . '</div>';
                },
            ],
            [
                'label' => 'Project Name',
                'value' => fn($m) => $m->project_name ?: '-',
            ],
            [
                'label' => 'Quote For',
                'format' => 'raw',
                'value' => function ($m) use ($quoteForLabels, $quoteForBadges) {
                    $label = $quoteForLabels[(int) $m->quote_for] ?? '-';
                    $badge = $quoteForBadges[(int) $m->quote_for] ?? 'bg-secondary';
                    return '<span class="badge ' . $badge . '">' . Html::encode($label) . '</span>';
                },
            ],
            [
                'label' => 'Estimated Cost<br>(Latest)',
                'encodeLabel' => false,
                'headerOptions' => ['style' => 'width:120px'],
                'contentOptions' => ['class' => 'small'],
                'value' => function ($m) {
                    $latest = ClientQuotation::find()
                        ->where(['or', ['parent_id' => $m->id], ['id' => $m->id]])
                        ->orderBy(['id' => SORT_DESC])
                        ->one();
                    return $latest->estimation_cost ?? '-';
                },
            ],
            [
                'label' => 'No of<br>Quote',
                'encodeLabel' => false,
                'headerOptions' => ['style' => 'width:80px', 'class' => 'text-center'],
                'contentOptions' => ['class' => 'text-center small'],
                'value' => fn($m) => ClientQuotation::find()
                    ->where(['or', ['parent_id' => $m->id], ['id' => $m->id]])
                    ->count(),
            ],
            [
                'label' => 'Quote Date',
                'headerOptions' => ['style' => 'width:90px'],
                'contentOptions' => ['class' => 'small'],
                'value' => fn($m) => $m->quote_date ? date('d M Y', strtotime($m->quote_date)) : '-',
            ],
            [
                'label' => 'Quoted By',
                'value' => fn($m) => $userOptions[$m->quoted_by] ?? '-',
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'header' => 'Actions',
                'template' => '{menu}',
                'headerOptions' => ['class' => 'text-center actions-col', 'style' => 'width:60px'],
                'contentOptions' => ['class' => 'text-center actions-col'],
                'buttons' => [
                    'menu' => function ($url, $m) {
                        $canView     = UserAccess::checkAccess('client-quotation/view');
                        $canCancel   = (int) $m->status !== 2 && UserAccess::checkAccess('client-quotation/cancel');
                        $canDownload = false;
                        if (UserAccess::checkAccess('client-quotation/download-latest')) {
                            // Match delivery: only show when at least one attachment exists in the revision chain.
                            $quoteIds = ClientQuotation::find()
                                ->select('id')
                                ->where(['parent_id' => $m->id])
                                ->column();
                            $quoteIds[] = $m->id;
                            $canDownload = ClientQuotationFile::find()
                                ->where(['quote_id' => $quoteIds, 'status' => 1])
                                ->andWhere(['not', ['file_path' => null]])
                                ->andWhere(['!=', 'file_path', ''])
                                ->exists();
                        }
                        if (!$canView && !$canDownload && !$canCancel) {
                            return '';
                        }
                        $items = '';
                        if ($canView) {
                            $items .= '<li>' . Html::a(
                                '<i class="fas fa-eye me-2 text-primary"></i>View',
                                ['view', 'id' => $m->id],
                                ['class' => 'dropdown-item', 'data-pjax' => '0', 'encode' => false]
                            ) . '</li>';
                        }
                        if ($canDownload) {
                            $items .= '<li>' . Html::a(
                                '<i class="fas fa-download me-2 text-secondary"></i>Download',
                                ['download-latest', 'id' => $m->id],
                                ['class' => 'dropdown-item', 'data-pjax' => '0', 'encode' => false]
                            ) . '</li>';
                        }
                        if ($canCancel) {
                            // Inline onclick → window.openCancelQuoteModal — necessary because
                            // delegated jQuery handlers don't reliably fire on dropdown-items in
                            // this layout.
                            $items .= '<li><a href="javascript:void(0)" class="dropdown-item text-danger" onclick="openCancelQuoteModal(' . (int) $m->id . ')">'
                                . '<i class="fas fa-times me-2"></i>Cancel</a></li>';
                        }
                        return '<div class="dropdown">'
                            . '<button class="btn btn-outline-secondary act-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">&#8942;</button>'
                            . '<ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem; min-width:160px;">'
                            . $items
                            . '</ul>'
                            . '</div>';
                    },
                ],
            ],
        ],
    ]) ?>
      </div>
    </div>

    <?php Pjax::end(); ?>
</div>

<?php
Modal::begin([
    'title' => 'Cancel Quotation',
    'id' => 'cancelModal',
]);
?>
<input type="hidden" id="cancel_quote_id">
<div class="mb-3">
    <label>Reason <span class="text-danger">*</span></label>
    <textarea id="cancel_reason" class="form-control" placeholder="Enter reason"></textarea>
</div>
<button class="btn btn-danger" id="submitCancel">Submit</button>
<?php Modal::end(); ?>

<?php
$cancelUrl = Url::to(['cancel']);
$this->registerJs(<<<JS
// Exposed on window so the inline onclick on the Cancel dropdown-item works.
// Delegated jQuery handlers don't reliably reach .dropdown-item anchors in
// this Yii2 layout.
window.openCancelQuoteModal = function (id) {
    $('#cancel_quote_id').val(id);
    $('#cancel_reason').val('');
    $('#cancelModal').modal('show');
};
// Backwards-compatible: any old .btn-cancel-quote buttons still work.
$(document).on('click', '.btn-cancel-quote', function () {
    window.openCancelQuoteModal($(this).data('id'));
});

$('#submitCancel').on('click', function () {
    var id     = $('#cancel_quote_id').val();
    var reason = ($('#cancel_reason').val() || '').trim();
    if (reason === '') {
        if (window.Swal) {
            Swal.fire({title:'Error', text:'Please enter reason', icon:'error', allowOutsideClick:false, allowEscapeKey:false});
        } else {
            alert('Please enter reason');
        }
        return;
    }
    $.ajax({
        url: '$cancelUrl',
        type: 'POST',
        dataType: 'json',
        data: { id: id, reason: reason, _csrf: (window.yii ? yii.getCsrfToken() : '') },
        success: function (r) {
            if (r && r.success) {
                if (window.Swal) {
                    Swal.fire({title:'Success', text: r.message || 'Quotation cancelled successfully.', icon:'success',
                        confirmButtonText:'OK', allowOutsideClick:false, allowEscapeKey:false}).then(function(){ location.reload(); });
                } else {
                    alert(r.message || 'Quotation cancelled successfully.');
                    location.reload();
                }
                return;
            }
            var msg = (r && r.message) ? r.message : 'Unable to cancel quotation.';
            if (window.Swal) Swal.fire({title:'Error', text: msg, icon:'error'}); else alert(msg);
        },
        error: function (xhr) {
            var msg = 'Something went wrong while cancelling the quotation.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (window.Swal) Swal.fire({title:'Error', text: msg, icon:'error'}); else alert(msg);
        }
    });
});
JS);
?>
