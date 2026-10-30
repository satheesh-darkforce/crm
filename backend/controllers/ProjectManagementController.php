<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\models\Developer;
use backend\models\Project;
use Yii;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * PM (Project Management) module — PM projects are distinguished from regular
 * projects by having main_project_id IS NOT NULL (phases/sub-projects) or by
 * being a root PM project.  In practice the PM index shows ALL projects that
 * were created via this controller (we use a simple flag: no main_project_id =
 * root PM project).
 */
class ProjectManagementController extends Controller
{
    /**
     * List all PM root-projects (main_project_id IS NULL) with phase counts.
     */
    public function actionIndex(): string
    {
        $db    = Yii::$app->db;
        $title = Yii::$app->request->get('title', '');
        $statusF  = Yii::$app->request->get('project_status', '');
        $clientF  = Yii::$app->request->get('client_id', '');

        $where  = 'p.status = 1 AND p.main_project_id IS NULL';
        $params = [];
        if ($title !== '') {
            $where .= ' AND p.title LIKE :title';
            $params[':title'] = '%' . $title . '%';
        }
        if ($statusF !== '') {
            $where .= ' AND p.project_status = :ps';
            $params[':ps'] = (int)$statusF;
        }
        if ($clientF !== '') {
            $where .= ' AND p.client_id = :cid';
            $params[':cid'] = (int)$clientF;
        }

        $rows = $db->createCommand("
            SELECT p.id, p.title, p.date, p.project_status, p.project_type,
                   p.total_estimation_hours, p.total_estimation_cost,
                   c.client_name, c.id AS client_id,
                   COUNT(DISTINCT ph.id) AS phase_count,
                   COUNT(DISTINCT t.id)  AS open_tasks
            FROM project p
            LEFT JOIN client        c  ON c.id  = p.client_id
            LEFT JOIN project       ph ON ph.main_project_id = p.id AND ph.status = 1
            LEFT JOIN task          t  ON t.project_id = p.id AND t.status = 1
                                       AND t.task_status IN (1,2,3)
            WHERE $where
            GROUP BY p.id
            ORDER BY p.id DESC
        ", $params)->queryAll();

        $dataProvider = new ArrayDataProvider([
            'allModels'  => $rows,
            'pagination' => ['pageSize' => 20],
        ]);

        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('index', compact(
            'dataProvider', 'clients', 'title', 'statusF', 'clientF'
        ));
    }

    /**
     * All PM projects (including sub-phases) — full list view.
     */
    public function actionAllproject(): string
    {
        $query = Project::find()
            ->alias('p')
            ->with(['client'])
            ->where(['p.status' => 1])
            ->orderBy(['p.id' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 30],
        ]);

        return $this->render('all-project', compact('dataProvider'));
    }

