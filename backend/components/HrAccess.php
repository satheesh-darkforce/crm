<?php

namespace backend\components;

use backend\models\User;
use common\components\UserAccess;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Record-level access for HR data (payslips, profiles, leave, attendance).
 *
 * Several HR routes are in every developer's default permissions so people
 * can see their *own* payslip, leave and profile. Those defaults must not let
 * them open other employees' records by changing an id. A user may see all
 * records only when they are admin / sub-admin, or a developer who was
 * explicitly granted one of the area's management routes (defaults never
 * count — see UserAccess::checkSpecificMenuAccess()).
 */
class HrAccess
{
    /** Admin or sub-admin. */
    public static function isManager(): bool
    {
        /** @var User|null $u */
        $u = Yii::$app->user->identity;
        return $u && ($u->isAdmin() || $u->isSubAdmin());
    }

    /** Manager, or explicitly granted any of $routes (not via the developer defaults). */
    public static function canManage(array $routes): bool
    {
        if (static::isManager()) {
            return true;
        }
        foreach ($routes as $r) {
            if (UserAccess::checkSpecificMenuAccess($r)) {
                return true;
            }
        }
        return false;
    }

    public static function myDeveloperId(): ?int
    {
        $u = Yii::$app->user->identity;
        $id = $u ? $u->getDeveloperId() : null;
        return $id ? (int)$id : null;
    }

    public static function isOwn($developerId): bool
    {
        $mine = static::myDeveloperId();
        return $mine !== null && (int)$developerId === $mine;
    }

    /**
     * 404 unless the record belongs to the current user or they manage the area.
     * (404, not 403, so ids of other employees' records are not confirmed.)
     */
    public static function ensureOwnOrManager($developerId, array $manageRoutes, string $what = 'Record'): void
    {
        if (!static::isOwn($developerId) && !static::canManage($manageRoutes)) {
            throw new NotFoundHttpException($what . ' not found.');
        }
    }
}
