<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */
/** @var array{id:string,title:string,project_type:string,client_name:string} $f */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'All Projects';

// Yii1 only had Productive (1) / Non Productive (2). Match that exactly.
$typeLabel = [1 => 'Productive', 2 => 'Non Productive'];
$typeColor = [1 => 'success', 2 => 'secondary'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$hasFilter = $f['id'] !== '' || $f['title'] !== '' || $f['project_type'] !== '' || $f['client_name'] !== '';
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.list-table .filter-row td{padding:.4rem .5rem}
.list-table .filter-row input,
.list-table .filter-row select{font-size:.78rem}
</style>

<div class="project-all">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">All Projects</h5>
            <small class="text-muted"><?= (int) $total ?> project<?= $total != 1 ? 's' : '' ?></small>
        </div>
    </div>

    <form method="get" action="<?= Url::to(['all-project']) ?>" id="all-project-filter">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table list-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Project Name</th>
                            <th style="width:130px">Project Code</th>
                            <th style="width:180px">Project Type</th>
                            <th>Client</th>
                        </tr>
                        <tr class="filter-row">
                            <td></td>
                            <td>
                                <input type="text" name="title" value="<?= Html::encode($f['title']) ?>"
                                       class="form-control form-control-sm" placeholder="Search project name">
                            </td>
                            <td>
                                <input type="text" name="id" value="<?= Html::encode($f['id']) ?>"
                                       class="form-control form-control-sm" placeholder="Code">
                            </td>
                            <td>
                                <select name="project_type" class="form-select form-select-sm"
                                        onchange="document.getElementById('all-project-filter').submit();">
                                    <option value="">Select Project Type</option>
                                    <option value="1" <?= $f['project_type'] === '1' ? 'selected' : '' ?>>Productive</option>
                                    <option value="2" <?= $f['project_type'] === '2' ? 'selected' : '' ?>>Non Productive</option>
                                </select>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <input type="text" name="client_name" value="<?= Html::encode($f['client_name']) ?>"
                                           class="form-control form-control-sm" placeholder="Search client">
                                    <button class="btn btn-secondary btn-sm" type="submit" title="Apply filters">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </button>
                                    <?php if ($hasFilter): ?>
                                        <?= Html::a('<i class="fa-solid fa-xmark"></i>', ['all-project'], [
                                            'class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Clear', 'encode' => false,
                                        ]) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($models)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-5">No projects found.</td></tr>
                    <?php else: ?>
                        <?php $i = 0; foreach ($models as $p): ?>
                        <tr>
                            <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                            <td class="fw-semibold"><?= Html::encode($p['title'] ?? '') ?></td>
                            <td class="small"><?= Html::encode((string)($p['id'] ?? '')) ?></td>
                            <td>
                                <?php $pt = (int) ($p['project_type'] ?? 0); ?>
                                <?php if (isset($typeLabel[$pt])): ?>
                                    <span class="badge bg-<?= $typeColor[$pt] ?>"><?= $typeLabel[$pt] ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= Html::encode($p['client_name'] ?? '—') ?></td>
                        </tr>
                        <?php $i++; endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pagination && $pagination->pageCount > 1): ?>
            <div class="card-footer bg-white border-top-0 py-2">
                <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                    'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›']) ?>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>
