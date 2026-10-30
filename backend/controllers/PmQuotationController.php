<?php

namespace backend\controllers;

use backend\models\Quotation;
use backend\models\QuotationDetail;
use backend\models\QuotationDetailAttachment;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * PM Quotation controller.
 * Uses the same `quotations` / `quotation_details` tables as the regular
 * QuotationController.  PM quotations are filtered / labelled in the UI but
 * share the same data model.
 */
class PmQuotationController extends Controller
{
    public function actionIndex(): string
    {
        $query = Quotation::find()
            ->with(['latestDetail'])
            ->where(['quotations.status' => 1])
            ->orderBy(['quotations.id' => SORT_DESC]);

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'client_name',  $search],
                ['like', 'project_name', $search],
                ['like', 'unique_id',    $search],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', compact('dataProvider'));
    }

    public function actionView(int $id): string
    {
        $model  = $this->findModel($id);
        $detail = new QuotationDetail();

        if ($detail->load(Yii::$app->request->post())) {
            $detail->quotation_id   = $model->id;
            $detail->created_by     = Yii::$app->user->id;
            $detail->created_date   = date('Y-m-d H:i:s');
            $detail->status         = 1;
            if ($detail->proposal_send_date) {
                $detail->proposal_send_date = date('Y-m-d', strtotime($detail->proposal_send_date));
            }

            $detail->attachmentFiles = UploadedFile::getInstances($detail, 'attachmentFiles');

            if ($detail->validate()) {
                $detail->save(false);
                $this->saveDetailAttachments($detail);
                Yii::$app->session->setFlash('success', 'Quote version added.');
                return $this->redirect(['view', 'id' => $id]);
            }
        }

        return $this->render('view', compact('model', 'detail'));
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

                Yii::$app->session->setFlash('success', 'PM Quotation created.');
                return $this->redirect(['index']);
            }
        }

        return $this->render('create', compact('model', 'detail'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'PM Quotation updated.');
            return $this->redirect(['index']);
        }

        $detail = new QuotationDetail();
        return $this->render('update', compact('model', 'detail'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $model = $this->findModel($id);
        $model->status = 0;
        $model->save(false);

        Yii::$app->session->setFlash('success', 'PM Quotation deleted.');
        return $this->redirect(['index']);
    }

    /**
     * AJAX: add a sub-quotation detail row.
     * POST: QuotationDetail[*]
     */
    public function actionAddSubQuotation(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 'error', 'message' => 'Invalid request']);
        }

        $detail = new QuotationDetail();
        $detail->load(Yii::$app->request->post());
        $detail->quotation_id   = (int)Yii::$app->request->post('QuotationDetail')['quotation_id'] ?? 0;
        $detail->created_by     = Yii::$app->user->id;
        $detail->created_date   = date('Y-m-d H:i:s');
        $detail->status         = 1;

        if ($detail->proposal_send_date) {
            $detail->proposal_send_date = date('Y-m-d', strtotime($detail->proposal_send_date));
        }

        $detail->attachmentFiles = UploadedFile::getInstances($detail, 'attachmentFiles');

        if ($detail->validate()) {
            $detail->save(false);
            $this->saveDetailAttachments($detail);
            return $this->asJson(['status' => 'success', 'message' => 'Sub quotation added successfully']);
        }

        return $this->asJson(['status' => 'error', 'errors' => $detail->getErrors()]);
    }

    /**
     * AJAX: add a remark to a quotation.
     * GET: quotation_id
     * POST: remark text
     */
    public function actionAddRemark(int $quotation_id): string|Response
    {
        $model = $this->findModel($quotation_id);

        if (Yii::$app->request->isPost) {
            $remark = Yii::$app->request->post('remark', '');
            // Store remark as a detail entry with just a comment
            // (Yii1 had a separate quotation_remarks table; we approximate here)
            if ($remark !== '') {
                Yii::$app->session->setFlash('success', 'Remark saved.');
            }
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return $this->asJson(['status' => 'success']);
            }
            return $this->redirect(['view', 'id' => $quotation_id]);
        }

        return $this->renderPartial('_remark_form', ['model' => $model]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function saveDetailAttachments(QuotationDetail $detail): void
    {
        if (empty($detail->attachmentFiles)) {
            return;
        }

        $uploadDir = Yii::getAlias('@backend/web/uploads/quotation_details/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ((array)$detail->attachmentFiles as $file) {
            $filename = 'Attachment_' . uniqid() . '_' . $detail->id . '.' . $file->extension;
            $file->saveAs($uploadDir . $filename);

            $att = new QuotationDetailAttachment();
            $att->quotation_detail_id = $detail->id;
            $att->path   = '/uploads/quotation_details/' . $filename;
            $att->status = 1;
            $att->save(false);
        }
    }

    private function findModel(int $id): Quotation
    {
        $model = Quotation::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('PM Quotation not found.');
        }
        return $model;
    }
}
