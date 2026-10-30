<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Proposal $model */

use yii\bootstrap5\Html;

$this->title = $model->title;

$statusColors = [1 => 'secondary', 2 => 'success', 3 => 'danger', 4 => 'warning', 5 => 'primary', 6 => 'info', 7 => 'dark'];
$statusColor  = $statusColors[$model->proposal_status] ?? 'secondary';
?>
<div class="proposal-view">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($model->title) ?></h5>
        <div class="d-flex gap-2">
            <?= Html::a('<i class="fas fa-pen"></i> Edit', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary', 'encode' => false]) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-sm btn-outline-secondary', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0" style="max-width:700px">
                <tr>
                    <th class="text-muted" style="width:180px">Title</th>
                    <td><?= Html::encode($model->title) ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Client</th>
                    <td><?= $model->client ? Html::encode($model->client->client_name) : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Project</th>
                    <td><?= $model->project ? Html::encode($model->project->title) : '—' ?></td>
                </tr>
                <tr>
                    <th class="text-muted">Status</th>
                    <td><span class="badge bg-<?= $statusColor ?>"><?= Html::encode($model->getStatusLabel()) ?></span></td>
                </tr>
                <tr>
                    <th class="text-muted">Created Date</th>
                    <td><?= $model->created_date ? date('d M Y H:i', strtotime($model->created_date)) : '—' ?></td>
                </tr>
                <?php if ($model->attachment): ?>
                <tr>
                    <th class="text-muted">Attachment</th>
                    <td>
                        <?= Html::a('<i class="fas fa-file-alt"></i> Download', $model->attachment, [
                            'class'  => 'btn btn-sm btn-outline-secondary',
                            'target' => '_blank',
                            'encode' => false,
                        ]) ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
