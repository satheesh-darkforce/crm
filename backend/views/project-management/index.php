<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $title */
/** @var string $statusF */
/** @var string $clientF */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'PM Projects';

$statusLabel = [1 => 'Active', 2 => 'Completed', 3 => 'On Hold', 4 => 'Cancelled'];
$statusBadge = [1 => 'success', 2 => 'primary', 3 => 'warning', 4 => 'danger'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="pm-project-index">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">PM Projects</h5>
            <small class="text-muted"><?= $total ?> project<?= $total != 1 ? 's' : '' ?></small>
        </div>
        <?= Html::a('+ New PM Project', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="title" value="<?= Html::encode($title) ?>"
                           class="form-control form-control-sm" placeholder="Project title…">
                </div>
                <div class="col-md-3">
                    <select name="client_id" class="form-select form-select-sm">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c->id ?>" <?= $clientF == $c->id ? 'selected' : '' ?>>
                                <?= Html::encode($c->client_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="project_status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusLabel as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $statusF == $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if ($title || $statusF || $clientF): ?>
                        <?= Html::a('Clear', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th class="text-center">Phases</th>
                        <th class="text-end">Est. Cost</th>
                        <th class="text-center">Open Tasks</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end" style="width:60px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-5">No PM projects found.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $row):
                        $st = (int)($row['project_status'] ?? 1);
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $row['id']]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($row['title']) ?>
                            </a>
                        </td>
                        <td><?= Html::encode($row['client_name'] ?? '—') ?></td>
                        <td class="text-center">
                            <?php if ($row['phase_count'] > 0): ?>
                                <span class="badge bg-info-subtle text-info border" style="font-size:.72rem">
                                    <?= $row['phase_count'] ?> phase<?= $row['phase_count'] != 1 ? 's' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-muted small">
                            <?= $row['total_estimation_cost'] ? '₹' . number_format((float)$row['total_estimation_cost'], 0) : '—' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($row['open_tasks'] > 0): ?>
                                <span class="badge bg-warning text-dark"><?= $row['open_tasks'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>">
                                <?= $statusLabel[$st] ?? '—' ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= $row['date'] ? date('d M Y', strtotime($row['date'])) : '—' ?></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-plus"></i> Add PM Task', ['/pm-task/create', 'project_id' => $row['id']], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('<i class="fas fa-ban"></i> Delete', ['delete', 'id' => $row['id']], [
                                        'class' => 'dropdown-item text-danger', 'encode' => false,
                                        'data-method' => 'post',
                                        'data-confirm' => 'Delete ' . Html::encode($row['title']) . '?',
                                    ]) ?></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center py-2">
            <small class="text-muted">
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?> of <?= $total ?>
            </small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›', 'maxButtonCount' => 7]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
