<?php

namespace api\modules\v1\controllers;

use common\models\Project;
use common\models\Task;
use Yii;

/**
 * Task API endpoints — migrated from Yii1 WebserviceController:
 *   actionAddnewtask()      → POST /api/v1/task/add
 *   actionListmytasks()     → POST /api/v1/task/my-list
 *   actionTaskstart()       → POST /api/v1/task/start     (requires TaskLogs)
 *   actionTaskstop()        → POST /api/v1/task/stop      (requires TaskLogs)
 *   actionTaskaccept()      → POST /api/v1/task/accept
 *   actionTaskview()        → POST /api/v1/task/view
 *   actionSubtasklist()     → POST /api/v1/task/subtask-list
 *   actionFullreassigntask()→ POST /api/v1/task/bulk-reassign
 *
 * task_status values (same as Yii1):
 *   1 = Pending, 2 = Started, 3 = Stopped, 4 = Completed, 5 = Accepted
 *
 * list_type values for my-list:
 *   1 = tasks I created/assigned to others
 *   2 = my tasks in progress or stopped (status 2 or 3)
 *   3 = my pending tasks (status 1)
 *   4 = my accepted tasks (status 5)
 */
class TaskController extends BaseController
{
    /**
     * POST /api/v1/task/add
     * Fields: project_id, assigned_to, task, expected_delivery_date, expected_time, priority, type
     *         task_id (required if type=2 for subtask), description (optional)
     */
    public function actionAdd(): array
    {
        $post = Yii::$app->request->post();

        $projectId   = trim($post['project_id'] ?? '');
        $assignedTo  = trim($post['assigned_to'] ?? '');
        $taskName    = trim($post['task'] ?? '');
        $deliveryDate = trim($post['expected_delivery_date'] ?? '');
        $expectedTime = trim($post['expected_time'] ?? '');
        $priority    = trim($post['priority'] ?? '');
        $type        = trim($post['type'] ?? '');

        if ($projectId === '')    return $this->error('Project id is missing.');
        if ($assignedTo === '')   return $this->error('Assign developer id is missing.');
        if ($taskName === '')     return $this->error('Task is missing.');
        if ($deliveryDate === '') return $this->error('Expected Delivery date is missing.');
        if ($expectedTime === '') return $this->error('Expected Time is missing.');
        if ($priority === '')     return $this->error('Priority is missing.');
        if ($type === '')         return $this->error('type is missing.');

        $taskId = null;
        if ((int)$type === Task::TYPE_SUBTASK) {
            $taskId = trim($post['task_id'] ?? '');
            if ($taskId === '') {
                return $this->error('Task id is missing.');
            }
        }

        $userId = Yii::$app->user->id;

        // Duplicate check
        if (Task::find()->where(['task' => $taskName])->exists()) {
            return $this->error('Task Name already exist.');
        }

        $project  = Project::findOne((int)$projectId);
        $clientId = $project ? $project->client_id : null;

        $model                        = new Task();
        $model->parent_id             = ($type == Task::TYPE_SUBTASK && $taskId) ? (int)$taskId : 0;
        $model->project_id            = (int)$projectId;
        $model->client_id             = $clientId;
        $model->task                  = $taskName;
        $model->expected_time         = $expectedTime;
        $model->expected_delivery_date = date('Y-m-d H:i:s', strtotime($deliveryDate));
        $model->task_status           = ((int)$assignedTo === (int)$userId)
            ? Task::STATUS_ACCEPTED
            : Task::STATUS_PENDING;
        $model->created_by            = (int)$userId;
        $model->description           = trim($post['description'] ?? '') ?: null;
        $model->priority              = (int)$priority;
        $model->assigned_by           = (int)$userId;
        $model->assigned_to           = (int)$assignedTo;
        $model->is_read               = 1;
        $model->assign_type           = 2;
        $model->created_date          = date('Y-m-d H:i:s');
        $model->status                = 1;

        if ($model->save()) {
            return $this->success([], 'New Task Is Added Successfully !');
        }

        return $this->error('Task Name Already Exist.');
    }

