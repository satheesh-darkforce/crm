<?php

namespace backend\controllers;

use backend\assets\ProjectAsset;
use backend\models\Amc;
use backend\models\AmcRenew;
use backend\models\ClientModel;
use backend\models\Project;
use Yii;
use yii\data\SqlDataProvider;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * AMC (Annual Maintenance Charge) module.
 *
 * Workflow:
 *  - A completed project (project_status = 3) can be enrolled in AMC
 *    (project.amc = 1).
 *  - "Start AMC" creates an `amc` row (amc_status = Active) holding the
 *    warranty period + end date.
 *  - Each renewal after warranty/previous period creates an `amc_renew` row.
 *  - "End AMC" sets amc_status = Ended.
 */
class AmcController extends Controller
{
    /** `project` table status for "Completed" (1=In progress, 2=Waiting to assign, 3=Completed, 4=Hold). */
    const PROJECT_COMPLETED = Project::PROJECT_STATUS_COMPLETED;

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => ['end' => ['POST'], 'remove' => ['POST']],
            ],
        ]);
    }

    /** Full pages get the Projects/AMC stylesheet and script. */
    public function render($view, $params = []): string
    {
        ProjectAsset::register($this->getView());
        return parent::render($view, $params);
    }

    /** Trimmed string query value; arrays from tampered URLs become ''. */
    private function q(string $key): string
    {
        $v = Yii::$app->request->get($key, '');
        return is_string($v) ? mb_substr(trim($v), 0, 255) : '';
    }

    /**
     * SQL expression for an AMC row's current (live) end date: the latest
     * renewal end date if any, otherwise the original warranty end date.
     */
    private function currentEndExpr(string $alias = 'a'): string
    {
        return "COALESCE((SELECT MAX(r.end_date) FROM amc_renew r WHERE r.amc_id = $alias.id AND r.status = 1), $alias.end_date)";
    }

    // ---------------------------------------------------------------------
    // 1) AMC Projects — completed projects enrolled in the AMC program
    // ---------------------------------------------------------------------
    public function actionProjects(): string
    {
        $clientF = $this->q('client_id');
        $titleF  = $this->q('title');

        $where  = "p.status = 1 AND p.amc = 1 AND p.project_status = " . self::PROJECT_COMPLETED;
        $params = [];
        if ($clientF !== '') { $where .= ' AND p.client_id = :cid'; $params[':cid'] = (int)$clientF; }
        if ($titleF !== '')  { $where .= ' AND p.title LIKE :t';    $params[':t']   = '%' . $titleF . '%'; }

        $sql = "
            SELECT p.id, p.title, p.total_estimation_cost, c.client_name,
                   (SELECT COUNT(*) FROM amc a WHERE a.project_id = p.id AND a.status = 1 AND a.amc_status = 1) AS active_amc,
                   (SELECT a2.id FROM amc a2 WHERE a2.project_id = p.id AND a2.status = 1 AND a2.amc_status = 1 ORDER BY a2.id DESC LIMIT 1) AS amc_id
            FROM project p
            LEFT JOIN client c ON c.id = p.client_id
            WHERE $where
            ORDER BY p.id DESC
        ";
        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(*) FROM project p WHERE $where", $params)->queryScalar();

        $dataProvider = new SqlDataProvider([
            'sql' => $sql, 'params' => $params, 'totalCount' => $count,
            'pagination' => ['pageSize' => 20],
        ]);
        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('projects', compact('dataProvider', 'clients', 'clientF', 'titleF'));
    }

    // ---------------------------------------------------------------------
    // 7) Completed Projects — all completed projects; start AMC from here
    // ---------------------------------------------------------------------
    public function actionCompletedProjects(): string
    {
        $clientF = $this->q('client_id');
        $titleF  = $this->q('title');
        $amcF    = $this->q('amc');

        $where  = "p.status = 1 AND p.project_status = " . self::PROJECT_COMPLETED;
        $params = [];
        if ($clientF !== '') { $where .= ' AND p.client_id = :cid'; $params[':cid'] = (int)$clientF; }
        if ($titleF !== '')  { $where .= ' AND p.title LIKE :t';    $params[':t']   = '%' . $titleF . '%'; }
        if ($amcF === '0' || $amcF === '1') { $where .= ' AND p.amc = :amc'; $params[':amc'] = (int)$amcF; }

        $sql = "
            SELECT p.id, p.title, p.total_estimation_cost, p.amc, c.client_name,
                   (SELECT COUNT(*) FROM amc a WHERE a.project_id = p.id AND a.status = 1 AND a.amc_status = 1) AS active_amc,
                   (SELECT a2.id FROM amc a2 WHERE a2.project_id = p.id AND a2.status = 1 AND a2.amc_status = 1 ORDER BY a2.id DESC LIMIT 1) AS amc_id
            FROM project p
            LEFT JOIN client c ON c.id = p.client_id
            WHERE $where
            ORDER BY p.id DESC
        ";
        $count = (int) Yii::$app->db->createCommand("SELECT COUNT(*) FROM project p WHERE $where", $params)->queryScalar();

        $dataProvider = new SqlDataProvider([
            'sql' => $sql, 'params' => $params, 'totalCount' => $count,
            'pagination' => ['pageSize' => 20],
        ]);
        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('completed-projects', compact('dataProvider', 'clients', 'clientF', 'titleF', 'amcF'));
    }

    // ---------------------------------------------------------------------
    // 3) Start AMC form (create the initial contract)
    // ---------------------------------------------------------------------
    public function actionStart(int $project_id): string|Response
    {
        $project = Project::findOne(['id' => $project_id, 'status' => 1]);
        if (!$project) {
            throw new NotFoundHttpException('Project not found.');
        }
        if ((int)$project->project_status !== self::PROJECT_COMPLETED) {
            Yii::$app->session->setFlash('error', 'Only completed projects can have AMC.');
            return $this->redirect(['completed-projects']);
        }
        // Block a duplicate active contract.
        $existing = Amc::find()->where(['project_id' => $project_id, 'status' => 1, 'amc_status' => Amc::AMC_ACTIVE])->one();
        if ($existing) {
            Yii::$app->session->setFlash('error', 'This project already has an active AMC.');
            return $this->redirect(['view', 'id' => $existing->id]);
        }

        $model = new Amc();
        $model->project_id      = $project->id;
        $model->client_id       = (int)$project->client_id;
        $model->estimation_cost = $project->total_estimation_cost;
        // Start date defaults to the project's live/updated date (fallback today).
        $model->start_date      = $project->live_updated_date ?: date('Y-m-d');
        $model->charge_type     = Amc::CHARGE_PERCENTAGE;

        if ($model->load(Yii::$app->request->post())) {
            // Never trust client-posted context fields.
            $model->project_id      = $project->id;
            $model->client_id       = (int)$project->client_id;
            $model->estimation_cost = $project->total_estimation_cost;
            $model->amc_status      = Amc::AMC_ACTIVE;
            $model->status          = Amc::STATUS_ACTIVE;
            if ($model->save()) {
                Project::updateAll(['amc' => 1], ['id' => $project->id]);
                Yii::$app->session->setFlash('success', 'AMC started successfully.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', compact('model', 'project'));
    }

    // ---------------------------------------------------------------------
    // 3b) Edit an existing AMC contract
    // ---------------------------------------------------------------------
    public function actionUpdate(int $id): string|Response
    {
        $model   = $this->findAmc($id);
        $project = Project::findOne($model->project_id);

        // Ended AMCs are read-only.
        if ((int)$model->amc_status === Amc::AMC_ENDED) {
            Yii::$app->session->setFlash('error', 'This AMC has ended and can no longer be edited.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        if ($model->load(Yii::$app->request->post())) {
            // Context fields stay fixed; only contract terms are editable.
            $model->project_id      = (int)($project->id ?? $model->project_id);
            $model->client_id       = (int)($project->client_id ?? $model->client_id);
            $model->estimation_cost = $project->total_estimation_cost ?? $model->estimation_cost;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'AMC updated successfully.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', compact('model', 'project'));
    }

    // ---------------------------------------------------------------------
    // 4) Active AMC list
    // ---------------------------------------------------------------------
    public function actionActive(): string
    {
        return $this->renderAmcList('active', Amc::AMC_ACTIVE);
    }

    // ---------------------------------------------------------------------
    // 6) Ended AMC list
    // ---------------------------------------------------------------------
    public function actionEnded(): string
    {
        return $this->renderAmcList('ended', Amc::AMC_ENDED);
    }

    /**
     * Shared list renderer for Active / Ended AMC (same columns + filters).
     */
    private function renderAmcList(string $view, int $amcStatus): string
    {
        $clientF = $this->q('client_id');
        $titleF  = $this->q('title');
        $typeF   = $this->q('charge_type');
        $warrantyF = $this->q('warranty');
        $stateF    = $this->q('state');
        $startF    = $this->q('amc_start');
        $endF      = $this->q('amc_end');

        // Sanitize the date filters and enforce end >= start (drop the end date
        // when it falls before the start date).
        $isYmd = function (string $s): bool {
            $d = \DateTime::createFromFormat('Y-m-d', $s);
            return $d && $d->format('Y-m-d') === $s;
        };
        if ($startF !== '' && !$isYmd($startF)) { $startF = ''; }
        if ($endF !== '' && !$isYmd($endF))     { $endF = ''; }
        if ($startF !== '' && $endF !== '' && $endF < $startF) { $endF = ''; }

        $curEnd = $this->currentEndExpr('a');
        // Current AMC start: the latest renewal's start if renewed; otherwise it
        // begins right after the warranty (or at the AMC start when no warranty).
        $curStart = "COALESCE(
            (SELECT r3.start_date FROM amc_renew r3 WHERE r3.amc_id = a.id AND r3.status = 1 ORDER BY r3.end_date DESC, r3.id DESC LIMIT 1),
            CASE WHEN a.warranty_period = 0 THEN a.start_date ELSE DATE_ADD(a.end_date, INTERVAL 1 DAY) END
        )";

        $where  = "a.status = 1 AND a.amc_status = :as";
        $params = [':as' => $amcStatus];
        if ($clientF !== '') { $where .= ' AND a.client_id = :cid'; $params[':cid'] = (int)$clientF; }
        if ($titleF !== '')  { $where .= ' AND p.title LIKE :t';    $params[':t']   = '%' . $titleF . '%'; }
        if ($typeF === '1' || $typeF === '2') { $where .= ' AND a.charge_type = :ct'; $params[':ct'] = (int)$typeF; }

        // Warranty status filter.
        if ($warrantyF === 'under')      { $where .= " AND a.warranty_period > 0 AND a.end_date >= CURDATE()"; }
        elseif ($warrantyF === 'ended')  { $where .= " AND a.warranty_period > 0 AND a.end_date < CURDATE()"; }
        elseif ($warrantyF === 'none')   { $where .= " AND a.warranty_period = 0"; }

        // AMC state filter: overdue (effective end already past) vs active (still running).
        if ($stateF === 'overdue')     { $where .= " AND $curEnd < CURDATE()"; }
        elseif ($stateF === 'active')  { $where .= " AND $curEnd >= CURDATE()"; }

        // AMC start/end date window (matched against the current AMC period).
        if ($startF !== '') { $where .= " AND $curStart >= :asd"; $params[':asd'] = $startF; }
        if ($endF !== '')   { $where .= " AND $curEnd <= :aed";   $params[':aed'] = $endF; }

        // Latest renewal end + its start, so the list can show the current AMC
        // period (renewal dates if renewed; warranty-derived otherwise).
        $renewEnd   = "(SELECT MAX(r.end_date) FROM amc_renew r WHERE r.amc_id = a.id AND r.status = 1)";
        $renewStart = "(SELECT r.start_date FROM amc_renew r WHERE r.amc_id = a.id AND r.status = 1 ORDER BY r.end_date DESC, r.id DESC LIMIT 1)";
        $sql = "
            SELECT a.id, a.start_date, a.end_date, a.warranty_period,
                   a.charge_type, a.percentage, a.amount, a.amc_status,
                   $curEnd AS current_end_date,
                   $renewStart AS renew_start,
                   $renewEnd AS renew_end,
                   p.id AS project_id, p.title, c.client_name
            FROM amc a
            LEFT JOIN project p ON p.id = a.project_id
            LEFT JOIN client  c ON c.id = a.client_id
            WHERE $where
            ORDER BY a.id DESC
        ";
        $count = (int) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM amc a LEFT JOIN project p ON p.id = a.project_id WHERE $where", $params
        )->queryScalar();

        $dataProvider = new SqlDataProvider([
            'sql' => $sql, 'params' => $params, 'totalCount' => $count,
            'pagination' => ['pageSize' => 20],
        ]);
        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render($view, compact(
            'dataProvider', 'clients', 'clientF', 'titleF', 'typeF',
            'warrantyF', 'stateF', 'startF', 'endF'
        ));
    }

    // ---------------------------------------------------------------------
    // 5) Current month AMC renew list (nearest current end date first)
    // ---------------------------------------------------------------------
    public function actionRenewList(): string
    {
        $clientF = $this->q('client_id');
        $titleF  = $this->q('title');

        $curEnd = $this->currentEndExpr('a');
        $where  = "a.status = 1 AND a.amc_status = 1
                   AND YEAR($curEnd) = YEAR(CURDATE())
                   AND MONTH($curEnd) = MONTH(CURDATE())";
        $params = [];
        if ($clientF !== '') { $where .= ' AND a.client_id = :cid'; $params[':cid'] = (int)$clientF; }
        if ($titleF !== '')  { $where .= ' AND p.title LIKE :t';    $params[':t']   = '%' . $titleF . '%'; }

        $sql = "
            SELECT a.id, a.start_date, a.end_date, a.charge_type, a.percentage, a.amount,
                   $curEnd AS current_end_date,
                   p.id AS project_id, p.title, c.client_name
            FROM amc a
            LEFT JOIN project p ON p.id = a.project_id
            LEFT JOIN client  c ON c.id = a.client_id
            WHERE $where
            ORDER BY current_end_date ASC, a.id ASC
        ";
        $count = (int) Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM amc a LEFT JOIN project p ON p.id = a.project_id WHERE $where", $params
        )->queryScalar();

        $dataProvider = new SqlDataProvider([
            'sql' => $sql, 'params' => $params, 'totalCount' => $count,
            'pagination' => ['pageSize' => 20],
        ]);
        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('renew-list', compact('dataProvider', 'clients', 'clientF', 'titleF'));
    }

    // ---------------------------------------------------------------------
    // 5b) Renew form — reuse create form, "no. of months" instead of warranty
    // ---------------------------------------------------------------------
    public function actionRenew(int $id): string|Response
    {
        $amc = $this->findAmc($id);
        if ((int)$amc->amc_status !== Amc::AMC_ACTIVE) {
            Yii::$app->session->setFlash('error', 'Only active AMCs can be renewed.');
            return $this->redirect(['active']);
        }

        $model = new AmcRenew();
        $model->amc_id      = $amc->id;
        $model->project_id  = (int)$amc->project_id;
        $model->client_id   = (int)$amc->client_id;
        // Default the renewal start date to the day after the current end date.
        $curEnd = $amc->getCurrentEndDate();
        $model->start_date  = $curEnd ? date('Y-m-d', strtotime($curEnd . ' +1 day')) : date('Y-m-d');
        $model->charge_type = (int)$amc->charge_type;
        $model->percentage  = $amc->percentage;
        $model->amount      = $amc->amount;
        // Default months = original contract span (warranty period).
        $model->months      = (int)$amc->warranty_period;

        if ($model->load(Yii::$app->request->post())) {
            $model->amc_id     = $amc->id;
            $model->project_id = (int)$amc->project_id;
            $model->client_id  = (int)$amc->client_id;
            $model->status     = AmcRenew::STATUS_ACTIVE;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'AMC renewed successfully.');
                return $this->redirect(['view', 'id' => $amc->id]);
            }
        }

        $project = Project::findOne($amc->project_id);
        return $this->render('renew', compact('model', 'amc', 'project'));
    }

    // ---------------------------------------------------------------------
    // 5b-edit) Edit an existing renewal
    // ---------------------------------------------------------------------
    public function actionUpdateRenew(int $id): string|Response
    {
        $model = AmcRenew::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Renewal record not found.');
        }
        $amc     = $this->findAmc((int)$model->amc_id);
        $project = Project::findOne($model->project_id);

        // Renewals of an ended AMC are read-only.
        if ((int)$amc->amc_status === Amc::AMC_ENDED) {
            Yii::$app->session->setFlash('error', 'This AMC has ended and can no longer be edited.');
            return $this->redirect(['view', 'id' => $amc->id]);
        }

        if ($model->load(Yii::$app->request->post())) {
            $model->amc_id     = $amc->id;
            $model->project_id = (int)$amc->project_id;
            $model->client_id  = (int)$amc->client_id;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Renewal updated successfully.');
                return $this->redirect(['view', 'id' => $amc->id]);
            }
        }

        return $this->render('renew', compact('model', 'amc', 'project'));
    }

    // ---------------------------------------------------------------------
    // 5c) End AMC
    // ---------------------------------------------------------------------
    public function actionEnd(int $id): Response
    {
        $amc = $this->findAmc($id);
        if ((int)$amc->amc_status === Amc::AMC_ENDED) {
            Yii::$app->session->setFlash('info', 'This AMC had already ended.');
            return $this->redirect(['view', 'id' => $amc->id]);
        }
        $amc->amc_status   = Amc::AMC_ENDED;
        $amc->updated_date = date('Y-m-d H:i:s');
        $amc->save(false);
        $title = Project::find()->select('title')->where(['id' => $amc->project_id])->scalar();
        Yii::$app->session->setFlash('success', 'AMC ended' . ($title ? " for \"$title\"" : '') . '. It is now listed under Ended AMC.');
        return $this->redirect(['view', 'id' => $amc->id]);
    }

    // ---------------------------------------------------------------------
    // 2b) Remove a project from the AMC program
    // ---------------------------------------------------------------------
    public function actionRemove(int $project_id): Response
    {
        $project = Project::findOne(['id' => $project_id, 'status' => 1]);
        if (!$project) {
            throw new NotFoundHttpException('Project not found.');
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            Project::updateAll(['amc' => 0], ['id' => $project_id]);
            // Soft-delete any AMC contracts for this project.
            $contracts = Amc::updateAll(['status' => 0, 'updated_date' => date('Y-m-d H:i:s')], ['project_id' => $project_id, 'status' => 1]);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Remove from AMC failed: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'The project could not be removed from AMC. Please try again.');
            return $this->redirect(['projects']);
        }
        Yii::$app->session->setFlash('success', "\"{$project->title}\" was removed from AMC"
            . ($contracts ? " and its $contracts contract record(s) were archived." : '.'));
        return $this->redirect(['projects']);
    }

    // ---------------------------------------------------------------------
    // View — full AMC detail + renewal history
    // ---------------------------------------------------------------------
    public function actionView(int $id): string
    {
        $model   = $this->findAmc($id);
        $project = Project::findOne($model->project_id);
        $renews  = AmcRenew::find()
            ->where(['amc_id' => $id, 'status' => 1])
            ->orderBy(['end_date' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $this->render('view', compact('model', 'project', 'renews'));
    }

    private function findAmc(int $id): Amc
    {
        $model = Amc::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('AMC record not found.');
        }
        return $model;
    }
}
