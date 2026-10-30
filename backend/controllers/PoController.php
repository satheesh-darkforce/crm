<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\models\Developer;
use backend\models\PoDetail;
use backend\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Standalone Purchase Order management — distinct from quotation-linked POs
 * (which are handled by ClientQuotationController::actionAddPo).
 *
 *  - actionIndex : plain listing (no actions; gated by `po` permission)
 *  - actionCreate: standalone Add PO form (client + company select, no quote_id)
 *  - actionUpdate: edit existing PO
 *  - actionView  : full details of a PO (also reachable from dashboard)
 *  - actionGetClientCompany: AJAX — returns company_name when a client is picked
 */
class PoController extends Controller
{
    public function actionIndex(): string
    {
        $request = Yii::$app->request;
        $search  = trim((string) $request->get('search', ''));
        $status  = $request->get('status', '');
        $client  = (int) $request->get('client_id', 0);

        $query = PoDetail::find()
            ->alias('po')
            ->leftJoin(['c1' => ClientModel::tableName()], 'c1.id = po.client_id')
            ->leftJoin(['cq' => 'client_quotation'], 'cq.id = po.quote_id')
            ->leftJoin(['c2' => ClientModel::tableName()], 'c2.id = cq.client_id')
            ->where(['!=', 'po.status', 0])
            // Eager-load the client relations so the listing column doesn't
            // trigger N+1 queries while resolving each PO's client_name.
            ->with(['client', 'quote.client'])
            ->orderBy(['po.id' => SORT_DESC]);

        if ($search !== '') {
            $query->andWhere(['or',
                ['like', 'po.po_number', $search],
                ['like', 'po.particulars', $search],
                ['like', 'c1.company_name', $search],
                ['like', 'c2.company_name', $search],
            ]);
        }
        if ($status !== '' && array_key_exists((int) $status, PoDetail::statusOptions())) {
            $query->andWhere(['po.po_status' => (int) $status]);
        }
        if ($client > 0) {
            $query->andWhere(['or', ['po.client_id' => $client], ['cq.client_id' => $client]]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 25],
            'sort'       => false,
        ]);

