<?php

namespace backend\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Task model
 *
 * Table: task
 *
 * task_status (aligned with Yii1):
 *   1 = Pending
 *   2 = Accepted
 *   3 = On Going
 *   4 = Re Open
 *   5 = Completed
 *   6 = Re Assigned (subtask created)
 *
 * priority:
 *   1 = High
 *   2 = Medium
 *   3 = Low
 *
 * assign_type:
 *   1 = Full
 *   2 = Partial
 *
 * @property int    $id
 * @property int    $parent_id
 * @property int    $project_id
 * @property int    $client_id
 * @property string $task
 * @property int    $expected_time      seconds
 * @property string $expected_delivery_date
 * @property string $start_time
 * @property string $end_time
 * @property int    $task_status
 * @property string $completed_date
 * @property int    $total_time         seconds
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
class Task extends ActiveRecord
{
    const STATUS_PENDING    = 1;
    const STATUS_ACCEPTED   = 2;
    const STATUS_ONGOING    = 3;
    const STATUS_REOPEN     = 4;
    const STATUS_COMPLETED  = 5;
    const STATUS_REASSIGNED = 6;

    const PRIORITY_HIGH   = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_LOW    = 3;

    const TYPE_TASK    = 1;
    const TYPE_SUBTASK = 2;

    const ASSIGN_TYPE_FULL    = 1;
    const ASSIGN_TYPE_PARTIAL = 2;

    public static function tableName(): string
    {
        return 'task';
    }

