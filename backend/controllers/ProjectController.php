<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\models\Developer;
use backend\models\Project;
use backend\models\ProjectAttachments;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\data\SqlDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Backend CRUD for Project.
 */
class ProjectController extends Controller
{
    public function actionIndex(): string
    {
        $db       = Yii::$app->db;
        $idF      = trim((string) Yii::$app->request->get('id', ''));
        $title    = Yii::$app->request->get('title', '');
        $statusF  = Yii::$app->request->get('project_status', '');
        $typeF    = Yii::$app->request->get('project_type', '');
        $clientF  = Yii::$app->request->get('client_id', '');
        $costF    = (string) Yii::$app->request->get('cost_range', '');
        $fromF    = trim((string) Yii::$app->request->get('from_date', ''));
        $toF      = trim((string) Yii::$app->request->get('to_date', ''));

        // Accept only well-formed YYYY-MM-DD dates; anything else is treated as "no filter".
        $isDate = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)
            && (($t = strtotime($d)) !== false) && date('Y-m-d', $t) === $d;
        if ($fromF !== '' && !$isDate($fromF)) { $fromF = ''; }
        if ($toF   !== '' && !$isDate($toF))   { $toF   = ''; }
        // Server-side guard mirroring the UI: end date must not precede start date.
        if ($fromF !== '' && $toF !== '' && $toF < $fromF) {
            Yii::$app->session->setFlash('error', 'End date cannot be earlier than start date.');
            $fromF = $toF = '';
        }

        $where  = 'p.status = 1 AND (p.main_project_id IS NULL OR p.main_project_id = 0)';
        $params = [];
        if ($idF !== '')     { $where .= ' AND CAST(p.id AS CHAR) LIKE :pid'; $params[':pid'] = '%' . $idF . '%'; }
        if ($title !== '')   { $where .= ' AND p.title LIKE :title';    $params[':title'] = '%' . $title . '%'; }
        if ($statusF !== '') { $where .= ' AND p.project_status = :ps'; $params[':ps']    = (int)$statusF; }
        if ($typeF !== '')   { $where .= ' AND p.project_type = :pt';   $params[':pt']    = (int)$typeF; }
        if ($clientF !== '') { $where .= ' AND p.client_id = :cid';     $params[':cid']   = (int)$clientF; }
        // Project-creation date range (p.date is a datetime; compare on the date part).
        if ($fromF !== '') { $where .= ' AND p.date >= :fromD'; $params[':fromD'] = $fromF . ' 00:00:00'; }
        if ($toF   !== '') { $where .= ' AND p.date <= :toD';   $params[':toD']   = $toF   . ' 23:59:59'; }
        // Yii1 ranged filter on total_estimation_cost: 1=≤10000, 2=10000-50000,
        // 3=50000-100000, 4=>100000. Server-side ENUM check so we never trust
        // user-supplied operators.
        if (in_array($costF, ['1', '2', '3', '4'], true)) {
            if ($costF === '1') { $where .= ' AND p.total_estimation_cost <= 10000'; }
            if ($costF === '2') { $where .= ' AND p.total_estimation_cost > 10000  AND p.total_estimation_cost <= 50000'; }
            if ($costF === '3') { $where .= ' AND p.total_estimation_cost > 50000  AND p.total_estimation_cost <= 100000'; }
            if ($costF === '4') { $where .= ' AND p.total_estimation_cost > 100000'; }
        }

        // Pre-aggregate work_report and task in subqueries so we don't fan-out the main row set.
        // Range-based date filter keeps an index on work_report.date usable.
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

        $totalCount = (int)$db->createCommand("SELECT COUNT(*) FROM project p WHERE $where", $params)->queryScalar();

