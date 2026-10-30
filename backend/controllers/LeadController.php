<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\models\Lead;
use backend\models\LeadAttachments;
use backend\models\LeadFollowers;
use backend\models\LeadMaster;
use backend\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Backend CRM Lead management.
 */
class LeadController extends Controller
{
    public function actionIndex(): string
    {
        $query = Lead::find()
            ->with(['assignedTo', 'createdBy'])
            ->where(['lead.status' => 1])
            ->andWhere(['not in', 'lead.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]])
            ->orderBy(['lead.meeting_reported_date' => SORT_ASC]);

        if ($status = Yii::$app->request->get('lead_status')) {
            $query->andWhere(['lead_status' => (int)$status]);
        }
        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'client_name', $search],
                ['like', 'subject', $search],
                ['like', 'company_name', $search],
            ]);
        }
        if ($assignedTo = Yii::$app->request->get('assigned_to')) {
            $query->andWhere(['assigned_to' => (int)$assignedTo]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $users = $this->getAssignableUsers();

        return $this->render('index', compact('dataProvider', 'users'));
    }

    public function actionConverted(): string
    {
        $query = Lead::find()
            ->with(['assignedTo', 'createdBy'])
            ->where(['lead.status' => 1, 'lead.lead_status' => Lead::LEAD_STATUS_CONVERTED])
            ->orderBy(['lead.id' => SORT_DESC]);

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'client_name', $search],
                ['like', 'subject', $search],
                ['like', 'company_name', $search],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('converted', compact('dataProvider'));
    }

    public function actionDropped(): string
    {
        $query = Lead::find()
            ->with(['assignedTo', 'createdBy'])
            ->where(['lead.status' => 1, 'lead.lead_status' => Lead::LEAD_STATUS_DROPPED])
            ->orderBy(['lead.id' => SORT_DESC]);

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'client_name', $search],
                ['like', 'subject', $search],
                ['like', 'company_name', $search],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('dropped', compact('dataProvider'));
    }

    public function actionMyLeads(): string
    {
        $userId = Yii::$app->user->id;
        $query  = Lead::find()
            ->with(['assignedTo', 'createdBy'])
            ->where(['lead.status' => 1, 'lead.created_by' => $userId])
            ->andWhere(['not in', 'lead.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]])
            ->orderBy(['lead.id' => SORT_DESC]);

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'client_name', $search],
                ['like', 'subject', $search],
                ['like', 'company_name', $search],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('my-leads', compact('dataProvider'));
    }

    public function actionAssigned(): string
    {
        $userId = Yii::$app->user->id;
        $query  = Lead::find()
            ->with(['assignedTo', 'createdBy'])
            ->where(['lead.status' => 1, 'lead.assigned_to' => $userId])
            ->andWhere(['not in', 'lead.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]])
            ->orderBy(['lead.meeting_reported_date' => SORT_ASC]);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('assigned', compact('dataProvider'));
    }

    public function actionLeadMaster(): string
    {
        $query = LeadMaster::find()->where(['status' => 1])->orderBy(['id' => SORT_DESC]);

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'first_name', $search],
                ['like', 'last_name', $search],
                ['like', 'account_name', $search],
                ['like', 'email_id', $search],
                ['like', 'phone_number', $search],
            ]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 25],
        ]);

        return $this->render('lead-master', compact('dataProvider'));
    }

    /**
     * Convert a LeadMaster prospect into a full Lead record.
     */
    public function actionConvertToLead(int $id): string|Response
    {
        $prospect = $this->findLeadMaster($id);
        $lead     = new Lead();

        // Pre-fill from prospect
        $lead->client_name  = trim(($prospect->first_name ?? '') . ' ' . ($prospect->last_name ?? ''));
        $lead->company_name = $prospect->account_name ?? '';
        $lead->email        = $prospect->email_id ?? '';
        $lead->contact_no   = $prospect->phone_number ?? '';
        $lead->country      = $prospect->city ?? '';

        if (Yii::$app->request->isPost) {
            $lead->load(Yii::$app->request->post());
            $lead->created_by   = Yii::$app->user->id;
            $lead->created_date = date('Y-m-d H:i:s');
            $lead->lead_status  = Lead::LEAD_STATUS_OPEN;
            $lead->read_status  = 0;
            $lead->is_client    = 0;
            $lead->status       = 1;

            if ($lead->save()) {
                $this->handleAttachments($lead->id);
                // Soft-delete the prospect once converted
                $prospect->status = 0;
                $prospect->save(false);

                Yii::$app->session->setFlash('success', 'Prospect converted to lead successfully.');
                return $this->redirect(['view', 'id' => $lead->id]);
            }
        }

        $users = $this->getAssignableUsers();
        return $this->render('convert-to-lead', compact('lead', 'users', 'prospect'));
    }

    /**
     * Convert a Lead to a Client record.
     */
    public function actionConvertToClient(int $id): string|Response
    {
        $lead   = $this->findModel($id);
        $client = new ClientModel();

        // Pre-fill from lead
        $client->client_name  = $lead->client_name;
        $client->company_name = $lead->company_name ?? $lead->client_name;
        $client->phone        = $lead->contact_no ?? '';
        $client->email_id     = $lead->email ?? '';
        $client->address      = $lead->address ?? '';
        $client->status       = 1;

        if (Yii::$app->request->isPost) {
            $client->load(Yii::$app->request->post());
            $client->status = 1;

            if ($client->save()) {
                // Mark lead as converted
                $lead->lead_status         = Lead::LEAD_STATUS_CONVERTED;
                $lead->is_client           = 1;
                $lead->converted_date_time = date('Y-m-d H:i:s');
                $lead->save(false);

                Yii::$app->session->setFlash('success', 'Lead converted to client successfully.');
                return $this->redirect(['view', 'id' => $lead->id]);
            }
        }

        return $this->render('convert-to-client', compact('lead', 'client'));
    }

    public function actionUploadLead(): string|Response
    {
        if (Yii::$app->request->isPost) {
            $file = UploadedFile::getInstanceByName('lead_file');

            if ($file && $file->size > 0) {
                $allowed = ['csv', 'xlsx', 'xls'];
                $ext     = strtolower($file->extension);

                if (!in_array($ext, $allowed)) {
                    Yii::$app->session->setFlash('error', 'Invalid file format. Please upload a CSV or Excel file.');
                } else {
                    $uploadDir = Yii::getAlias('@backend/web/uploads/lead_imports/');
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }
                    $savePath = $uploadDir . uniqid('lead_') . '.' . $ext;
                    $file->saveAs($savePath);

                    $imported = 0;
                    $errors   = 0;

                    if ($ext === 'csv') {
                        $handle = fopen($savePath, 'r');
                        $header = fgetcsv($handle); // skip header row
                        while (($row = fgetcsv($handle)) !== false) {
                            $prospect = new LeadMaster();
                            $prospect->account_name  = $row[0] ?? '';
                            $prospect->first_name    = $row[1] ?? '';
                            $prospect->last_name     = $row[2] ?? '';
                            $prospect->designation   = $row[3] ?? '';
                            $prospect->email_id      = $row[4] ?? '';
                            $prospect->phone_number  = $row[5] ?? '';
                            $prospect->city          = $row[6] ?? '';
                            $prospect->created_at    = date('Y-m-d H:i:s');
                            $prospect->created_by    = Yii::$app->user->id;
                            $prospect->updated_at    = date('Y-m-d H:i:s');
                            $prospect->updated_by    = Yii::$app->user->id;
                            $prospect->status        = 1;
                            if ($prospect->save(false)) {
                                $imported++;
                            } else {
                                $errors++;
                            }
                        }
                        fclose($handle);
                    }

                    @unlink($savePath);
                    Yii::$app->session->setFlash('success', "Imported {$imported} records. Errors: {$errors}.");
                    return $this->redirect(['lead-master']);
                }
            } else {
                Yii::$app->session->setFlash('error', 'Please select a file to upload.');
            }
        }

        return $this->render('upload-lead');
    }

    public function actionView(int $id): string
    {
        $lead      = $this->findModel($id);
        $followers = LeadFollowers::find()
            ->with(['assignedTo'])
            ->where(['lead_id' => $id, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        $attachments = LeadAttachments::find()
            ->where(['lead_id' => $id])
            ->andWhere(['status' => 1])
            ->all();

        return $this->render('view', compact('lead', 'followers', 'attachments'));
    }

    public function actionCreate(): string|Response
    {
        $lead = new Lead();

        if ($lead->load(Yii::$app->request->post())) {
            $lead->created_by   = Yii::$app->user->id;
            $lead->created_date = date('Y-m-d H:i:s');
            $lead->lead_status  = Lead::LEAD_STATUS_OPEN;
            $lead->read_status  = 0;
            $lead->is_client    = 0;
            $lead->status       = 1;

            if ($lead->save()) {
                $this->handleAttachments($lead->id);
                Yii::$app->session->setFlash('success', 'Lead created.');
                return $this->redirect(['view', 'id' => $lead->id]);
            }
        }

        $users = $this->getAssignableUsers();
        return $this->render('create', compact('lead', 'users'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $lead = $this->findModel($id);

        if ($lead->load(Yii::$app->request->post())) {
            if ($lead->save()) {
                $this->handleAttachments($lead->id);
                Yii::$app->session->setFlash('success', 'Lead updated.');
                return $this->redirect(['view', 'id' => $lead->id]);
            }
        }

        $users = $this->getAssignableUsers();
        return $this->render('update', compact('lead', 'users'));
    }

    public function actionChangeStatus(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $lead   = $this->findModel($id);
        $status = (int)Yii::$app->request->post('lead_status');
        $reason = trim(Yii::$app->request->post('reason', ''));

        $lead->lead_status = $status;
        if ($reason !== '') {
            $lead->reason = $reason;
        }
        if ($status === Lead::LEAD_STATUS_CONVERTED) {
            $lead->converted_date_time = date('Y-m-d H:i:s');
        } elseif ($status === Lead::LEAD_STATUS_DROPPED) {
            $lead->dropped_date_time = date('Y-m-d H:i:s');
        }
        $lead->save(false);

        Yii::$app->session->setFlash('success', 'Lead status updated.');
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionAddFollower(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $lead = $this->findModel($id);
        $post = Yii::$app->request->post();

        $follower                   = new LeadFollowers();
        $follower->lead_id          = $lead->id;
        $follower->assigned_to      = (int)($post['assigned_to'] ?? $lead->assigned_to);
        $follower->created_by       = Yii::$app->user->id;
        $follower->created_date     = date('Y-m-d H:i:s');
        $follower->follow_up_date   = $post['follow_up_date'] ?? null;
        $follower->follow_up_time   = $post['follow_up_time'] ?? null;
        $follower->subject          = trim($post['subject'] ?? '');
        $follower->description      = trim($post['description'] ?? '');
        $follower->mode             = (int)($post['mode'] ?? 0);
        $follower->other_mode       = trim($post['other_mode'] ?? '');
        $follower->read_status      = 1;
        $follower->followup_status  = 1;
        $follower->status           = 1;
        $follower->save(false);

        // Update lead's next follow-up info
        if ($follower->follow_up_date) {
            $lead->meeting_reported_date = $follower->follow_up_date . ' ' . ($follower->follow_up_time ?? '00:00:00');
            $lead->assigned_to           = $follower->assigned_to;
            $lead->lead_status           = Lead::LEAD_STATUS_FOLLOWUP;
            $lead->save(false);
        }

        Yii::$app->session->setFlash('success', 'Follow-up added.');
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionDeleteAttachment(int $id): Response
    {
        $attachment = LeadAttachments::findOne(['id' => $id, 'status' => 1]);
        if ($attachment) {
            $attachment->status = 0;
            $attachment->save(false);
            Yii::$app->session->setFlash('success', 'Attachment removed.');
            return $this->redirect(['view', 'id' => $attachment->lead_id]);
        }
        throw new NotFoundHttpException('Attachment not found.');
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $lead         = $this->findModel($id);
        $lead->status = 0;
        $lead->save(false);

        Yii::$app->session->setFlash('success', 'Lead deleted.');
        return $this->redirect(['index']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function handleAttachments(int $leadId): void
    {
        $files = UploadedFile::getInstancesByName('attachments');
        foreach ($files as $file) {
            $filename   = 'lead_' . $leadId . '_' . time() . '_' . uniqid() . '.' . $file->extension;
            $uploadPath = Yii::getAlias('@backend/web/uploads/lead_attachments/');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0775, true);
            }
            if ($file->saveAs($uploadPath . $filename)) {
                $attachment                  = new LeadAttachments();
                $attachment->lead_id         = $leadId;
                $attachment->attachment_path = '/uploads/lead_attachments/' . $filename;
                $attachment->type            = $file->extension;
                $attachment->created_by      = Yii::$app->user->id;
                $attachment->status          = 1;
                $attachment->save(false);
            }
        }
    }

    private function getAssignableUsers(): array
    {
        return User::find()
            ->where(['status' => 1])
            ->orderBy('username')
            ->all();
    }

    private function findModel(int $id): Lead
    {
        $model = Lead::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Lead not found.');
        }
        return $model;
    }

    private function findLeadMaster(int $id): LeadMaster
    {
        $model = LeadMaster::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Prospect not found.');
        }
        return $model;
    }
}