    /**
     * POST /api/v1/task/my-list
     * Fields: list_type (required, 1-4), pagenumber, task_name, project_name,
     *         client_name, delivery_date, assigned_from_date, assigned_to_date
     */
    public function actionMyList(): array
    {
        $post     = Yii::$app->request->post();
        $listType = (int)trim($post['list_type'] ?? '0');
        $page     = max(1, (int)($post['pagenumber'] ?? 1));
        $userId   = Yii::$app->user->id;

        if ($listType === 0) {
            return $this->error('List type is missing.');
        }
        if (!in_array($listType, [1, 2, 3, 4])) {
            return $this->error('Invalid Type');
        }

        $query = Task::find()
            ->alias('t')
            ->joinWith(['project', 'client'])
            ->orderBy(['t.created_date' => SORT_DESC]);

        switch ($listType) {
            case 1:
                $query->where(['t.assigned_by' => $userId, 't.status' => 1]);
                break;
            case 2:
                $query->where(['t.status' => 1, 't.assigned_to' => $userId])
                    ->andWhere(['in', 't.task_status', [Task::STATUS_ACCEPTED, Task::STATUS_ONGOING]]);
                break;
            case 3:
                // Mark pending tasks as read
                Task::updateAll(['is_read' => 2], ['assigned_to' => $userId, 'task_status' => Task::STATUS_PENDING]);
                $query->where(['t.status' => 1, 't.assigned_to' => $userId, 't.task_status' => Task::STATUS_PENDING]);
                break;
            case 4:
                $query->where(['t.status' => 1, 't.assigned_to' => $userId, 't.task_status' => Task::STATUS_COMPLETED]);
                break;
        }

        if (!empty($post['task_name'])) {
            $query->andWhere(['like', 't.task', $post['task_name']]);
        }
        if (!empty($post['project_name'])) {
            $query->andWhere(['like', 'project.title', $post['project_name']]);
        }
        if (!empty($post['client_name'])) {
            $query->andWhere(['like', 'client.client_name', $post['client_name']]);
        }
        if (!empty($post['delivery_date'])) {
            $query->andWhere(['t.expected_delivery_date' => date('Y-m-d', strtotime($post['delivery_date']))]);
        }

        $fromDate = !empty($post['assigned_from_date']) ? date('Y-m-d', strtotime($post['assigned_from_date'])) : '';
        $toDate   = !empty($post['assigned_to_date']) ? date('Y-m-d', strtotime($post['assigned_to_date'])) : '';

        if ($fromDate !== '' && $toDate !== '') {
            $query->andWhere(['between', 'DATE(t.created_date)', $fromDate, $toDate]);
        } elseif ($fromDate !== '') {
            $query->andWhere(['DATE(t.created_date)' => $fromDate]);
        } elseif ($toDate !== '') {
            $query->andWhere(['DATE(t.created_date)' => $toDate]);
        }

        $count = $query->count();

        if ($count < 1) {
            return $this->error('No result found');
        }

        $pagination = $this->paginate((int)$count, $page);
        $results    = $query->offset($pagination['offset'])->limit($pagination['limit'])->all();

        if (empty($results)) {
            return $this->error('Page number not found');
        }

        $taskList = [];
        foreach ($results as $task) {
            $subtaskCount = Task::find()->where(['parent_id' => $task->id])->count();

            $taskList[] = [
                'task_id'                => $task->id,
                'project_id'             => $task->project_id,
                'task_name'              => $task->task,
                'task_count'             => $subtaskCount,
                'project_name'           => $task->project->title ?? '-',
                'client_name'            => $task->client->client_name ?? '-',
                'expected_time'          => $task->expected_time ?? '',
                'expected_delivery_date' => $task->expected_delivery_date
                    ? date('d-M-Y h:i a', strtotime($task->expected_delivery_date))
                    : '',
                'total_time'             => $this->secondsToHhmm((int)$task->total_time),
                'task_status'            => $task->task_status,
                'task_priority'          => $task->getPriorityLabel(),
                'assigned_by'            => $task->assignedBy->username ?? '-',
                'assigned_to'            => $task->assignedTo->username ?? '-',
                'description'            => $task->description ?? '',
                'assigned_date'          => $task->created_date
                    ? date('d-M-Y', strtotime($task->created_date))
                    : '',
            ];
        }

        return [
            'status'        => 1,
            'msg'           => 'Success',
            'totalpages'    => $pagination['totalPages'],
            'currentpageno' => $pagination['currentPage'],
            'my_tasklist'   => $taskList,
        ];
    }

    /**
     * POST /api/v1/task/accept
     * Fields: task_id, task_status (required)
     *
     * If task_status=5 (accepted), validates all subtasks are also complete first.
     */
    public function actionAccept(): array
    {
        $post       = Yii::$app->request->post();
        $taskId     = trim($post['task_id'] ?? '');
        $taskStatus = trim($post['task_status'] ?? '');

        if ($taskId === '')     return $this->error('Task id is missing.');
        if ($taskStatus === '') return $this->error('Task status is missing.');

        if ((int)$taskStatus === Task::STATUS_COMPLETED) {
            $totalSubtasks    = Task::find()->where(['parent_id' => $taskId])->count();
            $completeSubtasks = Task::find()->where(['parent_id' => $taskId, 'task_status' => Task::STATUS_COMPLETED])->count();

            if ($totalSubtasks !== $completeSubtasks) {
                return $this->error('Your subtasks are not completed fully.');
            }
        }

        $updated = Task::updateAll(['task_status' => (int)$taskStatus], ['id' => (int)$taskId]);

        if ($updated) {
            $msg = ((int)$taskStatus === Task::STATUS_COMPLETED)
                ? 'Your Task is Completed Successfully'
                : 'Your Task Status Updated Successfully';
            return $this->success([], $msg);
        }

        return $this->error('Your Task Accepting is failed');
    }

