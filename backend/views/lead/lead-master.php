<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var array $filters */

use backend\models\LeadMaster;
use common\components\UserAccess;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'Lead Master';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
$offset     = $pagination->getOffset();
$hasFilters = count(array_filter($filters, fn($v) => $v !== '')) > 0;
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.85rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="lead-master-index">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">Lead Master</h5>
            <small class="text-muted"><?= $total ?> prospect<?= $total == 1 ? '' : 's' ?></small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#lm-search">
                <i class="fas fa-search me-1"></i>Search
            </button>
            <?php if (UserAccess::checkAccess('lead/upload-lead')): ?>
                <?= Html::a('<i class="fas fa-upload me-1"></i>Upload Lead', ['upload-lead'], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if (UserAccess::checkAccess('lead/create-lead-master')): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Lead Master', ['create-lead-master'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="lm-search">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="<?= Url::to(['lead-master']) ?>" class="row g-2 align-items-end">
                    <?php foreach (['account_name' => 'Account Name', 'first_name' => 'First Name', 'last_name' => 'Last Name',
                                    'designation' => 'Designation', 'email_id' => 'Email', 'phone_number' => 'Phone', 'city' => 'City'] as $field => $label): ?>
                    <div class="col-md-2 col-sm-4">
                        <label class="form-label small mb-1"><?= $label ?></label>
                        <input type="text" name="<?= $field ?>" value="<?= Html::encode($filters[$field]) ?>" class="form-control form-control-sm">
                    </div>
                    <?php endforeach; ?>
                    <div class="col-md-2 col-sm-4">
                        <label class="form-label small mb-1">Status</label>
                        <?= Html::dropDownList('status', $filters['status'], LeadMaster::statusOptions(), ['class' => 'form-select form-select-sm', 'prompt' => 'All']) ?>
                    </div>
                    <div class="col-md-2 col-sm-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        <?= Html::a('Reset', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Account Name</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Designation</th>
                    <th>Email</th>
                    <th>Phone Number</th>
                    <th>City</th>
                    <th>Status</th>
                    <th class="text-end" style="width:60px">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr><td colspan="10" class="text-center text-muted py-5">No prospects found.</td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $m): /** @var LeadMaster $m */ ?>
                    <tr>
                        <td class="text-muted"><?= $offset + $i + 1 ?></td>
                        <td class="fw-semibold"><?= Html::encode($m->account_name) ?></td>
                        <td><?= Html::encode($m->first_name) ?></td>
                        <td><?= Html::encode($m->last_name ?: '-') ?></td>
                        <td><?= Html::encode($m->designation ?: '-') ?></td>
                        <td class="small"><?= Html::encode($m->email_id) ?></td>
                        <td class="small" style="white-space:nowrap"><?= Html::encode($m->phone_number ?: '-') ?></td>
                        <td><?= Html::encode($m->city ?: '-') ?></td>
                        <td>
                            <span class="badge bg-<?= (int)$m->status === LeadMaster::STATUS_ACTIVE ? 'success' : 'secondary' ?>">
                                <?= LeadMaster::statusOptions()[(int)$m->status] ?? '-' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:180px">
                                    <?php if (UserAccess::checkAccess('lead/lead-master-view')): ?>
                                        <li><a href="#" class="dropdown-item js-lm-view" data-url="<?= Url::to(['lead-master-view', 'id' => $m->id]) ?>">
                                            <i class="fas fa-eye me-2"></i>View</a></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('lead/update-lead-master')): ?>
                                        <li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['update-lead-master', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('lead/convert-to-lead')): ?>
                                        <li><?= Html::a('<i class="fas fa-random me-2"></i>Convert to Lead', ['convert-to-lead', 'id' => $m->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if (UserAccess::checkAccess('lead/delete-lead-master')): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-trash me-2"></i>Delete', ['delete-lead-master', 'id' => $m->id], [
                                            'class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data'  => ['method' => 'post', 'confirm' => 'Delete this prospect?'],
                                        ]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination->getPageCount() > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= $offset + 1 ?>–<?= $offset + count($models) ?> of <?= $total ?></small>
            <?= LinkPager::widget([
                'pagination' => $pagination,
                'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›',
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="lm-view-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Lead Master</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<JS
jQuery(document).on('click', '.js-lm-view', function (e) {
    e.preventDefault();
    var \$modal = jQuery('#lm-view-modal');
    \$modal.find('.modal-body').html('<div class="text-muted small">Loading…</div>').load(jQuery(this).data('url'), function (r, status) {
        if (status === 'error') { jQuery(this).html('<div class="text-danger small">Could not load the record.</div>'); }
    });
    bootstrap.Modal.getOrCreateInstance(\$modal[0]).show();
});
JS);
