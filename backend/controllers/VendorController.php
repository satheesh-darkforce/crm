<?php

namespace backend\controllers;

use backend\models\AccLedger;
use backend\models\Vendor;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class VendorController extends Controller
{
    public function actionIndex(): string
    {
        $query = Vendor::find()->where(['vendors.status' => 1])->orderBy('company_name');

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'company_name', $search],
                ['like', 'gst_no', $search],
            ]);
        }

        if ($type = Yii::$app->request->get('vendor_type')) {
            $query->andWhere(['vendor_type' => (int)$type]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);

        $iotPurchase = (int) Yii::$app->request->get('iotpurchase', 0);

        return $this->render('index', compact('dataProvider', 'iotPurchase'));
    }

    public function actionCreate(): string|Response
    {
        $model             = new Vendor();
        $model->status     = 1;

        if ($model->load(Yii::$app->request->post())) {
            $model->created_by = Yii::$app->user->id;
            $model->created_at = date('Y-m-d H:i:s');
            if ($model->save()) {
                // Create AccLedger entry (Sundry Creditor) — mirrors Yii1 behavior
                $ledger = new AccLedger();
                $ledger->category_id = 10; // Sundry Creditor
                $ledger->title       = $model->company_name;
                $ledger->type        = 2;  // Vendor ledger
                $ledger->is_fixed    = 0;
                $ledger->vendor_id   = $model->id;
                $ledger->created_at  = date('Y-m-d H:i:s');
                $ledger->created_by  = Yii::$app->user->id;
                $ledger->status      = 1;
                $ledger->save(false);

                Yii::$app->session->setFlash('success', 'Vendor added successfully.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('_form', compact('model'));
    }

    public function actionView(int $id): string
    {
        $model          = $this->findModel($id);
        $inwardInvoices = $model->inwardInvoices;
        return $this->render('view', compact('model', 'inwardInvoices'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);
        $oldCompanyName = $model->company_name;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // Sync AccLedger title when company name changes — mirrors Yii1 behavior
            if ($model->company_name !== $oldCompanyName) {
                AccLedger::updateAll(
                    ['title' => $model->company_name, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => Yii::$app->user->id],
                    ['vendor_id' => $model->id, 'type' => 2]
                );
            }

            Yii::$app->session->setFlash('success', 'Vendor updated successfully.');
            return $this->redirect(['view', 'id' => $id]);
        }

        return $this->render('_form', compact('model'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $model         = $this->findModel($id);
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Vendor deleted.');
        return $this->redirect(['index']);
    }

    private function findModel(int $id): Vendor
    {
        $model = Vendor::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Vendor not found.');
        }
        return $model;
    }
}
