<?php

namespace api\modules\v1\controllers;

use common\models\Lead;
use common\models\LeadFollowers;
use Yii;
use yii\db\Expression;

/**
 * Lead (CRM) API endpoints — migrated from Yii1 WebserviceController:
 *   actionAddnewlead()            → POST /api/v1/lead/add
 *   actionListleads()             → POST /api/v1/lead/list
 *   actionMyleads()               → POST /api/v1/lead/my-list
 *   actionGetleaddetail()         → POST /api/v1/lead/view
 *   actionChangeleadstatus()      → POST /api/v1/lead/change-status
 *   actionAddleadfollower()       → POST /api/v1/lead/add-follower
 *   actionListleadfollowers()     → POST /api/v1/lead/followers
 *   actionGetleadfollowerdetail() → POST /api/v1/lead/follower-detail
 *   actionLeadfollowupstatus()    → POST /api/v1/lead/followup-status
 *   actionLeadnotification()      → POST /api/v1/lead/notifications
 *
 * list_type for /list:
 *   1 = My leads (active, assigned to me)
 *   2 = Converted leads
 *   3 = Dropped leads
 *   4 = Leads I created/assigned
 */
class LeadController extends BaseController
{
    /**
     * POST /api/v1/lead/add
     * Required: client_name, contact_no, email, subject, meeting_reported, assigned_to, mode, attachment_count
     */
    public function actionAdd(): array
    {
        $post = Yii::$app->request->post();
        $userId = Yii::$app->user->id;

        $clientName      = trim($post['client_name'] ?? '');
        $contactNo       = trim($post['contact_no'] ?? '');
        $email           = trim($post['email'] ?? '');
        $subject         = trim($post['subject'] ?? '');
        $meetingReported = trim($post['meeting_reported'] ?? '');
        $assignedTo      = trim($post['assigned_to'] ?? '');
        $mode            = trim($post['mode'] ?? '');
        $attachmentCount = trim($post['attachment_count'] ?? '');

        if ($clientName === '')      return $this->error('Client Name is missing');
        if ($contactNo === '')       return $this->error('Contact Number is missing');
        if ($email === '')           return $this->error('Email ID is missing');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->error('Email ID is not valid');
        if ($subject === '')         return $this->error('Lead Subject is missing');
        if ($meetingReported === '') return $this->error('Meeting Reported Date & Time is missing');
        if ($assignedTo === '')      return $this->error('Assigned To is missing');
        if ($mode === '')            return $this->error('Mode is missing');
        if ($attachmentCount === '') return $this->error('Attachment Count is missing');

        $lead                        = new Lead();
        $lead->client_name           = $clientName;
        $lead->contact_no            = $contactNo;
        $lead->contact_no2           = trim($post['contact_no2'] ?? '') ?: null;
        $lead->email                 = $email;
        $lead->country               = trim($post['city'] ?? '') ?: null;
        $lead->company_name          = trim($post['company_name'] ?? '') ?: null;
        $lead->address               = trim($post['address'] ?? '') ?: null;
        $lead->contact_person_name   = trim($post['contact_person_name'] ?? '') ?: null;
        $lead->contact_person_mobile = trim($post['contact_person_mobile'] ?? '') ?: null;
        $lead->contact_person_email  = trim($post['contact_person_email'] ?? '') ?: null;
        $lead->title                 = trim($post['title'] ?? '') ?: '(unknown)';
        $lead->subject               = $subject;
        $lead->description           = trim($post['description'] ?? '') ?: null;
        $lead->meeting_attended_date = !empty($post['meeting_attended'])
            ? date('Y-m-d H:i:s', strtotime($post['meeting_attended'])) : null;
        $lead->meeting_reported_date = date('Y-m-d H:i:s', strtotime($meetingReported));
        $lead->priority              = (int)($post['priority'] ?? 1) ?: 1;
        $lead->assigned_to           = (int)$assignedTo;
        $lead->mode                  = (int)$mode;
        $lead->other_mode            = trim($post['other_mode'] ?? '') ?: null;
        $lead->created_by            = (int)$userId;
        $lead->created_date          = date('Y-m-d H:i:s');
        $lead->lead_status           = (int)($post['lead_status'] ?? Lead::LEAD_STATUS_OPEN);
        $lead->status                = 1;

        if (!$lead->save()) {
            return $this->error('Failed to add Lead. Try again!');
        }

        // Handle attachments (multipart upload)
        for ($i = 1; $i <= (int)$attachmentCount; $i++) {
            $fileKey = 'attachments' . $i;
            if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            $file      = $_FILES[$fileKey];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uploadDir = Yii::getAlias('@backend/web/uploads/lead_attachments/');

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $targetFile = "attachment_{$lead->id}_{$i}.{$extension}";
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $targetFile)) {
                $att              = new \common\models\LeadAttachments();
                $att->lead_id     = $lead->id;
                $att->attachment_path = '/uploads/lead_attachments/' . $targetFile;
                $att->type        = $extension;
                $att->created_by  = (int)$userId;
                $att->status      = 1;
                $att->save(false);
            }
        }

        // Auto-create first follower entry
        $follower                = new LeadFollowers();
        $follower->lead_id       = $lead->id;
        $follower->follow_up_date = date('Y-m-d', strtotime($lead->meeting_reported_date));
        $follower->follow_up_time = date('H:i:s', strtotime($lead->meeting_reported_date));
        $follower->subject        = $lead->subject;
        $follower->description    = $lead->description;
        $follower->mode           = $lead->mode;
        $follower->other_mode     = $lead->other_mode;
        $follower->assigned_to    = $lead->assigned_to;
        $follower->created_by     = (int)$userId;
        $follower->created_date   = date('Y-m-d H:i:s');
        $follower->status         = 1;
        $follower->save(false);

        return $this->success([], 'New Lead Added Successfully');
    }

    /**
     * POST /api/v1/lead/list
     * Fields: list_type (required, 1-4), pagenumber, client_name, contact_no, email,
     *         title, subject, lead_status, meeting_attended, meeting_reported
     */
    public function actionList(): array
    {
        $post     = Yii::$app->request->post();
        $listType = (int)trim($post['list_type'] ?? '0');
        $page     = max(1, (int)($post['pagenumber'] ?? 1));
        $userId   = Yii::$app->user->id;

        if ($listType === 0) {
            return $this->error('List type is missing');
        }

        $query = Lead::find()
            ->alias('t')
            ->joinWith(['followers'])
            ->orderBy(['t.meeting_reported_date' => SORT_ASC]);

        switch ($listType) {
            case 1: // My active leads
                $query->where([
                    't.status'                => 1,
                    'lead_followers.status'   => 1,
                ])->andWhere(['t.assigned_to' => $userId])
                  ->andWhere(['not in', 't.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]])
                  ->orderBy(['lead_followers.follow_up_date' => SORT_ASC, 'lead_followers.follow_up_time' => SORT_ASC]);
                break;
            case 2: // Converted leads
                $query->where(['t.status' => 1, 't.lead_status' => Lead::LEAD_STATUS_CONVERTED])
                    ->andWhere([
                        'or',
                        ['t.assigned_to' => $userId],
                        ['lead_followers.assigned_to' => $userId],
                        ['t.created_by' => $userId],
                    ])->groupBy('t.id');
                break;
            case 3: // Dropped leads
                $query->where(['t.status' => 1, 't.lead_status' => Lead::LEAD_STATUS_DROPPED])
                    ->andWhere([
                        'or',
                        ['t.assigned_to' => $userId],
                        ['lead_followers.assigned_to' => $userId],
                        ['t.created_by' => $userId],
                    ])->groupBy('t.id');
                break;
            case 4: // Leads I assigned/created
                $query->where(['t.status' => 1])
                    ->andWhere([
                        'or',
                        ['t.created_by' => $userId],
                        ['lead_followers.created_by' => $userId],
                    ])
                    ->andWhere(['not in', 't.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]])
                    ->groupBy('t.id');
                break;
        }

        $this->applyLeadFilters($query, $post);

        $count = $query->count();

        if ($count < 1) {
            return $this->error('No result found');
        }

        $pagination = $this->paginate((int)$count, $page);
        $results    = $query->with(['assignedTo', 'createdBy', 'followers'])
            ->offset($pagination['offset'])->limit($pagination['limit'])->all();

        if (empty($results)) {
            return $this->error('Page number not found');
        }

        $list = [];
        foreach ($results as $lead) {
            $list[] = $this->formatLeadRow($lead, $listType);
        }

        return [
            'status'        => 1,
            'msg'           => '',
            'totalpages'    => $pagination['totalPages'],
            'currentpageno' => $pagination['currentPage'],
            'lead_list'     => $list,
        ];
    }

    /**
     * POST /api/v1/lead/my-list
     * Returns leads assigned to me via lead_followers (same as listleads list_type=1).
     */
    public function actionMyList(): array
    {
        $post['list_type'] = 1;
        return $this->actionList();
    }

    /**
     * POST /api/v1/lead/view
     * Fields: lead_id (required)
     */
    public function actionView(): array
    {
        $post   = Yii::$app->request->post();
        $leadId = trim($post['lead_id'] ?? '');

        if ($leadId === '') {
            return $this->error('Lead ID is missing');
        }

        $lead = Lead::find()
            ->where(['id' => (int)$leadId, 'status' => 1])
            ->with(['assignedTo', 'createdBy', 'followers', 'attachments'])
            ->one();

        if (!$lead) {
            return $this->error('Lead not found');
        }

        return $this->success($this->formatLeadRow($lead, 0), '');
    }

    /**
     * POST /api/v1/lead/change-status
     * Fields: lead_id, lead_status (required); reason (optional)
     */
    public function actionChangeStatus(): array
    {
        $post      = Yii::$app->request->post();
        $leadId    = trim($post['lead_id'] ?? '');
        $newStatus = trim($post['lead_status'] ?? '');

        if ($leadId === '')    return $this->error('Lead ID is missing');
        if ($newStatus === '') return $this->error('Lead Status is missing');

        $lead = Lead::findOne(['id' => (int)$leadId, 'status' => 1]);
        if (!$lead) {
            return $this->error('Lead not found');
        }

        $lead->lead_status = (int)$newStatus;
        if (!empty($post['reason'])) {
            $lead->reason = trim($post['reason']);
        }

        if ($lead->save(false)) {
            return $this->success([], 'Lead status updated successfully');
        }

        return $this->error('Failed to update lead status. Try again.');
    }

    /**
     * POST /api/v1/lead/add-follower
     * Fields: lead_id, follow_up_date, follow_up_time, subject, assigned_to, mode (required)
     *         description, other_mode (optional)
     */
    public function actionAddFollower(): array
    {
        $post   = Yii::$app->request->post();
        $userId = Yii::$app->user->id;

        $leadId     = trim($post['lead_id'] ?? '');
        $followDate = trim($post['follow_up_date'] ?? '');
        $followTime = trim($post['follow_up_time'] ?? '');
        $subject    = trim($post['subject'] ?? '');
        $assignedTo = trim($post['assigned_to'] ?? '');
        $mode       = trim($post['mode'] ?? '');

        if ($leadId === '')     return $this->error('Lead ID is missing');
        if ($followDate === '') return $this->error('Follow Up Date is missing');
        if ($followTime === '') return $this->error('Follow Up Time is missing');
        if ($subject === '')    return $this->error('Subject is missing');
        if ($assignedTo === '') return $this->error('Assigned To is missing');
        if ($mode === '')       return $this->error('Mode is missing');

        $lead = Lead::findOne(['id' => (int)$leadId, 'status' => 1]);
        if (!$lead) {
            return $this->error('Lead not found');
        }

        $follower                = new LeadFollowers();
        $follower->lead_id       = (int)$leadId;
        $follower->follow_up_date = date('Y-m-d', strtotime($followDate));
        $follower->follow_up_time = date('H:i:s', strtotime($followTime));
        $follower->subject        = $subject;
        $follower->description    = trim($post['description'] ?? '') ?: null;
        $follower->mode           = (int)$mode;
        $follower->other_mode     = trim($post['other_mode'] ?? '') ?: null;
        $follower->assigned_to    = (int)$assignedTo;
        $follower->created_by     = (int)$userId;
        $follower->created_date   = date('Y-m-d H:i:s');
        $follower->status         = 1;

        if ($follower->save(false)) {
            // Update lead's meeting_reported_date to the new follow-up
            $lead->meeting_reported_date = date('Y-m-d H:i:s', strtotime($followDate . ' ' . $followTime));
            $lead->assigned_to           = (int)$assignedTo;
            $lead->save(false);

            return $this->success([], 'Follow-up added successfully');
        }

        return $this->error('Failed to add follow-up. Try again.');
    }

    /**
     * POST /api/v1/lead/followers
     * Fields: lead_id (required), pagenumber (optional)
     */
    public function actionFollowers(): array
    {
        $post   = Yii::$app->request->post();
        $leadId = trim($post['lead_id'] ?? '');
        $page   = max(1, (int)($post['pagenumber'] ?? 1));

        if ($leadId === '') {
            return $this->error('Lead ID is missing');
        }

        $query = LeadFollowers::find()
            ->where(['lead_id' => (int)$leadId, 'status' => 1])
            ->orderBy(['follow_up_date' => SORT_DESC, 'follow_up_time' => SORT_DESC]);

        $count = $query->count();

        if ($count < 1) {
            return $this->error('No result found');
        }

        $pagination = $this->paginate((int)$count, $page);
        $results    = $query->with(['assignedTo', 'createdBy'])
            ->offset($pagination['offset'])->limit($pagination['limit'])->all();

        $list = [];
        foreach ($results as $f) {
            $list[] = [
                'follower_id'    => $f->id,
                'follow_up_date' => $f->follow_up_date ?? '',
                'follow_up_time' => $f->follow_up_time ?? '',
                'subject'        => $f->subject ?? '',
                'description'    => $f->description ?? '',
                'mode'           => $f->mode ?? '',
                'other_mode'     => $f->other_mode ?? '',
                'assigned_to'    => $f->assignedTo->username ?? '-',
                'created_by'     => $f->createdBy->username ?? '-',
                'created_date'   => $f->created_date ?? '',
            ];
        }

        return [
            'status'          => 1,
            'msg'             => '',
            'totalpages'      => $pagination['totalPages'],
            'currentpageno'   => $pagination['currentPage'],
            'follower_list'   => $list,
        ];
    }

    /**
     * POST /api/v1/lead/follower-detail
     * Fields: follower_id (required)
     */
    public function actionFollowerDetail(): array
    {
        $post       = Yii::$app->request->post();
        $followerId = trim($post['follower_id'] ?? '');

        if ($followerId === '') {
            return $this->error('Follower ID is missing');
        }

        $f = LeadFollowers::find()
            ->where(['id' => (int)$followerId, 'status' => 1])
            ->with(['assignedTo', 'createdBy', 'lead'])
            ->one();

        if (!$f) {
            return $this->error('Follower not found');
        }

        return $this->success([
            'follower_id'    => $f->id,
            'lead_id'        => $f->lead_id,
            'follow_up_date' => $f->follow_up_date ?? '',
            'follow_up_time' => $f->follow_up_time ?? '',
            'subject'        => $f->subject ?? '',
            'description'    => $f->description ?? '',
            'mode'           => $f->mode ?? '',
            'other_mode'     => $f->other_mode ?? '',
            'assigned_to'    => $f->assignedTo->username ?? '-',
            'created_by'     => $f->createdBy->username ?? '-',
        ], '');
    }

    /**
     * POST /api/v1/lead/followup-status
     * Updates followup_status flag (e.g. mark as done) for a follower entry.
     * Fields: follower_id, followup_status (required)
     */
    public function actionFollowupStatus(): array
    {
        $post       = Yii::$app->request->post();
        $followerId = trim($post['follower_id'] ?? '');
        $status     = trim($post['followup_status'] ?? '');

        if ($followerId === '') return $this->error('Follower ID is missing');
        if ($status === '')     return $this->error('Follow-up status is missing');

        $updated = LeadFollowers::updateAll(['followup_status' => (int)$status], ['id' => (int)$followerId]);

        if ($updated > 0) {
            return $this->success([], 'Follow-up status updated successfully');
        }

        return $this->error('Failed to update follow-up status. Try again.');
    }

    /**
     * POST /api/v1/lead/notifications
     * Returns unread lead-related notifications for the current user.
     */
    public function actionNotifications(): array
    {
        $userId = Yii::$app->user->id;
        $page   = max(1, (int)(Yii::$app->request->post('pagenumber') ?? 1));

        $query = Lead::find()
            ->alias('t')
            ->joinWith(['followers'])
            ->where([
                't.status'               => 1,
                't.read_status'          => 0,
                'lead_followers.status'  => 1,
                'lead_followers.assigned_to' => $userId,
            ])
            ->orderBy(['lead_followers.follow_up_date' => SORT_ASC]);

        $count = $query->count();
        if ($count < 1) {
            return $this->error('No notifications found');
        }

        $pagination = $this->paginate((int)$count, $page);
        $results    = $query->offset($pagination['offset'])->limit($pagination['limit'])->all();

        $list = [];
        foreach ($results as $lead) {
            $list[] = [
                'lead_id'       => $lead->id,
                'client_name'   => $lead->client_name,
                'subject'       => $lead->subject,
                'lead_status'   => $lead->lead_status,
            ];
        }

        return [
            'status'        => 1,
            'msg'           => '',
            'totalpages'    => $pagination['totalPages'],
            'currentpageno' => $pagination['currentPage'],
            'notifications' => $list,
        ];
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function applyLeadFilters(\yii\db\ActiveQuery $query, array $post): void
    {
        if (!empty($post['client_name'])) {
            $query->andWhere(['like', 't.client_name', $post['client_name']]);
        }
        if (!empty($post['contact_no'])) {
            $query->andWhere(['like', 't.contact_no', $post['contact_no']]);
        }
        if (!empty($post['email'])) {
            $query->andWhere(['like', 't.email', $post['email']]);
        }
        if (!empty($post['title'])) {
            $query->andWhere(['like', 't.title', $post['title']]);
        }
        if (!empty($post['subject'])) {
            $query->andWhere(['like', 't.subject', $post['subject']]);
        }
        if (!empty($post['lead_status'])) {
            $query->andWhere(['t.lead_status' => (int)$post['lead_status']]);
        }
        if (!empty($post['meeting_attended'])) {
            $query->andWhere(['DATE(t.meeting_attended_date)' => date('Y-m-d', strtotime($post['meeting_attended']))]);
        }
        if (!empty($post['meeting_reported'])) {
            $query->andWhere(['DATE(t.meeting_reported_date)' => date('Y-m-d', strtotime($post['meeting_reported']))]);
        }
    }

    private function formatLeadRow(Lead $lead, int $listType): array
    {
        $row = [
            'lead_id'              => $lead->id,
            'client_name'          => $lead->client_name,
            'contact_no'           => $lead->contact_no,
            'contact_no2'          => $lead->contact_no2 ?? '-',
            'email'                => $lead->email,
            'city'                 => $lead->country ?? '-',
            'company_name'         => $lead->company_name ?? '-',
            'address'              => $lead->address ?? '-',
            'contact_person_name'  => $lead->contact_person_name ?? '-',
            'contact_person_mobile'=> $lead->contact_person_mobile ?? '-',
            'contact_person_email' => $lead->contact_person_email ?? '-',
            'title'                => $lead->title ?? '-',
            'subject'              => $lead->subject,
            'description'          => $lead->description ?? '-',
            'reason'               => $lead->reason ?? '-',
            'meeting_attended'     => $lead->meeting_attended_date ?? '-',
            'meeting_reported'     => $lead->meeting_reported_date ?? '-',
            'priority'             => $lead->priority,
            'assigned_to'          => $lead->assignedTo->username ?? '-',
            'lead_status'          => $lead->lead_status,
            'created_by'           => $lead->createdBy->username ?? '-',
            'created_date'         => $lead->created_date ?? '-',
            'mode'                 => $lead->mode,
            'other_mode'           => $lead->other_mode ?? '-',
        ];

        // For list_type=1 (my leads), override with follower data
        if ($listType === 1 && !empty($lead->followers)) {
            $firstFollower = $lead->followers[0];
            $row['subject']          = $firstFollower->subject ?? '-';
            $row['meeting_reported'] = ($firstFollower->follow_up_date ?? '') !== ''
                ? date('Y-m-d H:i:s', strtotime($firstFollower->follow_up_date . ' ' . ($firstFollower->follow_up_time ?? '00:00:00')))
                : '-';
            $row['assigned_to']  = $firstFollower->assignedTo->username ?? '-';
            $row['created_by']   = $firstFollower->createdBy->username ?? '-';
            $row['mode']         = $firstFollower->mode ?? '-';
            $row['other_mode']   = $firstFollower->other_mode ?? '-';
        }

        return $row;
    }
}
