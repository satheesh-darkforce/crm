<?php
/** @var \yii\web\View $this */
/** @var \yii\data\ActiveDataProvider $dataProvider */

use common\components\UserAccess;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = 'New Tasks';

// Per-action permission flags — matches Yii1 newtasks.php where each action
// column button is checked individually (Yii1 commented these out by default;
// we keep them here as a safety net so a developer with stripped permissions
// won't see buttons that 403 on click).
$canViewTask   = UserAccess::checkAccess('task/view');
$canAcceptTask = UserAccess::checkAccess('task/task-accept');
$canReassign   = UserAccess::checkAccess('task/full-reassign-task');

$priBadge = [1 => 'danger', 2 => 'warning', 3 => 'info'];
$priLabel = [1 => 'High', 2 => 'Medium', 3 => 'Low'];
$typLabel = [1 => 'Full', 2 => 'Partial'];

$fmt = fn(?int $s): string => $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';

$models     = $dataProvider->getModels();
$pagination = $dataProvider->getPagination();
$total      = $dataProvider->getTotalCount();

// Developer list for Re-Assign modal.
// Yii1 parity: dropdown shows the developer's full name, keyed by user_id.
$developers = \backend\models\Developer::find()
    ->where(['status' => 1])
    ->orderBy('developer_name')
    ->all();
