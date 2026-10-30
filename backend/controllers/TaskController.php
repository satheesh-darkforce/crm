<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\Project;
use backend\models\Task;
use backend\models\TaskLogs;
use backend\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend CRUD for Task — includes all list, status, log, and subtask actions.
 */
class TaskController extends Controller
{
    // ── Main list ─────────────────────────────────────────────────────────────

    public function actionIndex(): string
    {
        $userId   = Yii::$app->user->id;
        $usertype = (int)(Yii::$app->user->identity->usertype ?? 2);

        $query = Task::find()
            ->alias('t')
            // INNER JOIN user table so tasks assigned to soft-deleted developers
            // (user.status = 0) are excluded from the listing.
            ->innerJoin('user u_assign', 'u_assign.user_id = t.assigned_to AND u_assign.status = 1')
            ->with(['project', 'client', 'assignedTo', 'createdBy'])
            ->where(['t.status' => 1, 't.parent_id' => 0])
            ->orderBy(['t.id' => SORT_DESC]);

        if ($usertype === User::TYPE_ADMIN || $usertype === User::TYPE_SUBADMIN) {
            // Admin/sub-admin see every non-completed top-level task
            $query->andWhere(['!=', 't.task_status', Task::STATUS_COMPLETED]);
        } else {
            // Mirror Yii1 admin.php: employees only see their Accepted/On Going tasks
            $query->andWhere(['t.assigned_to' => $userId])
                  ->andWhere(['in', 't.task_status', [Task::STATUS_ACCEPTED, Task::STATUS_ONGOING]]);
        }

        if ($name = Yii::$app->request->get('task')) {
            $query->andWhere(['like', 't.task', $name]);
        }
        if ($status = Yii::$app->request->get('task_status')) {
            $query->andWhere(['t.task_status' => (int)$status]);
        }
        if ($projectId = Yii::$app->request->get('project_id')) {
            $query->andWhere(['t.project_id' => (int)$projectId]);
        }
        if ($assignedTo = Yii::$app->request->get('assigned_to')) {
            $query->andWhere(['t.assigned_to' => (int)$assignedTo]);
        }
        if ($assignedBy = Yii::$app->request->get('assigned_by')) {
            $query->andWhere(['t.assigned_by' => (int)$assignedBy]);
        }
        if ($createdBy = Yii::$app->request->get('created_by')) {
            $query->andWhere(['t.created_by' => (int)$createdBy]);
        }
        if ($expectedDate = Yii::$app->request->get('expected_date')) {
            $query->andWhere('DATE(t.expected_delivery_date) = :ed', [':ed' => $expectedDate]);
        }
        if ($dateTime = Yii::$app->request->get('date_time')) {
            $query->andWhere('DATE(t.created_date) = :cd', [':cd' => $dateTime]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $projects    = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $developers  = \backend\models\Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $isDeveloper = Yii::$app->user->identity->isDeveloper();

        // Find the task currently being timed by this user (open log = no end_time)
        $activeTaskId = (int)TaskLogs::find()
            ->select('task_id')
            ->where(['user_id' => Yii::$app->user->id, 'status' => 1])
            ->andWhere(['end_time' => null])
            ->orderBy(['id' => SORT_DESC])
            ->scalar();

        return $this->render('index', compact('dataProvider', 'projects', 'developers', 'isDeveloper', 'activeTaskId'));
    }

    // ── My tasks (assigned to current user, accepted/ongoing) ─────────────────

    public function actionMyTaskList(): string
    {
        $userId = Yii::$app->user->id;

        $query = Task::find()
            ->alias('t')
            ->with(['project', 'assignedBy'])
            ->where(['t.status' => 1, 't.assigned_to' => $userId])
            ->andWhere(['in', 't.task_status', [Task::STATUS_ACCEPTED, Task::STATUS_ONGOING, Task::STATUS_REOPEN]])
            ->orderBy(['t.id' => SORT_DESC]);

        if ($date = Yii::$app->request->get('expected_date')) {
            $query->andWhere('DATE(t.expected_delivery_date) = :ed', [':ed' => $date]);
        }
        if ($dateTime = Yii::$app->request->get('date_time')) {
            $query->andWhere('DATE(t.created_date) = :cd', [':cd' => $dateTime]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        // Find the task currently being timed by this user (open log = no end_time)
        $activeTaskId = (int)TaskLogs::find()
            ->select('task_id')
            ->where(['user_id' => $userId, 'status' => 1])
            ->andWhere(['end_time' => null])
            ->orderBy(['id' => SORT_DESC])
            ->scalar();

        return $this->render('my-task-list', compact('dataProvider', 'activeTaskId'));
    }

    // ── New tasks assigned to current user (task_status=1) ────────────────────

    public function actionMyNewTaskList(): string
    {
        $userId = Yii::$app->user->id;

        // Mark all as read
        Task::updateAll(['is_read' => 2], ['assigned_to' => $userId, 'task_status' => Task::STATUS_PENDING]);

        $query = Task::find()
            ->alias('t')
            ->with(['project', 'assignedTo', 'assignedBy'])
            ->where(['t.status' => 1, 't.assigned_to' => $userId, 't.task_status' => Task::STATUS_PENDING])
            ->orderBy(['t.id' => SORT_DESC]);

        if ($date = Yii::$app->request->get('expected_date')) {
            $query->andWhere('DATE(t.expected_delivery_date) = :ed', [':ed' => $date]);
        }
        if ($dateTime = Yii::$app->request->get('date_time')) {
            $query->andWhere('DATE(t.created_date) = :cd', [':cd' => $dateTime]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('my-new-task-list', compact('dataProvider'));
    }

    // ── Completed tasks ───────────────────────────────────────────────────────

    public function actionCompletedTaskList(): string
    {
        $userId   = Yii::$app->user->id;
        $usertype = Yii::$app->user->identity->usertype ?? 2;

        $query = Task::find()
            ->alias('t')
            ->with(['project', 'assignedTo', 'createdBy'])
            ->where(['t.status' => 1, 't.task_status' => Task::STATUS_COMPLETED])
            ->orderBy(['t.id' => SORT_DESC]);

        if ((int)$usertype !== User::TYPE_ADMIN && (int)$usertype !== User::TYPE_SUBADMIN) {
            $query->andWhere(['or', ['t.created_by' => $userId], ['t.assigned_to' => $userId]]);
        } else {
            $query->andWhere(['t.parent_id' => 0]);
        }

        if ($name = Yii::$app->request->get('task')) {
            $query->andWhere(['like', 't.task', $name]);
        }
        if ($projectId = Yii::$app->request->get('project_id')) {
            $query->andWhere(['t.project_id' => (int)$projectId]);
        }
        if ($assignedTo = Yii::$app->request->get('assigned_to')) {
            $query->andWhere(['t.assigned_to' => (int)$assignedTo]);
        }
        if ($assignedBy = Yii::$app->request->get('assigned_by')) {
            $query->andWhere(['t.assigned_by' => (int)$assignedBy]);
        }
        if ($createdBy = Yii::$app->request->get('created_by')) {
            $query->andWhere(['t.created_by' => (int)$createdBy]);
        }
        if ($date = Yii::$app->request->get('expected_date')) {
            $query->andWhere('DATE(t.expected_delivery_date) = :ed', [':ed' => $date]);
        }
        if ($dateTime = Yii::$app->request->get('date_time')) {
            $query->andWhere('DATE(t.created_date) = :cd', [':cd' => $dateTime]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $developers = \backend\models\Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        // task.total_time is never written by any code path — workdone time
        // lives in task_logs. Aggregate it for the page's task IDs so both the
        // Worked-Time column and the overrun comparison reflect real effort.
        $workedSecs = $this->buildWorkedSecsMap($dataProvider->getModels());

        return $this->render('completed-task-list', compact('dataProvider', 'projects', 'developers', 'workedSecs'));
    }

    // ── Tasks assigned by current user ────────────────────────────────────────

    public function actionAssignedTasks(): string
    {
        $userId   = Yii::$app->user->id;
        $usertype = Yii::$app->user->identity->usertype ?? 2;

        $query = Task::find()
            ->alias('t')
            ->with(['project', 'assignedTo'])
            ->where(['t.status' => 1, 't.parent_id' => 0])
            ->orderBy(['t.id' => SORT_DESC]);

        if ((int)$usertype !== User::TYPE_ADMIN && (int)$usertype !== User::TYPE_SUBADMIN) {
            $query->andWhere(['t.assigned_by' => $userId]);
        }

        if ($date = Yii::$app->request->get('expected_date')) {
            $query->andWhere('DATE(t.expected_delivery_date) = :ed', [':ed' => $date]);
        }
        if ($dateTime = Yii::$app->request->get('date_time')) {
            $query->andWhere('DATE(t.created_date) = :cd', [':cd' => $dateTime]);
        }
        if ($dateTime = Yii::$app->request->get('date_time')) {
            $query->andWhere('DATE(t.created_date) = :cd', [':cd' => $dateTime]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $workedSecs = $this->buildWorkedSecsMap($dataProvider->getModels());

        return $this->render('assigned-tasks', compact('dataProvider', 'workedSecs'));
    }

    /**
     * Build `[task_id => worked_seconds]` for a page of Task models.
     *
     * `tasks.total_time` is never written by any code path in the app; all
     * worked time is recorded as task_logs rows. For a parent task (parent_id=0)
     * the rollup is the sum of its own logs plus the logs of every subtask
     * (parent_id=this). For a subtask it is just the sum of its own logs.
     * The result is keyed by the Task model id so views can `$workedSecs[$id]`.
     *
     * @param Task[] $tasks
     * @return array<int,int>
     */
    private function buildWorkedSecsMap(array $tasks): array
    {
        $ids = [];
        foreach ($tasks as $t) {
            $ids[] = (int)$t->id;
        }
        if (!$ids) {
            return [];
        }
        $map = array_fill_keys($ids, 0);

        $taskTable = Task::tableName();
        $logTable  = TaskLogs::tableName();

        // 1) Logs directly attached to the page's tasks.
        $direct = (new Query())
            ->select(['task_id', 'secs' => 'SUM(total_time)'])
            ->from($logTable)
            ->where(['task_id' => $ids, 'status' => 1])
            ->groupBy('task_id')
            ->all();
        foreach ($direct as $row) {
            $map[(int)$row['task_id']] = (int)$row['secs'];
        }

        // 2) Logs attached to subtasks whose parent is in the page.
        //    Schema treats subtasks as one level deep (parent_id != 0); no
        //    nesting beyond that.
        $sub = (new Query())
            ->select(['pid' => 't.parent_id', 'secs' => 'SUM(tl.total_time)'])
            ->from(['t' => $taskTable])
            ->innerJoin(['tl' => $logTable], 'tl.task_id = t.id AND tl.status = 1')
            ->where(['t.parent_id' => $ids, 't.status' => 1])
            ->groupBy('t.parent_id')
            ->all();
        foreach ($sub as $row) {
            $map[(int)$row['pid']] += (int)$row['secs'];
        }

        return $map;
    }

    // ── View / CRUD ───────────────────────────────────────────────────────────

    public function actionView(int $id): string
    {
        $task      = $this->findModel($id);
        $subtasks  = Task::find()
            ->with(['createdBy', 'assignedBy', 'assignedTo'])
            ->where(['parent_id' => $id, 'status' => 1])
            ->all();
        $taskLogs  = TaskLogs::find()
            ->with(['user'])
            ->where(['task_id' => $id, 'status' => 1])
            ->orderBy('id ASC')
            ->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        return $this->render('view', compact('task', 'subtasks', 'taskLogs', 'developers'));
    }

    public function actionCreate(): string|Response
    {
        $task = new Task();

        if ($task->load(Yii::$app->request->post())) {
            $userId = Yii::$app->user->id;
            $task->created_by   = $userId;
            $task->assigned_by  = $userId;
            $task->is_read      = 1;
            $task->created_date = date('Y-m-d H:i:s');
            $task->status       = 1;
            $task->parent_id    = $task->parent_id ?: 0;

            $h = (int)Yii::$app->request->post('expected_hours', 0);
            $m = (int)Yii::$app->request->post('expected_minutes', 0);
            if ($h > 0 || $m > 0) {
                $task->expected_time = $h * 3600 + $m * 60;
            }

            if ((int)$task->assigned_to === (int)$userId) {
                $task->task_status = Task::STATUS_ACCEPTED;
            } else {
                $task->task_status = Task::STATUS_PENDING;
            }

            $project     = Project::findOne((int)$task->project_id);
            $task->client_id = $project ? $project->client_id : null;

            if ($task->save()) {
                Yii::$app->session->setFlash('success', 'Task created successfully.');
                return $this->redirect(['view', 'id' => $task->id]);
            }
        }

        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('create', compact('task', 'projects', 'developers'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $task = $this->findModel($id);

        if ($task->load(Yii::$app->request->post())) {
            $h = (int)Yii::$app->request->post('expected_hours', 0);
            $m = (int)Yii::$app->request->post('expected_minutes', 0);
            if ($h > 0 || $m > 0) {
                $task->expected_time = $h * 3600 + $m * 60;
            }

            $project     = Project::findOne((int)$task->project_id);
            $task->client_id = $project ? $project->client_id : $task->client_id;

            if ($task->save()) {
                Yii::$app->session->setFlash('success', 'Task updated.');
                return $this->redirect(['view', 'id' => $task->id]);
            }
        }

        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('update', compact('task', 'projects', 'developers'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $task         = $this->findModel($id);
        $task->status = 0;
        $task->save(false);

        Yii::$app->session->setFlash('success', 'Task deleted.');
        return $this->redirect(['index']);
    }

    // ── Accept task (task_status → 2 = Accepted) ──────────────────────────────

    public function actionTaskAccept(): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }

        $taskId = (int)Yii::$app->request->post('taskid');
        $task   = Task::findOne(['id' => $taskId, 'status' => 1]);

        if ($task) {
            $task->task_status = Task::STATUS_ACCEPTED;
            $task->save(false);
            Yii::$app->session->setFlash('success', 'Task accepted successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Task not found.');
        }

        return $this->redirect(Yii::$app->request->referrer ?: ['my-new-task-list']);
    }

    // ── Complete task (check all subtasks done, then set status=5) ────────────

    public function actionCompleteTask(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $taskId = (int)Yii::$app->request->get('taskid');
        $task   = Task::findOne(['id' => $taskId, 'status' => 1]);

        if (!$task) {
            return $this->asJson(['status' => 0, 'msg' => 'Task not found.']);
        }

        $pendingSubtasks = Task::find()
            ->where(['parent_id' => $taskId, 'status' => 1])
            ->andWhere(['!=', 'task_status', Task::STATUS_COMPLETED])
            ->select(['id', 'task'])
            ->all();

        if (!empty($pendingSubtasks)) {
            $names = implode(', ', array_map(fn($s) => '"' . $s->task . '"', $pendingSubtasks));
            return $this->asJson(['status' => 0, 'msg' => "Cannot complete: the following subtasks are still pending: {$names}."]);
        }

        $task->task_status    = Task::STATUS_COMPLETED;
        $task->completed_date = date('Y-m-d H:i:s');
        $task->save(false);

        return $this->asJson(['status' => 1, 'msg' => 'Task completed successfully.']);
    }

    // ── Update task status (AJAX POST); status=6 creates a subtask ────────────

    public function actionTaskStatus(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST required.']);
        }

        $taskId     = (int)Yii::$app->request->post('taskid');
        $taskStatus = (int)Yii::$app->request->post('taskstatus');

        $task = Task::findOne(['id' => $taskId, 'status' => 1]);
        if (!$task) {
            return $this->asJson(['status' => 0, 'msg' => 'Task not found.']);
        }

        if ($taskStatus === 6) {
            // Re-assigned: create a subtask
            $taskName    = trim((string)Yii::$app->request->post('taskname', ''));
            $expectDate  = trim((string)Yii::$app->request->post('expectdate', ''));
            $expectTime  = trim((string)Yii::$app->request->post('expecttime', '0:00'));
            $assignedTo  = (int)Yii::$app->request->post('assignto', 0);
            $description = trim((string)Yii::$app->request->post('description', ''));

            // Parse HH:MM:SS or HH:MM expected-time string into duration seconds
            // (mirrors actionReassignTask — NOT a timestamp delta)
            $parts       = explode(':', $expectTime);
            $hours       = (int)($parts[0] ?? 0);
            $minutes     = (int)($parts[1] ?? 0);
            $seconds     = (int)($parts[2] ?? 0);
            $timeSeconds = $hours * 3600 + $minutes * 60 + $seconds;

            $sub              = new Task();
            $sub->parent_id   = $taskId;
            $sub->project_id  = $task->project_id;
            $sub->client_id   = $task->client_id;
            $sub->task        = $taskName;
            $sub->expected_delivery_date = $expectDate ? date('Y-m-d', strtotime($expectDate)) : null;
            $sub->expected_time = $timeSeconds;
            $sub->task_status = Task::STATUS_PENDING;
            $sub->created_by  = Yii::$app->user->id;
            $sub->description = $description;
            $sub->priority    = 1;
            $sub->assigned_by = Yii::$app->user->id;
            $sub->assigned_to = $assignedTo ?: null;
            $sub->is_read     = 1;
            $sub->assign_type = 1;
            $sub->created_date = date('Y-m-d H:i:s');
            $sub->status      = 1;

            if ($sub->save(false)) {
                return $this->asJson(['status' => 1, 'msg' => 'Subtask created successfully.']);
            }
            return $this->asJson(['status' => 0, 'msg' => 'Failed to create subtask.']);
        }

        $task->task_status = $taskStatus;
        $task->save(false);

        return $this->asJson(['status' => 1, 'msg' => 'Status updated.']);
    }

    // ── Reassign task (create subtask) ────────────────────────────────────────

    public function actionReassignTask(): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }

        $post       = Yii::$app->request->post();
        $taskId     = (int)($post['task_id'] ?? 0);
        $assignedTo = (int)($post['assigned_to'] ?? 0);
        $taskName   = trim($post['task'] ?? '');
        $expectDate = trim($post['expect_date'] ?? '');
        $expectTime = trim($post['expect_time'] ?? '0:00');
        $description= trim($post['description'] ?? '');
        $priority   = (int)($post['priority'] ?? 1);
        $type       = (int)($post['type'] ?? 1);

        $parentTask = Task::findOne(['id' => $taskId, 'status' => 1]);
        if (!$parentTask) {
            Yii::$app->session->setFlash('error', 'Parent task not found.');
            return $this->redirect(Yii::$app->request->referrer ?: ['index']);
        }

        // Parse HH:MM:SS or HH:MM expected_time string into seconds
        $parts   = explode(':', $expectTime);
        $hours   = (int)($parts[0] ?? 0);
        $minutes = (int)($parts[1] ?? 0);
        $seconds = (int)($parts[2] ?? 0);
        $timeSeconds = $hours * 3600 + $minutes * 60 + $seconds;

        if ($timeSeconds < 60) {
            Yii::$app->session->setFlash('error', 'Hour Of Work Required must be at least 1 minute.');
            return $this->redirect(Yii::$app->request->referrer ?: ['view', 'id' => $taskId]);
        }

        $sub               = new Task();
        $sub->parent_id    = $taskId;
        $sub->project_id   = $parentTask->project_id;
        $sub->client_id    = $parentTask->client_id;
        $sub->task         = $taskName;
        $sub->expected_time= $timeSeconds;
        $sub->expected_delivery_date = $expectDate ? date('Y-m-d', strtotime($expectDate)) : null;
        $sub->task_status  = Task::STATUS_PENDING;
        $sub->created_by   = Yii::$app->user->id;
        $sub->description  = $description;
        $sub->priority     = $priority;
        $sub->assigned_by  = Yii::$app->user->id;
        $sub->assigned_to  = $assignedTo ?: null;
        $sub->is_read      = 1;
        $sub->assign_type  = $type;
        $sub->created_date = date('Y-m-d H:i:s');
        $sub->status       = 1;

        if ($sub->save(false)) {
            Yii::$app->session->setFlash('success', 'Subtask created successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Failed to create subtask.');
        }

        return $this->redirect(['view', 'id' => $taskId]);
    }

    // ── Full reassign (update assigned_to on existing task) ───────────────────

    public function actionFullReassignTask(): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }

        $taskId     = (int)Yii::$app->request->post('task_id');
        $assignedTo = (int)Yii::$app->request->post('assigned_to');

        $task = Task::findOne(['id' => $taskId, 'status' => 1]);
        if ($task) {
            $task->assigned_to = $assignedTo;
            $task->is_read     = 1;
            $task->save(false);
            Yii::$app->session->setFlash('success', 'Task re-assigned successfully.');
        } else {
            Yii::$app->session->setFlash('error', 'Task not found.');
        }

        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }

    // ── Task start (create TaskLogs entry, set task_status=3 On Going) ────────

    public function actionTaskStart(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST required.']);
        }

        $userId      = (int)Yii::$app->user->id;
        $taskId      = (int)Yii::$app->request->post('taskid', 0);
        $description = trim((string)Yii::$app->request->post('description', '')) ?: null;

        $task = Task::findOne(['id' => $taskId, 'status' => 1]);
        if (!$task) {
            return $this->asJson(['status' => 0, 'msg' => 'Task not found.']);
        }

        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // Block starting a new task while another one is still in progress.
        // The user must explicitly stop the current task first — silent
        // hand-off masked the fact that a timer was already running.
        $openLog = TaskLogs::find()
            ->where(['user_id' => $userId, 'status' => 1])
            ->andWhere(['end_time' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($openLog && (int)$openLog->task_id !== $taskId) {
            return $this->asJson([
                'status' => 0,
                'msg'    => 'You have already started a task. Please stop the existing task before starting a new one.',
                'activeTaskId' => (int)$openLog->task_id,
            ]);
        }

        // Same-task "Resume" click: nothing to close — fall through to the
        // existing log creation logic, which appends a new log row.
        if ($openLog && (int)$openLog->task_id === $taskId) {
            $startDate = date('Y-m-d', strtotime($openLog->start_time));
            $endTime   = ($startDate !== $today)
                ? date('Y-m-d H:i:s', strtotime($startDate . ' 18:30:00'))
                : $now;
            $openLog->end_time   = $endTime;
            $openLog->total_time = max(0, strtotime($endTime) - strtotime($openLog->start_time));
            $openLog->save(false);
        }

        $log             = new TaskLogs();
        $log->task_id    = $taskId;
        $log->user_id    = $userId;
        $log->start_time = $now;
        $log->end_time   = null;
        $log->total_time = 0;
        $log->comments   = $description;
        $log->status     = 1;

        if ($log->save(false)) {
            // Invariant: at most one On Going task per user at any time.
            // Demote every OTHER task currently flagged On Going for this
            // user back to Accepted before promoting the one we just started.
            // Without this, switching tasks via Start (without clicking Stop
            // on the previous one) left two rows with task_status=3.
            Task::updateAll(
                ['task_status' => Task::STATUS_ACCEPTED],
                [
                    'and',
                    ['assigned_to'  => $userId],
                    ['task_status'  => Task::STATUS_ONGOING],
                    ['!=', 'id', $taskId],
                    ['status' => 1],
                ]
            );
            Task::updateAll(['task_status' => Task::STATUS_ONGOING], ['id' => $taskId]);
            return $this->asJson(['status' => 1, 'msg' => 'Task started successfully.']);
        }

        return $this->asJson(['status' => 0, 'msg' => 'Failed to start task. Try again.']);
    }

    // ── Task stop (close open TaskLogs entry) ─────────────────────────────────

    public function actionTaskStop(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST required.']);
        }

        $userId = (int)Yii::$app->user->id;
        $taskId = (int)Yii::$app->request->post('taskid', 0);

        $task = Task::findOne(['id' => $taskId, 'status' => 1]);
        if (!$task) {
            return $this->asJson(['status' => 0, 'msg' => 'Task not found.']);
        }

        $now   = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        $openLogs = TaskLogs::find()
            ->where(['user_id' => $userId, 'status' => 1])
            ->andWhere(['end_time' => null])
            ->all();

        if (!$openLogs) {
            return $this->asJson(['status' => 0, 'msg' => 'Failed to stop task. Try again.']);
        }

        foreach ($openLogs as $openLog) {
            $startDate           = date('Y-m-d', strtotime($openLog->start_time));
            $endTime             = ($startDate !== $today)
                ? date('Y-m-d H:i:s', strtotime($startDate . ' 18:30:00'))
                : $now;
            $openLog->end_time   = $endTime;
            $openLog->total_time = max(0, strtotime($endTime) - strtotime($openLog->start_time));
            $openLog->save(false);
        }

        // Yii1 parity: stop does NOT change task_status — task remains On Going (3)

        return $this->asJson(['status' => 1, 'msg' => 'Task stopped successfully.']);
    }

    // ── Task history page ─────────────────────────────────────────────────────

    public function actionTaskHistory(int $id): string
    {
        $mainTask = $this->findModel($id);
        $subtasks = Task::find()
            ->with(['assignedTo', 'createdBy'])
            ->where(['parent_id' => $id, 'status' => 1])
            ->all();
        $taskLogs = TaskLogs::find()
            ->with(['user'])
            ->where(['task_id' => $id, 'status' => 1])
            ->orderBy('id ASC')
            ->all();

        return $this->render('task-history', compact('mainTask', 'subtasks', 'taskLogs'));
    }

    // ── Task logs HTML fragment (for AJAX) ────────────────────────────────────

    public function actionTaskLogs(int $id): string
    {
        Yii::$app->response->format = Response::FORMAT_HTML;

        $logs   = TaskLogs::find()
            ->with(['user'])
            ->where(['task_id' => $id, 'status' => 1])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return $this->renderPartial('_task-logs', compact('logs'));
    }

    // ── Subtask list HTML fragment (for AJAX POST) ────────────────────────────

    public function actionGetSubtasks(): string
    {
        Yii::$app->response->format = Response::FORMAT_HTML;

        $taskId   = (int)Yii::$app->request->post('taskid', 0);
        $subtasks = Task::find()
            ->with(['assignedTo'])
            ->where(['parent_id' => $taskId, 'status' => 1])
            ->all();

        return $this->renderPartial('_subtasks', compact('subtasks'));
    }

    // ── Calculate expected delivery date from hours (AJAX) ──────────────────

    public function actionCalcExpectedDate(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $expectedHour   = (int)Yii::$app->request->post('expected_hour', 0);
        $expectedMinute = (int)Yii::$app->request->post('expected_minute', 0);
        $expectedTime   = ($expectedHour * 3600) + ($expectedMinute * 60);

        if ($expectedTime > 0) {
            $result = date('d-m-Y', time() + $expectedTime);
            return $this->asJson(['status' => 1, 'msg' => $result]);
        }

        return $this->asJson(['status' => 0, 'msg' => '']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findModel(int $id): Task
    {
        $model = Task::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Task not found.');
        }
        return $model;
    }
}
