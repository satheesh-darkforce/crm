<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\DeveloperTask;
use backend\models\Project;
use backend\models\Tasks;
use backend\models\WorkReport;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend controller for the developer task system (tasks table + developer_task junction).
 * Migrated from Yii1 TasksController.
 */
class TasksController extends Controller
{
    // ── All tasks (admin) ──────────────────────────────────────────────────────

    public function actionIndex(): string
    {
        $query = Tasks::find()
            ->alias('t')
            ->with(['project', 'createdBy'])
            ->where(['t.status' => 1])
            ->orderBy(['t.id' => SORT_DESC]);

        if ($name = Yii::$app->request->get('task')) {
            $query->andWhere(['like', 't.task', $name]);
        }
        if ($projectId = Yii::$app->request->get('project_id')) {
            $query->andWhere(['t.project_id' => (int)$projectId]);
        }
        if (($status = Yii::$app->request->get('task_status')) !== null && $status !== '') {
            $query->andWhere(['t.task_status' => (int)$status]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('index', compact('dataProvider', 'projects'));
    }

    // ── Developer's own tasks ──────────────────────────────────────────────────

    public function actionDeveloperTasks(): string
    {
        $developer = Developer::find()->where(['user_id' => Yii::$app->user->id])->one();

        $query = Tasks::find()
            ->alias('t')
            ->innerJoin('developer_task dt', 'dt.task_id = t.id')
            ->with(['project'])
            ->where(['t.status' => 1])
            ->orderBy(['t.assigned_date' => SORT_DESC]);

        if ($developer) {
            $query->andWhere(['dt.developer_id' => $developer->id]);
        }

        if ($projectId = Yii::$app->request->get('project_id')) {
            $query->andWhere(['t.project_id' => (int)$projectId]);
        }
        if (($status = Yii::$app->request->get('task_status')) !== null && $status !== '') {
            $query->andWhere(['t.task_status' => (int)$status]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('developer-tasks', compact('dataProvider', 'projects'));
    }

    // ── View ──────────────────────────────────────────────────────────────────

    public function actionView(int $id): string
    {
        $task = $this->findModel($id);
        $task->refresh();

        $developers = DeveloperTask::find()
            ->with('developer')
            ->where(['task_id' => $id])
            ->all();

        return $this->render('view', compact('task', 'developers'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function actionCreate(): string|\yii\web\Response
    {
        $task = new Tasks();

        if (Yii::$app->request->isPost) {
            $task->load(Yii::$app->request->post());

            // Convert HH:MM → seconds
            $hours   = (int)Yii::$app->request->post('expected_hours', 0);
            $minutes = (int)Yii::$app->request->post('expected_minutes', 0);
            $task->hours_of_work = $hours * 3600 + $minutes * 60;

            $task->created_at = date('Y-m-d H:i:s');
            $task->created_by = Yii::$app->user->id;
            $task->status     = 1;

            if ($task->save()) {
                $this->saveDeveloperAssignments($task->id);
                Yii::$app->session->setFlash('success', 'Task created successfully.');
                return $this->redirect(['view', 'id' => $task->id]);
            }
        }

        if ($projectId = Yii::$app->request->get('project_id')) {
            $task->project_id = (int)$projectId;
        }

        $projects      = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $allDevelopers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('create', compact('task', 'projects', 'allDevelopers'));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function actionUpdate(int $id): string|\yii\web\Response
    {
        $task = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $task->load(Yii::$app->request->post());

            $hours   = (int)Yii::$app->request->post('expected_hours', 0);
            $minutes = (int)Yii::$app->request->post('expected_minutes', 0);
            $task->hours_of_work = $hours * 3600 + $minutes * 60;

            if ($task->save()) {
                // Reassign developers: clear old, save new
                DeveloperTask::deleteAll(['task_id' => $task->id]);
                $this->saveDeveloperAssignments($task->id);
                Yii::$app->session->setFlash('success', 'Task updated successfully.');
                return $this->redirect(['view', 'id' => $task->id]);
            }
        }

        $projects        = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $allDevelopers   = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $assignedDevIds  = ArrayHelper::getColumn(
            DeveloperTask::find()->where(['task_id' => $id])->all(),
            'developer_id'
        );

        return $this->render('update', compact('task', 'projects', 'allDevelopers', 'assignedDevIds'));
    }

    // ── Delete (soft) ─────────────────────────────────────────────────────────

    public function actionDelete(int $id): \yii\web\Response
    {
        $task = $this->findModel($id);
        $task->status = 0;
        $task->save(false);
        Yii::$app->session->setFlash('success', 'Task deleted.');
        return $this->redirect(['index']);
    }

    // ── Change status (AJAX) ──────────────────────────────────────────────────

    public function actionChangeStatus(int $id, int $status): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $task = $this->findModel($id);
        $task->task_status = $status;
        $task->save(false);
        return $this->asJson(['status' => 1, 'msg' => 'Status updated.']);
    }

    // ── Add work report form ──────────────────────────────────────────────────

    public function actionAddWorkReport(int $id): string|\yii\web\Response
    {
        $task = $this->findModel($id);

        if (Yii::$app->request->isPost && Yii::$app->request->post('submitworkreport')) {
            $developer = Developer::find()->where(['user_id' => Yii::$app->user->id])->one();
            $project   = $task->project;

            $fromTimes   = Yii::$app->request->post('fromtime', []);
            $toTimes     = Yii::$app->request->post('totime', []);
            $totalTimes  = Yii::$app->request->post('totaltime', []);
            $descs       = Yii::$app->request->post('description', []);
            $date        = Yii::$app->request->post('date');

            foreach ($totalTimes as $idx => $ttStr) {
                if (empty($ttStr) || $ttStr === '00:00') {
                    continue;
                }
                $parts     = explode(':', $ttStr);
                $totalMins = ((int)$parts[0]) * 60 + ((int)($parts[1] ?? 0));

                if ($totalMins > 0 && $project && $developer) {
                    $wr = new WorkReport();
                    $wr->developer_id = $developer->id;
                    $wr->project_id   = $project->id;
                    $wr->client_id    = $project->client_id ?? null;
                    $wr->task         = $task->task;
                    $wr->description  = $descs[$idx] ?? '';
                    $wr->date         = $date;
                    $wr->from_time    = $fromTimes[$idx] ?? null;
                    $wr->to_time      = $toTimes[$idx] ?? null;
                    $wr->total_time   = $totalMins;
                    $wr->task_id      = $task->id;
                    $wr->status       = 1;
                    $wr->data_source  = WorkReport::SOURCE_WEB;
                    $wr->save();
                }
            }

            Yii::$app->session->setFlash('success', 'Work report saved successfully.');
            return $this->redirect(['/work-report/my-work-reports']);
        }

        return $this->render('add-work-report', compact('task'));
    }

    // ── AJAX: developer autocomplete ──────────────────────────────────────────

    public function actionAutocompleteDeveloper(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $term = trim(Yii::$app->request->get('term', ''));
        if ($term === '') {
            return $this->asJson([]);
        }

        $rows = Developer::find()
            ->where(['like', 'developer_name', $term])
            ->andWhere(['status' => 1])
            ->limit(10)
            ->all();

        $result = [];
        foreach ($rows as $dev) {
            $result[] = ['label' => $dev->developer_name, 'value' => $dev->developer_name, 'id' => $dev->id];
        }

        return $this->asJson($result);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function findModel(int $id): Tasks
    {
        $model = Tasks::findOne(['id' => $id, 'status' => 1]);
        if ($model === null) {
            throw new NotFoundHttpException('Task not found.');
        }
        return $model;
    }

    /**
     * Saves developer assignments from the POST 'developer_ids' array.
     */
    private function saveDeveloperAssignments(int $taskId): void
    {
        $developerIds = Yii::$app->request->post('developer_ids', []);
        foreach ((array)$developerIds as $devId) {
            $devId = (int)$devId;
            if ($devId > 0) {
                $dt = new DeveloperTask();
                $dt->task_id      = $taskId;
                $dt->developer_id = $devId;
                $dt->save();
            }
        }
    }
}
