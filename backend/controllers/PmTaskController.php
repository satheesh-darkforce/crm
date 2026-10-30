<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\PmTask;
use backend\models\Project;
use backend\models\TaskLogs;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * PM Task controller.
 *
 * task_status values used in PM:
 *   1 = Pending
 *   2 = Accepted
 *   3 = On Going
 *   4 = Completed
 *   5 = Closed
 */
class PmTaskController extends Controller
{
    public function actionIndex(): string
    {
        $query = PmTask::find()
            ->alias('t')
            ->with(['project', 'assignedTo'])
            ->where(['t.status' => 1, 't.parent_id' => 0])
            ->orderBy(['t.id' => SORT_DESC]);

        if ($name = Yii::$app->request->get('task')) {
            $query->andWhere(['like', 't.task', $name]);
        }
        if ($taskStatus = Yii::$app->request->get('task_status')) {
            $query->andWhere(['t.task_status' => (int)$taskStatus]);
        }
        if ($projectId = Yii::$app->request->get('project_id')) {
            $query->andWhere(['t.project_id' => (int)$projectId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $projects = Project::find()
            ->where(['status' => 1, 'main_project_id' => null])
            ->orderBy('title')
            ->all();

        return $this->render('index', compact('dataProvider', 'projects'));
    }

    public function actionView(int $id): string
    {
        $task     = $this->findModel($id);
        $subtasks = PmTask::find()->where(['parent_id' => $id, 'status' => 1])->all();
        $logs     = TaskLogs::find()
            ->with(['user'])
            ->where(['task_id' => $id, 'status' => 1])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return $this->render('view', compact('task', 'subtasks', 'logs'));
    }

    public function actionCreate(): string|Response
    {
        $task = new PmTask();

        // Allow pre-filling project_id from URL
        $task->project_id = (int)Yii::$app->request->get('project_id', 0) ?: null;

        if ($task->load(Yii::$app->request->post())) {
            $post = Yii::$app->request->post();

            $userId = Yii::$app->user->id;

            // Convert HH:MM expected time to seconds
            $hours   = (int)($post['expected_hour']  ?? 0);
            $minutes = (int)($post['expected_minute'] ?? 0);
            $task->expected_time = ($hours * 3600) + ($minutes * 60) ?: null;

            $task->created_by   = $userId;
            $task->assigned_by  = $userId;
            $task->created_date = date('Y-m-d H:i:s');
            $task->status       = 1;
            $task->is_read      = 1;
            $task->parent_id    = $task->parent_id ?: 0;

            // Auto-set task_status based on assignment
            $task->task_status = ((int)$task->assigned_to === (int)$userId)
                ? PmTask::STATUS_ACCEPTED
                : PmTask::STATUS_PENDING;

            // Derive client_id from project
            $project = Project::findOne((int)$task->project_id);
            $task->client_id = $project ? $project->client_id : null;

            if ($task->save()) {
                Yii::$app->session->setFlash('success', 'PM Task created successfully.');
                return $this->redirect(['view', 'id' => $task->id]);
            }
        }

        $projects   = Project::find()->where(['status' => 1, 'main_project_id' => null])->orderBy('title')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('create', compact('task', 'projects', 'developers'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $task = $this->findModel($id);

        if ($task->load(Yii::$app->request->post())) {
            $project = Project::findOne((int)$task->project_id);
            $task->client_id = $project ? $project->client_id : $task->client_id;

            if ($task->save()) {
                Yii::$app->session->setFlash('success', 'PM Task updated.');
                return $this->redirect(['view', 'id' => $task->id]);
            }
        }

        $projects   = Project::find()->where(['status' => 1, 'main_project_id' => null])->orderBy('title')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('update', compact('task', 'projects', 'developers'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $task = $this->findModel($id);
        $task->status = 0;
        $task->save(false);

        Yii::$app->session->setFlash('success', 'PM Task deleted.');
        return $this->redirect(['index']);
    }

    // ── AJAX / action endpoints ──────────────────────────────────────────────

    /**
     * AJAX: change task_status.
     * POST: taskid, taskstatus
     * Special case: taskstatus=6 creates a sub-task instead.
     */
    public function actionTaskStatus(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid request']);
        }

        $taskId     = (int)Yii::$app->request->post('taskid');
        $taskStatus = (int)Yii::$app->request->post('taskstatus');

        if ($taskStatus === 6) {
            // Create sub-task
            $parentTask = PmTask::findOne($taskId);
            if (!$parentTask) {
                return $this->asJson(['status' => 0, 'msg' => 'Task not found']);
            }

            $sub = new PmTask();
            $sub->parent_id             = $taskId;
            $sub->project_id            = $parentTask->project_id;
            $sub->client_id             = $parentTask->client_id;
            $sub->task                  = Yii::$app->request->post('taskname', '');
            $sub->expected_delivery_date = Yii::$app->request->post('expectdate')
                ? date('Y-m-d', strtotime(Yii::$app->request->post('expectdate')))
                : null;
            $sub->expected_time         = $sub->expected_delivery_date
                ? (strtotime($sub->expected_delivery_date) - time())
                : null;
            $sub->assigned_to           = Yii::$app->request->post('assignto') ?: null;
            $sub->description           = Yii::$app->request->post('description') ?: null;
            $sub->task_status           = PmTask::STATUS_PENDING;
            $sub->created_by            = Yii::$app->user->id;
            $sub->assigned_by           = Yii::$app->user->id;
            $sub->priority              = PmTask::PRIORITY_HIGH;
            $sub->is_read               = 1;
            $sub->assign_type           = PmTask::ASSIGN_TYPE_FULL;
            $sub->created_date          = date('Y-m-d H:i:s');
            $sub->status                = 1;

            return $this->asJson($sub->save() ? ['status' => 1] : ['status' => 0]);
        }

        // Plain status update
        $ok = PmTask::updateAll(['task_status' => $taskStatus], ['id' => $taskId]);
        return $this->asJson(['status' => $ok ? 1 : 0]);
    }

    /**
     * AJAX: start task timer.
     * POST: taskid, description (optional)
     */
    public function actionTaskStart(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $userId  = (int)Yii::$app->user->id;
        $taskId  = (int)Yii::$app->request->post('taskid', 0);
        $comment = Yii::$app->request->post('description');

        $task = PmTask::findOne($taskId);
        if (!$task) {
            return $this->asJson(['status' => 0, 'msg' => 'Task not found']);
        }

        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // Close ALL open logs for this user (task_logs is shared with regular
        // Task, so the previous open could be from either flow). Using ->one()
        // historically left orphans behind.
        $openLogs = TaskLogs::find()
            ->where(['user_id' => $userId, 'status' => 1])
            ->andWhere(['end_time' => null])
            ->all();

        foreach ($openLogs as $openLog) {
            $startDate = date('Y-m-d', strtotime($openLog->start_time));
            $endTime   = ($startDate !== $today)
                ? date('Y-m-d H:i:s', strtotime($startDate . ' 18:30:00'))
                : $now;

            $openLog->end_time   = $endTime;
            $openLog->total_time = strtotime($endTime) - strtotime($openLog->start_time);
            $openLog->save(false);
        }

        // Create new log entry
        $log = new TaskLogs();
        $log->task_id    = $taskId;
        $log->user_id    = $userId;
        $log->start_time = $now;
        $log->end_time   = null;
        $log->total_time = null;
        $log->comments   = $comment;
        $log->status     = 1;

        if ($log->save()) {
            // Invariant: at most one PM task On Going per user. Demote any
            // other ONGOING PM task owned by this user back to Accepted.
            PmTask::updateAll(
                ['task_status' => PmTask::STATUS_ACCEPTED],
                [
                    'and',
                    ['assigned_to' => $userId],
                    ['task_status' => PmTask::STATUS_ONGOING],
                    ['!=', 'id', $taskId],
                    ['status' => 1],
                ]
            );
            PmTask::updateAll(['task_status' => PmTask::STATUS_ONGOING], ['id' => $taskId]);
            return $this->asJson(['status' => 1, 'msg' => 'Task started successfully']);
        }

        return $this->asJson(['status' => 0, 'msg' => 'Failed to start task.']);
    }

    /**
     * AJAX: stop task timer.
     * POST: taskid, description (optional)
     */
    public function actionTaskStop(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $userId = (int)Yii::$app->user->id;
        $taskId = (int)Yii::$app->request->post('taskid', 0);

        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        $openLogs = TaskLogs::find()
            ->where(['user_id' => $userId, 'status' => 1])
            ->andWhere(['end_time' => null])
            ->all();

        if (!$openLogs) {
            return $this->asJson(['status' => 0, 'msg' => 'No running task found.']);
        }

        foreach ($openLogs as $openLog) {
            $startDate = date('Y-m-d', strtotime($openLog->start_time));
            $endTime   = ($startDate !== $today)
                ? date('Y-m-d H:i:s', strtotime($startDate . ' 18:30:00'))
                : $now;

            $openLog->end_time   = $endTime;
            $openLog->total_time = strtotime($endTime) - strtotime($openLog->start_time);
            $openLog->save(false);
        }

        return $this->asJson(['status' => 1, 'msg' => 'Task stopped successfully']);
    }

    /**
     * AJAX: reassign a task to another developer (creates a sub-task).
     * POST: task_id, task, assigned_to, expect_date, expect_time, description, type, priority
     */
    public function actionReassignTask(): string
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }

        $post      = Yii::$app->request->post();
        $taskId    = (int)$post['task_id'];
        $parent    = PmTask::findOne($taskId);
        if (!$parent) {
            return 'Task not found';
        }

        // Parse HH:MM:SS or HH:MM expected time
        $timeStr = $post['expect_time'] ?? '00:00:00';
        sscanf($timeStr, '%d:%d:%d', $h, $m, $s);
        $timeSeconds = ($h * 3600) + (($m ?? 0) * 60) + ($s ?? 0);

        $sub = new PmTask();
        $sub->parent_id             = $taskId;
        $sub->project_id            = $parent->project_id;
        $sub->client_id             = $parent->client_id;
        $sub->task                  = $post['task'] ?? '';
        $sub->expected_time         = $timeSeconds ?: null;
        $sub->expected_delivery_date = isset($post['expect_date']) && $post['expect_date'] !== ''
            ? date('Y-m-d', strtotime($post['expect_date']))
            : null;
        $sub->task_status           = PmTask::STATUS_PENDING;
        $sub->created_by            = Yii::$app->user->id;
        $sub->description           = $post['description'] ?? null;
        $sub->priority              = (int)($post['priority'] ?? PmTask::PRIORITY_MEDIUM);
        $sub->assigned_by           = Yii::$app->user->id;
        $sub->assigned_to           = (int)($post['assigned_to'] ?? 0) ?: null;
        $sub->is_read               = 1;
        $sub->assign_type           = (int)($post['type'] ?? PmTask::ASSIGN_TYPE_FULL);
        $sub->created_date          = date('Y-m-d H:i:s');
        $sub->status                = 1;

        if ($sub->save()) {
            return 'Sub Task Is Created Successfully';
        }
        return 'Sub Task Name Is Already Exist';
    }

    /**
     * AJAX: get sub-tasks HTML for a task.
     * POST: taskid
     */
    public function actionGetSubtasks(): string
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }

        $taskId = (int)Yii::$app->request->post('taskid');
        $subs   = PmTask::find()
            ->with(['assignedTo'])
            ->where(['parent_id' => $taskId, 'status' => 1])
            ->all();

        $statusLabel = [
            1 => 'Pending', 2 => 'Accepted', 3 => 'On Going', 4 => 'Completed', 5 => 'Closed'
        ];

        ob_start(); ?>
        <table class="table table-sm list-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Sub Task</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($subs)): ?>
                <tr><td colspan="5" class="text-muted text-center">No sub-tasks found.</td></tr>
            <?php else: ?>
                <?php foreach ($subs as $i => $sub): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($sub->task) ?></td>
                    <td><?= htmlspecialchars($sub->assignedTo->username ?? '—') ?></td>
                    <td><?= $statusLabel[$sub->task_status] ?? '—' ?></td>
                    <td><?= $sub->getTotalTimeFormatted() ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php return ob_get_clean();
    }

    /**
     * AJAX: complete a task (only if all sub-tasks are complete).
     * GET: taskid
     */
    public function actionCompleteTask(): string
    {
        $taskId    = (int)Yii::$app->request->get('taskid');
        $total     = PmTask::find()->where(['parent_id' => $taskId, 'status' => 1])->count();
        $completed = PmTask::find()->where(['parent_id' => $taskId, 'status' => 1, 'task_status' => PmTask::STATUS_COMPLETED])->count();

        if ($total == 0 || $total == $completed) {
            PmTask::updateAll(
                ['task_status' => PmTask::STATUS_COMPLETED, 'completed_date' => date('Y-m-d H:i:s')],
                ['id' => $taskId]
            );
            return 'Your Task Completed Successfully';
        }

        return 'Task Completed Failed By Uncompleting Sub Tasks';
    }

    /**
     * AJAX: accept a task.
     * GET: taskid
     */
    public function actionTaskAccept(): string
    {
        $taskId = (int)Yii::$app->request->get('taskid');
        PmTask::updateAll(['task_status' => PmTask::STATUS_ACCEPTED], ['id' => $taskId]);
        return 'Your Task Accepted Successfully';
    }

    /**
     * AJAX: get task time logs HTML.
     * GET: taskid
     */
    public function actionTaskLogs(): string
    {
        $taskId = (int)Yii::$app->request->get('taskid');
        $logs   = TaskLogs::find()
            ->with(['user'])
            ->where(['task_id' => $taskId, 'status' => 1])
            ->all();

        ob_start(); ?>
        <table class="table table-sm list-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="text-muted text-center">No logs found.</td></tr>
            <?php else: ?>
                <?php $totalSecs = 0; ?>
                <?php foreach ($logs as $i => $log): ?>
                    <?php $totalSecs += (int)$log->total_time; ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($log->user->username ?? '—') ?></td>
                    <td><?= $log->start_time ? date('d M Y h:i A', strtotime($log->start_time)) : '—' ?></td>
                    <td><?= $log->end_time   ? date('d M Y h:i A', strtotime($log->end_time))   : 'Running' ?></td>
                    <td><?= $log->getTotalTimeFormatted() ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="table-light fw-semibold">
                    <td colspan="4" class="text-end">Total:</td>
                    <td><?= $totalSecs > 0 ? sprintf('%02d:%02d', intdiv($totalSecs, 3600), intdiv($totalSecs % 3600, 60)) : '—' ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php return ob_get_clean();
    }

    /**
     * My task list — tasks assigned to the current user.
     */
    public function actionMyTaskList(): string
    {
        $userId = Yii::$app->user->id;

        $query = PmTask::find()
            ->alias('t')
            ->with(['project'])
            ->where(['t.status' => 1, 't.assigned_to' => $userId])
            ->andWhere(['!=', 't.task_status', PmTask::STATUS_COMPLETED])
            ->orderBy(['t.id' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('my-task-list', compact('dataProvider'));
    }

    /**
     * Completed task list.
     */
    public function actionCompletedTaskList(): string
    {
        $userId   = Yii::$app->user->id;
        $isAdmin  = in_array(Yii::$app->user->identity->usertype ?? 0, [1, 4]);

        $query = PmTask::find()
            ->alias('t')
            ->with(['project', 'assignedTo'])
            ->where(['t.status' => 1, 't.task_status' => PmTask::STATUS_COMPLETED]);

        if (!$isAdmin) {
            $query->andWhere(['or', ['t.assigned_to' => $userId], ['t.created_by' => $userId]]);
        }

        $query->orderBy(['t.completed_date' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('completed-task-list', compact('dataProvider'));
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function findModel(int $id): PmTask
    {
        $model = PmTask::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('PM Task not found.');
        }
        return $model;
    }
}
