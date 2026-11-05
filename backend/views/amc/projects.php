<?php
/** @var \yii\web\View $this */
/** @var \yii\data\SqlDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $clientF */
/** @var string $titleF */

use common\components\UserAccess;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'AMC Projects';

$can = [
    'view'   => UserAccess::checkAccess('amc/view'),
    'update' => UserAccess::checkAccess('amc/update'),
    'start'  => UserAccess::checkAccess('amc/start'),
    'remove' => UserAccess::checkAccess('amc/remove'),
];
$rows        = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination ? $pagination->getOffset() : 0;
$activeCount = count(array_filter([$clientF, $titleF], fn($v) => $v !== ''));
?>
<div class="ui-page amc-projects">
    <div class="ui-head">
        <div>
            <h1>AMC Projects</h1>
            <div class="ui-sub"><?= number_format($total) ?> completed project<?= $total == 1 ? '' : 's' ?> enrolled in AMC</div>
        </div>
    </div>

    <?= $this->render('_tabs', ['active' => 'projects']) ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" for="amcp-client">Client</label>
                    <?= Select2::widget([
                        'name' => 'client_id', 'value' => $clientF ?: null,
                        'data' => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options' => ['placeholder' => 'All clients', 'id' => 'amcp-client'],
                        'size' => Select2::SMALL,
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" for="amcp-title">Project</label>
                    <input type="text" id="amcp-title" name="title" value="<?= Html::encode($titleF) ?>" class="form-control form-control-sm" maxlength="100" placeholder="Project title">
                </div>
                <div class="col-lg-2 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                    <?php if ($activeCount): ?><?= Html::a('Reset', ['projects'], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Project</th><th class="text-end">Est. Cost</th><th>AMC</th><th class="text-end" style="width:56px"><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="5" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $activeCount
                            ? ['icon' => 'search', 'title' => 'No AMC projects match your search', 'action' => Html::a('Clear search', ['projects'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'file-contract', 'title' => 'No projects enrolled in AMC', 'text' => 'When a project is marked Completed you can start its AMC.']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $r): ?>
                    <?php $hasActive = (int)$r['active_amc'] > 0 && $r['amc_id']; $title = (string)$r['title']; ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap"><?= Html::encode($title) ?><div class="sub"><?= Html::encode($r['client_name'] ?? 'No client') ?></div></td>
                        <td class="text-end nowrap small" data-label="Est. Cost"><?= (float)$r['total_estimation_cost'] > 0 ? '₹' . number_format((float)$r['total_estimation_cost'], 0) : '—' ?></td>
                        <td data-label="AMC"><span class="ui-pill tone-<?= $hasActive ? 'success' : 'neutral' ?>"><?= $hasActive ? 'Running' : 'Not started' ?></span></td>
                        <td class="cell-actions text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                        data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions for <?= Html::encode($title) ?>">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:190px">
                                    <?php if ($hasActive): ?>
                                        <?php if ($can['view']): ?><li><?= Html::a('<i class="fas fa-eye me-2"></i>View AMC', ['view', 'id' => $r['amc_id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                        <?php if ($can['update']): ?><li><?= Html::a('<i class="fas fa-pen me-2"></i>Edit AMC', ['update', 'id' => $r['amc_id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li><?php endif; ?>
                                    <?php elseif ($can['start']): ?>
                                        <li><?= Html::a('<i class="fas fa-play me-2"></i>Start AMC', ['start', 'project_id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <li><?= Html::a('<i class="fas fa-folder-open me-2"></i>Open project', ['/project/view', 'id' => $r['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php if ($can['remove']): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><?= Html::a('<i class="fas fa-times me-2"></i>Remove from AMC', ['remove', 'project_id' => $r['id']], [
                                            'class' => 'dropdown-item text-danger', 'encode' => false,
                                            'data' => ['method' => 'post', 'confirm' => 'Remove "' . $title . '" from AMC? Its AMC contract records are archived and it leaves every AMC list.'],
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
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows)]) ?>
    </div>
</div>
