<?php

namespace backend\controllers;

use backend\components\LeadAccess;
use backend\components\LeadFiles;
use backend\components\LeadMailer;
use backend\models\Lead;
use backend\models\LeadFollowerAttachments;
use backend\models\LeadFollowers;
use common\components\UserAccess;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Lead follow-ups — port of Yii1 LeadfollowersController.
 *
 * Creating a follow-up moves the lead's "Next Follow Up" date to it, can close
 * the follow-up it replaces ("Create & Close") and notifies the assignee.
 */
class LeadFollowersController extends Controller
{
    private const FORM_FIELDS = ['subject', 'follow_up_date', 'follow_up_time', 'assigned_to', 'mode', 'other_mode', 'description'];

    /** All follow-ups of one lead. */
    public function actionIndex(int $id): string
    {
        $lead = LeadAccess::findLead($id);
        $dataProvider = new ActiveDataProvider([
            'query' => LeadFollowers::find()
                ->with(['assignedTo', 'createdBy'])
                ->where(['lead_id' => $lead->id, 'status' => 1])
                ->orderBy(['id' => SORT_DESC]),
            'pagination' => ['pageSize' => 20],
        ]);
        $openFollowUps = (int)LeadFollowers::find()
            ->where(['lead_id' => $lead->id, 'status' => 1, 'followup_status' => LeadFollowers::FOLLOWUP_OPEN])
            ->count();

        return $this->render('index', compact('lead', 'dataProvider', 'openFollowUps'));
    }

    /**
     * @param int      $id       lead id
     * @param int|null $followup follow-up to close once this one is saved ("Create & Close")
     */
    public function actionCreate(int $id, ?int $followup = null): string|Response
    {
        $lead = LeadAccess::findLead($id);
        if (in_array((int)$lead->lead_status, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true)) {
            Yii::$app->session->setFlash('error', 'Follow-ups cannot be added to a converted or dropped lead.');
            return $this->redirect(['/lead/view', 'id' => $lead->id]);
        }
        $closing = $followup
            ? LeadFollowers::findOne(['id' => $followup, 'lead_id' => $lead->id, 'status' => 1, 'followup_status' => LeadFollowers::FOLLOWUP_OPEN])
            : null;

        $model = new LeadFollowers([
            'lead_id'     => $lead->id,
            'assigned_to' => Yii::$app->user->id,
        ]);

        if (Yii::$app->request->isPost) {
            $this->loadForm($model);
            $model->lead_id         = $lead->id;
            $model->created_by      = Yii::$app->user->id;
            $model->created_date    = date('Y-m-d H:i:s');
            $model->followup_status = LeadFollowers::FOLLOWUP_OPEN;
            $model->read_status     = LeadFollowers::READ_UNREAD;
            $model->status          = 1;

            if ($model->save()) {
                $lead->updateAttributes([
                    'meeting_reported_date' => $model->follow_up_date . ' ' . $model->follow_up_time,
                ]);
                LeadFiles::flashRejected($this->saveAttachments($model));
                if ($closing) {
                    $closing->updateAttributes(['followup_status' => LeadFollowers::FOLLOWUP_CLOSED]);
                }
                LeadMailer::sendAssigned($lead, $model);

                Yii::$app->session->setFlash('success', $closing
                    ? 'Follow-up added and the previous follow-up closed.'
                    : 'Follow-up added successfully.');
                return $this->redirectToLead($lead);
            }
        }

        $lastFollowUp = LeadFollowers::find()->with(['assignedTo', 'createdBy'])
            ->where(['lead_id' => $lead->id, 'status' => 1])->orderBy(['id' => SORT_DESC])->one();

        return $this->render('create', [
            'model'        => $model,
            'lead'         => $lead,
            'lastFollowUp' => $lastFollowUp,
            'closing'      => $closing,
            'users'        => LeadAccess::assignableUsers(),
        ]);
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findFollowUp($id);
        $lead  = LeadAccess::findLead((int)$model->lead_id);

        if (Yii::$app->request->isPost) {
            $this->loadForm($model);
            if ($model->save()) {
                // Editing the newest follow-up keeps the lead's "Next Follow Up" in step with it.
                $latestId = (int)LeadFollowers::find()->where(['lead_id' => $lead->id, 'status' => 1])->max('id');
                if ($latestId === (int)$model->id) {
                    $lead->updateAttributes(['meeting_reported_date' => $model->follow_up_date . ' ' . $model->follow_up_time]);
                }
                Yii::$app->session->setFlash('success', 'Follow-up updated successfully.');
                return $this->redirectToLead($lead);
            }
        } elseif ($model->follow_up_time) {
            $model->follow_up_time = substr($model->follow_up_time, 0, 5);
        }

        return $this->render('update', ['model' => $model, 'lead' => $lead, 'users' => LeadAccess::assignableUsers()]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findFollowUp($id);
        $lead  = LeadAccess::findLead((int)$model->lead_id);
        return $this->render('view', compact('model', 'lead'));
    }

    /** Attachment list for the follow-up (loaded into a modal). */
    public function actionViewAttachments(int $id): string
    {
        $model = $this->findFollowUp($id);
        LeadAccess::findLead((int)$model->lead_id);
        return $this->renderAjax('_attachments', ['attachments' => $model->attachments]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function loadForm(LeadFollowers $model): void
    {
        $data = array_intersect_key((array)Yii::$app->request->post('LeadFollowers', []), array_flip(self::FORM_FIELDS));
        foreach ($data as $name => $value) {
            $model->$name = is_string($value) ? $value : null;
        }
        if ((int)$model->mode !== Lead::MODE_OTHER) {
            $model->other_mode = null;
        }
    }

    private function saveAttachments(LeadFollowers $model): array
    {
        return LeadFiles::save('attachments', 'lead_follower_attachments', 'followup_' . $model->id,
            function (string $path, string $ext) use ($model) {
                (new LeadFollowerAttachments([
                    'lead_follower_id' => $model->id,
                    'attachment_path'  => $path,
                    'type'             => $ext,
                    'created_by'       => Yii::$app->user->id,
                    'status'           => 1,
                ]))->save(false);
            });
    }

    /** Admin follow-up list when permitted, otherwise the lead itself (Yii1 sent developers to Assigned Leads). */
    private function redirectToLead(Lead $lead): Response
    {
        if (UserAccess::checkAccess('lead-followers/index')) {
            return $this->redirect(['index', 'id' => $lead->id]);
        }
        if (UserAccess::checkAccess('lead/view')) {
            return $this->redirect(['/lead/view', 'id' => $lead->id]);
        }
        if (UserAccess::checkAccess('lead/view-assigned')) {
            return $this->redirect(['/lead/view-assigned', 'id' => $lead->id]);
        }
        return $this->redirect(['/lead/assigned']);
    }

    private function findFollowUp(int $id): LeadFollowers
    {
        $model = LeadFollowers::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('The requested follow-up does not exist.');
        }
        return $model;
    }
}
