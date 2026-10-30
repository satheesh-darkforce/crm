<?php

namespace backend\controllers;

use backend\models\BankDetail;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class BankDetailController extends Controller
{
    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query'      => BankDetail::find()->where(['status' => 1])->orderBy('bank_name'),
            'pagination' => ['pageSize' => 20],
        ]);
        return $this->render('index', ['dataProvider' => $dataProvider]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate(): string|Response
    {
        $model = new BankDetail();
        $model->status     = 1;
        $model->is_default = 0;

        if ($model->load(Yii::$app->request->post())) {
            // Only one default account
            if ($model->is_default) {
                BankDetail::updateAll(['is_default' => 0], ['status' => 1]);
            }
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Bank account added.');
                return $this->redirect(['index']);
            }
        }

        return $this->render('_form', compact('model'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            if ($model->is_default) {
                BankDetail::updateAll(['is_default' => 0], ['status' => 1]);
            }
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Bank account updated.');
                return $this->redirect(['index']);
            }
        }

        return $this->render('_form', compact('model'));
    }

    public function actionSetDefault(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        BankDetail::updateAll(['is_default' => 0], ['status' => 1]);
        $model = $this->findModel($id);
        $model->is_default = 1;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Default account updated.');
        return $this->redirect(['index']);
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $model = $this->findModel($id);
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Bank account deleted.');
        return $this->redirect(['index']);
    }

    private function findModel(int $id): BankDetail
    {
        $model = BankDetail::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Bank account not found.');
        }
        return $model;
    }
}
