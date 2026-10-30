<?php

namespace backend\controllers;

use backend\models\DailyAttendance;
use backend\models\Developer;
use backend\models\LeaveRequests;
use backend\models\LeaveSettings;
use backend\models\User;
use common\components\UserAccess;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Hourly Permission requests (requirement #2).
 *
 * Reuses the leave_requests table with leave_type = Permission (2) and the
 * standard approve/reject (request_status) workflow. Each employee is allowed a
 * fixed number of permission hours per month (LeaveSettings::no_of_permissions,
 * default 2) — e.g. one 2-hour permission or two 1-hour permissions.
 */
class PermissionController extends Controller
{
    /** Monthly permission-hour cap from Leave Settings (default 2). */
    private function monthlyHourCap(): int
    {
        $settings = LeaveSettings::find()->where(['status' => 1])->one();
        return $settings && (int)$settings->no_of_permissions > 0 ? (int)$settings->no_of_permissions : 2;
    }

    private function currentDeveloper(User $user): ?Developer
    {
        return Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
    }

    /**
     * List permission requests for a month. Admins see all; developers see their
     * own. Pending requests carry Approve / Reject actions (admin only).
     */
    public function actionIndex(): string
    {
        /** @var User $user */
        $user    = Yii::$app->user->identity;
        $isAdmin = $user->isAdmin() || $user->isSubAdmin();

        // RBAC: 'permission/index' is a developer self-service default, so an
        // EXPLICIT grant (Access Permissions → Permission Requests → List) is
        // what promotes a developer to the all-employees listing.
        $canViewAll = $isAdmin || UserAccess::checkSpecificMenuAccess('permission/index');
        // 'permission/decide' is never defaulted — admins, or developers with the
        // Approve / Reject checkbox ticked.
        $canDecide  = $isAdmin || UserAccess::checkSpecificMenuAccess('permission/decide');

        $month     = Yii::$app->request->get('month', date('Y-m'));
        $monthStart = date('Y-m-01', strtotime($month . '-01'));
        $monthEnd   = date('Y-m-t', strtotime($month . '-01'));

        $query = LeaveRequests::find()
            ->where(['leave_type' => LeaveRequests::TYPE_PERMISSION, 'status' => 1])
            ->andWhere(['between', 'from_date', $monthStart, $monthEnd])
            ->orderBy(['from_date' => SORT_DESC, 'id' => SORT_DESC]);

        $selfDev = null;
        if (!$isAdmin) {
            $selfDev = $this->currentDeveloper($user);
            if (!$selfDev) {
                throw new NotFoundHttpException('Developer profile not found.');
            }
            if (!$canViewAll) {
                $query->andWhere(['developer_id' => $selfDev->id]);
            }
        }

        // Employee filter (Select2) — only for users who can see everyone's requests.
        $developers  = [];
        $developerId = 0;
        if ($canViewAll) {
            $developers  = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
            $developerId = (int)Yii::$app->request->get('developer_id', 0);
            if ($developerId) {
                $query->andWhere(['developer_id' => $developerId]);
            }
        }

        $requests = $query->all();

        return $this->render('index', compact(
            'requests', 'isAdmin', 'month', 'selfDev', 'canViewAll', 'canDecide', 'developers', 'developerId'
        ));
    }

    /**
     * Create a permission request (date + hours + reason). Enforces the monthly
     * hour cap across the employee's pending + approved permissions.
     */
    public function actionCreate(): string|Response
    {
        /** @var User $user */
        $user    = Yii::$app->user->identity;
        $isAdmin = $user->isAdmin() || $user->isSubAdmin();

        $model      = new LeaveRequests();
        $developers = $isAdmin
            ? Developer::find()->where(['status' => 1])->orderBy('developer_name')->all()
            : [];

        $selfDev = null;
        if (!$isAdmin) {
            $selfDev = $this->currentDeveloper($user);
            if (!$selfDev) {
                throw new NotFoundHttpException('Developer profile not found.');
            }
        }

        $cap = $this->monthlyHourCap();

        if (Yii::$app->request->isPost) {
            $post  = Yii::$app->request->post();
            $devId = $isAdmin ? (int)($post['developer_id'] ?? 0) : $selfDev->id;
            $date  = $post['permission_date'] ?? '';
            $date  = $date !== '' ? date('Y-m-d', strtotime($date)) : '';

            // Permission is hourly only ("1"/"2" hours), subject to the monthly
            // hour cap. Half-day was moved to the Leave module.
            $hours = (float)($post['permission_hours'] ?? 0);

            $model->developer_id       = $devId ?: null;
            $model->leave_type         = LeaveRequests::TYPE_PERMISSION;
            $model->from_date          = $date;
            $model->to_date            = $date;
            $model->no_of_days         = 0;
            $model->permission_hours   = $hours;
            $model->permission_session = LeaveRequests::SESSION_NONE;
            $model->reason             = trim($post['reason'] ?? '');
            $model->description        = trim($post['description'] ?? '');
            $model->request_status     = LeaveRequests::REQ_STATUS_REQUESTED;
            $model->requested_date     = date('Y-m-d H:i:s');
            $model->status             = 1;

            // Validate basics first.
            $ok = true;
            if (!$model->developer_id) {
                $model->addError('developer_id', 'Please select an employee.');
                $ok = false;
            }
            if ($date === '') {
                $model->addError('from_date', 'Please select a date.');
                $ok = false;
            }
            if (!in_array($hours, [1.0, 2.0], true)) {
                $model->addError('permission_hours', 'Permission must be 1 hour or 2 hours.');
                $ok = false;
            }
            if ($model->reason === '') {
                $model->addError('reason', 'Please enter a reason.');
                $ok = false;
            }

            // Monthly cap applies to permission hours.
            if ($ok && $date !== '') {
                $used = $this->usedHours($model->developer_id, $date);
                if ($used + $hours > $cap) {
                    $model->addError('permission_hours',
                        sprintf('Monthly permission limit is %d hour(s). Already used %s hour(s) this month.', $cap, rtrim(rtrim((string)$used, '0'), '.')));
                    $ok = false;
                }
            }

            if ($ok && $model->save()) {
                Yii::$app->session->setFlash('success', 'Permission request submitted.');
                return $this->redirect(['index', 'month' => date('Y-m', strtotime($date))]);
            }
        }

        return $this->render('create', compact('model', 'developers', 'isAdmin', 'selfDev', 'cap'));
    }

    /** Sum of non-rejected/cancelled permission hours for a developer in the date's month. */
    private function usedHours(int $devId, string $date, ?int $excludeId = null): float
    {
        $monthStart = date('Y-m-01', strtotime($date));
        $monthEnd   = date('Y-m-t', strtotime($date));
        $query = LeaveRequests::find()
            ->where([
                'leave_type'         => LeaveRequests::TYPE_PERMISSION,
                'developer_id'       => $devId,
                'status'             => 1,
                'permission_session' => LeaveRequests::SESSION_NONE, // hourly only; half-day is separate
            ])
            ->andWhere(['between', 'from_date', $monthStart, $monthEnd])
            ->andWhere(['NOT IN', 'request_status',
                [LeaveRequests::REQ_STATUS_REJECTED, LeaveRequests::REQ_STATUS_CANCELED]]);
        if ($excludeId !== null) {
            $query->andWhere(['!=', 'id', $excludeId]);
        }
        return (float)$query->sum('permission_hours');
    }

    /**
     * Approve / reject a permission request (admin). On approve, stamp the
     * matching daily-attendance row's is_permission flag for that date.
     */
    public function actionDecide(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['index']);
        }
        /** @var User $user */
        $user = Yii::$app->user->identity;
        // Admin / sub-admin, or a developer with the Approve / Reject permission.
        if (!($user->isAdmin() || $user->isSubAdmin())
            && !UserAccess::checkSpecificMenuAccess('permission/decide')) {
            throw new ForbiddenHttpException('You do not have permission to approve or reject permission requests.');
        }

        $model  = $this->findModel($id);
        $action = Yii::$app->request->post('action');

        switch ($action) {
            case 'approve':
                $model->request_status = LeaveRequests::REQ_STATUS_APPROVED;
                $model->save(false);
                $this->stampAttendancePermission($model);
                Yii::$app->session->setFlash('success', 'Permission approved.');
                break;
            case 'reject':
                $model->request_status = LeaveRequests::REQ_STATUS_REJECTED;
                $model->save(false);
                Yii::$app->session->setFlash('success', 'Permission rejected.');
                break;
            default:
                Yii::$app->session->setFlash('error', 'Invalid action.');
        }

        return $this->redirect(['index', 'month' => date('Y-m', strtotime($model->from_date))]);
    }

    /** Developer cancels their own pending permission request (soft-delete row). */
    public function actionCancel(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['index']);
        }
        /** @var User $user */
        $user  = Yii::$app->user->identity;
        $model = $this->findModel($id);

        if (!($user->isAdmin() || $user->isSubAdmin())) {
            $selfDev = $this->currentDeveloper($user);
            if (!$selfDev || $model->developer_id !== $selfDev->id) {
                throw new ForbiddenHttpException('You cannot cancel this request.');
            }
        }
        if ($model->request_status !== LeaveRequests::REQ_STATUS_REQUESTED) {
            Yii::$app->session->setFlash('error', 'Only pending requests can be cancelled.');
            return $this->redirect(['index']);
        }
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Permission request cancelled.');
        return $this->redirect(['index', 'month' => date('Y-m', strtotime($model->from_date))]);
    }

    /** Mark the matching daily-attendance row as a permission day, if present. */
    private function stampAttendancePermission(LeaveRequests $model): void
    {
        $row = DailyAttendance::find()
            ->where(['developer_id' => $model->developer_id, 'attendance_date' => $model->from_date, 'status' => 1])
            ->one();
        if ($row) {
            $row->is_permission = 1;
            $row->save(false);
        }
    }

    private function findModel(int $id): LeaveRequests
    {
        $model = LeaveRequests::findOne(['id' => $id, 'leave_type' => LeaveRequests::TYPE_PERMISSION]);
        if ($model === null) {
            throw new NotFoundHttpException('Permission request not found.');
        }
        return $model;
    }
}