    public function rules(): array
    {
        return [
            [['task', 'description'], 'trim'],
            [['task', 'project_id', 'assigned_to', 'status', 'priority', 'expected_delivery_date', 'expected_time', 'assign_type'], 'required'],
            [['parent_id', 'project_id', 'client_id', 'task_status',
              'created_by', 'priority', 'assigned_by', 'assigned_to', 'is_read', 'assign_type', 'status'], 'integer'],
            [['total_time'], 'integer', 'min' => 0],
            [['expected_time'], 'integer', 'min' => 60, 'tooSmall' => 'Hour Of Work Required must be at least 1 minute.'],
            [['description'], 'string'],
            [['expected_delivery_date', 'start_time', 'end_time', 'completed_date', 'created_date'], 'safe'],
            [['task'], 'string', 'max' => 255],
            [['task'], 'unique', 'message' => 'A task with this name already exists.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'task'                   => 'Task Name',
            'project_id'             => 'Project',
            'client_id'              => 'Client',
            'assigned_to'            => 'Assigned To',
            'expected_delivery_date' => 'Deadline',
            'expected_time'          => 'Expected Hours',
            'priority'               => 'Priority',
            'task_status'            => 'Status',
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
        return $this->hasOne(Task::class, ['id' => 'parent_id']);
    }

    public function getSubtasks(): ActiveQuery
    {
        return $this->hasMany(Task::class, ['parent_id' => 'id']);
    }

    public function getHistory(): ActiveQuery
    {
        return $this->hasMany(TaskHistory::class, ['task_id' => 'id']);
    }

    public function getLogs(): ActiveQuery
    {
        return $this->hasMany(TaskLogs::class, ['task_id' => 'id']);
    }

    // ── Label helpers ──────────────────────────────────────────────────────────

    public function getStatusLabel(): string
    {
        return match ((int)$this->task_status) {
            self::STATUS_PENDING    => 'Pending',
            self::STATUS_ACCEPTED   => 'Accepted',
            self::STATUS_ONGOING    => 'On Going',
            self::STATUS_REOPEN     => 'Re Open',
            self::STATUS_COMPLETED  => 'Completed',
            self::STATUS_REASSIGNED => 'Re Assigned',
            default                 => 'Unknown',
        };
    }

    public function getStatusBadge(): string
    {
        return match ((int)$this->task_status) {
            self::STATUS_PENDING    => 'secondary',
            self::STATUS_ACCEPTED   => 'info',
            self::STATUS_ONGOING    => 'primary',
            self::STATUS_REOPEN     => 'warning',
            self::STATUS_COMPLETED  => 'success',
            self::STATUS_REASSIGNED => 'dark',
            default                 => 'secondary',
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

    public function getAssignTypeLabel(): string
    {
        return match ((int)$this->assign_type) {
            self::ASSIGN_TYPE_FULL    => 'Full',
            self::ASSIGN_TYPE_PARTIAL => 'Partial',
            default                   => '—',
        };
    }

    // ── Time formatters ────────────────────────────────────────────────────────

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
        $done = (int)$this->task_status === self::STATUS_COMPLETED;
        return !$done && strtotime($this->expected_delivery_date) < time();
    }

    // ── Named query scopes (Yii2 equivalents of Yii1 model methods) ───────────

    /**
     * Completed tasks scope.
     * Yii1 equivalent: completedtasks()
     * Admin: all root completed tasks. Developer: own completed tasks.
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
     * New / unread pending tasks scope.
     * Yii1 equivalent: newtasks()
     * Returns tasks with task_status=PENDING assigned to the given user.
     */
    public static function newTasksQuery(int $userId): ActiveQuery
    {
        return static::find()
            ->alias('t')
            ->with(['project', 'assignedTo', 'createdBy'])
            ->where(['t.status' => 1, 't.assigned_to' => $userId, 't.task_status' => self::STATUS_PENDING])
            ->orderBy(['t.created_date' => SORT_DESC]);
    }

    /**
     * Tasks created in the current calendar month.
     * Yii1 equivalent: currentmonth()
     */
    public static function currentMonthQuery(): ActiveQuery
    {
        return static::find()
            ->alias('t')
            ->with(['project', 'assignedTo', 'createdBy'])
            ->where(['t.status' => 1, 't.parent_id' => 0])
            ->andWhere('MONTH(t.created_date) = MONTH(NOW()) AND YEAR(t.created_date) = YEAR(CURRENT_DATE())')
            ->orderBy(['t.created_date' => SORT_DESC]);
    }

    /**
     * Tasks from the previous calendar month, with optional date range override.
     * Yii1 equivalent: previousmonth()
     */
    public static function previousMonthQuery(?string $fromDate = null, ?string $toDate = null): ActiveQuery
    {
        $q = static::find()
            ->alias('t')
            ->with(['project', 'assignedTo', 'createdBy'])
            ->where(['t.status' => 1, 't.parent_id' => 0])
            ->andWhere('YEAR(t.created_date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(t.created_date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)')
            ->orderBy(['t.created_date' => SORT_DESC]);

        if ($fromDate && !$toDate) {
            $q->andWhere(['>=', 'DATE(t.created_date)', $fromDate]);
        } elseif ($toDate && !$fromDate) {
            $q->andWhere(['<=', 'DATE(t.created_date)', $toDate]);
        } elseif ($fromDate && $toDate) {
            $q->andWhere(['between', 'DATE(t.created_date)', $fromDate, $toDate]);
        }

        return $q;
    }

    /**
     * Subtasks for a given parent task id.
     * Yii1 equivalent: sub_list($id)
     */
    public static function subListQuery(int $parentId): ActiveQuery
    {
        return static::find()
            ->alias('t')
            ->with(['assignedTo', 'createdBy'])
            ->where(['t.parent_id' => $parentId])
            ->orderBy(['t.created_date' => SORT_DESC]);
    }

    /**
     * Tasks assigned BY a user (for the "Assigned Tasks" listing).
     * Yii1 equivalent: assignedtasks()
     * Admin sees all. Developer sees tasks they assigned to others.
     */
    public static function assignedTasksQuery(int $userId, bool $isAdmin): ActiveQuery
    {
        $q = static::find()
            ->alias('t')
            ->with(['project', 'assignedTo', 'createdBy'])
            ->where(['t.status' => 1, 't.parent_id' => 0])
            ->orderBy(['t.created_date' => SORT_DESC]);

        if ($isAdmin) {
            // Admin: tasks that have been assigned (assigned_to is set)
            $q->andWhere(['is not', 't.assigned_to', null]);
        } else {
            // Developer: tasks they personally assigned to others
            $q->andWhere(['t.assigned_by' => $userId]);
        }

        return $q;
    }

    /**
     * Currently in-progress tasks (On Going status).
     * Yii1 equivalent: ongoing() — task_status=3 (On Going).
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
