<?php

namespace backend\controllers;

use backend\models\DailyAttendance;
use backend\models\Developer;
use backend\models\LeaveRequests;
use backend\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend Leave request management.
 * Mirrors Yii1 LeaverequestsController behaviour.
 *
 * Status workflow:
 *   0 = Requested  → developer submitted, awaiting
 *   1 = Waiting    → transferred to admin for final approval (L1 approved)
 *   2 = Approved   → admin approved
 *   3 = Rejected   → admin rejected
 *   4 = Cancelled  → admin cancelled an approved request
 *
 * Developer cancel (own pending request) sets status=0 (soft-delete row).
 */
class LeaveController extends Controller
{
    /** Full pages get the HR module stylesheet and script. */
    public function render($view, $params = []): string
    {
        \backend\assets\HrAsset::register($this->getView());
        return parent::render($view, $params);
    }

    // ── Admin: all requests ─────────────────────────────────────────────────

    public function actionIndex(): string
    {
        // Permission (leave_type=2) is an hourly request handled by its own module —
        // this screen lists leaves only.
        $query = LeaveRequests::find()
            ->alias('lr')
            ->with('developer')
            ->where(['lr.status' => 1])
            ->andWhere(['not', ['lr.leave_type' => LeaveRequests::TYPE_PERMISSION]])
            ->orderBy(['lr.id' => SORT_DESC]);

        if ($devId = Yii::$app->request->get('developer_id')) {
            $query->andWhere(['lr.developer_id' => (int)$devId]);
        }
        $reqStatus = Yii::$app->request->get('request_status');
        if ($reqStatus !== null && $reqStatus !== '') {
            $query->andWhere(['lr.request_status' => (int)$reqStatus]);
        }
        if ($fromDate = Yii::$app->request->get('from_date')) {
            $query->andWhere(['>=', 'lr.from_date', $fromDate]);
        }
        if ($toDate = Yii::$app->request->get('to_date')) {
            $query->andWhere(['<=', 'lr.from_date', $toDate]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 25],
        ]);

        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('index', compact('dataProvider', 'developers'));
    }

    // ── Developer: own requests ─────────────────────────────────────────────

    public function actionMyRequests(): string|Response
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;

        // Admins don't have a developer profile — redirect to full list
        if ($user->isAdmin() || $user->isSubAdmin()) {
            return $this->redirect(['index']);
        }

        $developer = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
        if (!$developer) {
            throw new NotFoundHttpException('Developer profile not found.');
        }

        // Leaves only — hourly Permission requests live in their own module.
        $query = LeaveRequests::find()
            ->where(['developer_id' => $developer->id, 'status' => 1])
            ->andWhere(['not', ['leave_type' => LeaveRequests::TYPE_PERMISSION]])
            ->orderBy(['id' => SORT_DESC]);

        $reqStatus = Yii::$app->request->get('request_status');
        if ($reqStatus !== null && $reqStatus !== '') {
            $query->andWhere(['request_status' => (int)$reqStatus]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('my-requests', compact('dataProvider', 'developer'));
    }

    // ── Create ──────────────────────────────────────────────────────────────

    public function actionCreate(): string|Response
    {
        /** @var User $user */
        $user    = Yii::$app->user->identity;
        $isAdmin = $user->isAdmin() || $user->isSubAdmin();

        $model      = new LeaveRequests();
        $developers = $isAdmin
            ? Developer::find()->where(['status' => 1])->orderBy('developer_name')->all()
            : [];

        $selfDeveloper = null;
        if (!$isAdmin) {
            $selfDeveloper = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if (!$selfDeveloper) {
                throw new NotFoundHttpException('Developer profile not found.');
            }
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();

            if ($isAdmin) {
                $devId = $post['developer_id'] ?? '';
                $model->developer_id = ($devId !== '' && $devId !== null) ? (int)$devId : null;
            } else {
                $model->developer_id = $selfDeveloper->id;
            }
            // This form is leave-only; permission requests go through the Permission menu.
            $model->leave_type     = LeaveRequests::TYPE_LEAVE;
            $model->from_date      = $post['from_date'] ?? '';
            $model->reason         = trim($post['reason'] ?? '');
            $model->description    = trim($post['description'] ?? '');
            // All requests start as "Requested" and go through approval flow
            $model->request_status = LeaveRequests::REQ_STATUS_REQUESTED;
            $model->requested_date = date('Y-m-d H:i:s');
            $model->status         = 1;

            // Half Day vs Full Day. A half-day leave is a single date, 0.5 day, with a
            // morning/evening session; late check-in / early checkout on the worked half
            // are only excused once this request is APPROVED (handled in AttendanceProcessor).
            $isHalf  = (($post['duration'] ?? 'full') === 'half');
            $session = $isHalf ? (int)($post['permission_session'] ?? 0) : LeaveRequests::SESSION_NONE;
            $model->permission_session = $session;
            if ($isHalf) {
                $model->no_of_days = 0.5;
                $model->to_date    = $model->from_date; // single day
            } else {
                $model->no_of_days = (float)($post['no_of_days'] ?? 1);
                $model->to_date    = $post['to_date'] ?? '';
            }

            $valid = $model->validate();
            if ($isHalf && !in_array($session, [LeaveRequests::SESSION_MORNING, LeaveRequests::SESSION_EVENING], true)) {
                $model->addError('permission_session', 'Please choose Morning or Evening for the half-day leave.');
                $valid = false;
            }
            if ($valid) {
                // Format dates after validation (matching Yii1 flow)
                if (!empty($model->from_date)) {
                    $model->from_date = date('Y-m-d', strtotime($model->from_date));
                }
                if (!empty($model->to_date)) {
                    $model->to_date = date('Y-m-d', strtotime($model->to_date));
                }
                if ($model->save()) {
                    Yii::$app->session->setFlash('success', 'Successfully sent your leave request');
                    return $this->redirect($isAdmin ? ['index'] : ['my-requests']);
                }
            }
        }

        return $this->render('create', compact('model', 'developers', 'isAdmin', 'selfDeveloper'));
    }

    // ── Update ──────────────────────────────────────────────────────────────

    public function actionUpdate(int $id): string|Response
    {
        /** @var User $user */
        $user    = Yii::$app->user->identity;
        $isAdmin = $user->isAdmin() || $user->isSubAdmin();
        $model   = $this->findModel($id);

        // Only REQUESTED (0) requests can be edited (matches Yii1)
        if ($model->request_status !== LeaveRequests::REQ_STATUS_REQUESTED) {
            Yii::$app->session->setFlash('error', 'Only pending (Requested) leave can be edited.');
            return $this->redirect($isAdmin ? ['index'] : ['my-requests']);
        }

        // Developer can only edit their own requests
        if (!$isAdmin) {
            $selfDev = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if (!$selfDev || $model->developer_id !== $selfDev->id) {
                throw new ForbiddenHttpException('You cannot edit this request.');
            }
        }

        $developers = $isAdmin
            ? Developer::find()->where(['status' => 1])->orderBy('developer_name')->all()
            : [];

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();

            if ($isAdmin) {
                $devId = $post['developer_id'] ?? '';
                $model->developer_id = ($devId !== '' && $devId !== null) ? (int)$devId : null;
            }
            $model->leave_type  = LeaveRequests::TYPE_LEAVE; // leave-only form
            $model->from_date   = $post['from_date'] ?? $model->from_date;
            $model->reason      = trim($post['reason'] ?? '');
            $model->description = trim($post['description'] ?? '');

            $isHalf  = (($post['duration'] ?? 'full') === 'half');
            $session = $isHalf ? (int)($post['permission_session'] ?? 0) : LeaveRequests::SESSION_NONE;
            $model->permission_session = $session;
            if ($isHalf) {
                $model->no_of_days = 0.5;
                $model->to_date    = $model->from_date; // single day
            } else {
                $model->no_of_days = (float)($post['no_of_days'] ?? $model->no_of_days);
                $model->to_date    = $post['to_date'] ?? '';
            }

            $valid = $model->validate();
            if ($isHalf && !in_array($session, [LeaveRequests::SESSION_MORNING, LeaveRequests::SESSION_EVENING], true)) {
                $model->addError('permission_session', 'Please choose Morning or Evening for the half-day leave.');
                $valid = false;
            }
            if ($valid) {
                if (!empty($model->from_date)) {
                    $model->from_date = date('Y-m-d', strtotime($model->from_date));
                }
                if (!empty($model->to_date)) {
                    $model->to_date = date('Y-m-d', strtotime($model->to_date));
                }
                if ($model->save()) {
                    Yii::$app->session->setFlash('success', 'Leave request updated successfully.');
                    return $this->redirect($isAdmin ? ['index'] : ['my-requests']);
                }
            }
        }

        return $this->render('update', compact('model', 'developers', 'isAdmin'));
    }

    // ── View ─────────────────────────────────────────────────────────────────

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        // leave/view is a developer default: own requests only, unless they manage leave.
        \backend\components\HrAccess::ensureOwnOrManager($model->developer_id, ['leave/index', 'leave/update', 'leave/decide'], 'Leave request');
        return $this->render('view', ['model' => $model]);
    }

    // ── Admin: change status (approve / reject / transfer / cancel) ──────────

    /**
     * Change request status.
     * action values:
     *   approve          → 2  (admin approves)
     *   reject           → 3  (admin rejects)
     *   transfer         → 1  (transfer to admin for final approval, Yii1 "waiting")
     *   cancel-approved  → 4  (admin cancels an already-approved request)
     */
    public function actionDecide(int $id): Response
    {
        $isAjax = Yii::$app->request->isAjax;

        if (!Yii::$app->request->isPost) {
            return $isAjax
                ? $this->asJson(['success' => false, 'message' => 'Invalid request.'])
                : $this->redirect(['index']);
        }
        $leave       = $this->findModel($id);
        $action      = Yii::$app->request->post('action');
        $wasApproved = (int)$leave->request_status === LeaveRequests::REQ_STATUS_APPROVED;
        $isHalf      = $leave->isHalfDayLeave();

        $applyHalf = false;
        $revertHalf = false;

        switch ($action) {
            case 'approve':
                $leave->request_status = LeaveRequests::REQ_STATUS_APPROVED;
                $leave->approved_date  = date('Y-m-d H:i:s');
                $applyHalf = $isHalf;
                $message = 'Leave approved.';
                break;
            case 'reject':
                // Undo a previously-approved half-day (credit CL back, clear the stamp).
                $revertHalf = $wasApproved && $isHalf;
                $leave->request_status = LeaveRequests::REQ_STATUS_REJECTED;
                $leave->approved_date  = null;
                $message = 'Leave rejected.';
                break;
            case 'transfer':
                // Transfer to admin-level approval (Yii1 "Waiting / 1st level approved")
                $leave->request_status = LeaveRequests::REQ_STATUS_L1;
                $message = 'Leave transferred for admin approval.';
                break;
            case 'cancel-approved':
                // Admin cancels an already-approved request
                $revertHalf = $wasApproved && $isHalf;
                $leave->request_status = LeaveRequests::REQ_STATUS_CANCELED;
                $message = 'Approved leave cancelled.';
                break;
            default:
                if ($isAjax) {
                    return $this->asJson(['success' => false, 'message' => 'Invalid action.']);
                }
                Yii::$app->session->setFlash('error', 'Invalid action.');
                return $this->redirect(['index']);
        }

        $leave->save(false);
        if ($applyHalf) {
            $this->applyHalfDayLeave($leave);
        } elseif ($revertHalf) {
            $this->revertHalfDayLeave($leave);
        }

        // AJAX (inline status change from the list) — no page reload, no flash.
        if ($isAjax) {
            return $this->asJson([
                'success'        => true,
                'message'        => $message,
                'id'             => $leave->id,
                'request_status' => (int)$leave->request_status,
            ]);
        }

        Yii::$app->session->setFlash('success', $message);
        return $this->redirect(['index']);
    }

    /**
     * Approve a Half Day Leave: stamp the day as a half-day (morning/evening) in
     * dailyattendance, mark it an exception day (so the worked half's late check-in /
     * early checkout is excused), and debit 0.5 CL if the employee has a balance —
     * otherwise it stays a 0.5-day LOP. Mirrors the full-day leave CL settlement.
     */
    private function applyHalfDayLeave(LeaveRequests $model): void
    {
        $row = DailyAttendance::find()
            ->where(['developer_id' => $model->developer_id, 'attendance_date' => $model->from_date, 'status' => 1])
            ->one();
        if (!$row) {
            $row = new DailyAttendance();
            $row->developer_id    = $model->developer_id;
            $row->attendance_date = $model->from_date;
            $row->created_date    = date('Y-m-d H:i:s');
            $row->status          = 1;
        }
        $row->is_present    = 1;
        $row->is_permission = 1;            // excuses the worked half's late-LOP for the day
        $row->leave_type    = 8;            // Half Day (dailyattendance leave-type code)
        $row->morning_leave = (int)$model->permission_session === LeaveRequests::SESSION_MORNING ? 1 : 0;
        $row->evening_leave = (int)$model->permission_session === LeaveRequests::SESSION_EVENING ? 1 : 0;

        // CL covers the half day if a balance exists; else it remains 0.5-day LOP.
        if ((float)$row->cl_debited < 0.5) {
            $dev = Developer::findOne($model->developer_id);
            if ($dev && (float)$dev->cl_backup >= 0.5) {
                $dev->cl_backup = (float)$dev->cl_backup - 0.5;
                $dev->save(false);
                $row->cl_debited = 0.5;     // covered → excluded from LOP by LopCalculator::absentLop
            }
        }
        $row->save(false);
    }

    /** Reverse a previously-approved half-day leave: credit CL back and clear the stamp. */
    private function revertHalfDayLeave(LeaveRequests $model): void
    {
        $row = DailyAttendance::find()
            ->where(['developer_id' => $model->developer_id, 'attendance_date' => $model->from_date, 'status' => 1])
            ->one();
        if (!$row) {
            return;
        }
        if ((float)$row->cl_debited >= 0.5) {
            $dev = Developer::findOne($model->developer_id);
            if ($dev) {
                $dev->cl_backup = (float)$dev->cl_backup + 0.5;
                $dev->save(false);
            }
            $row->cl_debited = 0;
        }
        $row->is_permission = 0;
        $row->morning_leave = 0;
        $row->evening_leave = 0;
        $row->leave_type    = $row->is_present ? 1 : $row->leave_type; // back to Present if worked
        $row->save(false);
    }

    // ── Developer: cancel own pending request ────────────────────────────────

    /**
     * Developer cancels their own REQUESTED (status=0) leave.
     * Matches Yii1 changestatus value=5: sets status=0 (soft-delete row from all views).
     */
    public function actionCancel(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['my-requests']);
        }

        /** @var User $user */
        $user  = Yii::$app->user->identity;
        $model = $this->findModel($id);

        // Ownership check — only the developer who submitted can cancel
        if (!($user->isAdmin() || $user->isSubAdmin())) {
            $selfDev = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if (!$selfDev || $model->developer_id !== $selfDev->id) {
                throw new ForbiddenHttpException('You cannot cancel this request.');
            }
        }

        if ($model->request_status !== LeaveRequests::REQ_STATUS_REQUESTED) {
            Yii::$app->session->setFlash('error', 'Only pending (Requested) requests can be cancelled.');
            return $this->redirect(['my-requests']);
        }

        // Matches Yii1 value=5: soft-delete the row (status=0 hides from all views)
        $model->status = 0;
        $model->save(false);

        Yii::$app->session->setFlash('success', 'Leave request cancelled.');
        return $this->redirect(['my-requests']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function findModel(int $id): LeaveRequests
    {
        $model = LeaveRequests::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Leave request not found.');
        }
        return $model;
    }
}
