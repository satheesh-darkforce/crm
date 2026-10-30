<?php

namespace backend\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * PM Task model — uses the same `task` table as Task but scoped to PM projects.
 *
 * task_status:
 *   1 = Pending
 *   2 = Accepted
 *   3 = On Going (started)
 *   4 = Completed
 *   5 = Closed / Accepted by admin
 *
 * Note on Yii1 mapping:
 *   Yii1 used 5=Completed, 6=Re-Assigned.
 *   Yii2 uses sequential 1-5. See data migration m260404_000003.
 *
 * priority:
 *   1 = High   2 = Medium   3 = Low
 *
 * assign_type:
 *   1 = Full   2 = Partial
 *
 * @property int    $id
 * @property int    $parent_id
 * @property int    $project_id
 * @property int    $client_id
 * @property string $task
 * @property int    $expected_time   (seconds)
 * @property string $expected_delivery_date
 * @property int    $task_status
 * @property string $completed_date
 * @property int    $total_time      (seconds)
 * @property int    $created_by
 * @property string $description
 * @property int    $priority
 * @property int    $assigned_by
 * @property int    $assigned_to
 * @property int    $is_read
 * @property int    $assign_type
 * @property string $created_date
 * @property int    $status
 */
class PmTask extends ActiveRecord
{
    const STATUS_PENDING   = 1;
    const STATUS_ACCEPTED  = 2;
    const STATUS_ONGOING   = 3;
    const STATUS_COMPLETED = 4;
    const STATUS_CLOSED    = 5;

    const PRIORITY_HIGH   = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_LOW    = 3;

    const ASSIGN_TYPE_FULL    = 1;
    const ASSIGN_TYPE_PARTIAL = 2;

    public static function tableName(): string
    {
        return 'task';
    }

