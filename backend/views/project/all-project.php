<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */
/** @var array{id:string,title:string,project_type:string,client_name:string} $f */

use backend\models\Project;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = 'All Projects';

$models      = $dataProvider->getModels();
$pagination  = $dataProvider->getPagination();
$total       = $dataProvider->getTotalCount();
$offset      = $pagination ? $pagination->getOffset() : 0;
$typeOptions = Project::typeOptions();
$hasFilters  = count(array_filter($f, fn($v) => $v !== '')) > 0;
?>
<div class="ui-page project-all">
    <div class="ui-head">
        <div>
            <h1>All Projects</h1>
            <div class="ui-sub"><?= number_format($total) ?> project<?= $total == 1 ? '' : 's' ?><?= $hasFilters ? ' matching your search' : '' ?> · A–Z, including phases</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="get" action="<?= Url::to(['all-project']) ?>" class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" for="all-filter-title">Project name</label>
                    <input type="text" id="all-filter-title" name="title" value="<?= Html::encode($f['title']) ?>" class="form-control form-control-sm" maxlength="100" placeholder="Search project name">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1" for="all-filter-id">Code</label>
                    <input type="text" id="all-filter-id" name="id" value="<?= Html::encode($f['id']) ?>" class="form-control form-control-sm" maxlength="10" inputmode="numeric" placeholder="e.g. 704">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1" for="all-filter-type">Type</label>
                    <?= Html::dropDownList('project_type', $f['project_type'], $typeOptions, ['class' => 'form-select form-select-sm', 'prompt' => 'All', 'id' => 'all-filter-type']) ?>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small mb-1" for="all-filter-client">Client</label>
                    <input type="text" id="all-filter-client" name="client_name" value="<?= Html::encode($f['client_name']) ?>" class="form-control form-control-sm" maxlength="100" placeholder="Search client">
                </div>
                <div class="col-lg-2 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i>Search</button>
                    <?php if ($hasFilters): ?><?= Html::a('Reset', ['all-project'], ['class' => 'btn btn-outline-secondary btn-sm flex-fill']) ?><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table ui-table stack mb-0">
                <thead class="table-light">
                <tr><th style="width:40px">#</th><th>Project</th><th style="width:110px">Code</th><th style="width:160px">Type</th></tr>
                </thead>
                <tbody>
                <?php if (!$models): ?>
                    <tr class="ui-empty-row"><td colspan="4" class="ui-empty-cell">
                        <?= $this->render('@backend/views/_ui/_empty', $hasFilters
                            ? ['icon' => 'search', 'title' => 'No projects match your search', 'text' => 'Try fewer or different search terms.',
                               'action' => Html::a('Clear search', ['all-project'], ['class' => 'btn btn-outline-secondary btn-sm'])]
                            : ['icon' => 'folder-open', 'title' => 'No projects yet']) ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($models as $i => $p): $pt = (int)$p['project_type']; ?>
                    <tr>
                        <td class="cell-num muted"><?= $offset + $i + 1 ?></td>
                        <td class="cell-title wrap"><?= Html::encode($p['title'] ?: '—') ?><div class="sub"><?= Html::encode($p['client_name'] ?: 'No client') ?></div></td>
                        <td class="small muted" data-label="Code">#<?= (int)$p['id'] ?></td>
                        <td data-label="Type"><?= isset($typeOptions[$pt]) ? '<span class="ui-pill plain tone-' . ($pt === Project::TYPE_PRODUCTIVE ? 'accent' : 'neutral') . '">' . $typeOptions[$pt] . '</span>' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->render('@backend/views/_ui/_pager', ['pagination' => $pagination, 'total' => $total, 'count' => count($models)]) ?>
    </div>
</div>
