<?php

namespace backend\controllers;

use backend\assets\ClientAsset;
use backend\models\AccLedger;
use backend\models\ClientModel;
use backend\models\Invoice;
use backend\models\User;
use Yii;
use yii\data\ArrayDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend CRUD for Client.
 */
class ClientController extends Controller
{
    /** Full pages get the Clients stylesheet/script. */
    public function render($view, $params = []): string
    {
        ClientAsset::register($this->getView());
        return parent::render($view, $params);
    }

    public function actionIndex(): string
    {
        // Mirror Yii1 Client::search() filter set — individual fields, all LIKE-matched.
        $req     = Yii::$app->request;
        $filters = [];
        foreach (['name', 'client_name', 'company_name', 'phone', 'email_id', 'gst_no', 'client_type'] as $key) {
            $value = $req->get($key, '');
            $filters[$key] = is_string($value) ? mb_substr(trim($value), 0, 100) : ''; // arrays from tampered URLs are ignored
        }
        if ($filters['client_type'] !== '' && !isset(ClientModel::typeOptions()[(int)$filters['client_type']])) {
            Yii::$app->session->setFlash('warning', 'Ignored an invalid client type filter.');
            $filters['client_type'] = '';
        }

        $where  = 'c.status = 1';
        $params = [];
        if ($filters['name'] !== '') { // legacy combined name/company filter
            $where .= ' AND (c.client_name LIKE :legacyname OR c.company_name LIKE :legacyname)';
            $params[':legacyname'] = '%' . $filters['name'] . '%';
        }
        foreach (['client_name' => 'c.client_name', 'company_name' => 'c.company_name', 'phone' => 'c.phone',
                  'email_id' => 'c.email_id', 'gst_no' => 'c.gst_no'] as $key => $column) {
            if ($filters[$key] !== '') {
                $where .= " AND $column LIKE :$key";
                $params[":$key"] = '%' . $filters[$key] . '%';
            }
        }
        if ($filters['client_type'] !== '') {
            $where .= ' AND c.client_type = :ctype';
            $params[':ctype'] = (int)$filters['client_type'];
        }

        // Yii1 parity: work_report joined on wr.client_id (NOT via project), clients
        // with the most recent work first. total_time is stored in SECONDS.
        // Aggregating work_report once per client in a derived table (with date
        // ranges instead of DATE_FORMAT) keeps this ~5x faster than grouping the
        // joined rows.
        $curStart  = date('Y-m-01');
        $lastStart = date('Y-m-01', strtotime($curStart . ' -1 month'));
        $nextStart = date('Y-m-01', strtotime($curStart . ' +1 month'));
        $rows = Yii::$app->db->createCommand("
            SELECT c.id, c.user_id, c.client_name, c.company_name,
                   c.phone, c.email_id, c.gst_no, c.logo, c.client_type, c.status,
                   wr.max_work_id, wr.last_work_date,
                   COALESCE(wr.cur_month_secs, 0)  AS cur_month_secs,
                   COALESCE(wr.last_month_secs, 0) AS last_month_secs
            FROM client c
            LEFT JOIN (
                SELECT client_id,
                       MAX(work_id) AS max_work_id,
                       MAX(date)    AS last_work_date,
                       SUM(CASE WHEN date >= :curStart  AND date < :nextStart THEN total_time ELSE 0 END) AS cur_month_secs,
                       SUM(CASE WHEN date >= :lastStart AND date < :curStart  THEN total_time ELSE 0 END) AS last_month_secs
                FROM work_report
                WHERE status = 1
                GROUP BY client_id
            ) wr ON wr.client_id = c.id
            WHERE $where
            ORDER BY wr.max_work_id IS NULL, wr.max_work_id DESC, c.id DESC
        ", array_merge($params, [':curStart' => $curStart, ':lastStart' => $lastStart, ':nextStart' => $nextStart]))->queryAll();

        $dataProvider = new ArrayDataProvider([
            'allModels'  => $rows,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', compact('dataProvider', 'filters'));
    }

    public function actionView(int $id): string
    {
        $model     = $this->findModel($id);
        $curMonth  = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));

        // work_report.total_time is stored in SECONDS (Yii1 parity).
        $workRows = Yii::$app->db->createCommand("
            SELECT p.title AS project_title,
                   SUM(CASE WHEN DATE_FORMAT(wr.date,'%Y-%m') = :cur  THEN wr.total_time ELSE 0 END) AS cur_secs,
                   SUM(CASE WHEN DATE_FORMAT(wr.date,'%Y-%m') = :last THEN wr.total_time ELSE 0 END) AS last_secs,
                   SUM(wr.total_time) AS total_secs
            FROM work_report wr
            JOIN project p ON p.id = wr.project_id
            WHERE wr.client_id = :cid AND wr.status = 1
            GROUP BY wr.project_id
            ORDER BY MAX(wr.work_id) DESC
        ", [':cid' => $id, ':cur' => $curMonth, ':last' => $lastMonth])->queryAll();

        // Totals across ALL projects (footer reflects entire result, not just page).
        $totals = ['cur' => 0, 'last' => 0, 'all' => 0];
        foreach ($workRows as $r) {
            $totals['cur']  += (int)$r['cur_secs'];
            $totals['last'] += (int)$r['last_secs'];
            $totals['all']  += (int)$r['total_secs'];
        }

        $workProvider = new ArrayDataProvider([
            'allModels'  => $workRows,
            'pagination' => ['pageSize' => 10],
            'sort'       => false,
        ]);

        $domains  = $model->domains;
        $invoices = Invoice::find()
            ->where(['client_id' => $id, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        return $this->render('view', compact('model', 'workProvider', 'totals', 'domains', 'invoices'));
    }

    public function actionAccessPermissions(int $id): string|Response
    {
        $client  = $this->findModel($id);
        if ($blocked = $this->portalBlocked($client, ['view', 'id' => $id])) {
            return $blocked;
        }
        $granted = array_filter(explode(',', (string)$client->access_permissions));

        if (Yii::$app->request->isPost) {
            // Only routes offered on the page can be stored.
            $allowed = array_merge(...array_map('array_keys', array_values(ClientModel::portalPermissionOptions())));
            $posted  = Yii::$app->request->post('accessoptions', []);
            $options = array_values(array_intersect($allowed, is_array($posted) ? $posted : []));
            $client->access_permissions = $options ? implode(',', $options) : null;
            $client->save(false);
            Yii::$app->session->setFlash('success', $options
                ? 'Portal permissions saved (' . count($options) . ' granted).'
                : 'Portal permissions saved. This client has no portal access.');
            return $this->redirect(['view', 'id' => $id]);
        }

        return $this->render('access-permissions', compact('client', 'granted'));
    }

    public function actionCreate(): string|Response
    {
        $client = new ClientModel(['tax_exemption' => 0]);
        $user   = new User();

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();

            $client->load($post, 'ClientModel');
            $client->status = 1;

            // Portal logins off: no username / password asked, no login account created.
            $portal = ClientModel::portalEnabled();
            if ($portal) {
                $user->username = is_string($post['username'] ?? null) ? trim($post['username']) : '';
                $user->usertype = User::TYPE_CLIENT;
                $user->status   = 1;
                $rawPassword    = is_string($post['password'] ?? null) ? $post['password'] : '';
            }

            $loginValid  = !$portal || ClientModel::validateLogin($user, $rawPassword);
            $clientValid = $client->validate();

            if ($loginValid && $clientValid) {
                if ($client->saveWithLogin($portal ? $user : null)) {
                    Yii::$app->session->setFlash('success', 'Client created successfully.');
                    return $this->redirect(['view', 'id' => $client->id]);
                }
                Yii::$app->session->setFlash('error', 'The client could not be saved. Please try again — nothing was created.');
            }
        }

        return $this->render('create', compact('client', 'user'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $client = $this->findModel($id);
        $oldCompanyName = $client->company_name;

        if ($client->load(Yii::$app->request->post())) {
            if ($client->save()) {
                // Sync AccLedger title when company name changes — mirrors Yii1 behavior
                if ($client->company_name !== $oldCompanyName && !empty($oldCompanyName)) {
                    AccLedger::updateAll(
                        ['title' => $client->company_name],
                        ['client_id' => $client->id, 'type' => 1]
                    );
                }

                Yii::$app->session->setFlash('success', 'Client updated successfully.');
                return $this->redirect(['view', 'id' => $client->id]);
            }
        }

        return $this->render('update', ['client' => $client]);
    }

    /**
     * Reset the client's login password (POST, modal form on the list and view pages).
     */
    /**
     * Redirect with a message when a portal action is not possible: portal logins
     * are switched off (params 'client.portalLogin') or the client has no login.
     */
    private function portalBlocked(ClientModel $client, array $back): ?Response
    {
        if (!ClientModel::portalEnabled()) {
            Yii::$app->session->setFlash('error', 'Client portal logins are switched off.');
            return $this->redirect($back);
        }
        if (!$client->hasLogin()) {
            Yii::$app->session->setFlash('error', 'This client has no portal login.');
            return $this->redirect($back);
        }
        return null;
    }
    public function actionResetPassword(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new BadRequestHttpException('POST only.');
        }
        $client   = $this->findModel($id);
        $back     = Yii::$app->request->post('return') === 'view' ? ['view', 'id' => $id] : ['index'];
        if ($blocked = $this->portalBlocked($client, $back)) {
            return $blocked;
        }
        $password = Yii::$app->request->post('new_password', '');
        $password = is_string($password) ? $password : '';

        if ($error = ClientModel::passwordError($password)) {
            Yii::$app->session->setFlash('error', $error);
            return $this->redirect($back);
        }

        $user = User::findOne(['user_id' => $client->user_id]);
        if ($user) {
            $user->setPassword(trim($password));
            $user->force_password_change = 1;
            $user->save(false);
            Yii::$app->session->setFlash('success', "Password reset for {$client->client_name}. They will be asked to change it at next login.");
        } else {
            Yii::$app->session->setFlash('error', 'This client has no login account, so there is no password to reset.');
        }

        return $this->redirect($back);
    }

    /**
     * Deactivate (soft delete) a client and its portal login.
     */
    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new BadRequestHttpException('POST only.');
        }
        $client         = $this->findModel($id);
        $client->status = 0;
        $client->save(false);
        if ($client->user_id) {
            User::updateAll(['status' => 0], ['user_id' => $client->user_id, 'usertype' => User::TYPE_CLIENT]);
        }

        Yii::$app->session->setFlash('success', "{$client->client_name} was deactivated and can no longer log in.");
        return $this->redirect(['index']);
    }

    private function findModel(int $id): ClientModel
    {
        $model = ClientModel::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Client not found.');
        }
        return $model;
    }
}
