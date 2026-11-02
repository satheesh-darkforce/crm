<?php

namespace backend\controllers;

use common\components\AttendanceProcessor;
use common\components\UserAccess;
use backend\components\PayslipGenerator;
use backend\components\MonthlyClCreditor;
use backend\models\CronJobCallLogs;
use backend\models\LoginForm;
use backend\models\User;
use Yii;
use yii\data\ArrayDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller as BaseController;
use yii\web\Response;

/**
 * Backend SiteController — handles login/logout/dashboard for all user roles.
 */
class SiteController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        // 'dailyattendancecall' is the cron endpoint hit by
                        // the server crontab — must be reachable without an
                        // authenticated session (Yii1 parity).
                        'actions' => ['login', 'error', 'dailyattendancecall', 'monthlypayslipcall', 'monthlyclcall'],
                        'allow'   => true,
                    ],
                    [
                        // Dashboard is gated by UserAccess::checkAccess('site/index')
                        // — mirroring Yii1 admin/dashboard, which requires an
                        // explicit permission for developers/sub-admins.
                        'actions' => ['index'],
                        'allow'   => true,
                        'roles'   => ['@'],
                        'matchCallback' => function () {
                            return \common\components\UserAccess::checkAccess('site/index');
                        },
                    ],
                    [
                        'actions' => ['logout', 'change-password'],
                        'allow'   => true,
                        'roles'   => ['@'],
                    ],
                ],
                'denyCallback' => function () {
                    if (Yii::$app->user->isGuest) {
                        return Yii::$app->response->redirect(['/site/login']);
                    }
                    // Authenticated but without dashboard permission — land
                    // them on their role-appropriate page instead of a 403.
                    return Yii::$app->response->redirect($this->getDefaultRoute());
                },
            ],
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    public function actions(): array
    {
        return [
            'error' => [
                'class' => \yii\web\ErrorAction::class,
            ],
        ];
    }

    /**
     * Dashboard — mirrors the Yii1 admin/dashboard exactly.
     */
    public function actionIndex(): string
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $db   = Yii::$app->db;

        // ── Domain expiry count (30 days) ───────────────────────────────────
        $domainExpiryCount = (int) $db->createCommand(
            "SELECT COUNT(*) FROM domains WHERE status=1 AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)"
        )->queryScalar();

        // ── Current month AMC renewals due ──────────────────────────────────
        // Active AMCs whose current end date (latest renewal end, else the
        // warranty end date) falls in the current month. Mirrors amc/renew-list.
        $amcDueCount = !UserAccess::isAmcEnabled() ? 0 : (int) $db->createCommand("
            SELECT COUNT(*) FROM amc a
            WHERE a.status = 1 AND a.amc_status = 1
              AND YEAR(COALESCE((SELECT MAX(r.end_date) FROM amc_renew r WHERE r.amc_id = a.id AND r.status = 1), a.end_date)) = YEAR(CURDATE())
              AND MONTH(COALESCE((SELECT MAX(r.end_date) FROM amc_renew r WHERE r.amc_id = a.id AND r.status = 1), a.end_date)) = MONTH(CURDATE())
        ")->queryScalar();

        // ── Ongoing tasks (task_status = 3 / On Going) ──────────────────────
        // Only include tasks whose assignee is still an active user (soft-deleted
        // developer accounts have user.status = 0 and must not appear here).
        $ongoingRows = $db->createCommand("
            SELECT t.id, t.task, t.expected_time, t.assigned_to,
                   u_assign.username AS assigned_to_name,
                   u_by.username     AS assigned_by_name,
                   p.title           AS project_title,
                   (SELECT tl.start_time FROM task_logs tl
                    WHERE tl.task_id = t.id AND tl.user_id = t.assigned_to AND tl.end_time IS NULL
                    ORDER BY tl.id DESC LIMIT 1) AS start_time
            FROM task t
            INNER JOIN user   u_assign ON u_assign.user_id = t.assigned_to AND u_assign.status = 1
            LEFT JOIN  user   u_by     ON u_by.user_id     = t.assigned_by
            LEFT JOIN  project p       ON p.id             = t.project_id
            WHERE t.status = 1 AND t.task_status = 3
            ORDER BY t.created_date DESC
        ")->queryAll();
        $ongoingProvider = new ArrayDataProvider([
            'allModels'  => $ongoingRows,
            'pagination' => ['pageSize' => 20, 'pageParam' => 'ongoing-page'],
        ]);

        // ── Current month projects (work_report grouped by project) ─────────
        $curMonthType = Yii::$app->request->get('cur_project_type', '');
        $curTypeFilter = ($curMonthType !== '' && (int)$curMonthType > 0)
            ? ' AND p.project_type = ' . (int)$curMonthType
            : '';
        $curMonthRows = $db->createCommand("
            SELECT p.id AS project_id, p.title AS project_title,
                   p.total_estimation_hours, p.total_estimation_cost, p.project_type,
                   c.client_name,
                   SUM(wr.total_time) AS total_seconds
            FROM work_report wr
            INNER JOIN project p ON p.id = wr.project_id
            INNER JOIN client  c ON c.id = p.client_id
            WHERE wr.status = 1
              AND YEAR(wr.date)  = YEAR(CURDATE())
              AND MONTH(wr.date) = MONTH(CURDATE())
              $curTypeFilter
            GROUP BY wr.project_id
            ORDER BY MAX(wr.work_id) DESC
        ")->queryAll();
        $curMonthTotal = array_sum(array_column($curMonthRows, 'total_seconds'));
        $curMonthProvider = new ArrayDataProvider([
            'allModels'  => $curMonthRows,
            'pagination' => ['pageSize' => 20, 'pageParam' => 'cur-page'],
        ]);

        // ── Previous month projects (filterable by date range + project type)
        $fromDate        = Yii::$app->request->get('from_date');
        $toDate          = Yii::$app->request->get('to_date');
        $prevProjectType = Yii::$app->request->get('prev_project_type', '');

        if ($fromDate && $toDate) {
            $prevCond   = "wr.date BETWEEN :from AND :to";
            $prevParams = [':from' => $fromDate, ':to' => $toDate];
        } else {
            $prevCond   = "YEAR(wr.date) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(wr.date) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
            $prevParams = [];
        }
        $prevTypeFilter = ($prevProjectType !== '' && (int)$prevProjectType > 0)
            ? ' AND p.project_type = ' . (int)$prevProjectType
            : '';
        $prevMonthRows = $db->createCommand("
            SELECT p.id AS project_id, p.title AS project_title,
                   p.total_estimation_hours, p.total_estimation_cost, p.project_type,
                   c.client_name,
                   SUM(wr.total_time) AS total_seconds
            FROM work_report wr
            INNER JOIN project p ON p.id = wr.project_id
            INNER JOIN client  c ON c.id = p.client_id
            WHERE wr.status = 1 AND $prevCond $prevTypeFilter
            GROUP BY wr.project_id
            ORDER BY p.title ASC
        ", $prevParams)->queryAll();
        $prevMonthTotal = array_sum(array_column($prevMonthRows, 'total_seconds'));
        $prevMonthProvider = new ArrayDataProvider([
            'allModels'  => $prevMonthRows,
            'pagination' => ['pageSize' => 20, 'pageParam' => 'prev-page'],
        ]);

        // ── Previous month tasks (filterable by date range) ──────────────────
        $taskFrom = Yii::$app->request->get('task_from_date');
        $taskTo   = Yii::$app->request->get('task_to_date');

        if ($taskFrom && $taskTo) {
            $taskCond   = "t.created_date BETWEEN :tfrom AND :tto";
            $taskParams = [':tfrom' => $taskFrom, ':tto' => $taskTo . ' 23:59:59'];
        } else {
            $taskCond   = "YEAR(t.created_date) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(t.created_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
            $taskParams = [];
        }
        // Filter out tasks where the creator's user account has been soft-deleted
        // (status=0) so the dashboard reflects only active employees' tasks.
        $prevTaskRows = $db->createCommand("
            SELECT t.id, t.task, t.total_time, t.created_date,
                   u.username AS created_by_name
            FROM task t
            INNER JOIN user u ON u.user_id = t.created_by AND u.status = 1
            WHERE t.status = 1 AND t.parent_id = 0 AND $taskCond
            ORDER BY t.created_date DESC
        ", $taskParams)->queryAll();
        $prevTaskProvider = new ArrayDataProvider([
            'allModels'  => $prevTaskRows,
            'pagination' => ['pageSize' => 20, 'pageParam' => 'task-page'],
        ]);

        return $this->render('index', [
            'user'              => $user,
            'domainExpiryCount' => $domainExpiryCount,
            'amcDueCount'       => $amcDueCount,
            'ongoingProvider'   => $ongoingProvider,
            'curMonthProvider'  => $curMonthProvider,
            'curMonthTotal'     => $curMonthTotal,
            'curMonthType'      => $curMonthType,
            'prevMonthProvider' => $prevMonthProvider,
            'prevMonthTotal'    => $prevMonthTotal,
            'prevProjectType'   => $prevProjectType,
            'prevTaskProvider'  => $prevTaskProvider,
            'fromDate'          => $fromDate,
            'toDate'            => $toDate,
            'taskFromDate'      => $taskFrom,
            'taskToDate'        => $taskTo,
        ]);
    }

    /**
     * Login action — accepts all user roles.
     */
    public function actionLogin(): string|Response
    {
        if (!Yii::$app->user->isGuest) {
            return $this->redirect($this->getDefaultRoute());
        }

        $this->layout = 'blank';

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect($this->getDefaultRoute());
        }

        $model->password = '';

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Determines the default landing page based on the user's permissions.
     * Admin users always go to dashboard. Other roles go to the first permitted route.
     */
    private function getDefaultRoute(): array
    {
        $user = Yii::$app->user->identity;
        if (!$user) {
            return ['/site/login'];
        }

        // Admin always gets dashboard
        if ($user->isAdmin()) {
            return ['/site/index'];
        }

        // Check if user has dashboard access
        if (\common\components\UserAccess::checkAccess('site/index')) {
            return ['/site/index'];
        }

        // Client role → redirect to client-specific projects page
        if ($user->isClient()) {
            return ['/project/client-projects'];
        }

        // Try common developer landing pages in priority order
        $fallbackRoutes = [
            'attendance/my-attendance',
            'work-report/my-work-reports',
            'task/index',
            'developer/index',
        ];
        foreach ($fallbackRoutes as $route) {
            if (\common\components\UserAccess::checkAccess($route)) {
                return ['/' . $route];
            }
        }

        // Last resort - just go to dashboard (will at least show the page)
        return ['/site/index'];
    }

    /**
     * Change password for the currently logged-in user.
     */
    public function actionChangePassword(): string|Response
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $error = null;

        if (Yii::$app->request->isPost) {
            $post       = Yii::$app->request->post();
            $current    = $post['current_password'] ?? '';
            $newPass    = $post['new_password'] ?? '';
            $confirm    = $post['confirm_password'] ?? '';

            if (!$user->validatePassword($current)) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPass) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($newPass !== $confirm) {
                $error = 'New password and confirmation do not match.';
            } else {
                $user->setPassword($newPass);
                $user->last_password_changed_date = date('Y-m-d H:i:s');
                $user->save(false);
                Yii::$app->session->setFlash('success', 'Password changed successfully.');
                return $this->redirect(['/site/index']);
            }
        }

        return $this->render('change-password', ['error' => $error]);
    }

    /**
     * Logout action.
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();

        return $this->redirect(['/site/login']);
    }

    /**
     * Cron endpoint: process raw `attendance` punches into `dailyattendance`
     * rows for the given date. Hit by the server crontab nightly at 00:15
     * UTC (05:45 IST) without arguments — defaults to processing yesterday.
     *
     * Manual backfill: `?date=YYYY-MM-DD` (optionally with `?developer_id=N`)
     * processes that exact date. Idempotent — re-running just updates the
     * existing row.
     *
     * Yii1 route: /site/dailyattendancecall (kept lowercase so the existing
     * crontab works without modification).
     */
    public function actionDailyattendancecall(?string $date = null, ?int $developer_id = null): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/html; charset=UTF-8');

        $log = new CronJobCallLogs();
        $log->url       = Yii::$app->request->url;
        $log->date_time = date('Y-m-d H:i:s');
        $log->response  = null;
        $log->save(false);

        $devId = ($developer_id !== null && (int)$developer_id > 0) ? (int)$developer_id : null;

        if ($date === null || $date === '') {
            // Nightly default: process yesterday (D-1). Also re-process the day
            // before (D-2): the sandwich rule looks one day forward, and only now
            // — with D-1 fully ended — can a holiday/Sunday on D-2 be judged
            // against complete next-day data. processDate is idempotent, so this
            // simply corrects D-2 if its flank turned out to be a real absence.
            $yesterday      = date('Y-m-d', strtotime('-1 days'));
            $dayBefore      = date('Y-m-d', strtotime('-2 days'));
            $resYesterday   = AttendanceProcessor::processDate($yesterday, $devId);
            $resDayBefore   = AttendanceProcessor::processDate($dayBefore, $devId);
            $res = "[{$yesterday}] {$resYesterday}\n[{$dayBefore} re-check] {$resDayBefore}";
        } else {
            // Manual backfill: process exactly the requested date.
            $resolved = date('Y-m-d', strtotime($date));
            $res = AttendanceProcessor::processDate($resolved, $devId);
        }

        $log->response = $res;
        $log->save(false);

        return $res;
    }

    /**
     * Cron endpoint: auto-generate payslips for all active employees. Hit by the
     * server crontab on the 1st of each month (after the nightly attendance job
     * has processed the final day of the month) without arguments — defaults to
     * the PREVIOUS month, the one that just closed.
     *
     * Manual backfill: `?month=YYYY-MM` (or `YYYY-MM-01`) generates that month.
     *
     * Idempotent: create-missing only — a developer who already has a payslip
     * for the month is skipped, so re-running never duplicates or overwrites an
     * admin's edits.
     *
     * Public (no auth) and lowercase, matching the dailyattendancecall pattern.
     */
    public function actionMonthlypayslipcall(?string $month = null, int $refresh = 1): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/html; charset=UTF-8');

        $log = new CronJobCallLogs();
        $log->url       = Yii::$app->request->url;
        $log->date_time = date('Y-m-d H:i:s');
        $log->response  = null;
        $log->save(false);

        $resolved = ($month === null || $month === '')
            ? date('Y-m-01', strtotime('first day of last month'))
            : date('Y-m-01', strtotime($month . (strlen($month) === 7 ? '-01' : '')));

        // Payslips are system-created here; attribute them to an admin account
        // so created_by stays a valid user reference.
        $createdBy = (int)(User::find()->select('user_id')->where(['usertype' => User::TYPE_ADMIN])->scalar() ?: 1);

        // refresh=1 (default): recompute every existing payslip in place from current
        // attendance + settings AND create any missing ones — so each call always
        // regenerates. refresh=0: create-missing only (skip existing, never clobber).
        $res = PayslipGenerator::generateForMonth($resolved, $createdBy, $refresh !== 0);

        // Also produce the monthly CSV + upload_payslip record so the run shows up in
        // the "Manage Payslips" (payslip-upload) screen, same as the Create Payslip button.
        PayslipGenerator::generateCsvFile($resolved, $createdBy);

        $log->response = $res;
        $log->save(false);

        return $res;
    }

    /**
     * Cron endpoint: credit +1 CL to every active developer for a month. Hit by
     * the server crontab on the 1st of each month — without arguments it grants
     * the CURRENT month's leave allowance.
     *
     * Manual run / back-fill: `?month=YYYY-MM` (or `YYYY-MM-01`) credits that
     * month.
     *
     * Idempotent: a (developer, month) guard row in `monthly_cl_credit` (UNIQUE)
     * is written before cl_backup is bumped, so re-running — or running twice at
     * once — never double-credits. It does nothing else: just +1 CL per active
     * developer per month.
     *
     * Public (no auth) and lowercase, matching the dailyattendancecall pattern.
     */
    public function actionMonthlyclcall(?string $month = null): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/html; charset=UTF-8');

        $log = new CronJobCallLogs();
        $log->url       = Yii::$app->request->url;
        $log->date_time = date('Y-m-d H:i:s');
        $log->response  = null;
        $log->save(false);

        $resolved = ($month === null || $month === '')
            ? date('Y-m-01')
            : date('Y-m-01', strtotime($month . (\strlen($month) === 7 ? '-01' : '')));

        $res = MonthlyClCreditor::creditForMonth($resolved);

        $log->response = $res;
        $log->save(false);

        return $res;
    }
}
