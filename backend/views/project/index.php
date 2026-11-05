<?php
/** @var \yii\web\View $this */
/** @var \yii\data\SqlDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var array $f  id, title, project_status, project_type, client_id, cost_range, from_date, to_date */

use backend\models\Project;
use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\LinkPager;

$this->title = 'Projects';

$can = [
    'create' => UserAccess::checkAccess('project/create'),
    'update' => UserAccess::checkAccess('project/update'),
    'delete' => UserAccess::checkAccess('project/delete'),
    'view'   => UserAccess::checkAccess('project/view'),
    'status' => UserAccess::checkAccess('project/change-status'),
    'amc'    => UserAccess::isAmcEnabled() && UserAccess::checkAccess('amc/start'),
];
$statusOptions = Project::statusOptions();
$typeOptions   = Project::typeOptions();
$costOptions   = [1 => '₹10,000 and below', 2 => '₹10,000 – 50,000', 3 => '₹50,000 – 1,00,000', 4 => 'Above ₹1,00,000'];

// work_report.total_time is stored in SECONDS — HH:MM (Yii1 Utility::convertSecondsToHours()).
$fmt = fn(int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$models      = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination ? $pagination->getOffset() : 0;
$activeCount = count(array_filter($f, fn($v) => $v !== ''));
$hasFilters  = $activeCount > 0;
$datePicker  = fn(string $name, string $ph, array $extra) => DatePicker::widget([
    'name' => $name, 'value' => $f[$name], 'type' => DatePicker::TYPE_INPUT,
    'options' => ['id' => 'project-filter-' . $name, 'class' => 'form-control form-control-sm', 'placeholder' => $ph, 'autocomplete' => 'off'] + $extra,
    'pluginOptions' => ['format' => 'yyyy-mm-dd', 'autoclose' => true, 'todayHighlight' => true, 'clearBtn' => true],
]);
?>
<div class="ui-page project-index">
    <div class="ui-head">
        <div>
            <h1>Projects</h1>
            <div class="ui-sub"><?= number_format($total) ?> project<?= $total == 1 ? '' : 's' ?><?= $hasFilters ? ' matching your search' : '' ?> · newest first</div>
        </div>
        <div class="ui-actions">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#project-search"
                    aria-expanded="<?= $hasFilters ? 'true' : 'false' ?>" aria-controls="project-search">
                <i class="fas fa-search me-1"></i>Search<?php if ($hasFilters): ?> <span class="ui-count ms-1"><?= $activeCount ?></span><?php endif; ?>
            </button>
            <?php if ($can['create']): ?>
                <?= Html::a('<i class="fas fa-plus me-1"></i>New Project', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="project-search">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="<?= Url::to(['index']) ?>" class="row g-2 align-items-end">
                    <div class="col-lg-1 col-md-2 col-sm-4">
                        <label class="form-label small mb-1" for="project-filter-id">Code</label>
                        <input type="text" id="project-filter-id" name="id" value="<?= Html::encode($f['id']) ?>" class="form-control form-control-sm" maxlength="10" inputmode="numeric" placeholder="e.g. 704">
                    </div>
                    <div class="col-lg-3 col-md-4 col-sm-8">
                        <label class="form-label small mb-1" for="project-filter-title">Project title</label>
                        <?= Select2::widget([
                            'name' => 'title', 'value' => $f['title'] !== '' ? $f['title'] : null,
                            'data' => $f['title'] !== '' ? [$f['title'] => $f['title']] : [],
                            'options' => ['placeholder' => 'Type to search…', 'id' => 'project-filter-title'],
                            'size' => Select2::SMALL,
                            'pluginOptions' => [
                                'allowClear' => true, 'width' => '100%', 'minimumInputLength' => 1,
                                'ajax' => [
                                    'url' => Url::to(['/project/search-title']), 'dataType' => 'json', 'delay' => 250, 'cache' => true,
                                    'data' => new JsExpression('function(params){return {q: params.term};}'),
                                ],
                            ],
                        ]) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="project-filter-client">Client</label>
                        <?= Select2::widget([
                            'name' => 'client_id', 'value' => $f['client_id'] ?: null,
                            'data' => ArrayHelper::map($clients, 'id', 'client_name'),
                            'options' => ['placeholder' => 'All clients', 'id' => 'project-filter-client'],
                            'size' => Select2::SMALL,
                            'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                        ]) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="project-filter-status">Status</label>
                        <?= Html::dropDownList('project_status', $f['project_status'], $statusOptions, ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'project-filter-status']) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="project-filter-type">Type</label>
                        <?= Html::dropDownList('project_type', $f['project_type'], $typeOptions, ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'project-filter-type']) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="project-filter-cost">Estimated cost</label>
                        <?= Html::dropDownList('cost_range', $f['cost_range'], $costOptions, ['class' => 'form-select form-select-sm', 'prompt' => 'Any', 'id' => 'project-filter-cost']) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="project-filter-from_date">Created from</label>
                        <?= $datePicker('from_date', 'YYYY-MM-DD', ['data-range-from' => '#project-filter-to_date']) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6">
                        <label class="form-label small mb-1" for="project-filter-to_date">Created to</label>
                        <?= $datePicker('to_date', 'YYYY-MM-DD', ['data-range-to' => '#project-filter-from_date']) ?>
                    </div>
                    <div class="col-lg-2 col-md-3 col-sm-6 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                        <?= Html::a('Reset', ['index'], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?>
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
                    <th style="width:64px">Code</th>
                    <th>Project</th>
                    <th>Type</th>
                    <th class="text-end">Est. Cost</th>
                    <th class="text-center" title="Hours logged this month"><?= date('M') ?> Hrs</th>
                    <th>Status</th>
                    <th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr class="ui-empty-row"><td colspan="7" class="ui-empty-cell">
                        <?php if ($hasFilters): ?>
                            <?= $this->render('@backend/views/_ui/_empty', [
                                'icon' => 'search', 'title' => 'No projects match your search', 'text' => 'Try fewer or different search terms.',
                                'action' => Html::a('Clear search', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']),
                            ]) ?>
                        <?php else: ?>
                            <?= $this->render('@backend/views/_ui/_empty', [
                                'icon' => 'folder-open', 'title' => 'No projects yet', 'text' => 'Projects you create appear here.',
                                'action' => $can['create'] ? Html::a('<i class="fas fa-plus me-1"></i>New Project', ['create'], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) : null,
                            ]) ?>
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($models as $row): ?>
                    <?php
                    $st    = (int)$row['project_status'];
                    $tp    = (int)$row['project_type'];
                    $secs  = (int)$row['cur_month_secs'];
                    $title = (string)$row['title'];
                    ?>
                    <tr>
                        <td class="cell-num small muted nowrap">#<?= (int)$row['id'] ?></td>
                        <td class="cell-title wrap">
                            <?= $can['view'] ? Html::a(Html::encode($title), ['view', 'id' => $row['id']], ['class' => 'text-reset text-decoration-none']) : Html::encode($title) ?>
                            <div class="sub"><span class="d-md-none">#<?= (int)$row['id'] ?> · </span><?= Html::encode($row['client_name'] ?: 'No client') ?></div>
                        </td>
                        <td data-label="Type">
                            <?php if (isset($typeOptions[$tp])): ?>
                                <span class="ui-pill plain tone-<?= $tp === Project::TYPE_PRODUCTIVE ? 'accent' : 'neutral' ?>"><?= $typeOptions[$tp] ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-end nowrap small" data-label="Est. Cost"><?= (float)$row['total_estimation_cost'] > 0 ? '₹' . number_format((float)$row['total_estimation_cost'], 0) : '<span class="muted">—</span>' ?></td>
                        <td class="text-center nowrap <?= $secs > 0 ? 'fw-semibold text-success' : 'muted' ?>" data-label="<?= date('M') ?> Hrs"><?= $fmt($secs) ?></td>
                        <td data-label="Status">
                            <?php if ($can['status']): ?>
                                <?= Html::dropDownList('project_status', $st, $statusOptions, [
                                    'class' => 'form-select form-select-sm js-project-status', 'data-id' => $row['id'], 'data-prev' => $st,
                                    'data-can-start-amc' => $can['amc'] ? 1 : 0, 'aria-label' => 'Status for ' . $title,
                                ]) ?>
                            <?php else: ?>
                                <span class="ui-pill tone-<?= Project::statusTone($st) ?>"><?= Html::encode($statusOptions[$st] ?? 'Unknown') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-actions text-end">
                            <?php if ($can['view'] || $can['update'] || $can['delete']): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false"
                                        aria-label="Actions for <?= Html::encode($title) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:170px">
                                    <?php if ($can['view']): ?>
                                        <li><?= Html::a('<i class="fas fa-eye me-2"></i>View', ['view', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($can['update']): ?>
                                        <li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit', ['update', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($can['delete']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-trash me-2"></i>Delete', ['delete', 'id' => $row['id']], [
                                            'class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data'  => ['method' => 'post', 'confirm' => 'Delete the project "' . $title . '"? It is removed from project lists.'],
                                        ]) ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->getPageCount() > 1): ?>
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

    <?php // Loads the date picker plugin used inside the "live date" popup. ?>
    <div class="d-none" aria-hidden="true">
        <?= DatePicker::widget(['name' => '_live_date_assets', 'type' => DatePicker::TYPE_INPUT, 'pluginOptions' => ['format' => 'yyyy-mm-dd']]) ?>
    </div>
</div>
