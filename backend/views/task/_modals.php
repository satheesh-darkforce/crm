<?php
/**
 * Re-assign, Add subtask and Time logs modals — shared by the task lists and
 * the task page. Opened by [data-reassign], [data-subtask] and
 * [data-ui-modal="#taskLogsModal"] (see tasks.js / page-ui.js).
 *
 * @var \yii\web\View $this
 * @var array  $developers  user_id => name
 * @var string $returnTo    list route to come back to after re-assigning
 */

use backend\models\Task;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\bootstrap5\Html;
use yii\helpers\Url;

$csrf = Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken());
?>
<div class="modal fade" id="reassignModal" tabindex="-1" aria-labelledby="reassignModalTitle" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= Url::to(['/task/full-reassign-task']) ?>" class="js-busy-form">
                <?= $csrf ?>
                <?= Html::hiddenInput('task_id', '') ?>
                <?= Html::hiddenInput('return', $returnTo) ?>
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold" id="reassignModalTitle">Re-assign task</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Task: <strong class="js-task-name"></strong></p>
                    <label class="form-label" for="reassign-to">Assign to <span class="text-danger">*</span></label>
                    <?= Select2::widget([
                        'name' => 'assigned_to', 'data' => $developers,
                        'options' => ['placeholder' => 'Select employee', 'required' => true, 'id' => 'reassign-to'],
                        'size' => Select2::SMALL,
                        'pluginOptions' => ['allowClear' => true, 'width' => '100%', 'dropdownParent' => '#reassignModal'],
                    ]) ?>
                    <div class="form-text">The new assignee sees it as unread in their tasks.</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Re-assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="subtaskModal" tabindex="-1" aria-labelledby="subtaskModalTitle" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= Url::to(['/task/reassign-task']) ?>" data-hm-target="#subtask-expect-time" data-hm-format="hhmm">
                <?= $csrf ?>
                <?= Html::hiddenInput('task_id', '') ?>
                <input type="hidden" name="expect_time" id="subtask-expect-time" value="">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold" id="subtaskModalTitle">Add subtask</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Under: <strong class="js-task-name"></strong></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="subtask-name">Subtask <span class="text-danger">*</span></label>
                            <input type="text" id="subtask-name" name="task" class="form-control form-control-sm" maxlength="255" placeholder="What needs doing" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="subtask-assignee">Assign to <span class="text-danger">*</span></label>
                            <?= Select2::widget([
                                'name' => 'assigned_to', 'data' => $developers,
                                'options' => ['placeholder' => 'Select employee', 'required' => true, 'id' => 'subtask-assignee'],
                                'size' => Select2::SMALL,
                                'pluginOptions' => ['allowClear' => true, 'width' => '100%', 'dropdownParent' => '#subtaskModal'],
                            ]) ?>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label" for="subtask-priority">Priority <span class="text-danger">*</span></label>
                            <?= Html::dropDownList('priority', Task::PRIORITY_MEDIUM, Task::priorityOptions(), ['class' => 'form-select form-select-sm', 'id' => 'subtask-priority', 'required' => true]) ?>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label" for="subtask-type">Assign type <span class="text-danger">*</span></label>
                            <?= Html::dropDownList('type', Task::ASSIGN_TYPE_FULL, Task::assignTypeOptions(), ['class' => 'form-select form-select-sm', 'id' => 'subtask-type', 'required' => true]) ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="subtask-date">Deadline <span class="text-danger">*</span></label>
                            <?= DatePicker::widget([
                                'name' => 'expect_date', 'type' => DatePicker::TYPE_INPUT,
                                'options' => ['id' => 'subtask-date', 'placeholder' => 'YYYY-MM-DD', 'class' => 'form-control form-control-sm', 'required' => true, 'autocomplete' => 'off'],
                                'pluginOptions' => ['autoclose' => true, 'format' => 'yyyy-mm-dd', 'todayHighlight' => true, 'startDate' => date('Y-m-d')],
                            ]) ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="subtask-hours">Work required <span class="text-danger">*</span></label>
                            <div class="hm-pair">
                                <input type="number" id="subtask-hours" class="form-control form-control-sm js-hm-hours" min="0" max="1000" value="0" aria-label="Hours"><span>h</span>
                                <input type="number" class="form-control form-control-sm js-hm-minutes" min="0" max="59" value="0" aria-label="Minutes"><span>m</span>
                            </div>
                            <div class="invalid-feedback d-block js-hm-error" style="display:none"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="subtask-desc">Description</label>
                            <textarea id="subtask-desc" name="description" class="form-control form-control-sm" rows="3" maxlength="10000" placeholder="Details for the assignee"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Create subtask</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="taskLogsModal" tabindex="-1" aria-labelledby="taskLogsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold" id="taskLogsModalTitle">Time logs</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0"></div>
        </div>
    </div>
</div>