    /**
     * Developer's projects list — only projects assigned to the current user.
     */
    public function actionDeveloperProjects(): string
    {
        $userId = Yii::$app->user->id;
        $query = Project::find()
            ->alias('p')
            ->with(['client'])
            ->where(['p.status' => 1, 'p.main_project_id' => null])
            ->andWhere(['like', 'p.employee_id', (string)$userId])
            ->orderBy(['p.id' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('developer-projects', compact('dataProvider'));
    }

    /**
     * View a single PM project + its phases.
     */
    public function actionView(int $id): string
    {
        $model  = $this->findModel($id);
        $phases = Project::find()
            ->where(['main_project_id' => $id, 'status' => 1])
            ->orderBy(['phase_value' => SORT_ASC, 'milestone_value' => SORT_ASC])
            ->all();

        return $this->render('view', compact('model', 'phases'));
    }

    /**
     * Create a new PM project with optional phases + milestones.
     */
    public function actionCreate(): string|Response
    {
        $model = new Project();

        if ($model->load(Yii::$app->request->post())) {
            $post = Yii::$app->request->post();

            $model->date       = date('Y-m-d');
            $model->lastupdate = date('Y-m-d');
            $model->status     = 1;
            $model->project_status = $model->project_status ?: Project::PROJECT_STATUS_ACTIVE;

            // Employee IDs come as array — encode as JSON
            $employeeIds = $post['Project']['employee_id'] ?? null;
            $model->employee_id = !empty($employeeIds) ? json_encode((array)$employeeIds) : null;

            // Convert estimation hours to seconds
            $estHours = $post['Project']['total_estimation_hours'] ?? '';
            $model->total_estimation_hours = ($estHours !== '') ? ((float)$estHours * 3600) : null;

            if ($model->save()) {
                $this->savePhases($model, $post);

                Yii::$app->session->setFlash('success', 'PM Project created successfully.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('create', compact('model', 'clients', 'developers'));
    }

    /**
     * Update a PM project + manage its phases.
     */
    public function actionUpdate(int $id): string|Response
    {
        $model  = $this->findModel($id);
        $phases = Project::find()
            ->where(['main_project_id' => $id, 'status' => 1])
            ->orderBy(['phase_value' => SORT_ASC, 'milestone_value' => SORT_ASC])
            ->all();

        if ($model->load(Yii::$app->request->post())) {
            $post = Yii::$app->request->post();

            $model->lastupdate = date('Y-m-d');

            $employeeIds = $post['Project']['employee_id'] ?? null;
            $model->employee_id = !empty($employeeIds) ? json_encode((array)$employeeIds) : null;

            $estHours = $post['Project']['total_estimation_hours'] ?? '';
            $model->total_estimation_hours = ($estHours !== '') ? ((float)$estHours * 3600) : null;

            if ($model->save()) {
                $this->savePhases($model, $post);

                Yii::$app->session->setFlash('success', 'PM Project updated successfully.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('update', compact('model', 'clients', 'developers', 'phases'));
    }

    /**
     * Soft-delete a PM project (and its phases).
     */
    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $model = $this->findModel($id);

        // Soft-delete all phases
        Project::updateAll(['status' => 0], ['main_project_id' => $id]);
        $model->status = 0;
        $model->save(false);

        Yii::$app->session->setFlash('success', 'PM Project deleted.');
        return $this->redirect(['index']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Save phase + milestone sub-rows after a project save.
     * Expects POST arrays: phase_value[], milestone_value[phase][i], hours_value[phase][i]
     */
    private function savePhases(Project $model, array $post): void
    {
        if (empty($post['phase_value'])) {
            return;
        }

        $employeeIds = !empty($post['Project']['employee_id'])
            ? json_encode((array)$post['Project']['employee_id'])
            : null;

        $estCost = $post['Project']['total_estimation_cost'] ?? null;

        $phaseValues = array_unique((array)$post['phase_value']);

        foreach ($phaseValues as $phaseValue) {
            $milestones = $post['milestone_value'][$phaseValue] ?? [];
            $hours      = $post['hours_value'][$phaseValue] ?? [];
            $statuses   = $post['status_value'][$phaseValue] ?? [];

            if (empty($milestones)) {
                continue;
            }

            foreach ($milestones as $i => $milestoneValue) {
                if ($milestoneValue === '') {
                    continue;
                }

                $totalHour   = isset($hours[$i]) && $hours[$i] !== '' ? (float)$hours[$i] : 0;
                $totalTime   = (int)($totalHour * 3600);

                $phase = new Project();
                $phase->attributes = $post['Project'];
                $phase->title             = $model->title . ' P' . $phaseValue . ' M' . $milestoneValue;
                $phase->main_project_id   = $model->id;
                $phase->phase_value       = (int)$phaseValue;
                $phase->milestone_value   = (int)$milestoneValue;
                $phase->milestone_hours   = $totalTime;
                $phase->total_estimation_hours = $totalTime;
                $phase->total_estimation_cost  = $estCost;
                $phase->milestone_status  = $statuses[$i] ?? null;
                $phase->employee_id       = $employeeIds;
                $phase->date              = date('Y-m-d');
                $phase->lastupdate        = null;
                $phase->status            = 1;
                $phase->save();
            }
        }
    }

    private function findModel(int $id): Project
    {
        $model = Project::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('PM Project not found.');
        }
        return $model;
    }
}
