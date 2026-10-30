<?php

/** @var yii\web\View $this */
/** @var yii\data\ArrayDataProvider $dataProvider */
/** @var string $title */
/** @var string $type */

use common\components\UserAccess;
use kartik\date\DatePicker;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;
use yii\helpers\Html;

$this->title = $title;
$totalCount = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="dc-list-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold"><?= Html::encode($title) ?></h5>
            <small class="text-muted"><?= (int) $totalCount ?> record<?= $totalCount != 1 ? 's' : '' ?></small>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('Back', ['/quotation-dashboard/index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'layout' => "{items}<div class='d-flex justify-content-between align-items-center px-3 py-2'><div class='d-none d-sm-block small text-muted'>{summary}</div>{pager}</div>",
                'tableOptions' => ['class' => 'table list-table mb-0'],
                'options' => ['tag' => 'div'],
                'emptyTextOptions' => ['class' => 'text-center text-muted py-5'],
                'emptyText' => 'No delivery challans found.',
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
                        'label' => 'DC No',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width:110px'],
                        'value' => fn($m) => '<span class="fw-semibold">' . Html::encode($m['dc_no'] ?? '—') . '</span>',
                    ],
                    [
                        'label' => 'Client',
                        'value' => fn($m) => $m['client']['client_name'] ?? '—',
                        'contentOptions' => ['class' => 'fw-semibold small'],
                    ],
                    [
                        'label' => 'Company',
                        'value' => fn($m) => $m['client']['company_name'] ?? '—',
                        'contentOptions' => ['class' => 'small text-muted'],
                    ],
                    [
                        'label' => 'DC Date',
                        'headerOptions' => ['style' => 'width:120px'],
                        'value' => fn($m) => !empty($m['dc_date']) ? date('d M Y', strtotime($m['dc_date'])) : '—',
                    ],
                    [
                        'label' => 'Type',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width:160px'],
                        'value' => function ($m) {
                            $t = (int) ($m['dc_type'] ?? 0);
                            $map = [
                                1 => ['Returnable', 'bg-warning text-dark'],
                                2 => ['Sale',       'bg-info text-dark'],
                                3 => ['Service',    'bg-primary'],
                            ];
                            [$txt, $cls] = $map[$t] ?? ['—', 'bg-secondary'];
                            $html = '<span class="badge ' . $cls . '">' . $txt . '</span>';
                            if ($t === 1) {
                                $returned = !empty($m['dc_returned_date']) && $m['dc_returned_date'] !== '0000-00-00';
                                $html .= ' ' . ($returned
                                    ? '<span class="badge bg-success">Returned</span>'
                                    : '<span class="badge bg-secondary">Not Returned</span>');
                            }
                            return $html;
                        },
                    ],
                    [
                        'label' => 'Return Date',
                        'headerOptions' => ['style' => 'width:120px'],
                        'value' => fn($m) => !empty($m['dc_return_date']) ? date('d M Y', strtotime($m['dc_return_date'])) : '—',
                    ],
                    [
                        'label' => 'Status',
                        'format' => 'raw',
                        'headerOptions' => ['style' => 'width:100px'],
                        'value' => function ($m) {
                            $s = (int) ($m['status'] ?? 0);
                            if ($s === 2) return '<span class="badge bg-danger">Cancelled</span>';
                            if ($s === 3) return '<span class="badge bg-warning text-dark">Draft</span>';
                            return '<span class="badge bg-success">Active</span>';
                        },
                    ],
                    [
                        'label' => 'Actions',
                        'format' => 'raw',
                        'headerOptions' => ['class' => 'text-center', 'style' => 'width:80px'],
                        'contentOptions' => ['class' => 'text-center'],
                        'value' => function ($m) {
                            $isActive = (int) ($m['status'] ?? 0) !== 2;
                            $isReturnable = (int) ($m['dc_type'] ?? 0) === 1;
                            $alreadyReturned = !empty($m['dc_returned_date']) && $m['dc_returned_date'] !== '0000-00-00';

                            $items = [];
                            $items[] = Html::a('<i class="fas fa-eye me-2"></i>View', ['/quotation-dashboard/view-dc', 'id' => $m['id']], [
                                'class' => 'dropdown-item', 'encode' => false,
                            ]);
                            if (UserAccess::checkAccess('dc/save-return') && $isActive && $isReturnable && !$alreadyReturned) {
                                $items[] = Html::button('<i class="fas fa-calendar-check me-2"></i>Mark Returned', [
                                    'type'         => 'button',
                                    'class'        => 'dropdown-item text-success btn-add-return',
                                    'data-id'      => $m['id'],
                                    'data-dc-date' => $m['dc_date'] ?? '',
                                    'encode'       => false,
                                ]);
                            }
                            if (UserAccess::checkAccess('dc/cancel') && $isActive) {
                                $items[] = '<li><hr class="dropdown-divider"></li>';
                                $items[] = Html::button('<i class="fas fa-times me-2"></i>Cancel DC', [
                                    'type'    => 'button',
                                    'class'   => 'dropdown-item text-danger btn-cancel-dc',
                                    'data-id' => $m['id'],
                                    'encode'  => false,
                                ]);
                            }

                            $menu = '';
                            foreach ($items as $it) {
                                $menu .= str_starts_with(trim($it), '<li>') ? $it : '<li>' . $it . '</li>';
                            }
                            return '<div class="dropdown">'
                                 . '<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions" style="padding:.2rem .45rem;font-size:.78rem;line-height:1.3">&#8942;</button>'
                                 . '<ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:180px">' . $menu . '</ul>'
                                 . '</div>';
                        },
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>

<?php if (UserAccess::checkAccess('dc/save-return')): ?>
<!-- Mark Returned modal -->
<div class="modal fade" id="returnDcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark DC as Returned</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="return_dc_id">
                <div class="mb-3">
                    <label class="form-label mb-1">Return Date <span class="text-danger">*</span></label>
                    <?= DatePicker::widget([
                        'id' => 'return_dc_date',
                        'name' => 'return_dc_date',
                        'value' => date('Y-m-d'),
                        'type' => DatePicker::TYPE_INPUT,
                        'options' => ['class' => 'form-control form-control-sm', 'autocomplete' => 'off'],
                        'pluginOptions' => [
                            'autoclose' => true,
                            'format' => 'yyyy-mm-dd',
                            'todayHighlight' => true,
                        ],
                    ]) ?>
                </div>
                <div class="mb-0">
                    <label class="form-label mb-1">Description / Remarks</label>
                    <textarea id="return_dc_desc" class="form-control form-control-sm" rows="3" placeholder="Enter return details..."></textarea>
                </div>
                <div class="text-danger small mt-1" id="return_dc_err"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="confirm_return_dc">Save Return</button>
            </div>
        </div>
    </div>
</div>
<?php
$saveReturnUrl = \yii\helpers\Url::to(['/dc/save-return']);
$this->registerJs(<<<JS
$(document).on('click', '.btn-add-return', function (e) {
    e.preventDefault();
    var dcDate = (($(this).data('dc-date') || '') + '').slice(0, 10);
    var today  = new Date().toISOString().slice(0, 10);
    var id     = $(this).data('id');
    $('#return_dc_id').val(id);
    $('#return_dc_err').text('');
    $('#return_dc_desc').val('');
    var input = $('#return_dc_date');
    input.data('min-date', dcDate);
    if (input.length && typeof input.kvDatepicker === 'function') {
        try {
            if (dcDate) { input.kvDatepicker('setStartDate', dcDate); }
            else        { input.kvDatepicker('setStartDate', null); }
            input.kvDatepicker('update', today);
        } catch (err) { input.val(today); }
    } else {
        input.val(today);
    }
    var el = document.getElementById('returnDcModal');
    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        console.error('Mark Returned: modal element or Bootstrap not available', { el: !!el, bs: typeof bootstrap });
        return;
    }
    setTimeout(function () {
        bootstrap.Modal.getOrCreateInstance(el).show();
    }, 0);
});
$('#confirm_return_dc').on('click', function () {
    var id   = $('#return_dc_id').val();
    var date = $('#return_dc_date').val();
    var desc = $('#return_dc_desc').val();
    if (!date) { $('#return_dc_err').text('Please select a return date.'); return; }
    var minDate = $('#return_dc_date').data('min-date');
    if (minDate && date < minDate) {
        $('#return_dc_err').text('Return date cannot be earlier than the DC date (' + minDate + ').');
        return;
    }
    $.ajax({
        url: '$saveReturnUrl', type: 'POST', dataType: 'json',
        data: { id: id, date: date, description: desc, _csrf: (window.yii ? yii.getCsrfToken() : '') },
        success: function (r) {
            if (r && r.success) {
                if (window.Swal) {
                    Swal.fire({title:'Saved', text:'Return date recorded.', icon:'success',
                        confirmButtonText:'OK', allowOutsideClick:false}).then(function(){ location.reload(); });
                } else { location.reload(); }
                return;
            }
            var msg = (r && r.message) ? r.message : 'Unable to save return.';
            if (window.Swal) Swal.fire({title:'Error', text:msg, icon:'error'}); else alert(msg);
        },
        error: function (xhr) {
            var msg = 'Something went wrong while saving the return.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (window.Swal) Swal.fire({title:'Error', text:msg, icon:'error'}); else alert(msg);
        }
    });
});
JS);
?>
<?php endif; ?>

<?php if (UserAccess::checkAccess('dc/cancel')): ?>
<!-- Cancel DC modal -->
<div class="modal fade" id="cancelDcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Delivery Challan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancel_dc_id">
                <label class="form-label mb-1">Reason <span class="text-danger">*</span></label>
                <textarea id="cancel_dc_reason" class="form-control form-control-sm" rows="3" placeholder="Why is this DC being cancelled?"></textarea>
                <div class="text-danger small mt-1" id="cancel_dc_err"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirm_cancel_dc">Cancel DC</button>
            </div>
        </div>
    </div>
</div>
<?php
$cancelUrl = \yii\helpers\Url::to(['/dc/cancel']);
$this->registerJs(<<<JS
$(document).on('click', '.btn-cancel-dc', function () {
    $('#cancel_dc_id').val($(this).data('id'));
    $('#cancel_dc_reason').val('');
    $('#cancel_dc_err').text('');
    new bootstrap.Modal(document.getElementById('cancelDcModal')).show();
});
$('#confirm_cancel_dc').on('click', function () {
    var id     = $('#cancel_dc_id').val();
    var reason = ($('#cancel_dc_reason').val() || '').trim();
    if (reason === '') { $('#cancel_dc_err').text('Please enter a reason.'); return; }
    $.ajax({
        url: '$cancelUrl', type: 'POST', dataType: 'json',
        data: { id: id, reason: reason, _csrf: (window.yii ? yii.getCsrfToken() : '') },
        success: function (r) {
            if (r && r.success) {
                if (window.Swal) {
                    Swal.fire({title:'Cancelled', text: r.message || 'DC cancelled.', icon:'success',
                        confirmButtonText:'OK', allowOutsideClick:false}).then(function(){ location.reload(); });
                } else { location.reload(); }
                return;
            }
            var msg = (r && r.message) ? r.message : 'Unable to cancel DC.';
            if (window.Swal) Swal.fire({title:'Error', text: msg, icon:'error'}); else alert(msg);
        },
        error: function (xhr) {
            var msg = 'Something went wrong while cancelling the DC.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (window.Swal) Swal.fire({title:'Error', text: msg, icon:'error'}); else alert(msg);
        }
    });
});
JS);
?>
<?php endif; ?>
<?php // end of page
