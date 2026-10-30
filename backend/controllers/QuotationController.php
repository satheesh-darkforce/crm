<?php

namespace backend\controllers;

use backend\models\Currency;
use backend\models\Quotation;
use backend\models\QuotationDetail;
use backend\models\QuotationDetailAttachment;
use backend\models\QuotationRemark;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class QuotationController extends Controller
{
    public function actionIndex(): string
    {
        $query = Quotation::find()
            ->with(['latestDetail.currency'])
            ->where(['quotations.status' => 1])
            ->orderBy(['quotations.id' => SORT_DESC]);

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'client_name', $search],
                ['like', 'project_name', $search],
                ['like', 'email', $search],
                ['like', 'unique_id', $search],
            ]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);

        return $this->render('index', compact('dataProvider'));
    }

    public function actionCreate(): string|Response
    {
        $model  = new Quotation();
        $detail = new QuotationDetail();

        if ($model->load(Yii::$app->request->post()) && $detail->load(Yii::$app->request->post())) {
            $model->created_by   = Yii::$app->user->id;
            $model->created_date = date('Y-m-d H:i:s');
            $model->status       = 1;

            if ($detail->proposal_send_date) {
                $detail->proposal_send_date = date('Y-m-d', strtotime($detail->proposal_send_date));
            }
            $detail->attachmentFiles = UploadedFile::getInstances($detail, 'attachmentFiles');

            if ($model->validate() && $detail->validate()) {
                $model->save(false);
                $model->unique_id = 'MAEQTA' . sprintf('%06d', $model->id);
                $model->save(false);

                $detail->quotation_id = $model->id;
                $detail->created_by   = Yii::$app->user->id;
                $detail->created_date = date('Y-m-d H:i:s');
                $detail->status       = 1;
                $detail->save(false);
                $this->saveDetailAttachments($detail);

                Yii::$app->session->setFlash('success', 'Quotation created successfully.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        $currencies = Currency::find()->where(['status' => 1])->orderBy(['is_default' => SORT_DESC, 'title' => SORT_ASC])->all();
        return $this->render('_form', compact('model', 'detail', 'currencies'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Quotation updated successfully.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $currencies = Currency::find()->where(['status' => 1])->orderBy(['is_default' => SORT_DESC, 'title' => SORT_ASC])->all();
        $detail     = new QuotationDetail();
        return $this->render('_form', compact('model', 'detail', 'currencies'));
    }

    public function actionView(int $id): string|Response
    {
        $model      = $this->findModel($id);
        $detail     = new QuotationDetail();
        $currencies = Currency::find()->where(['status' => 1])->orderBy(['is_default' => SORT_DESC, 'title' => SORT_ASC])->all();

        if ($detail->load(Yii::$app->request->post())) {
            $detail->quotation_id = $model->id;
            $detail->created_by   = Yii::$app->user->id;
            $detail->created_date = date('Y-m-d H:i:s');
            $detail->status       = 1;

            if ($detail->proposal_send_date) {
                $detail->proposal_send_date = date('Y-m-d', strtotime($detail->proposal_send_date));
            }
            $detail->attachmentFiles = UploadedFile::getInstances($detail, 'attachmentFiles');

            if ($detail->validate()) {
                $detail->save(false);
                $this->saveDetailAttachments($detail);
                Yii::$app->session->setFlash('success', 'Quotation detail added successfully.');
                return $this->redirect(['view', 'id' => $id]);
            }
        }

        return $this->render('view', compact('model', 'detail', 'currencies'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $model         = $this->findModel($id);
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Quotation deleted.');
        return $this->redirect(['index']);
    }

    /**
     * Delete a single quotation detail version.
     */
    public function actionDeleteDetail(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $detail = QuotationDetail::findOne(['id' => $id, 'status' => 1]);
        if (!$detail) {
            throw new NotFoundHttpException('Detail not found.');
        }
        $quotationId    = $detail->quotation_id;
        $detail->status = 0;
        $detail->save(false);

        Yii::$app->session->setFlash('success', 'Quote version removed.');
        return $this->redirect(['view', 'id' => $quotationId]);
    }

    /**
     * Add a remark — GET renders modal partial, POST saves via AJAX.
     */
    public function actionAddRemark(int $id): string|Response
    {
        $model  = $this->findModel($id);
        $remark = new QuotationRemark();

        if (Yii::$app->request->isPost) {
            $remark->load(Yii::$app->request->post());
            $remark->quotation_id = $model->id;
            $remark->created_by   = Yii::$app->user->id;
            $remark->created_date = date('Y-m-d H:i:s');
            $remark->status       = 1;

            if ($remark->save()) {
                if (Yii::$app->request->isAjax) {
                    Yii::$app->response->format = Response::FORMAT_JSON;
                    return $this->asJson(['success' => true]);
                }
                Yii::$app->session->setFlash('success', 'Remark added.');
                return $this->redirect(['view', 'id' => $id]);
            }

            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return $this->asJson(['success' => false, 'errors' => $remark->errors]);
            }
        }

        // GET — render partial for modal
        $this->layout = false;
        return $this->render('_remark_form', compact('model', 'remark'));
    }

    private function saveDetailAttachments(QuotationDetail $detail): void
    {
        if (empty($detail->attachmentFiles)) {
            return;
        }
        $dir = Yii::getAlias('@webroot/uploads/quotation_details');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        foreach ($detail->attachmentFiles as $file) {
            $fileName = 'Attachment_' . uniqid() . '_' . $detail->id . '.' . $file->extension;
            $file->saveAs($dir . '/' . $fileName);
            $att                        = new QuotationDetailAttachment();
            $att->quotation_detail_id   = $detail->id;
            $att->path                  = '/uploads/quotation_details/' . $fileName;
            $att->status                = 1;
            $att->save(false);
        }
    }

    private function findModel(int $id): Quotation
    {
        $model = Quotation::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Quotation not found.');
        }
        return $model;
    }
}
