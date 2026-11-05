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

$models      = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination->getOffset();
$activeCount = count(array_filter($filters, fn($v) => $v !== ''));
$hasFilters  = $activeCount > 0;
$canCreate   = UserAccess::checkAccess('lead/create-lead-master');
$canUpload   = UserAccess::checkAccess('lead/upload-lead');
?>
<div class="ui-page lead-master-index">
    <div class="ui-head">
        <div>
            <h1>Lead Master</h1>
            <div class="ui-sub"><?= number_format($total) ?> prospect<?= $total == 1 ? '' : 's' ?><?= $hasFilters ? ' matching your search' : '' ?></div>
        </div>
        <div class="ui-actions">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#lm-search"
                    aria-expanded="<?= $hasFilters ? 'true' : 'false' ?>" aria-controls="lm-search">
                <i class="fas fa-search me-1"></i>Search<?php if ($hasFilters): ?> <span class="ui-count ms-1"><?= $activeCount ?></span><?php endif; ?>
            </button>
            <?php if ($canUpload): ?>
                <?= Html::a('<i class="fas fa-upload me-1"></i>Upload Lead', ['upload-lead'], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
            <?php if ($canCreate): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>Add Lead Master', ['create-lead-master'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="lm-search">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="<?= Url::to(['lead-master']) ?>" class="row g-2 align-items-end">
                    <?php foreach (['account_name' => ['Account Name', 'Company'], 'first_name' => ['First Name', 'First name'],
                                    'last_name' => ['Last Name', 'Last name'], 'designation' => ['Designation', 'e.g. CIO'],
                                    'email_id' => ['Email', 'name@company.com'], 'phone_number' => ['Phone', 'Phone number'],
                                    'city' => ['City', 'City']] as $field => [$label, $ph]): ?>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lm-filter-<?= $field ?>"><?= $label ?></label>
                        <input type="text" id="lm-filter-<?= $field ?>" name="<?= $field ?>" value="<?= Html::encode($filters[$field]) ?>"
                               class="form-control form-control-sm" maxlength="100" placeholder="<?= $ph ?>">
                    </div>
                    <?php endforeach; ?>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="lm-filter-status">Status</label>
                        <?= Html::dropDownList('status', $filters['status'], LeadMaster::statusOptions(), ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'lm-filter-status']) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                        <?= Html::a('Reset', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:40px">#</th>
                    <th>Account Name</th>
                    <th>Contact</th>
                    <th>Designation</th>
                    <th>Email</th>
                    <th>Phone Number</th>
                    <th>City</th>
                    <th>Status</th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr class="ui-empty-row"><td colspan="9" class="ui-empty-cell">
                        <?php if ($hasFilters): ?>
                            <?= $this->render('@backend/views/_ui/_empty', [
                                'icon' => 'search', 'title' => 'No prospects match your search', 'text' => 'Try fewer or different search terms.',
                                'action' => Html::a('Clear search', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm']),
                            ]) ?>
                        <?php else: ?>
                            <?= $this->render('@backend/views/_ui/_empty', [
                                'icon' => 'address-book', 'title' => 'No prospects yet',
                                'text' => 'Add prospects one at a time or upload them from a spreadsheet.',
                                'action' => ($canUpload ? Html::a('<i class="fas fa-upload me-1"></i>Upload Lead', ['upload-lead'], ['class' => 'btn btn-outline-primary btn-sm me-2', 'encode' => false]) : '')
                                    . ($canCreate ? Html::a('<i class="fas fa-plus me-1"></i>Add Lead Master', ['create-lead-master'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : ''),
                            ]) ?>
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $m): /** @var LeadMaster $m */ ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap"><?= Html::encode($m->account_name ?: '-') ?></td>
                        <td class="wrap" data-label="Contact"><?= Html::encode($m->getFullName() ?: '-') ?></td>
                        <td class="wrap" data-label="Designation"><?= Html::encode($m->designation ?: '-') ?></td>
                        <td class="small" data-label="Email" style="overflow-wrap:anywhere"><?= Html::encode($m->email_id ?: '-') ?></td>
                        <td class="small nowrap" data-label="Phone"><?= Html::encode($m->phone_number ?: '-') ?></td>
                        <td data-label="City"><?= Html::encode($m->city ?: '-') ?></td>
                        <td data-label="Status">
                            <span class="ui-pill tone-<?= (int)$m->status === LeadMaster::STATUS_ACTIVE ? 'success' : 'neutral' ?>">
                                <?= Html::encode(LeadMaster::statusOptions()[(int)$m->status] ?? '-') ?>
                            </span>
                        </td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false"
                                        aria-label="Actions for <?= Html::encode($m->account_name) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:180px">
                                    <?php if (UserAccess::checkAccess('lead/lead-master-view')): ?>
                                        <li><a href="<?= Url::to(['lead-master-view', 'id' => $m->id]) ?>" class="dropdown-item" data-ui-modal="#lm-view-modal"
                                               data-url="<?= Url::to(['lead-master-view', 'id' => $m->id]) ?>"><i class="fas fa-eye me-2"></i>View</a></li>
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
                                            'data'  => ['method' => 'post', 'confirm' => 'Delete the prospect "' . $m->getFullName() . '" (' . $m->account_name . ')?'],
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
        <div class="card-footer bg-white ui-pager">
            <small class="text-muted">Showing <?= $offset + 1 ?>–<?= $offset + count($models) ?> of <?= number_format($total) ?></small>
            <?= LinkPager::widget([
                'pagination' => $pagination,
                'options' => ['class' => 'pagination pagination-sm mb-0'],
                'linkContainerOptions' => ['class' => 'page-item'],
                'linkOptions' => ['class' => 'page-link'],
                'disabledListItemSubTagOptions' => ['tag' => 'span', 'class' => 'page-link'],
                'maxButtonCount' => 5,
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›',
            ]) ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="lm-view-modal" tabindex="-1" aria-labelledby="lm-view-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title" id="lm-view-title">Prospect details</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0"></div>
            </div>
        </div>
    </div>
</div>
