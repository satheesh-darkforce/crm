<?php

namespace backend\controllers;

use backend\assets\TaskAsset;
use backend\components\TaskAccess;
use backend\models\Attendance;
use backend\models\ClientModel;
use backend\models\Developer;
use backend\models\Project;
use backend\models\TaskLogs;
use backend\models\WorkReport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend CRUD for WorkReport.
 */
class WorkReportController extends Controller
{
    /** Query parameters the reports read; cleaned once in beforeAction(). */
    private const DATE_PARAMS = ['date', 'from_date', 'to_date'];
    private const ID_PARAMS   = ['developer_id', 'project_id', 'client_id'];
    private const MATRIX_MAX_DAYS = 62;
    private const MULTI_ID_ACTIONS = ['overall', 'export-overall-report'];

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['delete' => ['POST']]],
        ]);
    }

    /**
     * Normalise report filters for every action: dates become Y-m-d (unreadable
     * ones are dropped with a notice instead of turning into 1970-01-01), ids
     * must be digits, arrays from tampered URLs are discarded, and a reversed
     * from/to range is swapped.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $req     = Yii::$app->request;
        $params  = $req->getQueryParams();
        $ignored = [];
        // Arrays only where a page takes multi-select ids; elsewhere (int)[...] would read as 1.
        $multi = in_array($action->id, self::MULTI_ID_ACTIONS, true);
        foreach ($params as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if ($multi && in_array($key, self::ID_PARAMS, true)) {
                $params[$key] = array_values(array_filter($value, fn($v) => is_string($v) && ctype_digit($v)));
            } else {
                unset($params[$key]);
            }
        }
        foreach (self::DATE_PARAMS as $key) {
            if (!isset($params[$key]) || trim((string)$params[$key]) === '') {
                continue;
            }
            $ts = strtotime(trim((string)$params[$key]));
            if ($ts === false || (int)date('Y', $ts) < 2000 || (int)date('Y', $ts) > 2100) {
                $ignored[] = str_replace('_', ' ', $key);
                unset($params[$key]);
            } else {
                $params[$key] = date('Y-m-d', $ts);
            }
        }
        foreach (self::ID_PARAMS as $key) {
            if (isset($params[$key]) && !is_array($params[$key]) && $params[$key] !== '' && !ctype_digit((string)$params[$key])) {
                $ignored[] = str_replace('_', ' ', $key);
                unset($params[$key]);
            }
        }
        if (!empty($params['from_date']) && !empty($params['to_date']) && $params['to_date'] < $params['from_date']) {
            [$params['from_date'], $params['to_date']] = [$params['to_date'], $params['from_date']];
        }
        $req->setQueryParams($params);
        if ($ignored && !$req->isAjax) {
            Yii::$app->session->setFlash('warning', 'Ignored an invalid filter value (' . implode(', ', array_unique($ignored)) . ').');
        }
        return true;
    }

    /** Full pages get the Tasks / Work Reports stylesheet and script. */
    public function render($view, $params = []): string
    {
        TaskAsset::register($this->getView());
        return parent::render($view, $params);
    }

    public function actionIndex(): string
    {
        $query = WorkReport::find()
            ->alias('wr')
            ->with(['developer', 'project', 'client'])
            ->where(['wr.status' => 1])
            ->orderBy(['wr.work_id' => SORT_DESC]);

        if ($devId = Yii::$app->request->get('developer_id')) {
            $query->andWhere(['wr.developer_id' => (int)$devId]);
        }
        if ($projectId = Yii::$app->request->get('project_id')) {
            $query->andWhere(['wr.project_id' => (int)$projectId]);
        }
        if ($clientId = Yii::$app->request->get('client_id')) {
            $query->andWhere(['wr.client_id' => (int)$clientId]);
        }
        if (($task = trim((string) Yii::$app->request->get('task', ''))) !== '') {
            $query->andWhere(['like', 'wr.task', $task]);
        }
        if ($date = Yii::$app->request->get('date')) {
            $query->andWhere(['wr.date' => date('Y-m-d', strtotime($date))]);
        }
        if ($fromDate = Yii::$app->request->get('from_date')) {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        }
        if ($toDate = Yii::$app->request->get('to_date')) {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 25],
        ]);

        // Sum total_time across the entire filtered set (not just current page).
        $grandTotalSecs = (int) (clone $query)->sum('wr.total_time');

        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('index', compact('dataProvider', 'developers', 'projects', 'clients', 'grandTotalSecs'));
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionDeveloperCurrentMonth(): string
    {
        // Parse month/year — accepts ?year=MM-YYYY (Yii1 format) or defaults to current month
        $yearParam = Yii::$app->request->get('year');
        if ($yearParam && preg_match('/^(\d{1,2})-(\d{4})$/', $yearParam, $m)) {
            $month = (int)$m[1];
            $year  = (int)$m[2];
        } else {
            $year  = (int)date('Y');
            $month = (int)date('m');
        }

        $devFilter      = Yii::$app->request->get('developer_id');
        $projectFilter  = Yii::$app->request->get('project_id');
        $clientFilter   = Yii::$app->request->get('client_id');
        $fromDate       = Yii::$app->request->get('from_date');
        $toDate         = Yii::$app->request->get('to_date');

        // Mirror Yii1 developer_current_month_report(): GROUP BY developer_id+project_id,
        // ORDER BY developer_name, with optional from/to_date overriding the month filter.
        $query = WorkReport::find()
            ->alias('wr')
            ->select([
                'wr.developer_id',
                'wr.project_id',
                'wr.client_id',
                'd.developer_name',
                'p.title AS project_title',
                'c.client_name',
                'SUM(wr.total_time) AS groupbysum',
            ])
            ->leftJoin('developer d', 'd.id = wr.developer_id')
            ->leftJoin('project p',   'p.id = wr.project_id')
            ->leftJoin('client c',    'c.id = wr.client_id')
            ->where(['wr.status' => 1])
            // Yii1 grouped by (developer_id, project_id) — that's the semantic intent.
            // The other selected columns (client_id, developer_name, project title,
            // client_name) are functionally dependent on the dev/project pair via
            // 1:1 joins, but MySQL's only_full_group_by mode requires them in GROUP BY
            // explicitly. Adding them does not change the resulting row set.
            ->groupBy([
                'wr.developer_id', 'wr.project_id', 'wr.client_id',
                'd.developer_name', 'p.title', 'c.client_name',
            ])
            ->orderBy(['d.developer_name' => SORT_ASC])
            ->asArray();

        // from/to_date override the month filter (Yii1 parity)
        if (!empty($fromDate) && !empty($toDate)) {
            $query->andWhere(['between', 'wr.date', date('Y-m-d', strtotime($fromDate)), date('Y-m-d', strtotime($toDate))]);
        } elseif (!empty($fromDate)) {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        } elseif (!empty($toDate)) {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        } else {
            $query->andWhere('MONTH(wr.date) = :mm AND YEAR(wr.date) = :yy', [':mm' => $month, ':yy' => $year]);
        }

        if ($devFilter) {
            $query->andWhere(['wr.developer_id' => (int)$devFilter]);
        }
        if ($projectFilter) {
            $query->andWhere(['wr.project_id' => (int)$projectFilter]);
        }
        if ($clientFilter) {
            $query->andWhere(['wr.client_id' => (int)$clientFilter]);
        }

        // Grand total — separate SUM query without GROUP BY, with same WHERE clauses.
        // Mirrors Yii1's $model->searchtotal computation.
        $totalQuery = WorkReport::find()->alias('wr')->where(['wr.status' => 1]);
        if (!empty($fromDate) && !empty($toDate)) {
            $totalQuery->andWhere(['between', 'wr.date', date('Y-m-d', strtotime($fromDate)), date('Y-m-d', strtotime($toDate))]);
        } elseif (!empty($fromDate)) {
            $totalQuery->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        } elseif (!empty($toDate)) {
            $totalQuery->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        } else {
            $totalQuery->andWhere('MONTH(wr.date) = :mm AND YEAR(wr.date) = :yy', [':mm' => $month, ':yy' => $year]);
        }
        if ($devFilter) {
            $totalQuery->andWhere(['wr.developer_id' => (int)$devFilter]);
        }
        if ($projectFilter) {
            $totalQuery->andWhere(['wr.project_id' => (int)$projectFilter]);
        }
        if ($clientFilter) {
            $totalQuery->andWhere(['wr.client_id' => (int)$clientFilter]);
        }
        $grandTotal = (int)$totalQuery->sum('wr.total_time');

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'key'        => fn($r) => $r['developer_id'] . '-' . $r['project_id'],
            'pagination' => ['pageSize' => 50],
            'sort'       => false,
        ]);

        $monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $developers = \backend\models\Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('developer-current-month', compact(
            'dataProvider', 'monthLabel', 'developers', 'projects', 'clients', 'year', 'month',
            'grandTotal', 'devFilter', 'projectFilter', 'clientFilter', 'fromDate', 'toDate'
        ));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function actionCreate(): string|Response
    {
        $model = new WorkReport();
        $model->date   = date('Y-m-d');
        $model->status = 1;

        // Determine default date from attendance (same as Yii1)
        $checkAttendance = Attendance::check_attendance();
        if ($checkAttendance['reply'] == 4 && $checkAttendance['date'] !== '') {
            $model->date = $checkAttendance['date'];
        }

        $userType    = Yii::$app->user->identity->usertype ?? 2;
        $isAdmin     = ($userType == 1 || $userType == 4);

        // Pre-fill developer_id for non-admin users
        if (!$isAdmin) {
            $model->developer_id = Yii::$app->user->identity->getDeveloperId();
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post('WorkReport', []);
            $post = is_array($post) ? array_map(fn($v) => is_scalar($v) ? (string)$v : null, $post) : [];

            // Admin can pick developer; non-admin uses own developer_id
            if ($isAdmin) {
                $model->developer_id = $post['developer_id'] ?? null;
            }
            if (!$model->developer_id) {
                Yii::$app->session->setFlash('error', 'Your login is not linked to an employee record, so work reports cannot be filed. Ask an admin to link it.');
                return $this->redirect($this->listRoute());
            }
            $model->project_id   = $post['project_id'] ?? null;
            $model->date         = $post['date'] ?? date('Y-m-d');
            $model->task         = $post['task'] ?? null;
            $model->description  = $post['description'] ?? null;
            $model->from_time    = $post['from_time'] ?? null;
            $model->to_time      = $post['to_time'] ?? null;

            // Total time arrives as HH:MM (Yii1 format) and is stored in seconds.
            $secs = $this->parseTotalTime($post['total_time'] ?? '');
            $model->total_time = $secs ?? 0;

            // Auto-fill client from project
            if ($model->project_id) {
                $project = Project::findOne((int)$model->project_id);
                $model->client_id = $project ? ($project->client_id ?: null) : null;
            }

            $model->created_time      = date('Y-m-d H:i:s');
            $model->created_by        = Yii::$app->user->id;
            $model->updated_by        = null;
            $model->last_updated_time = null;
            $model->data_source       = WorkReport::SOURCE_WEB;

            $valid = $model->validate();
            if ($secs === null) {
                $model->clearErrors('total_time');
                $model->addError('total_time', 'Enter the total time as HH:MM (e.g. 02:30).');
                $valid = false;
            }
            if ($valid && $model->save(false)) {
                Yii::$app->session->setFlash('success', 'Work report saved for ' . date('d M Y', strtotime($model->date)) . '.');
                // Yii1 redirected to My Work Reports; those with the company-wide list land there.
                return $this->redirect($this->listRoute());
            }
        }

        // Only in-progress projects (project_status=1), same as Yii1
        $projects = Project::find()
            ->where(['status' => 1, 'project_status' => 1])
            ->orderBy('title')
            ->all();

        // Developers list for admin dropdown
        $developers = Developer::find()
            ->where(['status' => 1])
            ->orderBy('developer_name')
            ->all();

        return $this->render('create', compact('model', 'projects', 'developers', 'isAdmin'));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function actionUpdate(int $id): string|Response
    {
        $model    = $this->findModel($id, true);
        $userType = Yii::$app->user->identity->usertype ?? 2;
        $isAdmin  = ($userType == 1 || $userType == 4);

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post('WorkReport', []);
            $post = is_array($post) ? array_map(fn($v) => is_scalar($v) ? (string)$v : null, $post) : [];

            $model->date        = isset($post['date']) && trim($post['date']) !== '' ? trim($post['date']) : $model->date;
            $model->project_id  = isset($post['project_id']) ? (int)$post['project_id'] : $model->project_id;
            $model->task        = $post['task'] ?? $model->task;
            $model->description = $post['description'] ?? $model->description;
            $model->from_time   = isset($post['from_time']) && trim($post['from_time']) !== '' ? trim($post['from_time']) : null;
            $model->to_time     = isset($post['to_time']) && trim($post['to_time']) !== '' ? trim($post['to_time']) : null;

            // BUG-6 fix: admin/sub-admin can reassign work report to different developer
            if ($isAdmin && isset($post['developer_id']) && (int)$post['developer_id'] > 0) {
                $model->developer_id = (int)$post['developer_id'];
            }

            // BUG-9 fix: preserve task_id if submitted (don't lose task system link)
            // Only a task assigned to this report's employee can be linked.
            if (isset($post['task_id']) && (int)$post['task_id'] > 0) {
                $devUser = Developer::find()->select('user_id')->where(['id' => (int)$model->developer_id])->scalar();
                if ($devUser && \backend\models\Task::find()->where(['id' => (int)$post['task_id'], 'status' => 1, 'assigned_to' => (int)$devUser])->exists()) {
                    $model->task_id = (int)$post['task_id'];
                }
            }

            // Resolve client from project
            $project = Project::findOne($model->project_id);
            if ($project) {
                $model->client_id = $project->client_id ?: null;
            }

            // Total time arrives as HH:MM (Yii1 format) and is stored in seconds.
            $secs = null;
            if (isset($post['total_time']) && trim($post['total_time']) !== '') {
                $secs = $this->parseTotalTime($post['total_time']);
                if ($secs !== null) {
                    $model->total_time = $secs;
                }
            }

            $model->updated_by        = Yii::$app->user->id;
            $model->last_updated_time = date('Y-m-d H:i:s');

            $valid = $model->validate();
            if (isset($post['total_time']) && trim($post['total_time']) !== '' && $secs === null) {
                $model->addError('total_time', 'Enter the total time as HH:MM (e.g. 02:30).');
                $valid = false;
            }
            if ($valid && $model->save(false)) {
                Yii::$app->session->setFlash('success', 'Work report updated.');
                return $this->redirect($this->listRoute());
            }
        }

        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        // BUG-6 fix: pass developers list so _form.php can show developer selector for admin
        $developers = $isAdmin ? Developer::find()->where(['status' => 1])->orderBy('developer_name')->all() : [];

        return $this->render('update', compact('model', 'projects', 'developers'));
    }

    // ── My Work Reports (developer's own reports) ─────────────────────────────

    public function actionMyWorkReports(): string
    {
        $developerId = Yii::$app->user->identity->getDeveloperId();

        $query = WorkReport::find()
            ->alias('wr')
            ->with(['project', 'client'])
            ->where(['wr.status' => 1])
            ->orderBy(['wr.date' => SORT_DESC, 'wr.work_id' => SORT_DESC]);

        // Always filter by current user — if no developer record, show nothing
        if ($developerId) {
            $query->andWhere(['wr.developer_id' => $developerId]);
        } else {
            $query->andWhere('0=1'); // no developer record → empty result
        }

        if ($projectId = Yii::$app->request->get('project_id')) {
            $query->andWhere(['wr.project_id' => (int)$projectId]);
        }
        if ($fromDate = Yii::$app->request->get('from_date')) {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        }
        if ($toDate = Yii::$app->request->get('to_date')) {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }

        $totalQuery = clone $query;
        $totalSecs  = (int)$totalQuery->select('SUM(wr.total_time)')->scalar();

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 25],
        ]);

        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('my-work-reports', compact('dataProvider', 'projects', 'totalSecs'));
    }

    // ── Client Work Reports (client-facing view) ──────────────────────────────

    public function actionClientWorkReports(): string
    {
        $identity  = Yii::$app->user->identity;
        $clientId  = $identity->client->id ?? null;

        $query = WorkReport::find()
            ->alias('wr')
            ->with(['developer', 'project'])
            ->where(['wr.status' => 1])
            ->orderBy(['wr.date' => SORT_DESC, 'wr.work_id' => SORT_DESC]);

        // A login without a client record must see nothing — never every client's reports.
        $query->andWhere($clientId ? ['wr.client_id' => $clientId] : '0=1');

        if ($fromDate = Yii::$app->request->get('from_date')) {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        }
        if ($toDate = Yii::$app->request->get('to_date')) {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }
        if ($projectId = Yii::$app->request->get('project_id')) {
            $query->andWhere(['wr.project_id' => (int)$projectId]);
        }
        if ($devId = Yii::$app->request->get('developer_id')) {
            $query->andWhere(['wr.developer_id' => (int)$devId]);
        }

        $totalQuery = clone $query;
        $totalMins  = (int)$totalQuery->select('SUM(wr.total_time)')->scalar();

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 25],
        ]);

        // Projects belonging to this client
        $projects = [];
        if ($clientId) {
            $projects = Project::find()->where(['client_id' => $clientId, 'status' => 1])->orderBy('title')->all();
        }
        // Developers who have worked for this client
        $developers = [];
        if ($clientId) {
            $devIds = WorkReport::find()
                ->select('developer_id')->distinct()
                ->where(['client_id' => $clientId, 'status' => 1])
                ->column();
            if (!empty($devIds)) {
                $developers = Developer::find()->where(['id' => $devIds, 'status' => 1])->orderBy('developer_name')->all();
            }
        }

        return $this->render('client-work-reports', compact('dataProvider', 'projects', 'developers', 'totalMins'));
    }

    public function actionDelete(int $id): Response
    {
        $wr = $this->findModel($id, true);
        $wr->updateAttributes(['status' => 0]);

        Yii::$app->session->setFlash('success', 'Work report of ' . date('d M Y', strtotime($wr->date)) . ' removed.');
        return $this->redirect($this->listRoute());
    }

    // ── Yesterday's Reports ──────────────────────────────────────────────────

    public function actionYesterday(): string
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $fromDate   = Yii::$app->request->get('from_date');
        $toDate     = Yii::$app->request->get('to_date');
        $devFilter  = Yii::$app->request->get('developer_id');
        $projFilter = Yii::$app->request->get('project_id');
        $cliFilter  = Yii::$app->request->get('client_id');

        $query = WorkReport::find()
            ->alias('wr')
            ->with(['developer', 'project', 'client'])
            ->where(['wr.status' => 1])
            ->orderBy(['wr.developer_id' => SORT_ASC, 'wr.work_id' => SORT_DESC]);

        // Apply date range if user filtered, otherwise default to yesterday only (Yii1 parity).
        if (!empty($fromDate) && !empty($toDate)) {
            $query->andWhere(['between', 'wr.date', date('Y-m-d', strtotime($fromDate)), date('Y-m-d', strtotime($toDate))]);
        } elseif (!empty($fromDate)) {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        } elseif (!empty($toDate)) {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        } else {
            $query->andWhere(['wr.date' => $yesterday]);
        }

        if ($devFilter)  { $query->andWhere(['wr.developer_id' => (int)$devFilter]); }
        if ($projFilter) { $query->andWhere(['wr.project_id'   => (int)$projFilter]); }
        if ($cliFilter)  { $query->andWhere(['wr.client_id'    => (int)$cliFilter]); }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 50],
        ]);

        $grandTotalSecs = (int) (clone $query)->sum('wr.total_time');

        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('yesterday', compact(
            'dataProvider', 'developers', 'projects', 'clients', 'yesterday',
            'fromDate', 'toDate', 'devFilter', 'projFilter', 'cliFilter', 'grandTotalSecs'
        ));
    }

    // ── Monthly by Employee ───────────────────────────────────────────────────

    public function actionMonthlyByEmployee(): string
    {
        $fromDate = Yii::$app->request->get('from_date', date('Y-m-01'));
        $toDate   = Yii::$app->request->get('to_date',   date('Y-m-t'));

        $fromDate = date('Y-m-d', strtotime($fromDate));
        $toDate   = date('Y-m-d', strtotime($toDate));

        // One column per day: keep the grid readable (and the query small).
        if ((strtotime($toDate) - strtotime($fromDate)) / 86400 > self::MATRIX_MAX_DAYS - 1) {
            $toDate = date('Y-m-d', strtotime($fromDate . ' +' . (self::MATRIX_MAX_DAYS - 1) . ' days'));
            Yii::$app->session->setFlash('warning', 'The monthly grid shows up to ' . self::MATRIX_MAX_DAYS . ' days, so it ends on ' . date('d M Y', strtotime($toDate)) . '.');
        }

        // Collect all dates in range
        $dates = [];
        $cur   = $fromDate;
        while ($cur <= $toDate) {
            $dates[] = $cur;
            $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
        }

        // Get distinct developers who have reports in this range
        $devIds = WorkReport::find()
            ->select('developer_id')->distinct()
            ->where(['status' => 1])
            ->andWhere(['>=', 'date', $fromDate])
            ->andWhere(['<=', 'date', $toDate])
            ->column();

        $developers = [];
        if (!empty($devIds)) {
            $developers = Developer::find()
                ->where(['id' => $devIds])
                ->orderBy('developer_name')
                ->all();
        }

        // Build data: [dev_id][date] = total_minutes
        $matrix = [];
        $devTotals = [];
        if (!empty($devIds)) {
            $rows = WorkReport::find()
                ->select(['developer_id', 'date', 'SUM(total_time) AS day_total'])
                ->where(['status' => 1])
                ->andWhere(['developer_id' => $devIds])
                ->andWhere(['>=', 'date', $fromDate])
                ->andWhere(['<=', 'date', $toDate])
                ->groupBy(['developer_id', 'date'])
                ->asArray()
                ->all();

            foreach ($rows as $row) {
                $matrix[$row['developer_id']][$row['date']] = (int)$row['day_total'];
                $devTotals[$row['developer_id']] = ($devTotals[$row['developer_id']] ?? 0) + (int)$row['day_total'];
            }
        }

        return $this->render('monthly-by-employee', compact('developers', 'dates', 'matrix', 'devTotals', 'fromDate', 'toDate'));
    }

    // ── Cumulative by Employee ────────────────────────────────────────────────

    public function actionCumulativeEmployee(): string
    {
        $fromDate   = Yii::$app->request->get('from_date', '');
        $toDate     = Yii::$app->request->get('to_date', '');
        $devFilter  = Yii::$app->request->get('developer_id');
        $projFilter = Yii::$app->request->get('project_id');
        $cliFilter  = Yii::$app->request->get('client_id');

        // Reusable filter applier — keeps main query and grand-total query in sync (Yii1 parity).
        $applyFilters = function ($q) use ($fromDate, $toDate, $devFilter, $projFilter, $cliFilter) {
            if (!empty($fromDate)) { $q->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]); }
            if (!empty($toDate))   { $q->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]); }
            if ($devFilter)        { $q->andWhere(['wr.developer_id' => (int)$devFilter]); }
            if ($projFilter)       { $q->andWhere(['wr.project_id' => (int)$projFilter]); }
            if ($cliFilter)        { $q->andWhere(['wr.client_id' => (int)$cliFilter]); }
        };

        $query = WorkReport::find()
            ->select(['wr.developer_id', 'wr.project_id', 'wr.client_id', 'SUM(wr.total_time) AS total_secs',
                       'd.developer_name', 'p.title AS project_title', 'c.client_name'])
            ->alias('wr')
            ->leftJoin('developer d', 'd.id = wr.developer_id')
            ->leftJoin('project p', 'p.id = wr.project_id')
            ->leftJoin('client c', 'c.id = wr.client_id')
            ->where(['wr.status' => 1])
            ->groupBy(['wr.developer_id', 'wr.project_id', 'wr.client_id',
                       'd.developer_name', 'p.title', 'c.client_name'])
            ->orderBy(['d.developer_name' => SORT_ASC])
            ->asArray();
        $applyFilters($query);

        // Grand total — separate SUM query without grouping (matches Yii1 searchtotal pattern).
        $totalQuery = WorkReport::find()->alias('wr')->where(['wr.status' => 1]);
        $applyFilters($totalQuery);
        $grandTotal = (int)$totalQuery->sum('wr.total_time');

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'key'        => fn($r) => $r['developer_id'] . '-' . $r['project_id'] . '-' . $r['client_id'],
            'pagination' => ['pageSize' => 20],
            'sort'       => false,
        ]);

        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('cumulative-employee', compact(
            'dataProvider', 'grandTotal',
            'fromDate', 'toDate', 'devFilter', 'projFilter', 'cliFilter',
            'developers', 'projects', 'clients'
        ));
    }

    // ── Monthly by Project ────────────────────────────────────────────────────

    public function actionMonthlyByProject(): string
    {
        $fromDate   = Yii::$app->request->get('from_date', '');
        $toDate     = Yii::$app->request->get('to_date', '');
        $projFilter = Yii::$app->request->get('project_id');
        $cliFilter  = Yii::$app->request->get('client_id');

        // Default to current month if no date provided (matching Yii1)
        if (empty($fromDate) && empty($toDate)) {
            $fromDate = date('Y-m-01');
            $toDate   = date('Y-m-t');
        }

        $query = WorkReport::find()
            ->select(['wr.project_id', 'wr.client_id', 'SUM(wr.total_time) AS total_secs', 'COUNT(wr.work_id) AS entry_count'])
            ->alias('wr')
            ->where(['wr.status' => 1])
            ->groupBy(['wr.project_id', 'wr.client_id'])
            ->orderBy(['total_secs' => SORT_DESC]);

        if (!empty($fromDate)) {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        }
        if (!empty($toDate)) {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }
        if ($projFilter) {
            $query->andWhere(['wr.project_id' => (int)$projFilter]);
        }
        if ($cliFilter) {
            $query->andWhere(['wr.client_id' => (int)$cliFilter]);
        }

        $rows      = $query->asArray()->all();
        $grandTotal = 0;

        $projMap = [];
        $cliMap  = [];
        foreach ($rows as $r) {
            $projMap[$r['project_id']] = null;
            $cliMap[$r['client_id']]   = null;
            $grandTotal += (int)$r['total_secs'];
        }

        if ($projMap) { foreach (Project::find()->where(['id' => array_keys($projMap)])->all()    as $m) $projMap[$m->id] = $m; }
        if ($cliMap)  { foreach (ClientModel::find()->where(['id' => array_keys($cliMap)])->all() as $m) $cliMap[$m->id]  = $m; }

        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $clients  = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('monthly-by-project', compact(
            'rows', 'projMap', 'cliMap', 'grandTotal',
            'fromDate', 'toDate', 'projFilter', 'cliFilter', 'projects', 'clients'
        ));
    }

    // ── Cumulative by Client ──────────────────────────────────────────────────

    public function actionCumulativeClient(): string
    {
        $fromDate   = Yii::$app->request->get('from_date', '');
        $toDate     = Yii::$app->request->get('to_date', '');
        $cliFilter  = Yii::$app->request->get('client_id');
        $projFilter = Yii::$app->request->get('project_id');

        $query = WorkReport::find()
            ->select(['wr.client_id', 'wr.project_id', 'SUM(wr.total_time) AS total_secs', 'c.client_name', 'p.title'])
            ->alias('wr')
            ->leftJoin('client c', 'c.id = wr.client_id')
            ->leftJoin('project p', 'p.id = wr.project_id')
            ->where(['wr.status' => 1])
            ->groupBy(['wr.client_id', 'wr.project_id'])
            ->orderBy(['c.client_name' => SORT_ASC])
            ->asArray();

        if (!empty($fromDate) && empty($toDate)) {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        } elseif (!empty($toDate) && empty($fromDate)) {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        } elseif (!empty($fromDate) && !empty($toDate)) {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }

        if ($cliFilter) {
            $query->andWhere(['wr.client_id' => (int)$cliFilter]);
        }
        if ($projFilter) {
            $query->andWhere(['wr.project_id' => (int)$projFilter]);
        }

        // Grand total across ALL matching rows (no groupBy, no pagination)
        $totalQuery = WorkReport::find()->alias('wr')->where(['wr.status' => 1]);
        if (!empty($fromDate) && empty($toDate)) {
            $totalQuery->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        } elseif (!empty($toDate) && empty($fromDate)) {
            $totalQuery->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        } elseif (!empty($fromDate) && !empty($toDate)) {
            $totalQuery->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
            $totalQuery->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }
        if ($cliFilter) { $totalQuery->andWhere(['wr.client_id' => (int)$cliFilter]); }
        if ($projFilter) { $totalQuery->andWhere(['wr.project_id' => (int)$projFilter]); }
        $grandTotal = (int)$totalQuery->sum('wr.total_time');

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'key'        => function ($model) {
                return $model['client_id'] . '-' . $model['project_id'];
            },
            'pagination' => ['pageSize' => 20],
            'sort'       => false,
        ]);

        $clients  = ClientModel::find()->orderBy('client_name')->all();
        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('cumulative-client', compact(
            'dataProvider', 'grandTotal',
            'fromDate', 'toDate', 'cliFilter', 'projFilter', 'clients', 'projects'
        ));
    }

    // ── Overall Report ────────────────────────────────────────────────────────

    public function actionOverall(): string
    {
        $fromDate  = Yii::$app->request->get('from_date');
        $toDate    = Yii::$app->request->get('to_date');
        $devIds    = Yii::$app->request->get('developer_id', []);
        $projIds   = Yii::$app->request->get('project_id',   []);
        $cliIds    = Yii::$app->request->get('client_id',    []);

        $reportData  = [];
        $grandTotal  = 0;

        if ($fromDate && $toDate) {
            $fromDate = date('Y-m-d', strtotime($fromDate));
            $toDate   = date('Y-m-d', strtotime($toDate));

            $projQuery = WorkReport::find()
                ->select(['project_id', 'client_id'])
                ->distinct()
                ->where(['status' => 1])
                ->andWhere(['>=', 'date', $fromDate])
                ->andWhere(['<=', 'date', $toDate]);

            if (!empty($devIds)) {
                $projQuery->andWhere(['developer_id' => array_map('intval', (array)$devIds)]);
            }
            if (!empty($projIds)) {
                $projQuery->andWhere(['project_id' => array_map('intval', (array)$projIds)]);
            }
            if (!empty($cliIds)) {
                $projQuery->andWhere(['client_id' => array_map('intval', (array)$cliIds)]);
            }

            $projectRows = $projQuery->asArray()->all();

            $allProjIds = array_column($projectRows, 'project_id');
            $projMap    = [];
            $cliMap     = [];
            if ($allProjIds) {
                foreach (Project::find()->where(['id' => $allProjIds])->orderBy('title')->all() as $m) $projMap[$m->id] = $m;
            }
            $allCliIds = array_unique(array_column($projectRows, 'client_id'));
            if ($allCliIds) {
                foreach (ClientModel::find()->where(['id' => $allCliIds])->all() as $m) $cliMap[$m->id] = $m;
            }

            foreach ($projMap as $projId => $proj) {
                $projTotalQuery = WorkReport::find()
                    ->select('SUM(total_time) AS total_secs')
                    ->where(['status' => 1, 'project_id' => $projId])
                    ->andWhere(['>=', 'date', $fromDate])
                    ->andWhere(['<=', 'date', $toDate]);
                if (!empty($devIds)) $projTotalQuery->andWhere(['developer_id' => array_map('intval', (array)$devIds)]);
                $projTotal = (int)$projTotalQuery->scalar();

                $devRows = WorkReport::find()
                    ->select(['developer_id', 'SUM(total_time) AS dev_proj_secs'])
                    ->where(['status' => 1, 'project_id' => $projId])
                    ->andWhere(['>=', 'date', $fromDate])
                    ->andWhere(['<=', 'date', $toDate])
                    ->groupBy('developer_id')
                    ->orderBy(['dev_proj_secs' => SORT_DESC]);
                if (!empty($devIds)) $devRows->andWhere(['developer_id' => array_map('intval', (array)$devIds)]);
                $devRows = $devRows->asArray()->all();

                $devDetail = [];
                foreach ($devRows as $dr) {
                    $dev = Developer::findOne($dr['developer_id']);
                    $devDetail[] = [
                        'name'  => $dev ? $dev->developer_name : '—',
                        'secs'  => (int)$dr['dev_proj_secs'],
                        'pct'   => $projTotal > 0 ? round((int)$dr['dev_proj_secs'] / $projTotal * 100, 1) : 0,
                    ];
                }

                $clientId = null;
                foreach ($projectRows as $pr) {
                    if ($pr['project_id'] == $projId) { $clientId = $pr['client_id']; break; }
                }

                $reportData[] = [
                    'project_id'   => $projId,
                    'project_name' => $proj->title,
                    'client_name'  => isset($cliMap[$clientId]) ? $cliMap[$clientId]->client_name : '—',
                    'total_secs'   => $projTotal,
                    'developers'   => $devDetail,
                ];

                $grandTotal += $projTotal;
            }
        }

        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('overall', compact(
            'reportData', 'grandTotal', 'fromDate', 'toDate',
            'devIds', 'projIds', 'cliIds', 'developers', 'projects', 'clients'
        ));
    }

    // ── Performance Report ────────────────────────────────────────────────────

    public function actionPerformance(): string
    {
        $fromDate  = Yii::$app->request->get('from_date', date('Y-m-01'));
        $toDate    = Yii::$app->request->get('to_date',   date('Y-m-t'));
        $devFilter = Yii::$app->request->get('developer_id');

        $fromDate = date('Y-m-d', strtotime($fromDate));
        $toDate   = date('Y-m-d', strtotime($toDate));

        $workingDays = 0;
        $cur = $fromDate;
        while ($cur <= $toDate) {
            $dow = (int)date('N', strtotime($cur));
            if ($dow <= 6) $workingDays++;
            $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
        }
        $expectedSecsPerDay = 8 * 3600; // 8 hours in seconds

        $devQuery = WorkReport::find()
            ->select(['developer_id', 'SUM(total_time) AS total_secs', 'COUNT(DISTINCT date) AS days_worked',
                      'COUNT(work_id) AS entry_count', 'COUNT(DISTINCT project_id) AS project_count'])
            ->where(['status' => 1])
            ->andWhere(['>=', 'date', $fromDate])
            ->andWhere(['<=', 'date', $toDate])
            ->groupBy('developer_id')
            ->orderBy(['total_secs' => SORT_DESC]);

        if ($devFilter) {
            $devQuery->andWhere(['developer_id' => (int)$devFilter]);
        }

        $rows    = $devQuery->asArray()->all();
        $devMap  = [];
        foreach ($rows as $r) $devMap[$r['developer_id']] = null;
        if ($devMap) {
            foreach (Developer::find()->where(['id' => array_keys($devMap)])->all() as $m) $devMap[$m->id] = $m;
        }

        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('performance', compact(
            'rows', 'devMap', 'fromDate', 'toDate', 'devFilter',
            'developers', 'workingDays', 'expectedSecsPerDay'
        ));
    }

    // ── Export: Monthly Developer Report (Excel) ──────────────────────────────

    public function actionExportMonthlyDeveloperReport(): void
    {
        $fromDate = Yii::$app->request->get('from_date', date('Y-m-01'));
        $toDate   = Yii::$app->request->get('to_date',   date('Y-m-t'));

        $fromDate = date('Y-m-d', strtotime($fromDate));
        $toDate   = date('Y-m-d', strtotime($toDate));
        if ((strtotime($toDate) - strtotime($fromDate)) / 86400 > self::MATRIX_MAX_DAYS - 1) {
            $toDate = date('Y-m-d', strtotime($fromDate . ' +' . (self::MATRIX_MAX_DAYS - 1) . ' days'));
        }

        // Collect date range
        $dates = [];
        $cur   = $fromDate;
        while ($cur <= $toDate) {
            $dates[] = $cur;
            $cur = date('Y-m-d', strtotime('+1 day', strtotime($cur)));
        }

        // Developers with entries in range
        $devIds = WorkReport::find()
            ->select('developer_id')->distinct()
            ->where(['status' => 1])
            ->andWhere(['>=', 'date', $fromDate])
            ->andWhere(['<=', 'date', $toDate])
            ->column();

        $developers = [];
        if (!empty($devIds)) {
            $developers = Developer::find()->where(['id' => $devIds])->orderBy('developer_name')->indexBy('id')->all();
        }

        // Matrix [dev_id][date] = total_minutes
        $matrix    = [];
        $devTotals = [];
        if (!empty($devIds)) {
            $rows = WorkReport::find()
                ->select(['developer_id', 'date', 'SUM(total_time) AS day_total'])
                ->where(['status' => 1, 'developer_id' => $devIds])
                ->andWhere(['>=', 'date', $fromDate])
                ->andWhere(['<=', 'date', $toDate])
                ->groupBy(['developer_id', 'date'])
                ->asArray()->all();
            foreach ($rows as $row) {
                $matrix[$row['developer_id']][$row['date']] = (int)$row['day_total'];
                $devTotals[$row['developer_id']] = ($devTotals[$row['developer_id']] ?? 0) + (int)$row['day_total'];
            }
        }

        $fmtSecs = function (int $secs): string {
            return sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monthly Developer Report');

        // Header row
        $col = 1;
        $sheet->setCellValue([$col++, 1], '#');
        $sheet->setCellValue([$col++, 1], 'Developer');
        foreach ($dates as $d) {
            $sheet->setCellValue([$col++, 1], date('d M', strtotime($d)));
        }
        $sheet->setCellValue([$col, 1], 'Total');

        // Data rows
        $row = 2;
        $sno = 1;
        foreach ($developers as $devId => $dev) {
            $col = 1;
            $sheet->setCellValue([$col++, $row], $sno++);
            $sheet->setCellValue([$col++, $row], $dev->developer_name);
            foreach ($dates as $d) {
                $mins = $matrix[$devId][$d] ?? 0;
                $sheet->setCellValue([$col++, $row], $mins > 0 ? $fmtSecs($mins) : '-');
            }
            $sheet->setCellValue([$col, $row], $fmtSecs($devTotals[$devId] ?? 0));
            $row++;
        }

        foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Sanitize dates (already reformatted via date() above, but enforce again for header safety)
        $safeFrom = preg_replace('/[^0-9\-]/', '', $fromDate);
        $safeTo   = preg_replace('/[^0-9\-]/', '', $toDate);
        $filename = 'Monthly_Developer_Report_' . $safeFrom . '_to_' . $safeTo . '.xls';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ── Export: Daily Work Report (Excel) ─────────────────────────────────────

    public function actionExportDailyWorkReport(): void
    {
        // Exporting up to ~40k rows with PhpSpreadsheet pushes well past the
        // default 128M; raise the ceiling for the duration of the request.
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $fromDate    = Yii::$app->request->get('from_date', '');
        $toDate      = Yii::$app->request->get('to_date',   '');
        $developerId = Yii::$app->request->get('developer_id', '');
        $projectId   = Yii::$app->request->get('project_id', '');
        $clientId    = Yii::$app->request->get('client_id', '');

        // No date range = full-table dump, which is the path that exhausts memory.
        // Default to the current calendar month so the export stays bounded.
        if ($fromDate === '' && $toDate === '') {
            $fromDate = date('Y-m-01');
            $toDate   = date('Y-m-t');
        }

        // Plain JOIN + select only the columns we render — avoids hydrating
        // four ActiveRecord objects per row (developer, project, client, wr).
        $query = (new \yii\db\Query())
            ->select([
                'date'           => 'wr.date',
                'developer_name' => 'd.developer_name',
                'project_title'  => 'p.title',
                'client_name'    => 'c.client_name',
                'task'           => 'wr.task',
                'total_time'     => 'wr.total_time',
            ])
            ->from(['wr' => WorkReport::tableName()])
            ->leftJoin(['d' => Developer::tableName()],   'd.id = wr.developer_id')
            ->leftJoin(['p' => Project::tableName()],     'p.id = wr.project_id')
            ->leftJoin(['c' => ClientModel::tableName()], 'c.id = wr.client_id')
            ->where(['wr.status' => 1])
            ->orderBy(['wr.date' => SORT_ASC, 'wr.work_id' => SORT_ASC]);

        if ($fromDate !== '') {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        }
        if ($toDate !== '') {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }
        if ($developerId !== '') {
            $query->andWhere(['wr.developer_id' => (int)$developerId]);
        }
        if ($projectId !== '') {
            $query->andWhere(['wr.project_id' => (int)$projectId]);
        }
        if ($clientId !== '') {
            $query->andWhere(['wr.client_id' => (int)$clientId]);
        }

        $fmtSecs = function (int $secs): string {
            return sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daily Work Reports');

        $headers = ['#', 'Date', 'Developer', 'Project', 'Client', 'Task', 'Total Time'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }

        $row = 2;
        $idx = 0;
        $totalMins = 0;
        // Stream rows in 500-row batches so we never hold more than that many
        // hydrated arrays in memory at once.
        foreach ($query->batch(500) as $batch) {
            foreach ($batch as $wr) {
                $mins = (int)$wr['total_time'];
                $totalMins += $mins;
                $idx++;
                $sheet->setCellValue([1, $row], $idx);
                $sheet->setCellValue([2, $row], $wr['date']);
                $sheet->setCellValue([3, $row], $wr['developer_name'] ?? '');
                $sheet->setCellValue([4, $row], $wr['project_title']  ?? '');
                $sheet->setCellValue([5, $row], $wr['client_name']    ?? '');
                $sheet->setCellValue([6, $row], $wr['task']           ?? '');
                $sheet->setCellValue([7, $row], $fmtSecs($mins));
                $row++;
            }
        }

        // Total row
        $sheet->setCellValue([6, $row], 'Total Hours');
        $sheet->setCellValue([7, $row], $fmtSecs($totalMins));

        // Fixed widths instead of setAutoSize() — autosize forces PhpSpreadsheet
        // to measure every cell and is the second-biggest memory cost here.
        $widths = ['A' => 6, 'B' => 12, 'C' => 24, 'D' => 30, 'E' => 28, 'F' => 50, 'G' => 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $filename = 'Daily_Work_Reports' . ($fromDate ? '_' . $fromDate : '') . ($toDate ? '_to_' . $toDate : '') . '.xls';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ── Export: Client Cumulative Report (Excel) ──────────────────────────────

    public function actionExportClientCumulativeReport(): void
    {
        $fromDate  = Yii::$app->request->get('from_date', '');
        $toDate    = Yii::$app->request->get('to_date',   '');
        $projectId = Yii::$app->request->get('project_id', '');
        $clientId  = Yii::$app->request->get('client_id',  '');

        $query = WorkReport::find()
            ->select(['wr.client_id', 'wr.project_id', 'SUM(wr.total_time) AS group_total'])
            ->alias('wr')
            ->where(['wr.status' => 1])
            ->groupBy(['wr.client_id', 'wr.project_id'])
            ->orderBy(['wr.client_id' => SORT_ASC]);

        if ($fromDate !== '') {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        }
        if ($toDate !== '') {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }
        if ($projectId !== '') {
            $query->andWhere(['wr.project_id' => (int)$projectId]);
        }
        if ($clientId !== '') {
            $query->andWhere(['wr.client_id' => (int)$clientId]);
        }

        $rows = $query->asArray()->all();

        // Load maps
        $projIds = array_unique(array_column($rows, 'project_id'));
        $cliIds  = array_unique(array_column($rows, 'client_id'));
        $projMap = [];
        $cliMap  = [];
        if ($projIds) foreach (Project::find()->where(['id' => $projIds])->all() as $m) $projMap[$m->id] = $m;
        if ($cliIds)  foreach (ClientModel::find()->where(['id' => $cliIds])->all() as $m) $cliMap[$m->id] = $m;

        $fmtSecs = function (int $secs): string {
            return sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Client Cumulative Report');

        $headers = ['#', 'Client', 'Project', 'Total Time'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }

        $rowNum = 2;
        $totalMins = 0;
        foreach ($rows as $i => $r) {
            $mins = (int)$r['group_total'];
            $totalMins += $mins;
            $sheet->setCellValue([1, $rowNum], $i + 1);
            $sheet->setCellValue([2, $rowNum], isset($cliMap[$r['client_id']]) ? $cliMap[$r['client_id']]->client_name : '');
            $sheet->setCellValue([3, $rowNum], isset($projMap[$r['project_id']]) ? $projMap[$r['project_id']]->title : '');
            $sheet->setCellValue([4, $rowNum], $fmtSecs($mins));
            $rowNum++;
        }

        $sheet->setCellValue([3, $rowNum], 'Total Hours');
        $sheet->setCellValue([4, $rowNum], $fmtSecs($totalMins));

        foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $filename = 'Client_Cumulative_Reports' . ($fromDate ? '_' . $fromDate : '') . '.xls';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ── Export: Developer Cumulative Report (Excel) ───────────────────────────

    public function actionExportDeveloperCumulativeReport(): void
    {
        $fromDate    = Yii::$app->request->get('from_date', '');
        $toDate      = Yii::$app->request->get('to_date',   '');
        $developerId = Yii::$app->request->get('developer_id', '');
        $projectId   = Yii::$app->request->get('project_id', '');
        $clientId    = Yii::$app->request->get('client_id',  '');

        $query = WorkReport::find()
            ->select(['wr.developer_id', 'wr.project_id', 'wr.client_id', 'SUM(wr.total_time) AS group_total'])
            ->alias('wr')
            ->where(['wr.status' => 1])
            ->groupBy(['wr.developer_id', 'wr.project_id', 'wr.client_id'])
            ->orderBy(['wr.developer_id' => SORT_ASC]);

        if ($fromDate !== '') {
            $query->andWhere(['>=', 'wr.date', date('Y-m-d', strtotime($fromDate))]);
        }
        if ($toDate !== '') {
            $query->andWhere(['<=', 'wr.date', date('Y-m-d', strtotime($toDate))]);
        }
        if ($developerId !== '') {
            $query->andWhere(['wr.developer_id' => (int)$developerId]);
        }
        if ($projectId !== '') {
            $query->andWhere(['wr.project_id' => (int)$projectId]);
        }
        if ($clientId !== '') {
            $query->andWhere(['wr.client_id' => (int)$clientId]);
        }

        $rows = $query->asArray()->all();

        $devIds  = array_unique(array_column($rows, 'developer_id'));
        $projIds = array_unique(array_column($rows, 'project_id'));
        $cliIds  = array_unique(array_column($rows, 'client_id'));

        $devMap  = [];
        $projMap = [];
        $cliMap  = [];
        if ($devIds)  foreach (Developer::find()->where(['id' => $devIds])->all()    as $m) $devMap[$m->id]  = $m;
        if ($projIds) foreach (Project::find()->where(['id' => $projIds])->all()     as $m) $projMap[$m->id] = $m;
        if ($cliIds)  foreach (ClientModel::find()->where(['id' => $cliIds])->all()  as $m) $cliMap[$m->id]  = $m;

        $fmtSecs = function (int $secs): string {
            return sprintf('%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60));
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Developer Cumulative Report');

        $headers = ['#', 'Developer', 'Project', 'Client', 'Total Time'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }

        $rowNum    = 2;
        $totalMins = 0;
        foreach ($rows as $i => $r) {
            $mins = (int)$r['group_total'];
            $totalMins += $mins;
            $sheet->setCellValue([1, $rowNum], $i + 1);
            $sheet->setCellValue([2, $rowNum], isset($devMap[$r['developer_id']]) ? $devMap[$r['developer_id']]->developer_name : '');
            $sheet->setCellValue([3, $rowNum], isset($projMap[$r['project_id']]) ? $projMap[$r['project_id']]->title : '');
            $sheet->setCellValue([4, $rowNum], isset($cliMap[$r['client_id']]) ? $cliMap[$r['client_id']]->client_name : '');
            $sheet->setCellValue([5, $rowNum], $fmtSecs($mins));
            $rowNum++;
        }

        $sheet->setCellValue([4, $rowNum], 'Total Hours');
        $sheet->setCellValue([5, $rowNum], $fmtSecs($totalMins));

        foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $filename = 'Developer_Cumulative_Reports' . ($fromDate ? '_' . $fromDate : '') . '.xls';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // ── Project Detail Drill-down (AJAX) ─────────────────────────────────────

    public function actionProjectDetail(int $id): string
    {
        $fromDate = Yii::$app->request->get('from_date', date('Y-m-01'));
        $toDate   = Yii::$app->request->get('to_date',   date('Y-m-t'));

        $fromDate = date('Y-m-d', strtotime($fromDate));
        $toDate   = date('Y-m-d', strtotime($toDate));

        $rows = WorkReport::find()
            ->select(['developer_id', 'SUM(total_time) AS dev_total'])
            ->where(['status' => 1, 'project_id' => $id])
            ->andWhere(['>=', 'date', $fromDate])
            ->andWhere(['<=', 'date', $toDate])
            ->groupBy('developer_id')
            ->orderBy(['dev_total' => SORT_DESC])
            ->asArray()
            ->all();

        $devMap = [];
        foreach ($rows as $r) {
            $devMap[$r['developer_id']] = null;
        }
        if ($devMap) {
            foreach (Developer::find()->where(['id' => array_keys($devMap)])->all() as $m) {
                $devMap[$m->id] = $m;
            }
        }

        $project    = Project::findOne($id);
        $grandTotal = array_sum(array_column($rows, 'dev_total'));

        return $this->renderPartial('_project-detail', compact(
            'rows', 'devMap', 'project', 'grandTotal', 'fromDate', 'toDate'
        ));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Load an active work report the current user may see, or 404 (also for
     * reports they may not see, so ids cannot be probed).
     *  - managers and anyone with the company-wide list (work-report/index): all reports
     *  - employees: their own reports
     *  - clients: reports filed against their company
     * With $forEdit, clients can never edit and employees only their own.
     */
    private function findModel(int $id, bool $forEdit = false): WorkReport
    {
        $model = WorkReport::findOne(['work_id' => $id, 'status' => 1]);
        $user  = Yii::$app->user->identity;
        $seesAll = TaskAccess::isManager() || \common\components\UserAccess::checkAccess('work-report/index');
        $own     = $model && $user && (int)$model->developer_id > 0 && (int)$model->developer_id === (int)$user->getDeveloperId();
        $client  = $model && $user && $user->isClient() && ($user->client->id ?? null) !== null && (int)$model->client_id === (int)$user->client->id;

        if (!$model || !($seesAll || $own || $client)) {
            throw new NotFoundHttpException('Work report not found.');
        }
        if ($forEdit && !($seesAll || $own)) {
            throw new ForbiddenHttpException('You can only change your own work reports.');
        }
        return $model;
    }

    /** Where to go after saving/deleting: the list this user can open. */
    private function listRoute(): array
    {
        return \common\components\UserAccess::checkAccess('work-report/index') ? ['index'] : ['my-work-reports'];
    }

    /** "HH:MM" from the form → seconds, or null when it is not a valid duration. */
    private function parseTotalTime($raw): ?int
    {
        if (!is_string($raw) || !preg_match('/^(\d{1,2}):([0-5]\d)$/', trim($raw), $m)) {
            return null;
        }
        return (int)$m[1] * 3600 + (int)$m[2] * 60;
    }

    // ── Export: Overall Report (Excel) ────────────────────────────────────────
    // Mirrors the on-screen `actionOverall()` rendering: one section per project
    // with totals, sub-rows per developer, and a grand total at the end.
    public function actionExportOverallReport(): void
    {
        $fromDate = Yii::$app->request->get('from_date');
        $toDate   = Yii::$app->request->get('to_date');
        $devIds   = (array)Yii::$app->request->get('developer_id', []);
        $projIds  = (array)Yii::$app->request->get('project_id',   []);

        if (!$fromDate || !$toDate) {
            Yii::$app->session->setFlash('error', 'Date range required for export.');
            $this->redirect(['overall']);
            return;
        }
        $fromDate = date('Y-m-d', strtotime($fromDate));
        $toDate   = date('Y-m-d', strtotime($toDate));

        $projQuery = WorkReport::find()
            ->select(['project_id', 'client_id'])->distinct()
            ->where(['status' => 1])
            ->andWhere(['between', 'date', $fromDate, $toDate]);
        if ($devIds)  { $projQuery->andWhere(['developer_id' => array_map('intval', $devIds)]); }
        if ($projIds) { $projQuery->andWhere(['project_id'   => array_map('intval', $projIds)]); }
        $projectRows = $projQuery->asArray()->all();

        $allProjIds = array_column($projectRows, 'project_id');
        $projMap = $cliMap = [];
        if ($allProjIds) {
            foreach (Project::find()->where(['id' => $allProjIds])->orderBy('title')->all() as $m) $projMap[$m->id] = $m;
        }
        $allCliIds = array_unique(array_column($projectRows, 'client_id'));
        if ($allCliIds) {
            foreach (ClientModel::find()->where(['id' => $allCliIds])->all() as $m) $cliMap[$m->id] = $m;
        }

        $fmt = function (int $s): string { return sprintf('%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60)); };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Overall Report');

        $sheet->setCellValue([1, 1], 'Overall Report — ' . date('d M Y', strtotime($fromDate)) . ' to ' . date('d M Y', strtotime($toDate)));
        $sheet->mergeCells([1, 1, 5, 1]);

        $headers = ['#', 'Project', 'Client', 'Developer', 'Hours'];
        foreach ($headers as $i => $h) { $sheet->setCellValue([$i + 1, 3], $h); }

        $rowNum     = 4;
        $projIdx    = 0;
        $grandTotal = 0;

        foreach ($projMap as $pid => $proj) {
            $projTotalQ = WorkReport::find()
                ->select('SUM(total_time)')
                ->where(['status' => 1, 'project_id' => $pid])
                ->andWhere(['between', 'date', $fromDate, $toDate]);
            if ($devIds) { $projTotalQ->andWhere(['developer_id' => array_map('intval', $devIds)]); }
            $projTotal = (int)$projTotalQ->scalar();

            $clientId = null;
            foreach ($projectRows as $pr) {
                if ($pr['project_id'] == $pid) { $clientId = $pr['client_id']; break; }
            }

            $sheet->setCellValue([1, $rowNum], ++$projIdx);
            $sheet->setCellValue([2, $rowNum], $proj->title);
            $sheet->setCellValue([3, $rowNum], $cliMap[$clientId]->client_name ?? '—');
            $sheet->setCellValue([4, $rowNum], 'Project Total');
            $sheet->setCellValue([5, $rowNum], $fmt($projTotal));
            $rowNum++;

            $devRowsQ = WorkReport::find()
                ->select(['developer_id', 'SUM(total_time) AS dev_secs'])
                ->where(['status' => 1, 'project_id' => $pid])
                ->andWhere(['between', 'date', $fromDate, $toDate])
                ->groupBy('developer_id')
                ->orderBy(['dev_secs' => SORT_DESC]);
            if ($devIds) { $devRowsQ->andWhere(['developer_id' => array_map('intval', $devIds)]); }
            foreach ($devRowsQ->asArray()->all() as $dr) {
                $dev = Developer::findOne($dr['developer_id']);
                $sheet->setCellValue([4, $rowNum], $dev ? $dev->developer_name : '—');
                $sheet->setCellValue([5, $rowNum], $fmt((int)$dr['dev_secs']));
                $rowNum++;
            }

            $grandTotal += $projTotal;
            $rowNum++;
        }

        $sheet->setCellValue([4, $rowNum], 'Grand Total');
        $sheet->setCellValue([5, $rowNum], $fmt($grandTotal));

        foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $filename = 'Overall_Report_' . $fromDate . '_to_' . $toDate . '.xls';
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
