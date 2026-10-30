<?php

namespace backend\controllers;

use backend\models\AccLedger;
use backend\models\ClientModel;
use backend\models\Invoice;
use backend\models\User;
use Yii;
use yii\data\ArrayDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend CRUD for Client.
 */
class ClientController extends Controller
{
    public function actionIndex(): string
    {
        $db = Yii::$app->db;

        // Mirror Yii1 Client::search() filter set — individual fields, all LIKE-matched.
        $req         = Yii::$app->request;
        $name        = trim((string)$req->get('name', ''));          // legacy combined name/company filter
        $clientName  = trim((string)$req->get('client_name', ''));
        $companyName = trim((string)$req->get('company_name', ''));
        $phone       = trim((string)$req->get('phone', ''));
        $email       = trim((string)$req->get('email_id', ''));
        $gstNo       = trim((string)$req->get('gst_no', ''));
        $clientType  = trim((string)$req->get('client_type', ''));

        $where  = 'c.status = 1';
        $params = [];

        if ($name !== '') {
            $where .= " AND (c.client_name LIKE :legacyname OR c.company_name LIKE :legacyname)";
            $params[':legacyname'] = '%' . $name . '%';
        }
        if ($clientName !== '') {
            $where .= ' AND c.client_name LIKE :cname';
            $params[':cname'] = '%' . $clientName . '%';
        }
        if ($companyName !== '') {
            $where .= ' AND c.company_name LIKE :coname';
            $params[':coname'] = '%' . $companyName . '%';
        }
        if ($phone !== '') {
            $where .= ' AND c.phone LIKE :phone';
            $params[':phone'] = '%' . $phone . '%';
        }
        if ($email !== '') {
            $where .= ' AND c.email_id LIKE :email';
            $params[':email'] = '%' . $email . '%';
        }
        if ($gstNo !== '') {
            $where .= ' AND c.gst_no LIKE :gst';
            $params[':gst'] = '%' . $gstNo . '%';
        }
        if ($clientType !== '' && in_array($clientType, ['1', '2', '3'], true)) {
            $where .= ' AND c.client_type = :ctype';
            $params[':ctype'] = (int)$clientType;
        }

        // Yii1 parity: join work_report directly on wr.client_id (NOT via project),
        // sort by MAX(wr.work_id) DESC so clients with the most recent work activity
        // appear first. last_work_date / cur_month / last_month aggregate from
        // work_report.total_time (stored in SECONDS).
        $rows = $db->createCommand("
            SELECT c.id, c.user_id, c.client_name, c.company_name,
                   c.phone, c.email_id, c.gst_no, c.logo, c.client_type, c.status,
                   MAX(wr.work_id) AS max_work_id,
                   MAX(wr.date)    AS last_work_date,
                   SUM(CASE WHEN DATE_FORMAT(wr.date, '%Y-%m') = :curMonth
                            THEN wr.total_time ELSE 0 END) AS cur_month_secs,
                   SUM(CASE WHEN DATE_FORMAT(wr.date, '%Y-%m') = :lastMonth
                            THEN wr.total_time ELSE 0 END) AS last_month_secs
            FROM client c
            LEFT JOIN work_report wr ON wr.client_id = c.id AND wr.status = 1
            WHERE $where
            GROUP BY c.id
            ORDER BY MAX(wr.work_id) IS NULL, MAX(wr.work_id) DESC, c.id DESC
        ", array_merge($params, [
            ':curMonth'  => date('Y-m'),
            ':lastMonth' => date('Y-m', strtotime('-1 month')),
        ]))->queryAll();

        $dataProvider = new ArrayDataProvider([
            'allModels'  => $rows,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchName'   => $name,
            'clientName'   => $clientName,
            'companyName'  => $companyName,
            'phone'        => $phone,
            'email'        => $email,
            'gstNo'        => $gstNo,
            'clientType'   => $clientType,
        ]);
    }

    public function actionView(int $id): string
    {
        $model     = $this->findModel($id);
        $curMonth  = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));

        // work_report.total_time is stored in SECONDS (Yii1 parity).
        // Renamed *_secs to make the unit obvious — the previous *_mins names
        // led to a formatter bug that displayed values 60× too large.
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
        $client = $this->findModel($id);
        $granted = array_filter(explode(',', (string)$client->access_permissions));

        if (Yii::$app->request->isPost) {
            $options = Yii::$app->request->post('accessoptions', []);
            $client->access_permissions = $options ? implode(',', $options) : null;
            $client->save(false);
            Yii::$app->session->setFlash('success', 'Permissions saved.');
            return $this->redirect(['view', 'id' => $id]);
        }

        return $this->render('access-permissions', compact('client', 'granted'));
    }

    public function actionCreate(): string|Response
    {
        $client = new ClientModel();
        $user   = new User();

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();

            $user->username = trim($post['username'] ?? '');
            $user->usertype = User::TYPE_CLIENT;
            $user->status   = 1;
            $rawPassword    = trim($post['password'] ?? '');

            $client->load($post, 'ClientModel');
            $client->status = 1;

            // Collect errors together
            $hasUserError = false;
            if ($rawPassword === '') {
                $user->addError('password', 'Password is required.');
                $hasUserError = true;
            }
            if (!$hasUserError) {
                $user->setPassword($rawPassword);
            }

            $userValid   = !$hasUserError && $user->validate();
            $clientValid = $client->validate();

            if (!$userValid || !$clientValid) {
                return $this->render('create', compact('client', 'user'));
            }

            if (!$user->save(false)) {
                $user->addError('username', 'Failed to create user account.');
                return $this->render('create', compact('client', 'user'));
            }

            $client->user_id = $user->user_id;

            if ($client->save(false)) {
                // Create AccLedger entry (Sundry Debtor) — mirrors Yii1 behavior
                if ($client->company_name !== '') {
                    $ledger = new AccLedger();
                    $ledger->category_id = 8; // Sundry Debtor
                    $ledger->title = $client->company_name;
                    $ledger->type = 1; // For client
                    $ledger->is_fixed = 0;
                    $ledger->client_id = $client->id;
                    $ledger->created_at = date('Y-m-d H:i:s');
                    $ledger->created_by = Yii::$app->user->id;
                    $ledger->status = 1;
                    $ledger->save(false);
                }

                Yii::$app->session->setFlash('success', 'Client created successfully.');
                return $this->redirect(['view', 'id' => $client->id]);
            }

            $user->delete();
            $client->addError('client_name', 'Failed to save client record.');
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
     * Reset the client's login password (POST, modal form).
     */
    public function actionResetPassword(int $id): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }
        $client = $this->findModel($id);

        $newPassword = trim(Yii::$app->request->post('new_password', ''));
        if ($newPassword === '') {
            Yii::$app->session->setFlash('error', 'Password cannot be empty.');
            return $this->redirect(['index']);
        }

        $user = User::findOne(['user_id' => $client->user_id]);
        if ($user) {
            $user->setPassword($newPassword);
            $user->force_password_change = 1;
            $user->save(false);
            Yii::$app->session->setFlash('success', "Password reset for {$client->client_name}.");
        } else {
            Yii::$app->session->setFlash('error', 'User account not found for this client.');
        }

        return $this->redirect(['index']);
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }
        $client         = $this->findModel($id);
        $client->status = 0;
        $client->save(false);

        Yii::$app->session->setFlash('success', 'Client deactivated.');
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
