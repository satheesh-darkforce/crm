<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Tasks $task */
/** @var \backend\models\DeveloperTask[] $developers */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$this->title = $task->task;

$statusBadge = [1 => 'secondary', 2 => 'primary', 3 => 'success'];
$statusLabel = [1 => 'Pending', 2 => 'Inprogress', 3 => 'Completed'];

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
?>

<div class="tasks-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
        <div class="d-flex gap-2">
            <?= Html::a('Update', ['update', 'id' => $task->id], ['class' => 'btn btn-sm btn-warning']) ?>
            <?= Html::a('Back', ['index'], ['class' => 'btn btn-sm btn-secondary']) ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Task Info -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2"><strong>Task Details</strong></div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><th style="width:45%" class="text-muted">Project</th>
                            <td><?= Html::encode($task->project->title ?? '—') ?></td></tr>
                        <tr><th class="text-muted">Task</th>
                            <td><?= Html::encode($task->task) ?></td></tr>
                        <tr><th class="text-muted">Hours Required</th>
                            <td><?= $fmt($task->hours_of_work) ?></td></tr>
                        <tr><th class="text-muted">Assigned Date</th>
                            <td><?= Html::encode($task->assigned_date ?? '—') ?></td></tr>
                        <tr><th class="text-muted">Expected Completion</th>
                            <td><?= Html::encode($task->expected_completion_date ? date('Y-m-d', strtotime($task->expected_completion_date)) : '—') ?></td></tr>
                        <tr><th class="text-muted">Status</th>
                            <td>
                                <span class="badge bg-<?= $statusBadge[$task->task_status] ?? 'secondary' ?>">
                                    <?= $statusLabel[$task->task_status] ?? 'Unknown' ?>
                                </span>
                            </td></tr>
                        <tr><th class="text-muted">Created By</th>
                            <td><?= Html::encode($task->createdBy->username ?? '—') ?></td></tr>
                        <tr><th class="text-muted">Created At</th>
                            <td><?= Html::encode($task->created_at ?? '—') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assigned Developers -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2"><strong>Assigned Developers</strong></div>
                <div class="card-body">
                    <?php if (empty($developers)): ?>
                        <p class="text-muted">No developers assigned.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($developers as $dt): ?>
                                <li class="list-group-item px-0">
                                    <?= Html::encode($dt->developer->developer_name ?? '—') ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Description -->
        <?php if ($task->description): ?>
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header py-2"><strong>Description</strong></div>
                <div class="card-body">
                    <p class="mb-0" style="white-space:pre-line"><?= Html::encode($task->description) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Work Reports for this task -->
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <strong>Work Reports</strong>
                    <?= Html::a('+ Add Work Report', ['add-work-report', 'id' => $task->id], ['class' => 'btn btn-sm btn-success']) ?>
                </div>
                <div class="card-body p-0">
                    <?php
                    $workReports = $task->workReports;
                    ?>
                    <?php if (empty($workReports)): ?>
                        <p class="text-muted p-3 mb-0">No work reports yet.</p>
                    <?php else: ?>
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Total Time</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workReports as $i => $wr): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= Html::encode($wr->date) ?></td>
                                        <td><?= Html::encode($wr->from_time ?? '—') ?></td>
                                        <td><?= Html::encode($wr->to_time ?? '—') ?></td>
                                        <td><?= $wr->total_time ? sprintf('%02d:%02d', intdiv((int)$wr->total_time, 60), (int)$wr->total_time % 60) : '—' ?></td>
                                        <td><?= Html::encode($wr->description ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
