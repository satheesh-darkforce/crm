<?php
/** @var \yii\web\View $this */
/** @var \yii\data\SqlDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $clientF */
/** @var string $titleF */
/** @var string $amcF */

use common\components\UserAccess;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

$this->title = 'Completed Projects';

$canView  = UserAccess::checkAccess('amc/view');
$canStart = UserAccess::checkAccess('amc/start');

$rows        = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination ? $pagination->getOffset() : 0;
$activeCount = count(array_filter([$clientF, $titleF, $amcF], fn($v) => $v !== ''));
?>
<div class="ui-page amc-completed-projects">
    <div class="ui-head">
        <div>
            <h1>Completed Projects</h1>
            <div class="ui-sub"><?= number_format($total) ?> completed project<?= $total == 1 ? '' : 's' ?> · start an AMC from here</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" for="cp-client">Client</label>
                    <?= Select2::widget([
                        'name' => 'client_id', 'value' => $clientF ?: null,
                        'data' => ArrayHelper::map($clients, 'id', 'client_name'),
                        'options' => ['placeholder' => 'All clients', 'id' => 'cp-client'],
                        'size' => Select2::SMALL,
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                    ]) ?>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small mb-1" for="cp-title">Project</label>
                    <input type="text" id="cp-title" name="title" value="<?= Html::encode($titleF) ?>" class="form-control form-control-sm" maxlength="100" placeholder="Project title">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small mb-1" for="cp-amc">AMC</label>
                    <?= Html::dropDownList('amc', $amcF, ['1' => 'Enrolled in AMC', '0' => 'Not enrolled'], ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'cp-amc']) ?>
                </div>
                <div class="col-lg-2 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                    <?php if ($activeCount): ?><?= Html::a('Reset', ['completed-projects'], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Project</th><th class="text-end">Est. Cost</th><th>AMC</th><th class="text-end"><span class="visually-hidden">Action</span></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="ui-empty-row"><td colspan="5" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $activeCount
                            ? ['icon' => 'search', 'title' => 'No completed projects match your search', 'action' => Html::a('Clear search', ['completed-projects'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'check-circle', 'title' => 'No completed projects yet']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $i => $r): ?>
                    <?php $hasActive = (int)$r['active_amc'] > 0 && $r['amc_id']; $enrolled = (int)$r['amc'] === 1; ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap"><?= Html::encode($r['title']) ?><div class="sub"><?= Html::encode($r['client_name'] ?? 'No client') ?></div></td>
                        <td class="text-end nowrap small" data-label="Est. Cost"><?= (float)$r['total_estimation_cost'] > 0 ? '₹' . number_format((float)$r['total_estimation_cost'], 0) : '—' ?></td>
                        <td data-label="AMC">
                            <?php if ($hasActive): ?><span class="ui-pill tone-success">Running</span>
                            <?php elseif ($enrolled): ?><span class="ui-pill tone-warning">Enrolled, not started</span>
                            <?php else: ?><span class="ui-pill">Not enrolled</span><?php endif; ?>
                        </td>
                        <td class="text-end" data-label="Action">
                            <?php if ($hasActive && $canView): ?>
                                <?= Html::a('<i class="fas fa-eye me-1"></i>View AMC', ['view', 'id' => $r['amc_id']], ['class' => 'btn btn-outline-primary btn-sm', 'encode' => false]) ?>
                            <?php elseif (!$hasActive && $canStart): ?>
                                <?= Html::a('<i class="fas fa-play me-1"></i>Start AMC', ['start', 'project_id' => $r['id']], ['class' => 'btn btn-primary btn-sm', 'encode' => false]) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($rows)]) ?>
    </div>
</div>
