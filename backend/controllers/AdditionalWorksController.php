<?php

namespace backend\controllers;

use backend\models\AdditionalWorks;
use backend\models\Developer;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class AdditionalWorksController extends Controller
{
    public function actionIndex(): string
    {
        $query = AdditionalWorks::find()
            ->with('developer')
            ->where(['status' => 1])
            ->orderBy(['id' => SORT_DESC]);

        $dev = Yii::$app->request->get('developer_id');
        if ($dev) {
            $query->andWhere(['developer_id' => (int)$dev]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 30]]);
        $developers   = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('index', compact('dataProvider', 'developers'));
    }

    public function actionCreate(): string|Response
    {
        $model = new AdditionalWorks();
        $model->status       = 1;
        $model->created_by   = Yii::$app->user->id;
        $model->created_date = date('Y-m-d H:i:s');
        $model->date         = date('Y-m-d');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Additional work record added.');
            return $this->redirect(['index']);
        }

        $developers = ArrayHelper::map(
            Developer::find()->where(['status' => 1])->orderBy('developer_name')->all(),
            'id', 'developer_name'
        );

        return $this->render('_form', compact('model', 'developers'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Record updated.');
            return $this->redirect(['index']);
        }

        $developers = ArrayHelper::map(
            Developer::find()->where(['status' => 1])->orderBy('developer_name')->all(),
            'id', 'developer_name'
        );

        return $this->render('_form', compact('model', 'developers'));
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST only.');
        }
        $model = $this->findModel($id);
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Record deleted.');
        return $this->redirect(['index']);
    }

    private function findModel(int $id): AdditionalWorks
    {
        $model = AdditionalWorks::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Record not found.');
        }
        return $model;
    }
}
