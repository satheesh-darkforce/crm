<?php

namespace backend\components;

use backend\models\Task;
use backend\models\User;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Per-task permissions, on top of the route checks in UserAccess.
 *
 * Yii1 let anyone with a task route act on any task id. These rules match
 * how the data shows tasks are actually used (time is logged by the
 * assignee; subtasks are created by the parent's assignee or creator):
 *
 *  - managers (admin / sub-admin): everything
 *  - view:   anyone involved in the task or in its parent task
 *  - edit / delete:  the task's creator or assigner
 *  - accept / start / stop the timer:  the assignee
 *  - status / complete / subtask / reassign:  assignee, creator or assigner
 */
class TaskAccess
{
    public static function isManager(): bool
    {
        $type = (int)(Yii::$app->user->identity->usertype ?? 0);
        return $type === User::TYPE_ADMIN || $type === User::TYPE_SUBADMIN;
    }

    private static function uid(): int
    {
        return (int)Yii::$app->user->id;
    }

    /** Directly involved: created, assigned or was assigned the task. */
    public static function isInvolved(Task $task): bool
    {
        $uid = self::uid();
        return in_array($uid, [(int)$task->created_by, (int)$task->assigned_by, (int)$task->assigned_to], true);
    }

    public static function canView(Task $task): bool
    {
        if (self::isManager() || self::isInvolved($task)) {
            return true;
        }
        $parent = (int)$task->parent_id ? Task::findOne((int)$task->parent_id) : null;
        return $parent !== null && self::isInvolved($parent);
    }

    public static function canEdit(Task $task): bool
    {
        $uid = self::uid();
        return self::isManager() || (int)$task->created_by === $uid || (int)$task->assigned_by === $uid;
    }

    /** Accept / start / stop — only the person doing the work. */
    public static function isAssignee(Task $task): bool
    {
        return (int)$task->assigned_to === self::uid();
    }

    public static function canManageWork(Task $task): bool
    {
        return self::isManager() || self::isInvolved($task);
    }

    /**
     * Load an active task the current user may see, or 404. Tasks the user is
     * not involved in also answer 404 so ids cannot be probed.
     */
    public static function findTask(int $id): Task
    {
        $task = Task::findOne(['id' => $id, 'status' => 1]);
        if (!$task || !self::canView($task)) {
            throw new NotFoundHttpException('Task not found.');
        }
        return $task;
    }

    /** Throw 403 unless $allowed — for actions on a task the user can see. */
    public static function ensure(bool $allowed, string $message = 'You cannot change this task.'): void
    {
        if (!$allowed) {
            throw new ForbiddenHttpException($message);
        }
    }
}