$devData = ArrayHelper::map($developers, 'user_id', 'developer_name');
?>
<style>
.list-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#6c757d;font-weight:600;border-bottom:2px solid #dee2e6;white-space:nowrap}
.list-table td{vertical-align:middle;font-size:.875rem}
.list-table tbody tr:hover{background:#f8f9ff}
.act-btn{padding:.2rem .45rem;font-size:.78rem;line-height:1.3}
.pagination{--bs-pagination-font-size:.825rem}
.pagination .page-link{border-radius:6px!important;margin:0 2px}
</style>

<div class="task-new-list">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0 fw-semibold">New Tasks <span class="badge bg-danger ms-1"><?= $total ?></span></h5>
            <small class="text-muted">Tasks newly assigned to you (pending acceptance)</small>
        </div>
        <div class="d-flex gap-2">
<?php /* Yii1 parity: newtasks page has no top-level navigation buttons.
            "Add New Task", "My Active Tasks" and "All Tasks" are reachable
            via the sidebar menu (which already does its own permission gating). */ ?>
        </div>
    </div>

    <!-- Date filter -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="get" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <?= DatePicker::widget([
                        'name'          => 'expected_date',
                        'value'         => Yii::$app->request->get('expected_date'),
                        'type'          => DatePicker::TYPE_INPUT,
                        'options'       => ['placeholder' => 'Filter by deadline…', 'class' => 'form-control form-control-sm'],
                        'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true],
                    ]) ?>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm">Filter</button>
                    <?php if (Yii::$app->request->get('expected_date')): ?>
                        <?= Html::a('Clear', ['my-new-task-list'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table list-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Task</th>
                        <th>Project</th>
                        <th>Assigned By</th>
                        <th class="text-center">Exp. Time</th>
                        <th>Priority</th>
                        <th>Type</th>
                        <th>Deadline</th>
                        <th>Created</th>
                        <th class="text-end" style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($models)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-5">No new tasks. You are all caught up!</td></tr>
                <?php else: ?>
                    <?php foreach ($models as $i => $task):
                        $overdue = $task->expected_delivery_date && strtotime($task->expected_delivery_date) < time();
                    ?>
                    <tr>
                        <td class="text-muted"><?= $pagination ? ($pagination->getOffset() + $i + 1) : ($i + 1) ?></td>
                        <td>
                            <a href="<?= Url::to(['view', 'id' => $task->id]) ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= Html::encode($task->task) ?>
                            </a>
                            <?php if ($task->description): ?>
                                <div class="text-muted" style="font-size:.75rem">
                                    <?= Html::encode(mb_strimwidth($task->description, 0, 80, '…')) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= Html::encode($task->project->title ?? '—') ?></td>
                        <td><?= Html::encode($task->assignedBy->username ?? '—') ?></td>
                        <td class="text-center text-muted small"><?= $fmt((int)$task->expected_time) ?></td>
                        <td>
                            <?php $pri = (int)$task->priority; ?>
                            <?php if ($pri && isset($priLabel[$pri])): ?>
                                <span class="badge bg-<?= $priBadge[$pri] ?>-subtle text-<?= $priBadge[$pri] ?> border" style="font-size:.72rem">
                                    <?= $priLabel[$pri] ?>
                                </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= $typLabel[(int)$task->assign_type] ?? '—' ?></td>
                        <td>
                            <?php if ($task->expected_delivery_date): ?>
                                <span class="<?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?> small">
                                    <?= date('d M Y', strtotime($task->expected_delivery_date)) ?>
                                    <?= $overdue ? '<span class="badge bg-danger ms-1">Overdue</span>' : '' ?>
                                </span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?= $task->created_date ? date('d M Y', strtotime($task->created_date)) : '—' ?>
                        </td>
                        <td class="text-end">
                            <?php if ($canViewTask || $canAcceptTask || $canReassign): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary act-btn dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown">&#8942;</button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size:.825rem;min-width:160px">
                                    <?php if ($canViewTask): ?>
                                    <li><?= Html::a('<i class="fas fa-eye"></i> View', ['view', 'id' => $task->id], ['class' => 'dropdown-item', 'encode' => false]) ?></li>
                                    <?php endif; ?>
                                    <?php if ($canAcceptTask): ?>
                                    <li>
                                        <a class="dropdown-item text-success js-accept" href="#"
                                           data-taskid="<?= $task->id ?>"><i class="fas fa-check"></i> Accept Task</a>
                                    </li>
                                    <?php endif; ?>
                                    <?php if ($canReassign): ?>
                                    <li>
                                        <a class="dropdown-item text-warning js-reassign" href="#"
                                           data-taskid="<?= $task->id ?>"><i class="fas fa-sync-alt"></i> Re-Assign</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
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
            <?= LinkPager::widget([
                'pagination'    => $pagination,
                'options'       => ['class' => 'pagination mb-0'],
                'firstPageLabel'=> '«', 'lastPageLabel' => '»',
                'prevPageLabel' => '‹', 'nextPageLabel' => '›',
                'maxButtonCount'=> 7,
            ]) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Accept form (hidden, submitted via JS) -->
<form id="acceptForm" method="post" action="<?= Url::to(['task-accept']) ?>" style="display:none">
    <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
    <input type="hidden" name="taskid" id="acceptTaskId" value="">
</form>

<!-- Full Re-Assign Modal -->
<div class="modal fade" id="reassignModal" tabindex="-1" aria-labelledby="reassignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reassignModalLabel">Re-Assign Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= Url::to(['full-reassign-task']) ?>">
                <input type="hidden" name="<?= Yii::$app->request->csrfParam ?>" value="<?= Yii::$app->request->csrfToken ?>">
                <input type="hidden" name="task_id" id="reassignTaskId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Assign To <span class="text-danger">*</span></label>
                        <?= Select2::widget([
                            'name'          => 'assigned_to',
                            'data'          => $devData,
                            'options'       => ['placeholder' => '— Select Developer —', 'required' => true],
                            'pluginOptions' => [
                                'allowClear'     => true,
                                'width'          => '100%',
                                // dropdownParent makes Select2 search input typable inside Bootstrap modals.
                                'dropdownParent' => new \yii\web\JsExpression("jQuery('#reassignModal')"),
                            ],
                        ]) ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Re-Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$this->registerJs(<<<'JS'
jQuery('.js-accept').on('click', function(e) {
    e.preventDefault();
    var taskId = jQuery(this).data('taskid');
    Swal.fire({
        icon: 'question',
        title: 'Accept Task?',
        text: 'Are you sure you want to accept this task?',
        showCancelButton: true,
        confirmButtonText: 'Yes, accept',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (result.isConfirmed) {
            jQuery('#acceptTaskId').val(taskId);
            jQuery('#acceptForm').submit();
        }
    });
});

jQuery('.js-reassign').on('click', function(e) {
    e.preventDefault();
    jQuery('#reassignTaskId').val(jQuery(this).data('taskid'));
    new bootstrap.Modal(document.getElementById('reassignModal')).show();
});
JS, \yii\web\View::POS_READY);
?>
