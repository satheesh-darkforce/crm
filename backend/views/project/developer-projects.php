<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ArrayDataProvider $dataProvider */

use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'My Projects';

$statusLabel = [1 => 'Active', 2 => 'Completed', 3 => 'On Hold', 4 => 'Cancelled'];
$statusBadge = [1 => 'success', 2 => 'primary', 3 => 'warning', 4 => 'danger'];
$typeLabel   = [1 => 'Fixed', 2 => 'Hourly', 3 => 'Monthly', 4 => 'Internal'];

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
</style>

<div class="project-developer">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">My Projects</h5>
            <small class="text-muted"><?= $total ?> project<?= $total != 1 ? 's' : '' ?> with assigned tasks</small>
        </div>
        <?= Html::a('All Projects', ['index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">No projects with assigned tasks.</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $row):
                        $ps = (int)($row['project_status'] ?? 0);
                        $pt = (int)($row['project_type']   ?? 0);
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['/project/view', 'id' => $row['id']]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($row['title']) ?>
                            </a>
                        </td>
                        <td class="small text-muted"><?= Html::encode($row['client_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($pt && isset($typeLabel[$pt])): ?>
                                <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.7rem">
                                    <?= $typeLabel[$pt] ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ps && isset($statusBadge[$ps])): ?>
                                <span class="badge bg-<?= $statusBadge[$ps] ?>-subtle text-<?= $statusBadge[$ps] ?> border" style="font-size:.7rem">
                                    <?= $statusLabel[$ps] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= $row['date'] ? date('d M Y', strtotime($row['date'])) : '—' ?>
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
                Showing <?= $pagination->getOffset() + 1 ?>–<?= min($pagination->getOffset() + $pagination->pageSize, $total) ?>
                of <?= $total ?>
            </small>
            <?= LinkPager::widget([
                'pagination'     => $pagination,
                'options'        => ['class' => 'pagination mb-0'],
                'firstPageLabel' => '«', 'lastPageLabel' => '»',
                'prevPageLabel'  => '‹', 'nextPageLabel'  => '›',
                'maxButtonCount' => 7,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