    public function rules(): array
    {
        return [
            [['project_id', 'task', 'expected_delivery_date', 'priority', 'assign_type'], 'required'],
            [['parent_id', 'project_id', 'client_id', 'task_status', 'total_time',
              'created_by', 'priority', 'assigned_by', 'assigned_to', 'is_read',
              'assign_type', 'status'], 'integer'],
            [['description'], 'string'],
            [['expected_delivery_date', 'completed_date', 'created_date'], 'safe'],
            [['task'], 'string', 'max' => 255],
            [['expected_time', 'total_time'], 'integer', 'min' => 0],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'task'                   => 'Task Name',
            'project_id'             => 'Project',
            'client_id'              => 'Client',
            'assigned_to'            => 'Assigned To',
            'expected_delivery_date' => 'Expected Delivery Date',
            'expected_time'          => 'Hour Of Work Required',
            'priority'               => 'Priority',
            'task_status'            => 'Task Status',
            'assign_type'            => 'Assign Type',
            'parent_id'              => 'Task Type',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function getProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getClient(): ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getAssignedTo(): ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'assigned_to']);
    }

    public function getAssignedBy(): ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'assigned_by']);
    }

    public function getCreatedBy(): ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getParent(): ActiveQuery
    {
        return $this->hasOne(PmTask::class, ['id' => 'parent_id']);
    }

    public function getSubtasks(): ActiveQuery
    {
        return $this->hasMany(PmTask::class, ['parent_id' => 'id']);
    }

    public function getLogs(): ActiveQuery
    {
        return $this->hasMany(TaskLogs::class, ['task_id' => 'id']);
    }

    // ── Label / format helpers ─────────────────────────────────────────────────

    public function getStatusLabel(): string
    {
        return match ((int)$this->task_status) {
            self::STATUS_PENDING   => 'Pending',
            self::STATUS_ACCEPTED  => 'Accepted',
            self::STATUS_ONGOING   => 'On Going',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CLOSED    => 'Closed',
            default                => 'Unknown',
        };
    }

    public function getStatusBadge(): string
    {
        return match ((int)$this->task_status) {
            self::STATUS_PENDING   => 'secondary',
            self::STATUS_ACCEPTED  => 'primary',
            self::STATUS_ONGOING   => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CLOSED    => 'info',
            default                => 'secondary',
        };
    }

    public function getPriorityLabel(): string
    {
        return match ((int)$this->priority) {
            self::PRIORITY_HIGH   => 'High',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_LOW    => 'Low',
            default               => 'Normal',
        };
    }

    public function getPriorityBadge(): string
    {
        return match ((int)$this->priority) {
            self::PRIORITY_HIGH   => 'danger',
            self::PRIORITY_MEDIUM => 'warning',
            self::PRIORITY_LOW    => 'info',
            default               => 'secondary',
        };
    }

    public function getExpectedTimeFormatted(): string
    {
        $s = (int)$this->expected_time;
        return $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
    }

    public function getTotalTimeFormatted(): string
    {
        $s = (int)$this->total_time;
        return $s > 0 ? sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)) : '—';
    }

    public function isOverdue(): bool
    {
        if (!$this->expected_delivery_date) {
            return false;
        }
        $done = in_array((int)$this->task_status, [self::STATUS_COMPLETED, self::STATUS_CLOSED]);
        return !$done && strtotime($this->expected_delivery_date) < time();
    }

    // ── Named query scopes (Yii1 equivalents) ─────────────────────────────────

    /**
     * Unassigned tasks for a project (assigned_to IS NULL).
     * Yii1 equivalent: listTasksUnassigned($projectId)
     */
    public static function listTasksUnassigned(?int $projectId = null, int $userId = 0, bool $isAdmin = true): ActiveQuery
    {
        $q = static::find()
            ->alias('t')
            ->with(['project', 'assignedTo'])
            ->where(['t.status' => 1, 't.parent_id' => 0])
            ->andWhere(['t.task_status' => [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_ONGOING]])
            ->andWhere(['t.assigned_to' => null])
            ->orderBy(['t.created_date' => SORT_DESC]);

        if ($projectId) {
            $q->andWhere(['t.project_id' => $projectId]);
        }
        if (!$isAdmin && $userId) {
            $q->andWhere(['t.created_by' => $userId]);
        }

        return $q;
    }

    /**
     * Assigned tasks for a project (assigned_to IS NOT NULL).
     * Yii1 equivalent: listTasksAssigned($projectId)
     */
    public static function listTasksAssigned(?int $projectId = null, int $userId = 0, bool $isAdmin = true): ActiveQuery
    {
        $q = static::find()
            ->alias('t')
            ->with(['project', 'assignedTo'])
            ->where(['t.status' => 1, 't.parent_id' => 0])
            ->andWhere(['t.task_status' => [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_ONGOING]])
            ->andWhere(['is not', 't.assigned_to', null])
            ->orderBy(['t.created_date' => SORT_DESC]);

        if ($projectId) {
            $q->andWhere(['t.project_id' => $projectId]);
        }
        if (!$isAdmin && $userId) {
            $q->andWhere(['t.created_by' => $userId]);
        }

        return $q;
    }

    /**
     * Get project title by ID.
     * Yii1 equivalent: getProjectName($projectId)
     */
    public static function getProjectName(int $projectId): ?string
    {
        $row = Yii::$app->db->createCommand(
            'SELECT title FROM project WHERE id = :id LIMIT 1',
            [':id' => $projectId]
        )->queryOne();
        return $row ? $row['title'] : null;
    }

    /**
     * Assign a task to a developer.
     * Yii1 equivalent: assignTask($taskId, $devId)
     *
     * @return int number of rows affected
     */
    public static function assignTask(int $taskId, int $devId): int
    {
        return Yii::$app->db->createCommand(
            'UPDATE task SET assigned_to = :dev WHERE id = :id',
            [':dev' => $devId, ':id' => $taskId]
        )->execute();
    }

    /**
     * Get task status breakdown counts for a project.
     * Yii1 equivalent: gettaskstatus($projectId)
     *
     * Returns: [['task_status' => N, 'total' => N], ...]
     */
    public static function getTaskStatusBreakdown(int $projectId): array
    {
        return Yii::$app->db->createCommand(
            'SELECT task_status, COUNT(*) AS total FROM task WHERE project_id = :pid AND status = 1 GROUP BY task_status',
            [':pid' => $projectId]
        )->queryAll();
    }

    /**
     * Get assigned/unassigned counts for a project.
     * Yii1 equivalent: getassignedStatus($projectId)
     *
     * Returns: [['unassigned_count' => N, 'assigned_count' => N]]
     */
    public static function getAssignedStatus(int $projectId): array
    {
        return Yii::$app->db->createCommand(
            'SELECT
                SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) AS unassigned_count,
                SUM(CASE WHEN assigned_to IS NOT NULL THEN 1 ELSE 0 END) AS assigned_count
             FROM task WHERE project_id = :pid AND status = 1',
            [':pid' => $projectId]
        )->queryAll();
    }

    /**
     * Completed PM tasks scope.
     * Yii1 equivalent: completedtasks()
     */
    public static function completedTasksQuery(int $userId, bool $isAdmin): ActiveQuery
    {
        $q = static::find()
            ->alias('t')
            ->with(['project', 'assignedTo', 'createdBy'])
            ->where(['t.status' => 1, 't.parent_id' => 0, 't.task_status' => self::STATUS_COMPLETED])
            ->orderBy(['t.created_date' => SORT_DESC]);

        if (!$isAdmin) {
            $q->andWhere(['or', ['t.created_by' => $userId], ['t.assigned_to' => $userId]]);
        }

        return $q;
    }

    /**
     * My PM tasks (assigned to current user, not completed/closed).
     */
    public static function myTasksQuery(int $userId): ActiveQuery
    {
        return static::find()
            ->alias('t')
            ->with(['project', 'createdBy'])
            ->where(['t.status' => 1, 't.assigned_to' => $userId])
            ->andWhere(['not in', 't.task_status', [self::STATUS_COMPLETED, self::STATUS_CLOSED]])
            ->orderBy(['t.created_date' => SORT_DESC]);
    }

    /**
     * Tasks in progress (On Going status).
     * Yii1 equivalent: ongoing()
     */
    public static function ongoingQuery(): ActiveQuery
    {
        return static::find()
            ->alias('t')
            ->with(['project', 'assignedTo', 'createdBy'])
            ->where(['t.status' => 1, 't.task_status' => self::STATUS_ONGOING])
            ->orderBy(['t.created_date' => SORT_DESC]);
    }
}
