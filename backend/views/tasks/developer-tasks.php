<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */
/** @var \backend\models\Project[] $projects */

use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'My Developer Tasks';

$statusBadge = [1 => 'secondary', 2 => 'primary', 3 => 'success'];
$statusLabel = [1 => 'Pending', 2 => 'Inprogress', 3 => 'Completed'];

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

$changeStatusUrl = Url::to(['/tasks/change-status']);
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="tasks-developer">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0 fw-semibold"><?= Html::encode($this->title) ?></h5>
    </div>

    <!-- Filters -->
    <form method="get" class="row g-2 mb-3 align-items-end bg-white border rounded p-2 shadow-sm">
        <div class="col-md-4">
            <?= Select2::widget([
                'name'          => 'project_id',
                'value'         => Yii::$app->request->get('project_id'),
                'data'          => ArrayHelper::map($projects, 'id', 'title'),
                'options'       => ['placeholder' => 'All Projects', 'class' => 'form-control form-control-sm'],
                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
            ]) ?>
        </div>
        <div class="col-md-2">
            <select name="task_status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach ($statusLabel as $v => $l): ?>
                    <option value="<?= $v ?>" <?= Yii::$app->request->get('task_status') == $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
            <?= Html::a('Reset', ['developer-tasks'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 list-table">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Project</th>
                        <th>Task</th>
                        <th>Hours Required</th>
                        <th>Assigned Date</th>
                        <th>Expected Completion</th>
                        <th>Assigned By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($models)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No tasks assigned to you.</td></tr>
                    <?php else: ?>
                        <?php foreach ($models as $i => $task): ?>
                            <tr>
                                <td><?= $pagination ? $pagination->offset + $i + 1 : $i + 1 ?></td>
                                <td><?= Html::encode($task->project->title ?? '—') ?></td>
                                <td><?= Html::encode($task->task) ?></td>
                                <td><?= $fmt($task->hours_of_work) ?></td>
                                <td><?= Html::encode($task->assigned_date ?? '—') ?></td>
                                <td>
                                    <?php
                                    $expDate = $task->expected_completion_date ? strtotime($task->expected_completion_date) : null;
                                    $isOverdue = $expDate && $expDate < time() && (int)$task->task_status !== 3;
                                    ?>
                                    <span class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>">
                                        <?= Html::encode($task->expected_completion_date ? date('Y-m-d', $expDate) : '—') ?>
                                    </span>
                                </td>
                                <td><?= Html::encode($task->createdBy->username ?? '—') ?></td>
                                <td>
                                    <select class="form-select form-select-sm status-select"
                                            data-id="<?= $task->id ?>"
                                            style="width:auto">
                                        <?php foreach ($statusLabel as $v => $l): ?>
                                            <option value="<?= $v ?>" <?= (int)$task->task_status === $v ? 'selected' : '' ?>><?= $l ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary act-btn dropdown-toggle" data-bs-toggle="dropdown">&#8942;</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><?= Html::a('View', ['view', 'id' => $task->id], ['class' => 'dropdown-item']) ?></li>
                                            <li><?= Html::a('Add Work Report', ['add-work-report', 'id' => $task->id], ['class' => 'dropdown-item']) ?></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pagination && $pagination->pageCount > 1): ?>
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= count($models) ?> of <?= $total ?></small>
            <?= LinkPager::widget(['pagination' => $pagination, 'options' => ['class' => 'pagination mb-0']]) ?>
        </div>
    <?php endif; ?>
</div>

<?php
$this->registerJs(<<<JS
// Store previous value for rollback
jQuery('.status-select').each(function() {
    jQuery(this).data('prev', jQuery(this).val());
});

jQuery('.status-select').on('change', function() {
    var sel = jQuery(this);
    var id = sel.data('id');
    var status = sel.val();
    var prev = sel.data('prev');

    Swal.fire({
        icon: 'question',
        title: 'Change Status?',
        text: 'Are you sure you want to change the task status?',
        showCancelButton: true,
        confirmButtonText: 'Yes, change it',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            jQuery.ajax({
                url: '{$changeStatusUrl}',
                type: 'GET',
                data: {id: id, status: status},
                dataType: 'json',
                success: function(data) {
                    sel.data('prev', status);
                    Swal.fire({icon: 'success', title: 'Updated', text: 'Status updated successfully!', timer: 1500, showConfirmButton: false});
                },
                error: function() {
                    sel.val(prev);
                    Swal.fire({icon: 'error', title: 'Error', text: 'Failed to update status.'});
                }
            });
        } else {
            sel.val(prev);
        }
    });
});
JS, \yii\web\View::POS_READY);
?>