        return $this->render('index', [
            'dataProvider'  => $dataProvider,
            'clientOptions' => $this->getClientOptions(),
            'filters'       => [
                'search' => $search,
                'status' => (string) $status,
                'client_id' => $client,
            ],
        ]);
    }

    public function actionCreate(): string|Response
    {
        $model = new PoDetail(['scenario' => 'create']);
        $model->date      = date('Y-m-d');
        $model->po_status = PoDetail::STATUS_PENDING;

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->po_file = UploadedFile::getInstance($model, 'po_file');
            $now            = date('Y-m-d H:i:s');
            $model->created_at  = $now;
            $model->modified_at = $now;
            $model->status      = 1;
            $model->quote_id    = null; // standalone

            if (!empty($model->date)) {
                $model->date = date('Y-m-d H:i:s', strtotime((string) $model->date));
            }
            if (!empty($model->delivery_date)) {
                $model->delivery_date = date('Y-m-d H:i:s', strtotime((string) $model->delivery_date));
            } else {
                $model->delivery_date = null;
            }
            if (empty($model->po_status)) {
                $model->po_status = PoDetail::STATUS_PENDING;
            }
            // po_incharge column is NOT NULL on the live DB. Optional from the
            // user's perspective → store 0 (unassigned) when left blank.
            if ($model->po_incharge === '' || $model->po_incharge === null) {
                $model->po_incharge = 0;
            }
            // given_cost is form-only — for standalone POs there is no quotation
            // to compare against, so the conditional Reason rule sees gc=0 and
            // never fires unless the user explicitly enters one.
            $model->given_cost = $model->given_cost ?: '0';

            if ($model->validate()) {
                if ($this->savePoFile($model)) {
                    if ($model->save(false)) {
                        Yii::$app->session->setFlash('success', 'Purchase Order added successfully.');
                        return $this->redirect(['view', 'id' => $model->id]);
                    }
                }
                Yii::$app->session->setFlash('error', 'Unable to save the PO file. Please try again.');
            }
        }

        return $this->render('create', [
            'model'           => $model,
            'clientOptions'   => $this->getClientOptions(),
            'companyOptions'  => $this->getCompanyOptions(),
            'inchargeOptions' => $this->getInchargeOptions(),
        ]);
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);
        // Default scenario — keeps the existing file unless a new upload arrives.
        // The `on=>'create'` constraint on po_file only fires in the create flow.

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $upload = UploadedFile::getInstance($model, 'po_file');
            if ($upload) {
                $model->po_file = $upload;
            }
            $model->modified_at = date('Y-m-d H:i:s');

            if (!empty($model->date)) {
                $model->date = date('Y-m-d H:i:s', strtotime((string) $model->date));
            }
            if (!empty($model->delivery_date)) {
                $model->delivery_date = date('Y-m-d H:i:s', strtotime((string) $model->delivery_date));
            } else {
                $model->delivery_date = null;
            }
            // po_incharge column is NOT NULL on the live DB. Optional from the
            // user's perspective → store 0 (unassigned) when left blank.
            if ($model->po_incharge === '' || $model->po_incharge === null) {
                $model->po_incharge = 0;
            }
            $model->given_cost = $model->given_cost ?: '0';

            if ($model->validate()) {
                if ($upload) {
                    if (!$this->savePoFile($model)) {
                        Yii::$app->session->setFlash('error', 'Unable to save the PO file. Please try again.');
                        return $this->refresh();
                    }
                }
                if ($model->save(false)) {
                    Yii::$app->session->setFlash('success', 'Purchase Order updated successfully.');
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }

        return $this->render('update', [
            'model'           => $model,
            'clientOptions'   => $this->getClientOptions(),
            'companyOptions'  => $this->getCompanyOptions(),
            'inchargeOptions' => $this->getInchargeOptions(),
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /** AJAX: returns the company_name for a given client_id. */
    public function actionGetClientCompany(int $client_id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $row = ClientModel::find()
            ->select(['company_name', 'address', 'gst_no'])
            ->where(['id' => $client_id])
            ->asArray()
            ->one();
        return [
            'success'      => (bool) $row,
            'company_name' => (string) ($row['company_name'] ?? ''),
            'address'      => (string) ($row['address']      ?? ''),
            'gst_no'       => (string) ($row['gst_no']       ?? ''),
        ];
    }

    // ──────────────────────────────────────────────────── helpers ──

    private function findModel(int $id): PoDetail
    {
        $model = PoDetail::findOne($id);
        if (!$model || (int) $model->status === 0) {
            throw new NotFoundHttpException('Purchase Order not found.');
        }
        return $model;
    }

    private function getClientOptions(): array
    {
        return ClientModel::find()
            ->select(['client_name', 'id'])
            ->where(['status' => 1])
            ->orderBy(['client_name' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }

    private function getCompanyOptions(): array
    {
        return ClientModel::find()
            ->select(['company_name', 'id'])
            ->where(['status' => 1])
            ->andWhere(['not', ['company_name' => null]])
            ->andWhere(['<>', 'company_name', ''])
            ->orderBy(['company_name' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }

    private function getInchargeOptions(): array
    {
        return User::find()
            ->alias('u')
            ->select(['d.developer_name', 'u.user_id'])
            ->innerJoin(['d' => Developer::tableName()], 'd.user_id = u.user_id')
            ->where(['u.status' => 1, 'u.usertype' => 2, 'd.status' => 1])
            ->orderBy(['d.developer_name' => SORT_ASC])
            ->indexBy('user_id')
            ->column();
    }

    private function savePoFile(PoDetail $po): bool
    {
        $file = $po->po_file;
        if (!$file instanceof UploadedFile) {
            $po->addError('po_file', 'PO file is required.');
            return false;
        }
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'];
        if (!in_array(strtolower((string) $file->extension), $allowedExt, true)) {
            $po->addError('po_file', 'Allowed file types: PDF, JPG, PNG, GIF, WebP, DOC, DOCX.');
            return false;
        }
        $dir = Yii::getAlias('@webroot/uploads/po-details/');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $clean = preg_replace('/[^a-zA-Z0-9._-]/', '', $file->baseName);
        $name  = time() . '_' . ($po->id ?: 'new') . '_' . $clean . '.' . $file->extension;
        if (!$file->saveAs($dir . $name)) {
            $po->addError('po_file', 'Could not save uploaded file.');
            return false;
        }
        $po->file = 'uploads/po-details/' . $name;
        return true;
    }
}
