<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Project $model */
/** @var \backend\models\Project[] $phases */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = $model->title;

$statusLabel = [1 => 'Active', 2 => 'Completed', 3 => 'On Hold', 4 => 'Cancelled'];
$statusBadge = [1 => 'success', 2 => 'primary', 3 => 'warning', 4 => 'danger'];
$typeLabel   = [1 => 'Fixed Price', 2 => 'Hourly', 3 => 'Retainer', 4 => 'Internal'];

$st = (int)$model->project_status;
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
</style>

<div class="pm-project-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><?= Html::encode($model->title) ?></h4>
            <span class="badge bg-<?= $statusBadge[$st] ?? 'secondary' ?>"><?= $statusLabel[$st] ?? '—' ?></span>
        </div>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-warning btn-sm', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-plus"></i> Add PM Task', ['/pm-task/create', 'project_id' => $model->id], ['class' => 'btn btn-success btn-sm', 'encode' => false]) ?>
            <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger btn-sm',
                'data'  => ['confirm' => 'Delete this PM project?', 'method' => 'post'],
            ]) ?>
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-secondary btn-sm']) ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Project details -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold border-bottom">Project Details</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th class="text-muted" style="width:40%">Client</th>
                            <td><?= Html::encode($model->client->client_name ?? '—') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Type</th>
                            <td><?= $typeLabel[(int)$model->project_type] ?? '—' ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Est. Hours</th>
                            <td><?= $model->total_estimation_hours ? round($model->total_estimation_hours / 3600, 1) . ' h' : '—' ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Est. Cost</th>
                            <td><?= $model->total_estimation_cost ? '₹' . number_format((float)$model->total_estimation_cost, 0) : '—' ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Start Date</th>
                            <td><?= $model->date ? date('d M Y', strtotime($model->date)) : '—' ?></td>
                        </tr>
                        <?php if ($model->description): ?>
                        <tr>
                            <th class="text-muted">Description</th>
                            <td><?= nl2br(Html::encode($model->description)) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Phases -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold border-bottom d-flex justify-content-between">
                    <span>Phases &amp; Milestones (<?= count($phases) ?>)</span>
                </div>
                <?php if (!empty($phases)): ?>
                <div class="table-responsive">
                    <table class="table list-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Phase</th>
                                <th>Milestone</th>
                                <th>Title</th>
                                <th class="text-end">Hours</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($phases as $ph): ?>
                        <tr>
                            <td><span class="badge bg-primary-subtle text-primary border">P<?= $ph->phase_value ?></span></td>
                            <td><span class="badge bg-secondary-subtle text-secondary border">M<?= $ph->milestone_value ?></span></td>
                            <td class="small"><?= Html::encode($ph->title) ?></td>
                            <td class="text-end small"><?= $ph->milestone_hours ? round($ph->milestone_hours / 3600, 1) . ' h' : '—' ?></td>
                            <td>
                                <?php if ($ph->milestone_status): ?>
                                    <span class="badge bg-success">Done</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="card-body text-muted text-center py-4">No phases defined yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
