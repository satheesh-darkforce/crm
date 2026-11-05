<?php

namespace backend\controllers;

use backend\assets\TaskAsset;
use backend\components\TaskAccess;
use backend\models\Developer;
use backend\models\Project;
use backend\models\Task;
use backend\models\TaskLogs;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Query;
use yii\filters\VerbFilter;
use yii\web\Response;

/**
 * Tasks — lists, CRUD, accept/complete/status, subtasks, reassign and the
 * start/stop timer (task_logs). Per-task permissions: backend\components\TaskAccess.
 *
 * Times (expected_time, task_logs.total_time) are stored in SECONDS.
 * task.total_time is never written; worked time is the sum of task_logs.
 */
class TaskController extends Controller
{
    /** List filters read from the query string (all optional). */
    private const LIST_FILTERS = ['task', 'task_status', 'project_id', 'assigned_to', 'assigned_by', 'created_by', 'expected_date', 'date_time'];

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'delete'             => ['POST'],
                    'task-accept'        => ['POST'],
                    'complete-task'      => ['POST'],
                    'task-status'        => ['POST'],
                    'reassign-task'      => ['POST'],
                    'full-reassign-task' => ['POST'],
                    'task-start'         => ['POST'],
                    'task-stop'          => ['POST'],
                    'get-subtasks'       => ['POST'],
                    'calc-expected-date' => ['POST'],
                ],
            ],
        ]);
    }

    /** Full pages get the Tasks stylesheet/script. */
    public function render($view, $params = []): string
    {
        TaskAsset::register($this->getView());
        return parent::render($view, $params);
    }

    // ── Lists ─────────────────────────────────────────────────────────────────

    /** Managers: every open top-level task. Others: their accepted / on-going tasks (Yii1 admin.php). */
    public function actionIndex(): string
    {
        $query = $this->baseQuery()
            // Tasks assigned to deactivated users are hidden (Yii1 parity).
            ->innerJoin('user u_assign', 'u_assign.user_id = t.assigned_to AND u_assign.status = 1')
            ->andWhere(['t.parent_id' => 0]);
        if (TaskAccess::isManager()) {
            $query->andWhere(['!=', 't.task_status', Task::STATUS_COMPLETED]);
        } else {
            $query->andWhere(['t.assigned_to' => Yii::$app->user->id, 't.task_status' => [Task::STATUS_ACCEPTED, Task::STATUS_ONGOING]]);
        }
        return $this->renderList('index', TaskAccess::isManager() ? 'Open Tasks' : 'My Tasks', $query);
    }

    /** Tasks assigned to me that I am working on (accepted / on going / re-opened). */
    public function actionMyTaskList(): string
    {
        $query = $this->baseQuery()->andWhere([
            't.assigned_to' => Yii::$app->user->id,
            't.task_status' => [Task::STATUS_ACCEPTED, Task::STATUS_ONGOING, Task::STATUS_REOPEN],
        ]);
        return $this->renderList('my-task-list', 'My Tasks', $query);
    }

    /** New tasks waiting for me to accept. Opening the list marks them read. */
    public function actionMyNewTaskList(): string
    {
        $userId = Yii::$app->user->id;
        Task::updateAll(['is_read' => 2], ['assigned_to' => $userId, 'task_status' => Task::STATUS_PENDING]);
        $query = $this->baseQuery()->andWhere(['t.assigned_to' => $userId, 't.task_status' => Task::STATUS_PENDING]);
        return $this->renderList('my-new-task-list', 'New Tasks', $query);
    }

    public function actionCompletedTaskList(): string
    {
        $query = $this->baseQuery()->andWhere(['t.task_status' => Task::STATUS_COMPLETED]);
        if (TaskAccess::isManager()) {
            $query->andWhere(['t.parent_id' => 0]);
        } else {
            $uid = Yii::$app->user->id;
            $query->andWhere(['or', ['t.created_by' => $uid], ['t.assigned_to' => $uid]]);
        }
        return $this->renderList('completed-task-list', 'Completed Tasks', $query);
    }

    /** Tasks I assigned to others (managers: all top-level tasks). */
    public function actionAssignedTasks(): string
    {
        $query = $this->baseQuery()->andWhere(['t.parent_id' => 0]);
        if (!TaskAccess::isManager()) {
            $query->andWhere(['t.assigned_by' => Yii::$app->user->id]);
        }
        return $this->renderList('assigned-tasks', 'Assigned Tasks', $query);
    }

    // ── View / CRUD ───────────────────────────────────────────────────────────

    public function actionView(int $id): string
    {
        $task     = TaskAccess::findTask($id);
        $subtasks = Task::find()->with(['createdBy', 'assignedBy', 'assignedTo'])
            ->where(['parent_id' => $id, 'status' => 1])->orderBy(['id' => SORT_ASC])->all();
        $taskLogs = $this->logsOf($id);
        $worked   = $this->workedSecs(array_merge([$task], $subtasks));
        return $this->render('view', compact('task', 'subtasks', 'taskLogs', 'worked') + ['developers' => $this->developerOptions()]);
    }

    public function actionCreate(): string|Response
    {
        $task = new Task(['scenario' => Task::SCENARIO_FORM, 'priority' => Task::PRIORITY_MEDIUM, 'assign_type' => Task::ASSIGN_TYPE_FULL]);

        if (Yii::$app->request->isPost && $task->load(Yii::$app->request->post())) {
            $userId             = (int)Yii::$app->user->id;
            $task->created_by   = $userId;
            $task->assigned_by  = $userId;
            $task->is_read      = 1;
            $task->created_date = date('Y-m-d H:i:s');
            $task->status       = 1;
            $task->parent_id    = 0;
            // Assigning to yourself skips the accept step (Yii1).
            $task->task_status  = (int)$task->assigned_to === $userId ? Task::STATUS_ACCEPTED : Task::STATUS_PENDING;
            $task->client_id    = Project::find()->select('client_id')->where(['id' => (int)$task->project_id])->scalar() ?: null;

            if ($this->applyExpectedTime($task) && $task->save()) {
                Yii::$app->session->setFlash('success', 'Task created successfully.');
                return $this->redirect(['view', 'id' => $task->id]);
            }
        }

        return $this->render('create', ['task' => $task] + $this->formOptions());
    }

    public function actionUpdate(int $id): string|Response
    {
        $task = TaskAccess::findTask($id);
        TaskAccess::ensure(TaskAccess::canEdit($task), 'Only the person who created or assigned this task can edit it.');
        $task->scenario = Task::SCENARIO_FORM;

        if (Yii::$app->request->isPost && $task->load(Yii::$app->request->post())) {
            if ((int)$task->project_id !== (int)$task->getOldAttribute('project_id')) {
                $task->client_id = Project::find()->select('client_id')->where(['id' => (int)$task->project_id])->scalar() ?: null;
            }
            if ((int)$task->assigned_to !== (int)$task->getOldAttribute('assigned_to')) {
                $task->is_read = 1; // new assignee sees it as unread
            }
            if ($this->applyExpectedTime($task) && $task->save()) {
                Yii::$app->session->setFlash('success', 'Task updated.');
                return $this->redirect(['view', 'id' => $task->id]);
            }
        }

        return $this->render('update', ['task' => $task] + $this->formOptions());
    }

    public function actionDelete(int $id): Response
    {
        $task = TaskAccess::findTask($id);
        TaskAccess::ensure(TaskAccess::canEdit($task), 'Only the person who created or assigned this task can delete it.');
        $task->updateAttributes(['status' => 0]);

        Yii::$app->session->setFlash('success', "Task \"{$task->task}\" was deleted.");
        return $this->redirect((int)$task->parent_id ? ['view', 'id' => (int)$task->parent_id] : ['assigned-tasks']);
    }

    // ── Workflow ──────────────────────────────────────────────────────────────

    /** Accept a pending task (form post from New Tasks). */
    public function actionTaskAccept(): Response
    {
        $task = TaskAccess::findTask((int)Yii::$app->request->post('taskid'));
        TaskAccess::ensure(TaskAccess::isAssignee($task) || TaskAccess::isManager(), 'Only the assignee can accept this task.');

        if ((int)$task->task_status !== Task::STATUS_PENDING) {
            Yii::$app->session->setFlash('info', 'This task was already accepted.');
        } else {
            $task->updateAttributes(['task_status' => Task::STATUS_ACCEPTED, 'is_read' => 2]);
            Yii::$app->session->setFlash('success', "Task \"{$task->task}\" accepted. It is now in My Tasks.");
        }
        return $this->redirect(['my-new-task-list']);
    }

    /** AJAX: complete a task once all its subtasks are completed. */
    public function actionCompleteTask(): Response
    {
        $task = TaskAccess::findTask((int)Yii::$app->request->post('taskid'));
        TaskAccess::ensure(TaskAccess::canManageWork($task));

        if ((int)$task->task_status === Task::STATUS_COMPLETED) {
            return $this->json(true, 'This task is already completed.');
        }
        if ($this->hasOpenLog($task->id)) {
            return $this->json(false, 'Stop the timer on this task before completing it.');
        }
        $pending = Task::find()->select('task')
            ->where(['parent_id' => $task->id, 'status' => 1])
            ->andWhere(['!=', 'task_status', Task::STATUS_COMPLETED])
            ->column();
        if ($pending) {
            return $this->json(false, 'Complete these subtasks first: ' . implode(', ', array_map(fn($n) => "\"$n\"", $pending)) . '.');
        }

        $task->updateAttributes(['task_status' => Task::STATUS_COMPLETED, 'completed_date' => date('Y-m-d H:i:s')]);
        return $this->json(true, 'Task completed.');
    }

    /** AJAX (Yii1 changestatus): set a status; 6 = re-assign by creating a subtask. */
    public function actionTaskStatus(): Response
    {
        $req  = Yii::$app->request;
        $task = TaskAccess::findTask((int)$req->post('taskid'));
        TaskAccess::ensure(TaskAccess::canManageWork($task));
        $status = (int)$req->post('taskstatus');

        if ($status === Task::STATUS_REASSIGNED) {
            $sub = $this->buildSubtask($task, [
                'task'                   => $req->post('taskname'),
                'assigned_to'            => $req->post('assignto'),
                'expected_delivery_date' => $req->post('expectdate'),
                'description'            => $req->post('description'),
            ], $this->parseDuration($req->post('expecttime')));
            return $sub->save()
                ? $this->json(true, 'Subtask created.')
                : $this->json(false, implode(' ', $sub->getFirstErrors()));
        }
        if (!isset(Task::statusOptions()[$status]) || $status === Task::STATUS_COMPLETED) {
            return $this->json(false, $status === Task::STATUS_COMPLETED ? 'Use Complete to finish a task.' : 'Choose a valid status.');
        }
        $task->updateAttributes(['task_status' => $status]);
        return $this->json(true, 'Status changed to ' . Task::statusOptions()[$status] . '.');
    }

    /** Create a subtask (Subtask form). */
    public function actionReassignTask(): Response
    {
        $req    = Yii::$app->request;
        $parent = TaskAccess::findTask((int)$req->post('task_id'));
        TaskAccess::ensure(TaskAccess::canManageWork($parent), 'You cannot add subtasks to this task.');

        $sub = $this->buildSubtask($parent, [
            'task'                   => $req->post('task'),
            'assigned_to'            => $req->post('assigned_to'),
            'expected_delivery_date' => $req->post('expect_date'),
            'description'            => $req->post('description'),
            'priority'               => $req->post('priority'),
            'assign_type'            => $req->post('type'),
        ], $this->parseDuration($req->post('expect_time')));

        if ($sub->save()) {
            Yii::$app->session->setFlash('success', "Subtask \"{$sub->task}\" created.");
        } else {
            Yii::$app->session->setFlash('error', 'The subtask was not created: ' . implode(' ', $sub->getFirstErrors()));
        }
        return $this->redirect(['view', 'id' => $parent->id]);
    }

    /** Hand a task to someone else. */
    public function actionFullReassignTask(): Response
    {
        $task = TaskAccess::findTask((int)Yii::$app->request->post('task_id'));
        TaskAccess::ensure(TaskAccess::canManageWork($task), 'You cannot re-assign this task.');
        $back = $this->safeReturn(['index']);

        $to = Yii::$app->request->post('assigned_to');
        if ((string)$to === (string)$task->assigned_to) {
            Yii::$app->session->setFlash('info', 'The task is already assigned to that person.');
            return $this->redirect($back);
        }
        if ($this->hasOpenLog($task->id)) {
            Yii::$app->session->setFlash('error', 'The timer is running on this task. Stop it before re-assigning.');
            return $this->redirect($back);
        }
        $task->scenario    = Task::SCENARIO_FORM;
        $task->assigned_to = is_scalar($to) ? $to : null;
        if ($task->validate(['assigned_to'])) {
            // Yii1 parity: only the assignee changes; it shows as unread for them.
            $task->updateAttributes(['assigned_to' => (int)$task->assigned_to, 'is_read' => 1]);
            $name = Developer::find()->select('developer_name')->where(['user_id' => (int)$task->assigned_to])->scalar();
            Yii::$app->session->setFlash('success', "Task \"{$task->task}\" re-assigned" . ($name ? " to $name" : '') . '.');
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $task->getFirstErrors()) ?: 'Choose who to assign the task to.');
        }
        return $this->redirect($back);
    }

    /** AJAX: start (or resume) the timer on one of my tasks. */
    public function actionTaskStart(): Response
    {
        $task = TaskAccess::findTask((int)Yii::$app->request->post('taskid'));
        TaskAccess::ensure(TaskAccess::isAssignee($task), 'Only the assignee can start the timer on this task.');
        if (in_array((int)$task->task_status, [Task::STATUS_PENDING, Task::STATUS_COMPLETED], true)) {
            return $this->json(false, (int)$task->task_status === Task::STATUS_PENDING ? 'Accept the task before starting it.' : 'This task is already completed.');
        }

        $userId = (int)Yii::$app->user->id;
        $open   = TaskLogs::find()->where(['user_id' => $userId, 'status' => 1, 'end_time' => null])->orderBy(['id' => SORT_DESC])->one();
        // One running timer per user: stop the current task first.
        if ($open && (int)$open->task_id !== (int)$task->id) {
            $other = Task::find()->select('task')->where(['id' => $open->task_id])->scalar();
            return $this->json(false, 'Stop the timer on "' . ($other ?: 'your other task') . '" before starting another task.');
        }
        if ($open) {
            return $this->json(true, 'The timer is already running on this task.');
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $log             = new TaskLogs();
            $log->task_id    = $task->id;
            $log->user_id    = $userId;
            $log->start_time = date('Y-m-d H:i:s');
            $log->end_time   = null;
            $log->total_time = 0;
            $log->comments   = null;
            $log->status     = 1;
            $log->save(false);
            // At most one On Going task per user.
            Task::updateAll(['task_status' => Task::STATUS_ACCEPTED], ['and',
                ['assigned_to' => $userId], ['task_status' => Task::STATUS_ONGOING], ['!=', 'id', $task->id], ['status' => 1]]);
            Task::updateAll(['task_status' => Task::STATUS_ONGOING], ['id' => $task->id]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Task start failed: ' . $e->getMessage(), __METHOD__);
            return $this->json(false, 'The timer could not be started. Please try again.');
        }
        return $this->json(true, 'Timer started on "' . $task->task . '".');
    }

    /** AJAX: stop my running timer. Logs left open overnight end at 18:30 that day (Yii1). */
    public function actionTaskStop(): Response
    {
        $open = TaskLogs::find()->where(['user_id' => (int)Yii::$app->user->id, 'status' => 1, 'end_time' => null])->all();
        if (!$open) {
            return $this->json(true, 'No timer was running.');
        }
        $now = date('Y-m-d H:i:s');
        foreach ($open as $log) {
            $startDay = date('Y-m-d', strtotime($log->start_time));
            $end      = $startDay !== date('Y-m-d') ? $startDay . ' 18:30:00' : $now;
            $log->end_time   = $end;
            $log->total_time = max(0, strtotime($end) - strtotime($log->start_time));
            $log->save(false);
        }
        // Yii1 parity: stopping keeps the task On Going.
        $secs = array_sum(array_map(fn($l) => (int)$l->total_time, $open));
        return $this->json(true, 'Timer stopped (' . Task::hm($secs) . ' logged).');
    }

    public function actionTaskHistory(int $id): string
    {
        $mainTask = TaskAccess::findTask($id);
        $subtasks = Task::find()->with(['assignedTo', 'createdBy'])
            ->where(['parent_id' => $id, 'status' => 1])->orderBy(['id' => SORT_ASC])->all();
        $taskLogs = $this->logsOf($id);
        $worked   = $this->workedSecs(array_merge([$mainTask], $subtasks));
        return $this->render('task-history', compact('mainTask', 'subtasks', 'taskLogs', 'worked'));
    }

    /** HTML fragment for the Task Logs modal. */
    public function actionTaskLogs(int $id): string
    {
        $task = TaskAccess::findTask($id);
        return $this->renderPartial('_task-logs', ['logs' => $this->logsOf($task->id)]);
    }

    /** HTML fragment: subtasks of a task (Yii1 getsubtasks). */
    public function actionGetSubtasks(): string
    {
        $task     = TaskAccess::findTask((int)Yii::$app->request->post('taskid'));
        $subtasks = Task::find()->with(['assignedTo'])->where(['parent_id' => $task->id, 'status' => 1])->all();
        return $this->renderPartial('_subtasks', compact('subtasks'));
    }

    /** AJAX (Yii1 calcexpecteddate): today + the expected duration, as d-m-Y. */
    public function actionCalcExpectedDate(): Response
    {
        $h = max(0, min(1000, (int)Yii::$app->request->post('expected_hour', 0)));
        $m = max(0, min(59, (int)Yii::$app->request->post('expected_minute', 0)));
        $secs = $h * 3600 + $m * 60;
        return $this->asJson($secs > 0 ? ['status' => 1, 'msg' => date('d-m-Y', time() + $secs)] : ['status' => 0, 'msg' => '']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function baseQuery(): ActiveQuery
    {
        return Task::find()->alias('t')
            ->with(['project', 'assignedTo', 'assignedBy', 'createdBy'])
            ->where(['t.status' => 1])
            ->orderBy(['t.id' => SORT_DESC]);
    }

    /** Apply the list filters, then render the shared list view. */
    private function renderList(string $listType, string $title, ActiveQuery $query): string
    {
        $req = Yii::$app->request;
        $f   = [];
        foreach (self::LIST_FILTERS as $key) {
            $v = $req->get($key, '');
            $f[$key] = is_string($v) ? mb_substr(trim($v), 0, 255) : '';
        }
        $ignored = [];
        foreach (['task_status', 'project_id', 'assigned_to', 'assigned_by', 'created_by'] as $key) {
            if ($f[$key] !== '' && !ctype_digit($f[$key])) { $ignored[] = str_replace('_', ' ', $key); $f[$key] = ''; }
        }
        foreach (['expected_date', 'date_time'] as $key) {
            $d = \DateTime::createFromFormat('Y-m-d', $f[$key]);
            if ($f[$key] !== '' && (!$d || $d->format('Y-m-d') !== $f[$key])) { $ignored[] = $key === 'date_time' ? 'created date' : 'deadline'; $f[$key] = ''; }
        }
        if ($ignored) {
            Yii::$app->session->setFlash('warning', 'Ignored an invalid search value (' . implode(', ', $ignored) . ').');
        }

        if ($f['task'] !== '')          { $query->andWhere(['like', 't.task', $f['task']]); }
        if ($f['task_status'] !== '')   { $query->andWhere(['t.task_status' => (int)$f['task_status']]); }
        if ($f['project_id'] !== '')    { $query->andWhere(['t.project_id' => (int)$f['project_id']]); }
        if ($f['assigned_to'] !== '')   { $query->andWhere(['t.assigned_to' => (int)$f['assigned_to']]); }
        if ($f['assigned_by'] !== '')   { $query->andWhere(['t.assigned_by' => (int)$f['assigned_by']]); }
        if ($f['created_by'] !== '')    { $query->andWhere(['t.created_by' => (int)$f['created_by']]); }
        if ($f['expected_date'] !== '') { $query->andWhere(['DATE(t.expected_delivery_date)' => $f['expected_date']]); }
        if ($f['date_time'] !== '')     { $query->andWhere(['between', 't.created_date', $f['date_time'] . ' 00:00:00', $f['date_time'] . ' 23:59:59']); }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);
        $tasks        = $dataProvider->getModels();
        $ids          = array_map(fn($t) => (int)$t->id, $tasks);

        $subtaskCounts = $ids ? (new Query())->select(['cnt' => 'COUNT(*)', 'parent_id'])->from(Task::tableName())
            ->where(['parent_id' => $ids, 'status' => 1])->groupBy('parent_id')->indexBy('parent_id')->column() : [];

        return $this->render('list', [
            'listType'      => $listType,
            'title'         => $title,
            'dataProvider'  => $dataProvider,
            'f'             => $f,
            'workedSecs'    => $this->workedSecs($tasks),
            'subtaskCounts' => array_map('intval', $subtaskCounts),
            'activeTaskId'  => (int)TaskLogs::find()->select('task_id')
                ->where(['user_id' => Yii::$app->user->id, 'status' => 1, 'end_time' => null])->orderBy(['id' => SORT_DESC])->scalar(),
            'projects'      => Project::find()->select(['title', 'id'])->where(['status' => 1])->orderBy('title')->indexBy('id')->column(),
            'developers'    => $this->developerOptions(),
        ]);
    }

    /**
     * [task_id => worked seconds] from task_logs. A parent's time includes its
     * subtasks' logs (subtasks are one level deep).
     *
     * @param Task[] $tasks
     */
    private function workedSecs(array $tasks): array
    {
        $ids = array_values(array_unique(array_map(fn($t) => (int)$t->id, $tasks)));
        if (!$ids) {
            return [];
        }
        $map = array_fill_keys($ids, 0);
        foreach ((new Query())->select(['task_id', 'secs' => 'SUM(total_time)'])->from(TaskLogs::tableName())
                     ->where(['task_id' => $ids, 'status' => 1])->groupBy('task_id')->all() as $row) {
            $map[(int)$row['task_id']] += (int)$row['secs'];
        }
        foreach ((new Query())->select(['pid' => 't.parent_id', 'secs' => 'SUM(tl.total_time)'])
                     ->from(['t' => Task::tableName()])
                     ->innerJoin(['tl' => TaskLogs::tableName()], 'tl.task_id = t.id AND tl.status = 1')
                     ->where(['t.parent_id' => $ids, 't.status' => 1])->groupBy('t.parent_id')->all() as $row) {
            $map[(int)$row['pid']] += (int)$row['secs'];
        }
        return $map;
    }

    /** @return TaskLogs[] */
    private function logsOf(int $taskId): array
    {
        return TaskLogs::find()->with(['user'])->where(['task_id' => $taskId, 'status' => 1])->orderBy(['id' => SORT_ASC])->all();
    }

    private function hasOpenLog(int $taskId): bool
    {
        return TaskLogs::find()->where(['task_id' => $taskId, 'status' => 1, 'end_time' => null])->exists();
    }

    /** Expected time from the form's hours + minutes fields (seconds); false after adding an error. */
    private function applyExpectedTime(Task $task): bool
    {
        $req = Yii::$app->request;
        $h   = $req->post('expected_hours', '');
        $m   = $req->post('expected_minutes', '');
        if (!is_scalar($h) || !is_scalar($m) || ($h !== '' && !ctype_digit((string)$h)) || ($m !== '' && !ctype_digit((string)$m)) || (int)$m > 59) {
            $task->validate();
            $task->addError('expected_time', 'Enter whole hours and minutes (minutes 0–59).');
            return false;
        }
        $task->expected_time = (int)$h * 3600 + (int)$m * 60;
        return true;
    }

    /** "H:MM" / "H:MM:SS" → seconds; 0 for anything else. */
    private function parseDuration($value): int
    {
        if (!is_string($value) || !preg_match('/^(\d{1,4}):([0-5]?\d)(?::([0-5]?\d))?$/', trim($value), $m)) {
            return 0;
        }
        return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)($m[3] ?? 0);
    }

    /** A validated-on-save subtask of $parent from posted values. */
    private function buildSubtask(Task $parent, array $values, int $expectedSecs): Task
    {
        $userId = (int)Yii::$app->user->id;
        $sub = new Task(['scenario' => Task::SCENARIO_FORM]);
        $sub->setAttributes(array_map(fn($v) => is_scalar($v) ? trim((string)$v) : null, $values + [
            'priority' => Task::PRIORITY_HIGH, 'assign_type' => Task::ASSIGN_TYPE_FULL,
        ]));
        $sub->project_id    = $parent->project_id;
        $sub->client_id     = $parent->client_id;
        $sub->parent_id     = $parent->id;
        $sub->expected_time = $expectedSecs;
        $sub->task_status   = (int)$sub->assigned_to === $userId ? Task::STATUS_ACCEPTED : Task::STATUS_PENDING;
        $sub->created_by    = $userId;
        $sub->assigned_by   = $userId;
        $sub->is_read       = 1;
        $sub->created_date  = date('Y-m-d H:i:s');
        $sub->status        = 1;
        return $sub;
    }

    private function json(bool $ok, string $message): Response
    {
        return $this->asJson(['success' => $ok, 'message' => $message]);
    }

    /** Return to the posted list route if it is one of ours, else $default. */
    private function safeReturn(array $default): array
    {
        $r = Yii::$app->request->post('return');
        return in_array($r, ['index', 'my-task-list', 'my-new-task-list', 'assigned-tasks', 'completed-task-list'], true) ? [$r] : $default;
    }

    /** Active employees (developer name) keyed by user_id. */
    private function developerOptions(): array
    {
        return Developer::find()->select(['developer_name', 'user_id'])->where(['status' => 1])
            ->orderBy('developer_name')->indexBy('user_id')->column();
    }

    private function formOptions(): array
    {
        return [
            'projects'   => Project::find()->select(['title', 'id'])->where(['status' => 1])->orderBy('title')->indexBy('id')->column(),
            'developers' => $this->developerOptions(),
        ];
    }
}