        $dataProvider = new SqlDataProvider([
            'sql'        => $sqlSelect,
            'params'     => $params,
            'totalCount' => $totalCount,
            'pagination' => ['pageSize' => 20],
        ]);

        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('index', compact('dataProvider', 'clients', 'idF', 'title', 'statusF', 'typeF', 'clientF', 'costF', 'fromF', 'toF'));
    }

    /**
     * AJAX endpoint feeding the Select2 title filter on the index page.
     * Returns up to 20 matching active project titles for the typed query.
     */
    public function actionSearchTitle(): Response
    {
        $q = trim((string) Yii::$app->request->get('q', ''));
        $rows = [];
        if ($q !== '') {
            $rows = Project::find()
                ->select(['title'])
                ->where(['status' => 1])
                ->andWhere(['like', 'title', $q])
                ->groupBy('title')
                ->orderBy('title')
                ->limit(20)
                ->column();
        }
        $results = array_map(fn($t) => ['id' => $t, 'text' => $t], $rows);
        return $this->asJson(['results' => $results]);
    }

    /**
     * "All Projects" — mirrors Yii1's project/allproject (Project::developer_search).
     * Active projects only, ordered by title ASC, page size 50, with column filters
     * for Project Code, Project Name, Project Type, and Client (matched by name).
     */
    public function actionAllProject(): string
    {
        $req = Yii::$app->request;
        $f = [
            'id'           => trim((string) $req->get('id', '')),
            'title'        => trim((string) $req->get('title', '')),
            'project_type' => trim((string) $req->get('project_type', '')),
            'client_name'  => trim((string) $req->get('client_name', '')),
        ];

        $sql = "SELECT p.id, p.title, p.project_type, c.client_name
                FROM project p
                LEFT JOIN client c ON c.id = p.client_id
                WHERE p.status = 1";
        $params = [];
        if ($f['id'] !== '') {
            $sql .= " AND CAST(p.id AS CHAR) LIKE :id";
            $params[':id'] = '%' . $f['id'] . '%';
        }
        if ($f['title'] !== '') {
            $sql .= " AND p.title LIKE :title";
            $params[':title'] = '%' . $f['title'] . '%';
        }
        if ($f['project_type'] !== '' && in_array($f['project_type'], ['1', '2'], true)) {
            $sql .= " AND p.project_type = :ptype";
            $params[':ptype'] = (int) $f['project_type'];
        }
        if ($f['client_name'] !== '') {
            $sql .= " AND c.client_name LIKE :cname";
            $params[':cname'] = '%' . $f['client_name'] . '%';
        }
        $sql .= " ORDER BY p.title ASC";

        $rows = Yii::$app->db->createCommand($sql, $params)->queryAll();

        $dataProvider = new ArrayDataProvider([
            'allModels'  => $rows,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('all-project', compact('dataProvider', 'f'));
    }

    public function actionView(int $id): string
    {
        $project  = $this->findModel($id);
        $db       = Yii::$app->db;
        $fromDate = Yii::$app->request->get('from_date', '');
        $toDate   = Yii::$app->request->get('to_date', '');

        // ── Phase records for this project ────────────────────────────────
        $phases = Project::find()
            ->where(['main_project_id' => $id, 'status' => 1])
            ->orderBy(['phase_value' => SORT_ASC, 'milestone_value' => SORT_ASC])
            ->all();
        $hasPhases = !empty($phases);

        // ── Phase-based hours (grouped by phase+milestone) ────────────────
        $phaseHours = [];
        if ($hasPhases) {
            $phWhere  = 'wr.status = 1 AND p.main_project_id = :pid AND p.status = 1';
            $phParams = [':pid' => $id];
            if ($fromDate !== '') { $phWhere .= ' AND wr.date >= :from'; $phParams[':from'] = $fromDate; }
            if ($toDate   !== '') { $phWhere .= ' AND wr.date <= :to';   $phParams[':to']   = $toDate; }

            $phaseHours = $db->createCommand("
                SELECT p.phase_value, p.milestone_value, SUM(wr.total_time) AS total_secs
                FROM work_report wr
                JOIN project p ON p.id = wr.project_id
                WHERE $phWhere
                GROUP BY p.phase_value, p.milestone_value
                ORDER BY p.phase_value ASC, p.milestone_value ASC
            ", $phParams)->queryAll();
        }

        // ── Employee-based hours ──────────────────────────────────────────
        if ($hasPhases) {
            // With phases: group by developer + phase + milestone
            $empWhere  = 'wr.status = 1 AND p.main_project_id = :pid AND p.status = 1';
            $empParams = [':pid' => $id];
            if ($fromDate !== '') { $empWhere .= ' AND wr.date >= :from'; $empParams[':from'] = $fromDate; }
            if ($toDate   !== '') { $empWhere .= ' AND wr.date <= :to';   $empParams[':to']   = $toDate; }

            $empHours = $db->createCommand("
                SELECT d.developer_name, p.phase_value, p.milestone_value, SUM(wr.total_time) AS total_secs
                FROM work_report wr
                JOIN developer d ON d.id = wr.developer_id
                JOIN project p ON p.id = wr.project_id
                WHERE $empWhere
                GROUP BY wr.developer_id, p.phase_value, p.milestone_value
                ORDER BY d.developer_name ASC, p.phase_value ASC, p.milestone_value ASC
            ", $empParams)->queryAll();
        } else {
            // No phases: group by developer only
            $empWhere  = 'wr.project_id = :pid AND wr.status = 1';
            $empParams = [':pid' => $id];
            if ($fromDate !== '') { $empWhere .= ' AND wr.date >= :from'; $empParams[':from'] = $fromDate; }
            if ($toDate   !== '') { $empWhere .= ' AND wr.date <= :to';   $empParams[':to']   = $toDate; }

            $empHours = $db->createCommand("
                SELECT d.developer_name, SUM(wr.total_time) AS total_secs
                FROM work_report wr
                JOIN developer d ON d.id = wr.developer_id
                WHERE $empWhere
                GROUP BY wr.developer_id
                ORDER BY total_secs DESC
            ", $empParams)->queryAll();
        }

        // ── Invoices ──────────────────────────────────────────────────────
        $invoices = $db->createCommand("
            SELECT invoice_no, invoice_date, currency, paid_amount,
                   GREATEST(0, (net_total - COALESCE(tds_amount,0)) - paid_amount) AS pending_amount
            FROM invoice
            WHERE project_id = :pid AND status = 1
            ORDER BY id DESC
        ", [':pid' => $id])->queryAll();

        // Wrap empHours in a 10-row paginated provider (Yii1 parity).
        // The total displayed in the footer continues to reflect ALL rows.
        $empHoursProvider = new \yii\data\ArrayDataProvider([
            'allModels'  => $empHours,
            'pagination' => ['pageSize' => 10, 'pageParam' => 'emp-page'],
            'sort'       => false,
        ]);
        $totalEmpSecsAll = (int)array_sum(array_column($empHours, 'total_secs'));

        $model = $project;
        return $this->render('view', compact(
            'model', 'phases', 'hasPhases', 'phaseHours',
            'empHours', 'empHoursProvider', 'totalEmpSecsAll',
            'invoices', 'fromDate', 'toDate'
        ));
    }

    public function actionCreate(): string|Response
    {
        $project = new Project();

        if ($project->load(Yii::$app->request->post())) {
            // Convert estimation hours to seconds (matching Yii1: hours * 3600).
            // Validate raw input first so we catch overflow with a friendly
            // message instead of letting MySQL throw "Out of range" on INT(11).
            $estHours = Yii::$app->request->post('Project')['total_estimation_hours'] ?? null;
            if ($estHours !== null && $estHours !== '') {
                $rawHours = (float)$estHours;
                if ($rawHours < 0 || $rawHours > 500000) {
                    $project->addError('total_estimation_hours',
                        'Total Estimation Hours must be between 0 and 500,000.');
                }
                $project->total_estimation_hours = (int)($rawHours * 3600);
            } else {
                $project->total_estimation_hours = null;
            }

            $estCost = Yii::$app->request->post('Project')['total_estimation_cost'] ?? null;
            if ($estCost !== null && $estCost !== '') {
                $rawCost = (float)trim($estCost);
                if ($rawCost < 0 || $rawCost > 9999999999) {
                    $project->addError('total_estimation_cost',
                        'Total Estimation Cost must be between 0 and 9,999,999,999.');
                }
                $project->total_estimation_cost = trim($estCost);
            } elseif ((int)$project->project_type === 2) {
                // Non-productive projects default to 0 cost
                $project->total_estimation_cost = 0;
            } else {
                $project->total_estimation_cost = null;
            }

            // JSON-encode employee_id multi-select (matching Yii1)
            $empIds = Yii::$app->request->post('Project')['employee_id'] ?? null;
            $project->employee_id = (!empty($empIds) && is_array($empIds))
                ? json_encode($empIds) : null;

            $project->date           = date('Y-m-d H:i:s');
            $project->lastupdate     = null;
            $project->status         = 1;
            $project->project_status = $project->project_status ?: Project::PROJECT_STATUS_ACTIVE;

            if ($project->save()) {
                // Save phase/milestone child records (matching Yii1)
                $phaseValues = Yii::$app->request->post('phase_value', []);
                if (!empty($phaseValues)) {
                    $phaseValues = array_unique($phaseValues);
                    $milestoneValues = Yii::$app->request->post('milestone_value', []);
                    $hoursValues     = Yii::$app->request->post('hours_value', []);
                    $statusValues    = Yii::$app->request->post('status_value', []);

                    foreach ($phaseValues as $pv) {
                        if (isset($milestoneValues[$pv]) && isset($hoursValues[$pv])) {
                            $count = count($milestoneValues[$pv]);
                            for ($i = 0; $i < $count; $i++) {
                                $mv = $milestoneValues[$pv][$i] ?? '';
                                $mh = (float)($hoursValues[$pv][$i] ?? 0);
                                $ms = $statusValues[$pv][$i] ?? '';
                                $totalTime = (int)($mh * 3600);

                                $child = new Project();
                                $child->client_id             = $project->client_id;
                                $child->title                 = $project->title . ' P' . $pv . ' M' . $mv;
                                $child->description           = $project->description;
                                $child->project_type          = $project->project_type;
                                $child->main_project_id       = $project->id;
                                $child->phase_value           = (int)$pv;
                                $child->milestone_value       = (int)$mv;
                                $child->milestone_hours       = $totalTime;
                                $child->milestone_status      = (int)$ms;
                                $child->total_estimation_cost = $project->total_estimation_cost;
                                $child->total_estimation_hours = $totalTime;
                                $child->employee_id           = $project->employee_id;
                                $child->date                  = date('Y-m-d H:i:s');
                                $child->lastupdate            = null;
                                $child->status                = 1;
                                $child->project_status        = Project::PROJECT_STATUS_ACTIVE;
                                $child->save(false);
                            }
                        }
                    }
                }

                $this->handleAttachments($project->id);
                Yii::$app->session->setFlash('success', 'Project created successfully.');
                return $this->redirect(['view', 'id' => $project->id]);
            }
        }

        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('create', compact('project', 'clients', 'developers'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $project = $this->findModel($id);

        if ($project->load(Yii::$app->request->post())) {
            $project->lastupdate = date('Y-m-d H:i:s');

            // Convert estimation hours to seconds (matching Yii1: hours * 3600).
            // Validate raw input first to prevent INT(11) overflow in MySQL.
            $estHours = Yii::$app->request->post('Project')['total_estimation_hours'] ?? null;
            if ($estHours !== null && $estHours !== '') {
                $rawHours = (float)$estHours;
                if ($rawHours < 0 || $rawHours > 500000) {
                    $project->addError('total_estimation_hours',
                        'Total Estimation Hours must be between 0 and 500,000.');
                }
                $project->total_estimation_hours = (int)($rawHours * 3600);
            } else {
                $project->total_estimation_hours = null;
            }

            $estCost = Yii::$app->request->post('Project')['total_estimation_cost'] ?? null;
            if ($estCost !== null && $estCost !== '') {
                $rawCost = (float)trim($estCost);
                if ($rawCost < 0 || $rawCost > 9999999999) {
                    $project->addError('total_estimation_cost',
                        'Total Estimation Cost must be between 0 and 9,999,999,999.');
                }
                $project->total_estimation_cost = trim($estCost);
            } elseif ((int)$project->project_type === 2) {
                // Non-productive projects default to 0 cost
                $project->total_estimation_cost = 0;
            } else {
                $project->total_estimation_cost = null;
            }

            // JSON-encode employee_id multi-select (matching Yii1)
            $empIds = Yii::$app->request->post('Project')['employee_id'] ?? null;
            $project->employee_id = (!empty($empIds) && is_array($empIds))
                ? json_encode($empIds) : null;

            if ($project->save()) {
                $this->handleAttachments($project->id);

                // Save NEW phase/milestone child records (matching Yii1 — only new rows)
                $phaseValues = Yii::$app->request->post('phase_value', []);
                if (!empty($phaseValues)) {
                    $phaseValues = array_unique($phaseValues);
                    $milestoneValues = Yii::$app->request->post('milestone_value', []);
                    $hoursValues     = Yii::$app->request->post('hours_value', []);
                    $statusValues    = Yii::$app->request->post('status_value', []);

                    foreach ($phaseValues as $pv) {
                        if (isset($milestoneValues[$pv]) && isset($hoursValues[$pv])) {
                            $count = count($milestoneValues[$pv]);
                            for ($i = 0; $i < $count; $i++) {
                                $mv = $milestoneValues[$pv][$i] ?? '';
                                $mh = (float)($hoursValues[$pv][$i] ?? 0);
                                $ms = $statusValues[$pv][$i] ?? 0;
                                $totalTime = (int)($mh * 3600);

                                // Skip if this phase+milestone already exists
                                $exists = Project::find()->where([
                                    'main_project_id' => $project->id,
                                    'phase_value'     => (int)$pv,
                                    'milestone_value'  => (int)$mv,
                                    'status'           => 1,
                                ])->exists();
                                if ($exists) {
                                    continue;
                                }

                                $child = new Project();
                                $child->title                 = $project->title . ' P' . $pv . ' M' . $mv;
                                $child->client_id             = $project->client_id;
                                $child->main_project_id       = $project->id;
                                $child->phase_value           = (int)$pv;
                                $child->milestone_value       = (int)$mv;
                                $child->milestone_hours       = $totalTime;
                                $child->milestone_status      = (int)$ms;
                                $child->total_estimation_cost  = $project->total_estimation_cost;
                                $child->total_estimation_hours = $totalTime;
                                $child->project_type           = $project->project_type;
                                $child->description            = $project->description;
                                $child->employee_id            = $project->employee_id;
                                $child->date                   = date('Y-m-d H:i:s');
                                $child->status                 = 1;
                                $child->save(false);
                            }
                        }
                    }
                }

                Yii::$app->session->setFlash('success', 'Project updated successfully.');
                return $this->redirect(['view', 'id' => $project->id]);
            }
        }

        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        // Load existing phase sub-projects for the form
        $existingPhases = Project::find()
            ->where(['main_project_id' => $project->id, 'status' => 1])
            ->orderBy(['phase_value' => SORT_ASC, 'milestone_value' => SORT_ASC])
            ->all();

        return $this->render('update', compact('project', 'clients', 'developers', 'existingPhases'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }
        $project         = $this->findModel($id);
        $project->status = 0;
        $project->save(false);

        Yii::$app->session->setFlash('success', 'Project deleted.');
        return $this->redirect(['index']);
    }

    /**
     * AJAX: Change project status from listing page (matches Yii1 changestatus).
     */
    public function actionChangeStatus(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id     = (int)Yii::$app->request->get('id', Yii::$app->request->post('id'));
        $status = (int)Yii::$app->request->get('status', Yii::$app->request->post('status'));

        if (!$id || !in_array($status, [1, 2, 3, 4])) {
            return $this->asJson(['success' => false, 'message' => 'Invalid parameters.']);
        }

        Project::updateAll(['project_status' => $status], ['id' => $id]);
        return $this->asJson(['success' => true]);
    }

    /**
     * AJAX: enrol a project in the AMC program (project.amc = 1).
     * Triggered by the "Do you want to continue AMC?" popup shown when a
     * project's status is changed to Completed (project_status = 3).
     */
    public function actionSetAmc(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = (int)Yii::$app->request->post('id', Yii::$app->request->get('id'));
        $project = Project::findOne(['id' => $id, 'status' => 1]);
        if (!$project) {
            return $this->asJson(['success' => false, 'message' => 'Project not found.']);
        }
        // Only completed projects (main `project` table: 3 = Completed) may have AMC.
        if ((int)$project->project_status !== 3) {
            return $this->asJson(['success' => false, 'message' => 'Only completed projects can have AMC.']);
        }
        Project::updateAll(['amc' => 1], ['id' => $id]);
        return $this->asJson(['success' => true]);
    }

    /**
     * AJAX: store the project's live/updated date (entered in the popup shown
     * when a project is marked Completed, or via the "Start AMC" button on the
     * project view page). The AMC start date later defaults to this value.
     */
    public function actionSetLiveDate(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id   = (int)Yii::$app->request->post('id');
        $date = trim((string)Yii::$app->request->post('live_updated_date'));

        $project = Project::findOne(['id' => $id, 'status' => 1]);
        if (!$project) {
            return $this->asJson(['success' => false, 'message' => 'Project not found.']);
        }
        // Validate Y-m-d
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            return $this->asJson(['success' => false, 'message' => 'Invalid date.']);
        }
        $project->live_updated_date = $date;
        $project->save(false, ['live_updated_date']);
        return $this->asJson(['success' => true]);
    }

    /**
     * AJAX: Change milestone status for a phase sub-project (matches Yii1 changephasestatus).
     */
    public function actionChangePhaseStatus(): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST only.');
        }
        $projectId = (int)Yii::$app->request->post('project_id');
        $phaseVal  = (int)Yii::$app->request->post('phase_value');
        $msVal     = (int)Yii::$app->request->post('milestone_value');
        $status    = (int)Yii::$app->request->post('status_value');

        $record = Project::find()->where([
            'main_project_id' => $projectId,
            'phase_value'     => $phaseVal,
            'milestone_value' => $msVal,
            'status'          => 1,
        ])->one();

        Yii::$app->response->format = Response::FORMAT_JSON;
        if ($record) {
            $record->milestone_status = $status;
            $record->save(false);
            return $this->asJson(['success' => true]);
        }
        return $this->asJson(['success' => false, 'message' => 'Phase record not found.']);
    }

    /**
     * AJAX: Delete a phase sub-project and soft-delete linked work reports (matches Yii1 deletephase).
     */
    public function actionDeletePhase(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST only.');
        }
        $phase = Project::findOne(['id' => $id, 'status' => 1]);
        Yii::$app->response->format = Response::FORMAT_JSON;
        if ($phase && $phase->main_project_id) {
            $phase->status = 0;
            $phase->save(false);
            // Soft-delete related work reports
            \backend\models\WorkReport::updateAll(['status' => 0], ['project_id' => $id]);
            return $this->asJson(['success' => true]);
        }
        return $this->asJson(['success' => false, 'message' => 'Phase not found.']);
    }

    /**
     * Developer-facing project list — only projects where the current user has tasks assigned.
     */
    public function actionDeveloperProjects(): string
    {
        $userId = (int)Yii::$app->user->id;

        $rows = Yii::$app->db->createCommand("
            SELECT DISTINCT p.id, p.title, p.date, p.project_status, p.project_type,
                   c.client_name
            FROM project p
            LEFT JOIN client c ON c.id = p.client_id
            INNER JOIN task t ON t.project_id = p.id AND t.assigned_to = :uid AND t.status = 1
            WHERE p.status = 1
            ORDER BY p.id DESC
        ", [':uid' => $userId])->queryAll();

        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels'  => $rows,
            'pagination' => ['pageSize' => 25],
        ]);

        return $this->render('developer-projects', compact('dataProvider'));
    }

    private function handleAttachments(int $projectId): void
    {
        $files = UploadedFile::getInstancesByName('attachments');
        foreach ($files as $file) {
            $filename = 'proj_' . $projectId . '_' . time() . '_' . uniqid() . '.' . $file->extension;
            $uploadPath = Yii::getAlias('@backend/web/uploads/project_files/');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0775, true);
            }
            $file->saveAs($uploadPath . $filename);

            $attachment             = new ProjectAttachments();
            $attachment->project_id = $projectId;
            $attachment->file_name  = $filename;
            $attachment->save(false);
        }
    }

    /**
     * AJAX: Return projects for a given client (matches Yii1 getprojectbyclient).
     */
    public function actionGetProjectsByClient(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
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
        $result = [];
        foreach ($rows as $r) {
            $result[$r['id']] = $r['title'];
        }
        return $this->asJson($result);
    }

    /**
     * AJAX: Return distinct phase_value for sub-projects of a given project (matches Yii1 getphasevalue).
     */
    public function actionGetPhaseValue(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $projectId = (int)Yii::$app->request->post('project_id', Yii::$app->request->get('project_id', 0));
        if (!$projectId) {
            return $this->asJson([]);
        }
        $rows = Project::find()
            ->select(['phase_value'])
            ->where(['status' => 1, 'main_project_id' => $projectId])
            ->andWhere(['IS NOT', 'phase_value', null])
            ->groupBy('phase_value')
            ->orderBy('phase_value')
            ->asArray()
            ->all();
        $result = [];
        foreach ($rows as $r) {
            $result[$r['phase_value']] = $r['phase_value'];
        }
        return $this->asJson($result);
    }

    /**
     * AJAX: Return distinct milestone_value for sub-projects of a given project (matches Yii1 getmilestonevalue).
     */
    public function actionGetMilestoneValue(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $projectId = (int)Yii::$app->request->post('project_id', Yii::$app->request->get('project_id', 0));
        if (!$projectId) {
            return $this->asJson([]);
        }
        $rows = Project::find()
            ->select(['milestone_value'])
            ->where(['status' => 1, 'main_project_id' => $projectId])
            ->andWhere(['IS NOT', 'milestone_value', null])
            ->groupBy('milestone_value')
            ->orderBy('milestone_value')
            ->asArray()
            ->all();
        $result = [];
        foreach ($rows as $r) {
            $result[$r['milestone_value']] = $r['milestone_value'];
        }
        return $this->asJson($result);
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
