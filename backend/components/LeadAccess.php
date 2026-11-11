<?php

namespace backend\components;

use backend\models\Lead;
use backend\models\LeadFollowers;
use backend\models\User;
use common\components\UserAccess;
use Yii;
use yii\db\ActiveQuery;
use yii\web\NotFoundHttpException;

/**
 * Row-level access rules for the Leads module (route-level access is
 * UserAccess::checkAccess, enforced by the base Controller).
 *
 * Mirrors Yii1: admins / sub-admins and anyone granted the "Manage Leads"
 * list (lead/index) see every lead; everyone else only sees leads they
 * created, are assigned to, or have a follow-up created by / assigned to them.
 */
class LeadAccess
{
    /** Admin or sub-admin. */
    public static function isManager(): bool
    {
        /** @var User|null $user */
        $user = Yii::$app->user->identity;
        return $user && ($user->isAdmin() || $user->isSubAdmin());
    }

    public static function canSeeAllLeads(): bool
    {
        return self::isManager() || UserAccess::checkAccess('lead/index');
    }

    /** Only admins / sub-admins may re-open a dropped lead (Yii1 dropped list). */
    public static function canReopen(): bool
    {
        return self::isManager();
    }

    /** Condition limiting `lead` rows to ones the user is involved in. */
    public static function involvedCondition(int $userId, string $alias = 'lead'): array
    {
        $viaFollowUp = LeadFollowers::find()
            ->select('lead_id')
            ->where(['status' => 1])
            ->andWhere(['or', ['assigned_to' => $userId], ['created_by' => $userId]]);

        return ['or',
            ["$alias.assigned_to" => $userId],
            ["$alias.created_by" => $userId],
            // The site-visit and quotation owners work on the lead too.
            ["$alias.site_visit_by" => $userId],
            ["$alias.quotation_by" => $userId],
            ['in', "$alias.id", $viaFollowUp],
        ];
    }

    public static function scopeToUser(ActiveQuery $query): ActiveQuery
    {
        if (!self::canSeeAllLeads()) {
            $query->andWhere(self::involvedCondition((int)Yii::$app->user->id));
        }
        return $query;
    }

    public static function canAccess(Lead $lead): bool
    {
        if (self::canSeeAllLeads()) {
            return true;
        }
        return Lead::find()->alias('lead')
            ->where(['lead.id' => $lead->id])
            ->andWhere(self::involvedCondition((int)Yii::$app->user->id))
            ->exists();
    }

    /**
     * Load a non-deleted lead the current user may access. Leads the user
     * cannot see are reported as not found rather than forbidden, so ids
     * of other people's leads are not disclosed.
     */
    public static function findLead(int $id): Lead
    {
        $lead = Lead::find()->where(['id' => $id])->andWhere(['!=', 'status', Lead::STATUS_DELETED])->one();
        if (!$lead || !self::canAccess($lead)) {
            throw new NotFoundHttpException('The requested lead does not exist.');
        }
        return $lead;
    }

    /** Active, non-client users a lead / follow-up can be assigned to (Yii1: status=1 AND usertype!=3). */
    public static function assignableUsers(): array
    {
        return User::find()
            ->select(['username', 'user_id'])
            ->where(['status' => 1])
            ->andWhere(['!=', 'usertype', User::TYPE_CLIENT])
            ->orderBy('username')
            ->indexBy('user_id')
            ->column();
    }
}