    /**
     * POST /api/v1/task/view
     * Fields: task_id (required)
     */
    public function actionView(): array
    {
        $post   = Yii::$app->request->post();
        $taskId = trim($post['task_id'] ?? '');

        if ($taskId === '') {
            return $this->error('Task id is missing.');
        }

        $task = Task::find()
            ->where(['id' => (int)$taskId])
            ->with(['project', 'client', 'assignedBy', 'assignedTo', 'createdBy'])
            ->one();

        if (!$task) {
            return $this->error('No result found');
        }

        $taskDetails = [
            'task_id'                => $task->id,
            'task_name'              => $task->task,
            'project_name'           => $task->project->title ?? '-',
            'client_name'            => $task->client->client_name ?? '-',
            'expected_time'          => $task->expected_time ?? '',
            'expected_delivery_date' => $task->expected_delivery_date
                ? date('d-M-Y h:i a', strtotime($task->expected_delivery_date))
                : '',
            'task_status'            => $task->getStatusLabel(),
            'task_priority'          => $task->getPriorityLabel(),
            'created_by'             => $task->createdBy->username ?? '-',
            'assigned_by'            => $task->assignedBy->username ?? '-',
            'assigned_to'            => $task->assignedTo->username ?? '-',
            'description'            => $task->description ?? '-',
            'created_date'           => $task->created_date
                ? date('d-M-Y', strtotime($task->created_date))
                : '',
            'total_time'             => $this->secondsToHhmm((int)$task->total_time),
        ];

        return [
            'status'        => 1,
            'msg'           => 'Success',
            'task_details'  => $taskDetails,
            'logs_result'   => [],
            'total_working' => $this->secondsToHhmm((int)$task->total_time),
        ];
    }

    /**
     * POST /api/v1/task/subtask-list
     * Fields: task_id (required), pagenumber (optional)
     */
    public function actionSubtaskList(): array
    {
        $post   = Yii::$app->request->post();
        $taskId = trim($post['task_id'] ?? '');
        $page   = max(1, (int)($post['pagenumber'] ?? 1));

        if ($taskId === '') {
            return $this->error('Task id is missing.');
        }

        $query = Task::find()
            ->where(['parent_id' => (int)$taskId])
            ->with(['project', 'client', 'assignedBy', 'assignedTo'])
            ->orderBy(['created_date' => SORT_DESC]);

        $count = $query->count();

        if ($count < 1) {
            return $this->error('No result found');
        }

        $pagination = $this->paginate((int)$count, $page);
        $results    = $query->offset($pagination['offset'])->limit($pagination['limit'])->all();

        $subtaskList = [];
        foreach ($results as $task) {
            $subtaskList[] = [
                'task_id'                => $task->id,
                'task_name'              => $task->task,
                'project_name'           => $task->project->title ?? '-',
                'client_name'            => $task->client->client_name ?? '-',
                'expected_time'          => $task->expected_time ?? '',
                'expected_delivery_date' => $task->expected_delivery_date
                    ? date('d-M-Y h:i a', strtotime($task->expected_delivery_date))
                    : '',
                'task_status'            => $task->task_status,
                'task_priority'          => $task->getPriorityLabel(),
                'assigned_by'            => $task->assignedBy->username ?? '-',
                'assigned_to'            => $task->assignedTo->username ?? '-',
                'description'            => $task->description ?? '',
            ];
        }

        return [
            'status'        => 1,
            'msg'           => 'Success',
            'totalpages'    => $pagination['totalPages'],
            'currentpageno' => $pagination['currentPage'],
            'subtask_list'  => $subtaskList,
        ];
    }

    /**
     * POST /api/v1/task/bulk-reassign
     * Fields: task_ids (JSON array), assigned_to (required)
     *
     * Migrated from Yii1 actionFullreassigntask()
     */
    public function actionBulkReassign(): array
    {
        $post       = Yii::$app->request->post();
        $taskIdsJson = trim($post['task_ids'] ?? '');
        $assignedTo  = trim($post['assigned_to'] ?? '');

        if ($taskIdsJson === '') return $this->error('Task IDs are missing.');
        if ($assignedTo === '')  return $this->error('Assigned developer ID is missing.');

        $taskIds = json_decode($taskIdsJson, true);
        if (!is_array($taskIds) || empty($taskIds)) {
            return $this->error('Task IDs list is empty.');
        }

        $userId  = Yii::$app->user->id;
        $updated = Task::updateAll(
            ['assigned_to' => (int)$assignedTo, 'assigned_by' => (int)$userId],
            ['in', 'id', $taskIds]
        );

        if ($updated > 0) {
            return $this->success(['updated' => $updated], 'Tasks reassigned successfully.');
        }

        return $this->error('Failed to reassign tasks. Try again.');
    }
}
