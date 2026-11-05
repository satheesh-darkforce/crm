<?php

namespace backend\controllers;

use backend\assets\ProjectAsset;
use backend\models\ClientModel;
use backend\models\Developer;
use backend\models\Project;
use backend\models\WorkReport;
use Yii;
use yii\data\ArrayDataProvider;
use yii\data\SqlDataProvider;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend CRUD for Project.
 *
 * project_status: 1=In progress, 2=Waiting to assign, 3=Completed, 4=Hold (Yii1).
 * Phases/milestones are child `project` rows (main_project_id = parent id).
 * total_estimation_hours and milestone_hours are stored in SECONDS.
 */
class ProjectController extends Controller
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'delete'              => ['POST'],
                    'change-status'       => ['POST'],
                    'set-amc'             => ['POST'],
                    'set-live-date'       => ['POST'],
                    'change-phase-status' => ['POST'],
                    'delete-phase'        => ['POST'],
                ],
            ],
        ]);
    }

    /** Full pages get the Projects stylesheet/script. */
    public function render($view, $params = []): string
    {
        ProjectAsset::register($this->getView());
        return parent::render($view, $params);
    }

    public function actionIndex(): string
    {
        $db = Yii::$app->db;
        $f  = $this->stringParams(['id', 'title', 'project_status', 'project_type', 'client_id', 'cost_range', 'from_date', 'to_date']);

        // Values that cannot match anything are cleared and explained.
        $ignored = [];
        foreach (['project_status' => Project::statusOptions(), 'project_type' => Project::typeOptions(),
                  'cost_range' => [1 => 1, 2 => 2, 3 => 3, 4 => 4]] as $key => $allowed) {
            if ($f[$key] !== '' && !isset($allowed[(int)$f[$key]])) {
                $ignored[] = str_replace('_', ' ', $key);
                $f[$key] = '';
            }
        }
        if ($f['client_id'] !== '' && !ctype_digit($f['client_id'])) { $ignored[] = 'client'; $f['client_id'] = ''; }
        foreach (['from_date', 'to_date'] as $key) {
            if ($f[$key] !== '' && !$this->isDate($f[$key])) { $ignored[] = str_replace('_', ' ', $key); $f[$key] = ''; }
        }
        if ($ignored) {
            Yii::$app->session->setFlash('warning', 'Ignored an invalid search value (' . implode(', ', $ignored) . ').');
        }
        if ($f['from_date'] !== '' && $f['to_date'] !== '' && $f['to_date'] < $f['from_date']) {
            [$f['from_date'], $f['to_date']] = [$f['to_date'], $f['from_date']]; // entered the wrong way round
        }

        $where  = 'p.status = 1 AND (p.main_project_id IS NULL OR p.main_project_id = 0)';
        $params = [];
        if ($f['id'] !== '')             { $where .= ' AND CAST(p.id AS CHAR) LIKE :pid'; $params[':pid'] = '%' . $f['id'] . '%'; }
        if ($f['title'] !== '')          { $where .= ' AND p.title LIKE :title';    $params[':title'] = '%' . $f['title'] . '%'; }
        if ($f['project_status'] !== '') { $where .= ' AND p.project_status = :ps'; $params[':ps']    = (int)$f['project_status']; }
        if ($f['project_type'] !== '')   { $where .= ' AND p.project_type = :pt';   $params[':pt']    = (int)$f['project_type']; }
        if ($f['client_id'] !== '')      { $where .= ' AND p.client_id = :cid';     $params[':cid']   = (int)$f['client_id']; }
        // Project-creation date range (p.date is a datetime; compare on the date part).
        if ($f['from_date'] !== '') { $where .= ' AND p.date >= :fromD'; $params[':fromD'] = $f['from_date'] . ' 00:00:00'; }
        if ($f['to_date'] !== '')   { $where .= ' AND p.date <= :toD';   $params[':toD']   = $f['to_date'] . ' 23:59:59'; }
        // Yii1 ranged filter on total_estimation_cost: 1=≤10000, 2=10000-50000, 3=50000-100000, 4=>100000.
        $where .= [
            '1' => ' AND p.total_estimation_cost <= 10000',
            '2' => ' AND p.total_estimation_cost > 10000  AND p.total_estimation_cost <= 50000',
            '3' => ' AND p.total_estimation_cost > 50000  AND p.total_estimation_cost <= 100000',
            '4' => ' AND p.total_estimation_cost > 100000',
        ][$f['cost_range']] ?? '';

        // Pre-aggregate work_report and task in subqueries so we don't fan-out the main row set.
        $sqlSelect = "
            SELECT p.id, p.title, p.date, p.project_status, p.project_type,
                   p.total_estimation_hours, p.total_estimation_cost,
                   c.client_name, c.id AS client_id,
                   COALESCE(wr_agg.cur_month_secs, 0) AS cur_month_secs,
                   COALESCE(t_agg.open_tasks, 0)      AS open_tasks
            FROM project p
            LEFT JOIN client c ON c.id = p.client_id
            LEFT JOIN (
                SELECT project_id, SUM(total_time) AS cur_month_secs
                FROM work_report
                WHERE status = 1
                  AND date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                  AND date <  DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')
                GROUP BY project_id
            ) wr_agg ON wr_agg.project_id = p.id
            LEFT JOIN (
                SELECT project_id, COUNT(*) AS open_tasks
                FROM task
                WHERE status = 1 AND task_status IN (1,2,3)
                GROUP BY project_id
            ) t_agg ON t_agg.project_id = p.id
            WHERE $where
            ORDER BY p.id DESC
        ";

        $dataProvider = new SqlDataProvider([
            'sql'        => $sqlSelect,
            'params'     => $params,
            'totalCount' => (int)$db->createCommand("SELECT COUNT(*) FROM project p WHERE $where", $params)->queryScalar(),
            'pagination' => ['pageSize' => 20],
        ]);

        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('index', compact('dataProvider', 'clients', 'f'));
    }

    /**
     * AJAX endpoint feeding the Select2 title filter on the index page.
     * Returns up to 20 matching active project titles for the typed query.
     */
    public function actionSearchTitle(): Response
    {
        $q = $this->stringParams(['q'])['q'];
        $rows = $q === '' ? [] : Project::find()
            ->select(['title'])
            ->where(['status' => 1])
            ->andWhere(['like', 'title', $q])
            ->groupBy('title')
            ->orderBy('title')
            ->limit(20)
            ->column();
        return $this->asJson(['results' => array_map(fn($t) => ['id' => $t, 'text' => $t], $rows)]);
    }

    /**
     * "All Projects" — mirrors Yii1's project/allproject (Project::developer_search).
     * Active projects only, ordered by title ASC, with filters for Project Code,
     * Project Name, Project Type, and Client (matched by name).
     */
    public function actionAllProject(): string
    {
        $f = $this->stringParams(['id', 'title', 'project_type', 'client_name']);
        if ($f['project_type'] !== '' && !isset(Project::typeOptions()[(int)$f['project_type']])) {
            $f['project_type'] = '';
        }

        $sql = "SELECT p.id, p.title, p.project_type, c.client_name
                FROM project p
                LEFT JOIN client c ON c.id = p.client_id
                WHERE p.status = 1";
        $params = [];
        if ($f['id'] !== '')           { $sql .= ' AND CAST(p.id AS CHAR) LIKE :id'; $params[':id'] = '%' . $f['id'] . '%'; }
        if ($f['title'] !== '')        { $sql .= ' AND p.title LIKE :title';         $params[':title'] = '%' . $f['title'] . '%'; }
        if ($f['project_type'] !== '') { $sql .= ' AND p.project_type = :ptype';     $params[':ptype'] = (int)$f['project_type']; }
        if ($f['client_name'] !== '')  { $sql .= ' AND c.client_name LIKE :cname';   $params[':cname'] = '%' . $f['client_name'] . '%'; }
        $sql .= ' ORDER BY p.title ASC';

        $dataProvider = new ArrayDataProvider([
            'allModels'  => Yii::$app->db->createCommand($sql, $params)->queryAll(),
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('all-project', compact('dataProvider', 'f'));
    }

    public function actionView(int $id): string
    {
        $project = $this->findModel($id);
        $db      = Yii::$app->db;
        $range   = $this->stringParams(['from_date', 'to_date']);
        $fromDate = $this->isDate($range['from_date']) ? $range['from_date'] : '';
        $toDate   = $this->isDate($range['to_date']) ? $range['to_date'] : '';
        if (($range['from_date'] !== '' && $fromDate === '') || ($range['to_date'] !== '' && $toDate === '')) {
            Yii::$app->session->setFlash('warning', 'Ignored an invalid date. Dates must be YYYY-MM-DD.');
        }
        if ($fromDate !== '' && $toDate !== '' && $toDate < $fromDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        // ── Phase records for this project ────────────────────────────────
        $phases = Project::find()
            ->where(['main_project_id' => $id, 'status' => 1])
            ->orderBy(['phase_value' => SORT_ASC, 'milestone_value' => SORT_ASC])
            ->all();
        $hasPhases = !empty($phases);

        $dateWhere  = '';
        $dateParams = [];
        if ($fromDate !== '') { $dateWhere .= ' AND wr.date >= :from'; $dateParams[':from'] = $fromDate; }
        if ($toDate !== '')   { $dateWhere .= ' AND wr.date <= :to';   $dateParams[':to']   = $toDate; }

        // ── Phase-based hours (grouped by phase+milestone) ────────────────
        $phaseHours = [];
        if ($hasPhases) {
            $phaseHours = $db->createCommand("
                SELECT p.phase_value, p.milestone_value, SUM(wr.total_time) AS total_secs
                FROM work_report wr
                JOIN project p ON p.id = wr.project_id
                WHERE wr.status = 1 AND p.main_project_id = :pid AND p.status = 1 $dateWhere
                GROUP BY p.phase_value, p.milestone_value
                ORDER BY p.phase_value ASC, p.milestone_value ASC
            ", [':pid' => $id] + $dateParams)->queryAll();
        }

        // ── Employee-based hours ──────────────────────────────────────────
        $empHours = $hasPhases
            ? $db->createCommand("
                SELECT d.developer_name, p.phase_value, p.milestone_value, SUM(wr.total_time) AS total_secs
                FROM work_report wr
                JOIN developer d ON d.id = wr.developer_id
                JOIN project p ON p.id = wr.project_id
                WHERE wr.status = 1 AND p.main_project_id = :pid AND p.status = 1 $dateWhere
                GROUP BY wr.developer_id, p.phase_value, p.milestone_value
                ORDER BY d.developer_name ASC, p.phase_value ASC, p.milestone_value ASC
            ", [':pid' => $id] + $dateParams)->queryAll()
            : $db->createCommand("
                SELECT d.developer_name, SUM(wr.total_time) AS total_secs
                FROM work_report wr
                JOIN developer d ON d.id = wr.developer_id
                WHERE wr.project_id = :pid AND wr.status = 1 $dateWhere
                GROUP BY wr.developer_id
                ORDER BY total_secs DESC
            ", [':pid' => $id] + $dateParams)->queryAll();

        // ── Invoices ──────────────────────────────────────────────────────
        $invoices = $db->createCommand("
            SELECT id, invoice_no, invoice_date, currency, paid_amount,
                   GREATEST(0, (net_total - COALESCE(tds_amount,0)) - paid_amount) AS pending_amount
            FROM invoice
            WHERE project_id = :pid AND status = 1
            ORDER BY id DESC
        ", [':pid' => $id])->queryAll();

        // 10-row paginated employee hours (Yii1 parity); the footer total covers ALL rows.
        $empHoursProvider = new ArrayDataProvider([
            'allModels'  => $empHours,
            'pagination' => ['pageSize' => 10, 'pageParam' => 'emp-page'],
            'sort'       => false,
        ]);
        $totalEmpSecsAll = (int)array_sum(array_column($empHours, 'total_secs'));

        $model = $project;
        return $this->render('view', compact(
            'model', 'phases', 'hasPhases', 'phaseHours',
            'empHoursProvider', 'totalEmpSecsAll',
            'invoices', 'fromDate', 'toDate'
        ));
    }

    public function actionCreate(): string|Response
    {
        $project = new Project(['scenario' => Project::SCENARIO_FORM]);

        if (Yii::$app->request->isPost && $project->load(Yii::$app->request->post())) {
            $project->date           = date('Y-m-d H:i:s');
            $project->lastupdate     = null;
            $project->status         = 1;
            $project->project_status = Project::PROJECT_STATUS_ACTIVE;
            if ($this->saveProject($project)) {
                Yii::$app->session->setFlash('success', 'Project created successfully.');
                return $this->redirect(['view', 'id' => $project->id]);
            }
        }

        return $this->render('create', $this->formData($project));
    }

    public function actionUpdate(int $id): string|Response
    {
        $project = $this->findModel($id);
        $project->scenario = Project::SCENARIO_FORM;

        if (Yii::$app->request->isPost && $project->load(Yii::$app->request->post())) {
            $project->lastupdate = date('Y-m-d H:i:s');
            if ($this->saveProject($project)) {
                Yii::$app->session->setFlash('success', 'Project updated successfully.');
                return $this->redirect(['view', 'id' => $project->id]);
            }
        }

        return $this->render('update', $this->formData($project));
    }

    public function actionDelete(int $id): Response
    {
        $project         = $this->findModel($id);
        $project->status = 0;
        $project->save(false);

        Yii::$app->session->setFlash('success', "Project \"{$project->title}\" was deleted.");
        return $this->redirect(['index']);
    }

    /**
     * AJAX: Change project status from listing page (matches Yii1 changestatus).
     */
    public function actionChangeStatus(): Response
    {
        $id     = (int)Yii::$app->request->post('id');
        $status = (int)Yii::$app->request->post('status');

        if (!isset(Project::statusOptions()[$status])) {
            return $this->asJson(['success' => false, 'message' => 'Choose a valid status.']);
        }
        $project = Project::findOne(['id' => $id, 'status' => 1]);
        if (!$project || $project->main_project_id) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['success' => false, 'message' => 'This project no longer exists. Refresh the page.']);
        }

        Project::updateAll(['project_status' => $status, 'lastupdate' => date('Y-m-d H:i:s')], ['id' => $id]);
        return $this->asJson([
            'success'   => true,
            'message'   => 'Status changed to ' . Project::statusOptions()[$status] . '.',
            'completed' => $status === Project::PROJECT_STATUS_COMPLETED,
        ]);
    }

    /**
     * AJAX: enrol a completed project in the AMC program (project.amc = 1).
     */
    public function actionSetAmc(): Response
    {
        $project = Project::findOne(['id' => (int)Yii::$app->request->post('id'), 'status' => 1]);
        if (!$project) {
            return $this->asJson(['success' => false, 'message' => 'Project not found.']);
        }
        if ((int)$project->project_status !== Project::PROJECT_STATUS_COMPLETED) {
            return $this->asJson(['success' => false, 'message' => 'Only completed projects can have AMC.']);
        }
        Project::updateAll(['amc' => 1], ['id' => $project->id]);
        return $this->asJson(['success' => true]);
    }

    /**
     * AJAX: store the project's live/updated date (asked when a project is marked
     * Completed, or from "Start AMC" on the project page). The AMC start date
     * later defaults to this value.
     */
    public function actionSetLiveDate(): Response
    {
        $project = Project::findOne(['id' => (int)Yii::$app->request->post('id'), 'status' => 1]);
        if (!$project) {
            return $this->asJson(['success' => false, 'message' => 'Project not found.']);
        }
        $date = $this->stringParams(['live_updated_date'], true)['live_updated_date'];
        if (!$this->isDate($date)) {
            return $this->asJson(['success' => false, 'message' => 'Enter a valid date (YYYY-MM-DD).']);
        }
        if ($date > date('Y-m-d')) {
            return $this->asJson(['success' => false, 'message' => 'The live date cannot be in the future.']);
        }
        $project->live_updated_date = $date;
        $project->save(false, ['live_updated_date']);
        return $this->asJson(['success' => true, 'message' => 'Live date saved.']);
    }

    /**
     * AJAX: Change milestone status for a phase sub-project (matches Yii1 changephasestatus).
     */
    public function actionChangePhaseStatus(): Response
    {
        $req    = Yii::$app->request;
        $status = (int)$req->post('status_value');
        if (!isset(Project::milestoneStatusOptions()[$status])) {
            return $this->asJson(['success' => false, 'message' => 'Choose a valid milestone status.']);
        }
        $record = Project::find()->where([
            'main_project_id' => (int)$req->post('project_id'),
            'phase_value'     => (int)$req->post('phase_value'),
            'milestone_value' => (int)$req->post('milestone_value'),
            'status'          => 1,
        ])->one();

        if (!$record) {
            return $this->asJson(['success' => false, 'message' => 'This milestone no longer exists. Refresh the page.']);
        }
        $record->milestone_status = $status;
        $record->save(false);
        return $this->asJson(['success' => true, 'message' => 'Milestone status updated.']);
    }

    /**
     * AJAX: Delete a phase sub-project and soft-delete its work reports (matches Yii1 deletephase).
     */
    public function actionDeletePhase(int $id): Response
    {
        $phase = Project::findOne(['id' => $id, 'status' => 1]);
        if (!$phase || !$phase->main_project_id) {
            return $this->asJson(['success' => false, 'message' => 'This milestone no longer exists. Refresh the page.']);
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            $phase->status = 0;
            $phase->save(false);
            $reports = WorkReport::updateAll(['status' => 0], ['project_id' => $id]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Delete phase failed: ' . $e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return $this->asJson(['success' => false, 'message' => 'The milestone could not be deleted. Please try again.']);
        }
        return $this->asJson(['success' => true, 'message' => 'Milestone deleted' . ($reports ? " with $reports work report(s)." : '.')]);
    }

    /**
     * Developer-facing project list — only projects where the current user has tasks assigned.
     */
    public function actionDeveloperProjects(): string
    {
        $rows = Yii::$app->db->createCommand("
            SELECT DISTINCT p.id, p.title, p.date, p.project_status, p.project_type,
                   c.client_name
            FROM project p
            LEFT JOIN client c ON c.id = p.client_id
            INNER JOIN task t ON t.project_id = p.id AND t.assigned_to = :uid AND t.status = 1
            WHERE p.status = 1
            ORDER BY p.id DESC
        ", [':uid' => (int)Yii::$app->user->id])->queryAll();

        $dataProvider = new ArrayDataProvider([
            'allModels'  => $rows,
            'pagination' => ['pageSize' => 25],
        ]);

        return $this->render('developer-projects', compact('dataProvider'));
    }

    /**
     * AJAX: Return projects for a given client (matches Yii1 getprojectbyclient).
     */
    public function actionGetProjectsByClient(): Response
    {
        $clientId = (int)Yii::$app->request->post('client_id', Yii::$app->request->get('client_id', 0));
        if (!$clientId) {
            return $this->asJson([]);
        }
        $rows = Project::find()
            ->select(['id', 'title'])
            ->where(['status' => 1, 'client_id' => $clientId])
            ->andWhere(['IS', 'main_project_id', null])
            ->orderBy('title')
            ->asArray()
            ->all();
        return $this->asJson(array_column($rows, 'title', 'id'));
    }

    /**
     * AJAX: Return distinct phase_value for sub-projects of a given project (matches Yii1 getphasevalue).
     */
    public function actionGetPhaseValue(): Response
    {
        return $this->asJson($this->childValues('phase_value'));
    }

    /**
     * AJAX: Return distinct milestone_value for sub-projects of a given project (matches Yii1 getmilestonevalue).
     */
    public function actionGetMilestoneValue(): Response
    {
        return $this->asJson($this->childValues('milestone_value'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Shared create/update save: estimation hours (entered in hours, stored in
     * seconds) and cost, employees, and new phase/milestone rows — all in one
     * transaction so a failed phase never leaves a half-saved project.
     */
    private function saveProject(Project $project): bool
    {
        $post = Yii::$app->request->post('Project', []);
        $post = is_array($post) ? $post : [];

        // Hours → seconds. A non-numeric entry is kept as-is so the model's
        // `number` rule reports it instead of silently becoming 0.
        $hoursRaw = is_string($post['total_estimation_hours'] ?? null) ? trim($post['total_estimation_hours']) : '';
        $project->total_estimation_hours = $hoursRaw === '' ? null
            : (is_numeric($hoursRaw) ? (int)round((float)$hoursRaw * 3600) : $hoursRaw);

        $costRaw = is_string($post['total_estimation_cost'] ?? null) ? trim($post['total_estimation_cost']) : '';
        if ($costRaw !== '') {
            $project->total_estimation_cost = $costRaw;
        } else {
            // Non-productive projects default to 0 cost.
            $project->total_estimation_cost = (int)$project->project_type === Project::TYPE_NON_PRODUCTIVE ? 0 : null;
        }

        // Employees (JSON list of developer ids, Yii1 format) — only real developers.
        $empIds = array_values(array_filter((array)($post['employee_id'] ?? []), fn($v) => is_string($v) && ctype_digit($v)));
        $project->employee_id = $empIds ? json_encode($empIds) : null;

        // validate() clears errors, so run it before the phase checks add theirs.
        $valid  = $project->validate();
        $phases = $this->readPhases($project);
        if ($phases === null || !$valid) {
            return false;
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$project->save(false)) {
                throw new \RuntimeException('project');
            }
            foreach ($phases as [$pv, $mv, $secs, $ms]) {
                $child = new Project();
                $child->client_id              = $project->client_id;
                $child->title                  = $project->title . ' P' . $pv . ' M' . $mv;
                $child->description            = $project->description;
                $child->project_type           = $project->project_type;
                $child->industry_type          = $project->industry_type;
                $child->main_project_id        = $project->id;
                $child->phase_value            = $pv;
                $child->milestone_value        = $mv;
                $child->milestone_hours        = $secs;
                $child->milestone_status       = $ms;
                $child->total_estimation_cost  = $project->total_estimation_cost;
                $child->total_estimation_hours = $secs;
                $child->employee_id            = $project->employee_id;
                $child->date                   = date('Y-m-d H:i:s');
                $child->lastupdate             = null;
                $child->status                 = 1;
                $child->project_status         = Project::PROJECT_STATUS_ACTIVE;
                if (!$child->save(false)) {
                    throw new \RuntimeException('phase');
                }
            }
            $tx->commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Project save failed: ' . $e->getMessage(), __METHOD__);
            $project->addError('title', 'The project could not be saved. Please try again — nothing was changed.');
            return false;
        }
    }

    /**
     * New phase/milestone rows posted by the form (phase_value[], milestone_value[p][],
     * hours_value[p][], status_value[p][]). Returns [[phase, milestone, seconds, status], …],
     * or null after adding an error to $project. Mirrors the form's JS checks.
     */
    private function readPhases(Project $project): ?array
    {
        $req        = Yii::$app->request;
        $phaseList  = (array)$req->post('phase_value', []);
        $milestones = (array)$req->post('milestone_value', []);
        $hours      = (array)$req->post('hours_value', []);
        $statuses   = (array)$req->post('status_value', []);

        // Milestones already saved for this project.
        $existing = [];
        $existingSecs = 0;
        if (!$project->isNewRecord) {
            foreach (Project::find()->where(['main_project_id' => $project->id, 'status' => 1])->all() as $row) {
                $existing[(int)$row->phase_value . '-' . (int)$row->milestone_value] = true;
                $existingSecs += (int)$row->milestone_hours;
            }
        }

        $rows = [];
        $seen = $existing;
        $newSecs = 0;
        foreach (array_unique($phaseList) as $pv) {
            if (!is_scalar($pv) || !ctype_digit((string)$pv) || (int)$pv < 1 || (int)$pv > Project::MAX_PHASE) {
                $project->addError('title', 'Phases must be numbers from 1 to ' . Project::MAX_PHASE . '.');
                return null;
            }
            $pv = (int)$pv;
            foreach ((array)($milestones[$pv] ?? []) as $i => $mv) {
                $h  = $hours[$pv][$i] ?? null;
                $ms = $statuses[$pv][$i] ?? 0;
                if (!is_scalar($mv) || !ctype_digit((string)$mv) || (int)$mv < 1 || (int)$mv > Project::MAX_MILESTONE) {
                    $project->addError('title', "Phase $pv: milestones must be numbers from 1 to " . Project::MAX_MILESTONE . '.');
                    return null;
                }
                if (!is_scalar($h) || !is_numeric($h) || (float)$h <= 0 || (float)$h > Project::MAX_ESTIMATION_HOURS) {
                    $project->addError('title', "Phase $pv, milestone $mv: hours must be greater than 0 and at most " . number_format(Project::MAX_ESTIMATION_HOURS) . '.');
                    return null;
                }
                if (!is_scalar($ms) || !isset(Project::milestoneStatusOptions()[(int)$ms])) {
                    $project->addError('title', "Phase $pv, milestone $mv: choose a valid status.");
                    return null;
                }
                $key = $pv . '-' . (int)$mv;
                if (isset($seen[$key])) {
                    if (isset($existing[$key])) {
                        continue; // already saved (Yii1 behaviour: only new rows are added)
                    }
                    $project->addError('title', "Phase $pv, milestone $mv is listed twice.");
                    return null;
                }
                $seen[$key] = true;
                $secs = (int)round((float)$h * 3600);
                $newSecs += $secs;
                $rows[] = [$pv, (int)$mv, $secs, (int)$ms];
            }
        }

        // With milestones, the project total must equal their sum (the form shows the same rule).
        $totalSecs = is_numeric($project->total_estimation_hours) ? (int)$project->total_estimation_hours : 0;
        $hoursChanged = $project->isNewRecord || (int)$project->getOldAttribute('total_estimation_hours') !== $totalSecs;
        if (($rows || ($hoursChanged && $existing)) && $totalSecs > 0 && abs(($existingSecs + $newSecs) - $totalSecs) > 36) {
            $project->addError('total_estimation_hours', sprintf(
                'Total Estimation Hours (%s) must equal the sum of milestone hours (%s).',
                rtrim(rtrim(number_format($totalSecs / 3600, 2, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format(($existingSecs + $newSecs) / 3600, 2, '.', ''), '0'), '.')
            ));
            return null;
        }
        return $rows;
    }

    private function formData(Project $project): array
    {
        return [
            'project'        => $project,
            'clients'        => ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all(),
            'developers'     => Developer::find()->where(['status' => 1])->orderBy('developer_name')->all(),
            'existingPhases' => $project->isNewRecord ? [] : Project::find()
                ->where(['main_project_id' => $project->id, 'status' => 1])
                ->orderBy(['phase_value' => SORT_ASC, 'milestone_value' => SORT_ASC])
                ->all(),
        ];
    }

    /** Distinct child-project values (phase / milestone) for a project id posted or in the query. */
    private function childValues(string $column): array
    {
        $projectId = (int)Yii::$app->request->post('project_id', Yii::$app->request->get('project_id', 0));
        if (!$projectId) {
            return [];
        }
        $values = Project::find()
            ->select([$column])
            ->where(['status' => 1, 'main_project_id' => $projectId])
            ->andWhere(['IS NOT', $column, null])
            ->groupBy($column)
            ->orderBy($column)
            ->column();
        return array_combine($values, $values) ?: [];
    }

    /** Trimmed string request values; arrays from tampered URLs become ''. */
    private function stringParams(array $keys, bool $post = false): array
    {
        $req = Yii::$app->request;
        $out = [];
        foreach ($keys as $key) {
            $v = $post ? $req->post($key, '') : $req->get($key, '');
            $out[$key] = is_string($v) ? mb_substr(trim($v), 0, 255) : '';
        }
        return $out;
    }

    private function isDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }

    private function findModel(int $id): Project
    {
        $model = Project::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Project not found.');
        }
        return $model;
    }
}
