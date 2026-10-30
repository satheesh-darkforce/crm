<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\models\Project;
use backend\models\Proposal;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class ProposalController extends Controller
{
    public function actionIndex(): string
    {
        $query = Proposal::find()
            ->with(['client', 'project'])
            ->where(['proposals.status' => 1])
            ->orderBy(['proposals.id' => SORT_DESC]);

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['like', 'proposals.title', $search]);
        }
        if ($clientId = Yii::$app->request->get('client_id')) {
            $query->andWhere(['proposals.client_id' => $clientId]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);
        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('index', compact('dataProvider', 'clients'));
    }

    public function actionCreate(): string|Response
    {
        $model = new Proposal();
        $model->status = 1;
        $model->proposal_status = 1;

        if ($model->load(Yii::$app->request->post())) {
            $model->created_by   = Yii::$app->user->id;
            $model->created_date = date('Y-m-d H:i:s');

            $model->attachmentFile = UploadedFile::getInstance($model, 'attachmentFile');
            if ($model->attachmentFile) {
                $dir = Yii::getAlias('@webroot/uploads/proposals');
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
                $fileName = 'Attachment_' . uniqid() . '.' . $model->attachmentFile->extension;
                $model->attachmentFile->saveAs($dir . '/' . $fileName);
                $model->attachment = '/uploads/proposals/' . $fileName;
            }

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Proposal added successfully.');
                return $this->redirect(['index']);
            }
        }

        $clients  = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('_form', compact('model', 'clients', 'projects'));
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $model->attachmentFile = UploadedFile::getInstance($model, 'attachmentFile');
            if ($model->attachmentFile) {
                $dir = Yii::getAlias('@webroot/uploads/proposals');
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
                $fileName = 'Attachment_' . uniqid() . '.' . $model->attachmentFile->extension;
                $model->attachmentFile->saveAs($dir . '/' . $fileName);
                $model->attachment = '/uploads/proposals/' . $fileName;
            }

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Proposal updated successfully.');
                return $this->redirect(['index']);
            }
        }

        $clients  = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('_form', compact('model', 'clients', 'projects'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $model = $this->findModel($id);
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Proposal deleted.');
        return $this->redirect(['index']);
    }

    public function actionGetProjectsByClient(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $clientId = Yii::$app->request->post('client_id');
        $projects = Project::find()
            ->where(['status' => 1, 'client_id' => $clientId])
            ->orderBy('title')
            ->all();
        $result = [];
        foreach ($projects as $p) {
            $result[] = ['id' => $p->id, 'text' => $p->title];
        }
        return $this->asJson($result);
    }

    private function findModel(int $id): Proposal
    {
        $model = Proposal::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Proposal not found.');
        }
        return $model;
    }
}
