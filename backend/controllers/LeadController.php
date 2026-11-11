<?php

namespace backend\controllers;

use backend\assets\LeadAsset;
use backend\components\LeadAccess;
use backend\components\LeadFiles;
use backend\components\LeadInsight;
use backend\components\LeadMailer;
use backend\models\ClientModel;
use backend\models\Lead;
use backend\models\LeadProductInterest;
use backend\models\LeadAttachments;
use backend\models\LeadFollowers;
use backend\models\LeadMaster;
use backend\models\ProductCategory;
use backend\models\User;
use common\components\UserAccess;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * CRM Leads — port of Yii1 LeadController.
 *
 * Lists:  index (Manage Leads), my-leads, assigned, converted, dropped, lead-master
 * Leads:  create, update, view, view-assigned, change-status, change-lead-status,
 *         change-followup-status, delete-attachment, delete, add-to-client
 * Master: create-lead-master, update-lead-master, lead-master-view,
 *         delete-lead-master, upload-lead, download-sample, convert-to-lead
 *
 * Follow-ups live in LeadFollowersController.
 */
class LeadController extends Controller
{
    /** Lead fields the add / edit form may set (everything else is server-controlled). */
    private const FORM_FIELDS = [
        'client_name', 'company_name', 'email', 'contact_no', 'contact_no2', 'lead_value', 'priority', 'country',
        'contact_person_name', 'contact_person_mobile', 'contact_person_email', 'title', 'subject',
        'attended_date', 'attended_time', 'assigned_to', 'mode', 'other_mode', 'expected_budget',
        'current_stage', 'industry', 'offering_service', 'address',
        // Green Divine fields (PROJECT_ANALYSIS.md §2.1)
        'lead_source', 'source_detail', 'site_area', 'area_unit', 'expected_install_date',
        'visit_date', 'visit_time', 'site_visit_by', 'quotation_by', 'description',
    ];
    /** Only on create: the first follow-up. */
    private const CREATE_ONLY_FIELDS = ['nextfollowup_date', 'nextfollowup_time'];

    /** Follow-ups missed for longer than this are "stale" on the Today board. */
    private const STALE_DAYS = 60;

    private const LIST_FILTERS = ['client_name', 'contact_no', 'email', 'country', 'contact_person_name', 'title', 'lead_status', 'assigned_to', 'from_date', 'to_date',
        'lead_source', 'priority', 'interest'];

    /** Full pages get the Leads stylesheet/script; renderAjax() partials do not need them. */
    public function render($view, $params = []): string
    {
        LeadAsset::registerFor($this->getView());
        return parent::render($view, $params);
    }

    // ── Lists ────────────────────────────────────────────────────────────────

    /** Manage Leads — every active/inactive lead still in the pipeline. */
    public function actionIndex(): string
    {
        $query = $this->leadQuery()
            ->andWhere(['lead.status' => [Lead::STATUS_ACTIVE, Lead::STATUS_INACTIVE]])
            ->andWhere(['not in', 'lead.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]])
            ->orderBy(['lead.meeting_reported_date' => SORT_ASC, 'lead.id' => SORT_DESC]);
        $filters = $this->applyFilters($query, 'lead.meeting_attended_date');

        return $this->renderList('index', 'Manage Leads', $query, $filters);
    }

    /** Leads I created that are still open (Yii1 Lead::myleads()). */
    public function actionMyLeads(): string
    {
        $userId = (int)Yii::$app->user->id;
        $query  = $this->leadQuery()
            ->andWhere(['lead.status' => Lead::STATUS_ACTIVE, 'lead.created_by' => $userId])
            ->andWhere(['not in', 'lead.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]])
            ->andWhere(['in', 'lead.id', LeadFollowers::find()->select('lead_id')->where(['status' => 1])])
            ->orderBy(['lead.id' => SORT_DESC]);
        $filters = $this->applyFilters($query, 'follow_up_date');

        return $this->renderList('my-leads', 'My Leads', $query, $filters);
    }

    /** Open leads assigned to me (directly or through a follow-up) by someone else. */
    public function actionAssigned(): string
    {
        $userId = (int)Yii::$app->user->id;
        $query  = $this->leadQuery()
            ->andWhere(['lead.status' => Lead::STATUS_ACTIVE])
            ->andWhere(['not in', 'lead.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]])
            ->andWhere(['or',
                ['lead.assigned_to' => $userId],
                ['in', 'lead.id', LeadFollowers::find()->select('lead_id')->where(['status' => 1, 'assigned_to' => $userId])],
            ])
            ->andWhere(['or', ['!=', 'lead.created_by', $userId], ['lead.created_by' => null]])
            ->orderBy(['lead.id' => SORT_DESC]);
        $filters = $this->applyFilters($query, 'lead.meeting_attended_date');

        return $this->renderList('assigned', 'Assigned Leads', $query, $filters);
    }

    public function actionConverted(): string
    {
        $query = $this->leadQuery()
            ->andWhere(['lead.status' => Lead::STATUS_ACTIVE, 'lead.lead_status' => Lead::LEAD_STATUS_CONVERTED])
            ->orderBy(['lead.converted_date_time' => SORT_DESC, 'lead.id' => SORT_DESC]);
        $this->scopeNonManagers($query);
        $filters = $this->applyFilters($query, 'lead.meeting_attended_date');

        return $this->renderList('converted', 'Converted Leads', $query, $filters);
    }

    public function actionDropped(): string
    {
        $query = $this->leadQuery()
            ->andWhere(['lead.status' => Lead::STATUS_ACTIVE, 'lead.lead_status' => Lead::LEAD_STATUS_DROPPED])
            ->orderBy(['lead.dropped_date_time' => SORT_DESC, 'lead.id' => SORT_DESC]);
        $this->scopeNonManagers($query);
        $filters = $this->applyFilters($query, 'lead.meeting_attended_date');

        return $this->renderList('dropped', 'Dropped Leads', $query, $filters);
    }

    // ── View ─────────────────────────────────────────────────────────────────

    public function actionView(int $id): string
    {
        return $this->renderLead($id, false);
    }

    /** View opened from Assigned Leads — follow-up status is read-only here (Yii1 view_assigned). */
    public function actionViewAssigned(int $id): string
    {
        return $this->renderLead($id, true);
    }

    // ── Create / update ──────────────────────────────────────────────────────

    public function actionCreate(): string|Response
    {
        $lead = $this->newLead();

        if (Yii::$app->request->isPost) {
            $this->loadLeadForm($lead, true);
            if ($this->createWithFollowUp($lead)) {
                Yii::$app->session->setFlash('success', 'Lead added successfully.');
                $this->flashDuplicates($lead);
                return $this->redirectAfterCreate();
            }
        }

        return $this->render('create', ['lead' => $lead, 'users' => LeadAccess::assignableUsers()]);
    }

    public function actionUpdate(int $id): string|Response
    {
        $lead = LeadAccess::findLead($id);
        $lead->interestIds = array_map('intval', LeadProductInterest::find()->select('category_id')->where(['lead_id' => $lead->id])->column());
        if (in_array((int)$lead->lead_status, [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED], true)) {
            Yii::$app->session->setFlash('error', 'Converted or dropped leads cannot be edited.');
            return $this->redirect(['view', 'id' => $id]);
        }

        if (Yii::$app->request->isPost) {
            $this->loadLeadForm($lead, false);
            if ($lead->save()) {
                LeadFiles::flashRejected($this->saveLeadAttachments($lead));
                Yii::$app->session->setFlash('success', 'Lead updated successfully.');
                return $this->redirect(UserAccess::checkAccess('lead/index') ? ['index'] : ['view', 'id' => $id]);
            }
        }

        return $this->render('update', ['lead' => $lead, 'users' => LeadAccess::assignableUsers()]);
    }

    // ── AJAX status changes ──────────────────────────────────────────────────

    /** Lead pipeline status (Open → … → Converted / Dropped, Dropped → Re-open). */
    public function actionChangeStatus(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $this->requirePostRequest();

        $lead   = LeadAccess::findLead((int)Yii::$app->request->post('id'));
        $status = (int)Yii::$app->request->post('status');
        $reason = trim((string)Yii::$app->request->post('reason', ''));

        if (!$lead->canChangeLeadStatusTo($status, LeadAccess::canReopen())) {
            return ['success' => false, 'message' => 'This status change is not allowed.'];
        }
        if ($status === Lead::LEAD_STATUS_DROPPED && $reason === '') {
            return ['success' => false, 'message' => 'Please enter the reason for dropping this lead.'];
        }

        $attrs = ['lead_status' => $status];
        if ($reason !== '') {
            $attrs['reason'] = $reason;
        }
        if ($status === Lead::LEAD_STATUS_CONVERTED) {
            $attrs['converted_date_time'] = date('Y-m-d H:i:s');
        } elseif ($status === Lead::LEAD_STATUS_DROPPED) {
            $attrs['dropped_date_time'] = date('Y-m-d H:i:s');
        }
        $lead->updateAttributes($attrs);

        return ['success' => true, 'message' => 'Lead status changed to ' . $lead->getLeadStatusLabel() . '.'];
    }

    /** Active / Inactive toggle. */
    public function actionChangeLeadStatus(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $this->requirePostRequest();

        $lead   = LeadAccess::findLead((int)Yii::$app->request->post('id'));
        $status = (int)Yii::$app->request->post('status');
        if (!array_key_exists($status, Lead::statusOptions())) {
            return ['success' => false, 'message' => 'Invalid status.'];
        }
        $lead->updateAttributes(['status' => $status]);

        return ['success' => true, 'message' => 'Lead marked ' . Lead::statusOptions()[$status] . '.'];
    }

    /**
     * Close a follow-up. As in Yii1, the last open follow-up of a lead cannot
     * simply be closed — the user must create the next follow-up ("Create & Close").
     */
    public function actionChangeFollowupStatus(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $this->requirePostRequest();

        $followUp = LeadFollowers::findOne(['id' => (int)Yii::$app->request->post('followupid'), 'status' => 1]);
        if (!$followUp) {
            return ['success' => false, 'message' => 'Follow-up not found.'];
        }
        LeadAccess::findLead((int)$followUp->lead_id);

        if ((int)$followUp->followup_status === LeadFollowers::FOLLOWUP_CLOSED) {
            return ['success' => false, 'message' => 'This follow-up is already closed.'];
        }
        $openCount = (int)LeadFollowers::find()
            ->where(['lead_id' => $followUp->lead_id, 'status' => 1, 'followup_status' => LeadFollowers::FOLLOWUP_OPEN])
            ->count();
        if ($openCount <= 1) {
            return ['success' => false, 'message' => 'This is the only open follow-up. Create the next follow-up to close it.'];
        }
        $followUp->updateAttributes(['followup_status' => LeadFollowers::FOLLOWUP_CLOSED]);

        return ['success' => true, 'message' => 'Follow-up closed successfully.'];
    }

    public function actionDeleteAttachment(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $this->requirePostRequest();

        $attachment = LeadAttachments::findOne(['id' => (int)Yii::$app->request->post('id'), 'status' => 1]);
        if (!$attachment) {
            return ['success' => false, 'message' => 'Attachment not found.'];
        }
        LeadAccess::findLead((int)$attachment->lead_id);
        $attachment->updateAttributes(['status' => 0]);

        return ['success' => true, 'message' => 'Attachment deleted.'];
    }

    /** Soft delete (status = 0). */
    public function actionDelete(int $id): Response
    {
        $this->requirePostRequest();
        LeadAccess::findLead($id)->updateAttributes(['status' => Lead::STATUS_DELETED]);
        Yii::$app->session->setFlash('success', 'Lead deleted successfully.');
        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }

    // ── Converted lead → client ──────────────────────────────────────────────

    public function actionAddToClient(int $id): string|Response
    {
        $lead = LeadAccess::findLead($id);
        if ((int)$lead->lead_status !== Lead::LEAD_STATUS_CONVERTED) {
            Yii::$app->session->setFlash('error', 'Only converted leads can be added as a client.');
            return $this->redirect(['converted']);
        }
        if ((int)$lead->is_client === 1) {
            Yii::$app->session->setFlash('error', 'This lead has already been added as a client.');
            return $this->redirect(['converted']);
        }

        // Client phones are bare digits; drop a +91 / 0 prefix when 10 digits remain.
        $digits = fn($phone) => preg_replace('/^(?:91|0)(?=\d{10}$)/', '', preg_replace('/\D/', '', (string)$phone));
        $client = new ClientModel([
            'client_name'    => $lead->client_name,
            'company_name'   => $lead->company_name ?: $lead->client_name,
            'contact_person' => $lead->contact_person_name ?: $lead->client_name,
            'address'        => $lead->address,
            'phone'          => $digits($lead->contact_no),
            'alt_phone'      => $digits($lead->contact_no2),
            'email_id'       => $lead->email,
        ]);
        $user = new User();

        if (Yii::$app->request->isPost) {
            $post   = Yii::$app->request->post();
            $portal = ClientModel::portalEnabled(); // off: no login account for the client
            if ($portal) {
                $user->username = is_string($post['username'] ?? null) ? trim($post['username']) : '';
                $user->usertype = User::TYPE_CLIENT;
                $user->status   = 1;
            }
            $client->load($post, 'ClientModel');
            $client->status = 1;

            $userValid   = !$portal || ClientModel::validateLogin($user, is_string($post['password'] ?? null) ? $post['password'] : '');
            $clientValid = $client->validate();

            if ($userValid && $clientValid && $client->saveWithLogin($portal ? $user : null)) {
                $lead->updateAttributes(['is_client' => 1]);
                Yii::$app->session->setFlash('success', 'Client created successfully.');
                return $this->redirect(['converted']);
            }
        }

        return $this->render('add-to-client', compact('lead', 'client', 'user'));
    }

    // ── Lead Master (prospects) ──────────────────────────────────────────────

    public function actionLeadMaster(): string
    {
        $request = Yii::$app->request;
        $query   = LeadMaster::find()->where(['!=', 'status', LeadMaster::STATUS_DELETED])->orderBy(['id' => SORT_DESC]);

        $filters = [];
        foreach (['account_name', 'first_name', 'last_name', 'designation', 'email_id', 'phone_number', 'city'] as $field) {
            $value = $request->get($field, '');
            $value = is_string($value) ? mb_substr(trim($value), 0, 100) : '';
            $filters[$field] = $value;
            if ($value !== '') {
                $query->andWhere(['like', $field, $value]);
            }
        }
        $status = $request->get('status', '');
        $filters['status'] = is_string($status) ? trim($status) : '';
        if ($filters['status'] !== '' && !array_key_exists((int)$filters['status'], LeadMaster::statusOptions())) {
            Yii::$app->session->setFlash('warning', 'Ignored an invalid status filter.');
            $filters['status'] = '';
        }
        if ($filters['status'] !== '') {
            $query->andWhere(['status' => (int)$filters['status']]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 25]]);
        return $this->render('lead-master', compact('dataProvider', 'filters'));
    }

    public function actionLeadMasterView(int $id): string
    {
        $model = $this->findLeadMaster($id);
        return Yii::$app->request->isAjax
            ? $this->renderAjax('_lead-master-detail', compact('model'))
            : $this->render('lead-master-view', compact('model'));
    }

    public function actionCreateLeadMaster(): string|Response
    {
        $model = new LeadMaster(['status' => LeadMaster::STATUS_ACTIVE]);
        if ($model->load(Yii::$app->request->post())) {
            $now = date('Y-m-d H:i:s');
            $model->created_at = $model->updated_at = $now;
            $model->created_by = $model->updated_by = Yii::$app->user->id;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Lead master added successfully.');
                return $this->redirect(['lead-master']);
            }
        }
        return $this->render('lead-master-form', compact('model'));
    }

    public function actionUpdateLeadMaster(int $id): string|Response
    {
        $model = $this->findLeadMaster($id);
        if ($model->load(Yii::$app->request->post())) {
            $model->updated_at = date('Y-m-d H:i:s');
            $model->updated_by = Yii::$app->user->id;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Lead master updated successfully.');
                return $this->redirect(['lead-master']);
            }
        }
        return $this->render('lead-master-form', compact('model'));
    }

    /** Soft delete (status = -1). */
    public function actionDeleteLeadMaster(int $id): Response
    {
        $this->requirePostRequest();
        $this->findLeadMaster($id)->updateAttributes([
            'status'     => LeadMaster::STATUS_DELETED,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => Yii::$app->user->id,
        ]);
        Yii::$app->session->setFlash('success', 'Lead master deleted successfully.');
        return $this->redirect(['lead-master']);
    }

    /**
     * Import prospects from Excel / CSV. Layout matches Yii1: columns A–H with a
     * header row; A = S.No (ignored), B–H = Account Name, First Name, Last Name,
     * Designation, Email, Phone Number, City.
     */
    public function actionUploadLead(): string|Response
    {
        $rowErrors = [];
        if (Yii::$app->request->isPost) {
            $file = UploadedFile::getInstanceByName('lead_file');
            $ext  = $file ? strtolower($file->extension) : '';

            if (!$file || $file->error !== UPLOAD_ERR_OK) {
                Yii::$app->session->setFlash('error', 'Please choose a file to upload.');
            } elseif (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                Yii::$app->session->setFlash('error', 'Invalid file format. Please upload an XLSX, XLS or CSV file.');
            } elseif ($file->size > 5 * 1024 * 1024) {
                Yii::$app->session->setFlash('error', 'The file is too large. The maximum size is 5 MB.');
            } else {
                [$imported, $rowErrors] = $this->importLeadMaster($file->tempName, $ext);
                if ($imported === null) {
                    Yii::$app->session->setFlash('error', $rowErrors[0] ?? 'The file could not be read.');
                    $rowErrors = [];
                } elseif (!$rowErrors) {
                    Yii::$app->session->setFlash('success', "File imported successfully: {$imported} record(s) added.");
                    return $this->redirect(['lead-master']);
                } else {
                    Yii::$app->session->setFlash($imported ? 'warning' : 'error',
                        "{$imported} record(s) imported, " . count($rowErrors) . ' row(s) skipped — see details below.');
                }
            }
        }
        return $this->render('upload-lead', compact('rowErrors'));
    }

    /** Blank import template with the expected header row. */
    public function actionDownloadSample(): Response
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([['S.No', 'Account Name', 'First Name', 'Last Name', 'Designation', 'Email', 'Phone Number', 'City']]);
        $sheet->fromArray([[1, 'Acme Pvt Ltd', 'John', 'Doe', 'Manager', 'john@example.com', '9876543210', 'Chennai']], null, 'A2');
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'leadtpl');
        (new Xlsx($sheet->getParent()))->save($tmp);
        $content = file_get_contents($tmp);
        @unlink($tmp);

        return Yii::$app->response->sendContentAsFile($content, 'lead_master_sample.xlsx', [
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Turn a prospect into a lead; the prospect is removed from the master list once saved. */
    public function actionConvertToLead(int $id): string|Response
    {
        $prospect = $this->findLeadMaster($id);
        $lead     = $this->newLead();
        $lead->client_name  = $prospect->getFullName();
        $lead->company_name = $prospect->account_name;
        $lead->email        = $prospect->email_id;
        $lead->contact_no   = $prospect->phone_number;
        $lead->country      = $prospect->city;

        if (Yii::$app->request->isPost) {
            $this->loadLeadForm($lead, true);
            if ($this->createWithFollowUp($lead)) {
                $prospect->updateAttributes([
                    'status'     => LeadMaster::STATUS_DELETED,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => Yii::$app->user->id,
                ]);
                Yii::$app->session->setFlash('success', 'Lead added successfully.');
                return $this->redirectAfterCreate();
            }
        }

        return $this->render('convert-to-lead', ['lead' => $lead, 'prospect' => $prospect, 'users' => LeadAccess::assignableUsers()]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    // ── Smart helpers ────────────────────────────────────────────────────────

    /**
     * Today board: overdue and upcoming follow-ups and site visits, for the
     * current user (managers can pick any employee or see everyone).
     */
    public function actionToday(): string
    {
        $isManager = LeadAccess::canSeeAllLeads();
        $userId    = (int)Yii::$app->user->id;
        $who       = Yii::$app->request->get('user', $isManager ? '' : (string)$userId);
        $who       = is_string($who) && ($who === '' || ctype_digit($who)) ? $who : '';
        if (!$isManager) {
            $who = (string)$userId;
        }

        $base = fn() => $this->leadQuery()->with(['interests', 'siteVisitBy'])
            ->andWhere(['lead.status' => Lead::STATUS_ACTIVE])
            ->andWhere(['not in', 'lead.lead_status', [Lead::LEAD_STATUS_CONVERTED, Lead::LEAD_STATUS_DROPPED]]);
        $mine = function ($q) use ($who) {
            if ($who !== '') {
                $q->andWhere(LeadAccess::involvedCondition((int)$who));
            }
            return $q;
        };
        $today = date('Y-m-d');
        $week  = date('Y-m-d', strtotime('+7 days'));
        $stale = date('Y-m-d', strtotime('-' . self::STALE_DAYS . ' days'));

        $sections = [
            'overdue'  => $mine($base()->andWhere(['<', 'lead.meeting_reported_date', $today . ' 00:00:00'])->andWhere(['>=', 'lead.meeting_reported_date', $stale . ' 00:00:00']))
                            ->orderBy(['lead.meeting_reported_date' => SORT_ASC])->limit(100)->all(),
            'today'    => $mine($base()->andWhere(['between', 'lead.meeting_reported_date', $today . ' 00:00:00', $today . ' 23:59:59']))
                            ->orderBy(['lead.meeting_reported_date' => SORT_ASC])->limit(100)->all(),
            'visits'   => $mine($base()->andWhere(['between', 'lead.site_visit_date', $today . ' 00:00:00', $week . ' 23:59:59']))
                            ->orderBy(['lead.site_visit_date' => SORT_ASC])->limit(100)->all(),
            'upcoming' => $mine($base()->andWhere(['between', 'lead.meeting_reported_date', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00', $week . ' 23:59:59']))
                            ->orderBy(['lead.meeting_reported_date' => SORT_ASC])->limit(100)->all(),
            // Follow-ups missed for more than STALE_DAYS: kept apart so they do not bury this week's work.
            'stale'    => $mine($base()->andWhere(['<', 'lead.meeting_reported_date', $stale . ' 00:00:00'])->andWhere(['>', 'lead.meeting_reported_date', '1971-01-01']))
                            ->orderBy(['lead.meeting_reported_date' => SORT_DESC])->limit(100)->all(),
            'unplanned'=> $mine($base()->andWhere(['or', ['lead.meeting_reported_date' => null], ['<', 'lead.meeting_reported_date', '1971-01-01']]))
                            ->andWhere(['lead.priority' => [Lead::PRIORITY_MEDIUM, Lead::PRIORITY_HIGH]])
                            ->orderBy(['lead.priority' => SORT_DESC, 'lead.id' => SORT_DESC])->limit(50)->all(),
        ];

        return $this->render('today', [
            'sections'  => $sections,
            'who'       => $who,
            'isManager' => $isManager,
            'users'     => LeadAccess::assignableUsers(),
        ]);
    }

    /** Where leads come from and how they convert — by source, temperature and product. */
    public function actionInsights(): string
    {
        $req  = Yii::$app->request;
        $from = $this->validDate(is_string($req->get('from')) ? $req->get('from') : '') ?? date('Y-m-d', strtotime('-90 days'));
        $to   = $this->validDate(is_string($req->get('to')) ? $req->get('to') : '') ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $base = fn() => LeadAccess::scopeToUser(Lead::find()->alias('lead')
            ->where(['!=', 'lead.status', Lead::STATUS_DELETED])
            ->andWhere(['between', 'lead.created_date', $from . ' 00:00:00', $to . ' 23:59:59']));
        $agg = [
            'total'     => 'COUNT(*)',
            'open'      => 'SUM(lead.lead_status NOT IN (' . Lead::LEAD_STATUS_CONVERTED . ',' . Lead::LEAD_STATUS_DROPPED . '))',
            'converted' => 'SUM(lead.lead_status = ' . Lead::LEAD_STATUS_CONVERTED . ')',
            'dropped'   => 'SUM(lead.lead_status = ' . Lead::LEAD_STATUS_DROPPED . ')',
            'hot'       => 'SUM(lead.priority = ' . Lead::PRIORITY_HIGH . ')',
        ];
        $bySource = $base()->select(array_merge(['k' => 'lead.lead_source'], $agg))->groupBy('lead.lead_source')->asArray()->all();
        $byTemp   = $base()->select(array_merge(['k' => 'lead.priority'], $agg))->groupBy('lead.priority')->asArray()->all();
        $byProduct = $base()->innerJoin('lead_product_interest i', 'i.lead_id = lead.id')
            ->select(array_merge(['k' => 'i.category_id'], $agg))->groupBy('i.category_id')->asArray()->all();
        $totals = $base()->select($agg)->asArray()->one();

        return $this->render('insights', [
            'from'      => $from,
            'to'        => $to,
            'totals'    => array_map('intval', $totals ?: []),
            'bySource'  => $bySource,
            'byTemp'    => $byTemp,
            'byProduct' => $byProduct,
            'categories'=> ProductCategory::find()->select(['name', 'id'])->indexBy('id')->column(),
        ]);
    }

    /** JSON: leads / clients with the same phone or email (live check on the lead form). */
    public function actionCheckDuplicate(): Response
    {
        $req = Yii::$app->request;
        $str = fn($k) => is_string($req->get($k)) ? mb_substr(trim($req->get($k)), 0, 100) : '';
        $id  = ctype_digit($str('id')) ? (int)$str('id') : null;
        return $this->asJson(['matches' => LeadInsight::duplicates([$str('phone'), $str('phone2'), $str('mobile')], $str('email'), $id)]);
    }

    /** JSON: value estimate for products of interest × site area (form "Estimate" button). */
    public function actionEstimate(): Response
    {
        $req  = Yii::$app->request;
        $ids  = $req->get('interest', []);
        $ids  = is_array($ids) ? array_filter($ids, fn($v) => is_scalar($v) && ctype_digit((string)$v)) : [];
        $area = $req->get('area');
        $area = is_scalar($area) && is_numeric($area) ? (float)$area : 0;
        $unit = $req->get('unit');
        $unit = is_string($unit) && in_array($unit, Lead::AREA_UNITS, true) ? $unit : 'Sq.ft';
        return $this->asJson(['estimate' => LeadInsight::estimate($ids, $area, $unit)]);
    }

    /** Non-blocking warning when the new lead's phone / email is already known. */
    private function flashDuplicates(Lead $lead): void
    {
        $dups = LeadInsight::duplicates([$lead->contact_no, $lead->contact_no2, $lead->contact_person_mobile], $lead->email, (int)$lead->id, 3);
        if ($dups) {
            $names = array_map(fn($d) => ($d['type'] === 'client' ? 'client ' : '') . $d['name'], $dups);
            Yii::$app->session->addFlash('warning', 'Possible duplicate: the same ' . $dups[0]['match'] . ' is on ' . implode(', ', $names) . '.');
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    private function leadQuery(): ActiveQuery
    {
        return Lead::find()->alias('lead')->with(['assignedTo', 'createdBy', 'latestFollower', 'interests']);
    }

    /** Converted / dropped lists: developers & clients only see leads they are involved in (Yii1). */
    private function scopeNonManagers(ActiveQuery $query): void
    {
        if (!LeadAccess::isManager()) {
            $query->andWhere(LeadAccess::involvedCondition((int)Yii::$app->user->id));
        }
    }

    /**
     * Apply the list search form. Date range semantics follow Yii1: both dates
     * give a range, a single date matches that exact day.
     *
     * @param string $dateColumn lead column to filter by date, or 'follow_up_date' to match follow-ups
     */
    private function applyFilters(ActiveQuery $query, string $dateColumn): array
    {
        $request = Yii::$app->request;
        $filters = [];
        foreach (self::LIST_FILTERS as $key) {
            $value = $request->get($key, '');
            $filters[$key] = is_string($value) ? mb_substr(trim($value), 0, 100) : ''; // arrays from tampered URLs are ignored
        }

        // Values that cannot match anything are cleared from the form and explained.
        $ignored = [];
        if ($filters['lead_status'] !== '' && !array_key_exists((int)$filters['lead_status'], Lead::leadStatusOptions())) {
            $ignored[] = 'lead status';
            $filters['lead_status'] = '';
        }
        if ($filters['lead_source'] !== '' && !array_key_exists((int)$filters['lead_source'], Lead::sourceOptions())) {
            $ignored[] = 'source';
            $filters['lead_source'] = '';
        }
        if ($filters['priority'] !== '' && !array_key_exists((int)$filters['priority'], Lead::priorityOptions())) {
            $ignored[] = 'temperature';
            $filters['priority'] = '';
        }
        if ($filters['interest'] !== '' && !ctype_digit($filters['interest'])) {
            $ignored[] = 'interested in';
            $filters['interest'] = '';
        }
        if ($filters['assigned_to'] !== '' && !ctype_digit($filters['assigned_to'])) {
            $ignored[] = 'assigned to';
            $filters['assigned_to'] = '';
        }
        foreach (['from_date' => 'from date', 'to_date' => 'to date'] as $key => $label) {
            if ($filters[$key] !== '' && !$this->validDate($filters[$key])) {
                $ignored[] = $label;
                $filters[$key] = '';
            }
        }
        if ($ignored) {
            Yii::$app->session->setFlash('warning', 'Ignored an invalid search value (' . implode(', ', $ignored) . '). Dates must be YYYY-MM-DD.');
        }

        foreach (['client_name', 'contact_no', 'email', 'country', 'contact_person_name', 'title'] as $field) {
            if ($filters[$field] !== '') {
                $query->andWhere(['like', "lead.$field", $filters[$field]]);
            }
        }
        if ($filters['lead_status'] !== '') {
            $query->andWhere(['lead.lead_status' => (int)$filters['lead_status']]);
        }
        if ($filters['lead_source'] !== '') {
            $query->andWhere(['lead.lead_source' => (int)$filters['lead_source']]);
        }
        if ($filters['priority'] !== '') {
            $query->andWhere(['lead.priority' => (int)$filters['priority']]);
        }
        if ($filters['interest'] !== '') {
            $query->andWhere(['in', 'lead.id', LeadProductInterest::find()->select('lead_id')->where(['category_id' => (int)$filters['interest']])]);
        }
        if ($filters['assigned_to'] !== '') {
            $uid = (int)$filters['assigned_to'];
            $query->andWhere(['or',
                ['lead.assigned_to' => $uid],
                ['in', 'lead.id', LeadFollowers::find()->select('lead_id')->where(['status' => 1, 'assigned_to' => $uid])],
            ]);
        }

        $from = $this->validDate($filters['from_date']);
        $to   = $this->validDate($filters['to_date']);
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from]; // dates entered the wrong way round
            [$filters['from_date'], $filters['to_date']] = [$from, $to];
        }
        if ($from || $to) {
            $column    = $dateColumn === 'follow_up_date' ? 'follow_up_date' : "DATE($dateColumn)";
            $condition = ($from && $to) ? ['between', $column, $from, $to] : [$column => $from ?: $to];
            if ($dateColumn === 'follow_up_date') {
                $query->andWhere(['in', 'lead.id', LeadFollowers::find()->select('lead_id')->where(['status' => 1])->andWhere($condition)]);
            } else {
                $query->andWhere($condition);
            }
        }
        return $filters;
    }

    private function validDate(string $value): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $value : null;
    }

    private function renderList(string $listType, string $title, ActiveQuery $query, array $filters): string
    {
        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);
        return $this->render('list', [
            'listType'     => $listType,
            'title'        => $title,
            'dataProvider' => $dataProvider,
            'filters'      => $filters,
            'users'        => LeadAccess::assignableUsers(),
        ]);
    }

    private function renderLead(int $id, bool $assignedView): string
    {
        $lead   = LeadAccess::findLead($id);
        $userId = (int)Yii::$app->user->id;

        // Opening the lead marks it (and its follow-ups) read for the assignee.
        if ((int)$lead->assigned_to === $userId && (int)$lead->read_status !== LeadFollowers::READ_READ) {
            $lead->updateAttributes(['read_status' => LeadFollowers::READ_READ]);
        }
        LeadFollowers::updateAll(['read_status' => LeadFollowers::READ_READ],
            ['lead_id' => $id, 'assigned_to' => $userId, 'read_status' => LeadFollowers::READ_UNREAD]);

        $followers = LeadFollowers::find()
            ->with(['assignedTo', 'createdBy'])
            ->where(['lead_id' => $id, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        $openFollowUps = count(array_filter($followers, fn($f) => (int)$f->followup_status === LeadFollowers::FOLLOWUP_OPEN));

        $score = LeadInsight::score($lead);
        $insight = [
            'score'      => $score,
            'band'       => LeadInsight::band($score),
            'actions'    => LeadInsight::actions($lead),
            'duplicates' => LeadInsight::duplicates([$lead->contact_no, $lead->contact_no2, $lead->contact_person_mobile], $lead->email, (int)$lead->id),
            'estimate'   => LeadInsight::estimate(array_map(fn($c) => $c->id, $lead->interests), $lead->site_area, $lead->area_unit),
            'idle'       => LeadInsight::idleDays($lead),
        ];

        return $this->render('view', [
            'insight'       => $insight,
            'lead'          => $lead,
            'followers'     => $followers,
            'openFollowUps' => $openFollowUps,
            'attachments'   => $lead->attachments,
            'assignedView'  => $assignedView,
        ]);
    }

    private function newLead(): Lead
    {
        $lead = new Lead(['scenario' => Lead::SCENARIO_CREATE]);
        $lead->assigned_to   = Yii::$app->user->id;
        $lead->attended_date = date('Y-m-d');
        $lead->attended_time = date('H:i');
        $lead->priority      = Lead::PRIORITY_LOW;
        $lead->lead_status   = Lead::LEAD_STATUS_OPEN;
        return $lead;
    }

    private function loadLeadForm(Lead $lead, bool $isNew): void
    {
        $allowed = $isNew ? array_merge(self::FORM_FIELDS, self::CREATE_ONLY_FIELDS) : self::FORM_FIELDS;
        $data    = array_intersect_key((array)Yii::$app->request->post('Lead', []), array_flip($allowed));
        // Assign directly: setAttributes() would skip the virtual date/time fields (not DB columns).
        foreach ($data as $name => $value) {
            $lead->$name = is_string($value) ? $value : null;
        }
        if ((int)$lead->mode !== Lead::MODE_OTHER) {
            $lead->other_mode = null;
        }
        $posted = Yii::$app->request->post('Lead', []);
        $ids    = isset($posted['interestIds']) && is_array($posted['interestIds']) ? $posted['interestIds'] : [];
        $lead->interestIds   = array_values(array_filter($ids, fn($v) => is_scalar($v) && ctype_digit((string)$v)));
        $lead->syncInterests = true;
    }

    /** Save a new lead, its attachments and its first follow-up (Yii1 actionCreate). */
    private function createWithFollowUp(Lead $lead): bool
    {
        $lead->created_by   = Yii::$app->user->id;
        $lead->created_date = date('Y-m-d H:i:s');
        $lead->lead_status  = Lead::LEAD_STATUS_OPEN;
        $lead->is_client    = 0;
        $lead->read_status  = LeadFollowers::READ_UNREAD;
        $lead->status       = Lead::STATUS_ACTIVE;

        if (!$lead->validate()) {
            return false;
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $lead->save(false);

            $followUp = new LeadFollowers([
                'lead_id'         => $lead->id,
                'follow_up_date'  => date('Y-m-d', strtotime($lead->meeting_reported_date)),
                'follow_up_time'  => date('H:i:s', strtotime($lead->meeting_reported_date)),
                'subject'         => $lead->subject,
                'description'     => $lead->description,
                'mode'            => $lead->mode,
                'other_mode'      => $lead->other_mode,
                'assigned_to'     => $lead->assigned_to,
                'created_by'      => Yii::$app->user->id,
                'created_date'    => date('Y-m-d H:i:s'),
                'followup_status' => LeadFollowers::FOLLOWUP_OPEN,
                'read_status'     => LeadFollowers::READ_UNREAD,
                'status'          => 1,
            ]);
            if (!$followUp->save()) {
                throw new \RuntimeException('Follow-up: ' . implode(' ', $followUp->getFirstErrors()));
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Lead create failed: ' . $e->getMessage(), __METHOD__);
            $lead->setIsNewRecord(true);
            $lead->id = null;
            $lead->addError('client_name', 'The lead could not be saved. Please try again.');
            return false;
        }

        LeadFiles::flashRejected($this->saveLeadAttachments($lead));
        LeadMailer::sendAssigned($lead, $followUp);
        return true;
    }

    private function saveLeadAttachments(Lead $lead): array
    {
        return LeadFiles::save('attachments', 'lead_attachments', 'lead_' . $lead->id,
            function (string $path, string $ext) use ($lead) {
                (new LeadAttachments([
                    'lead_id'         => $lead->id,
                    'attachment_path' => $path,
                    'type'            => $ext,
                    'created_by'      => Yii::$app->user->id,
                    'status'          => 1,
                ]))->save(false);
            });
    }

    private function redirectAfterCreate(): Response
    {
        foreach (['index', 'my-leads', 'assigned'] as $list) {
            if (UserAccess::checkAccess('lead/' . $list)) {
                return $this->redirect([$list]);
            }
        }
        return $this->goHome();
    }

    /**
     * @return array{0: int|null, 1: string[]} imported count (null when the file
     *         itself is unusable) and per-row error messages
     */
    private function importLeadMaster(string $path, string $ext): array
    {
        if ($ext === 'xlsx' && !class_exists(\ZipArchive::class)) {
            return [null, ['XLSX files need the PHP "zip" extension, which is not enabled on this server. Upload the file as CSV or XLS, or ask the administrator to enable extension=zip.']];
        }
        try {
            $sheet = IOFactory::load($path)->getSheet(0);
        } catch (\Throwable $e) {
            return [null, ['The file could not be read: ' . $e->getMessage()]];
        }
        if ($sheet->getHighestDataColumn() !== 'H') {
            return [null, ['Columns in the file are not suitable. Expected 8 columns (A–H): S.No, Account Name, First Name, Last Name, Designation, Email, Phone Number, City.']];
        }

        $imported = 0;
        $errors   = [];
        $now      = date('Y-m-d H:i:s');
        $userId   = Yii::$app->user->id;
        $lastRow  = $sheet->getHighestDataRow();

        for ($row = 2; $row <= $lastRow; $row++) {
            $cells = array_map(fn($v) => trim((string)$v), $sheet->rangeToArray("A{$row}:H{$row}", null, true, false)[0]);
            if (implode('', array_slice($cells, 1)) === '') {
                continue; // blank row
            }
            $prospect = new LeadMaster([
                'account_name' => $cells[1],
                'first_name'   => $cells[2],
                'last_name'    => $cells[3],
                'designation'  => $cells[4],
                'email_id'     => $cells[5],
                'phone_number' => $cells[6],
                'city'         => $cells[7],
                'created_at'   => $now,
                'created_by'   => $userId,
                'updated_at'   => $now,
                'updated_by'   => $userId,
                'status'       => LeadMaster::STATUS_ACTIVE,
            ]);
            if ($prospect->save()) {
                $imported++;
            } else {
                $errors[] = "Row {$row}: " . implode(' ', $prospect->getFirstErrors());
            }
        }
        return [$imported, $errors];
    }

    private function findLeadMaster(int $id): LeadMaster
    {
        $model = LeadMaster::find()->where(['id' => $id])->andWhere(['!=', 'status', LeadMaster::STATUS_DELETED])->one();
        if (!$model) {
            throw new NotFoundHttpException('The requested lead master record does not exist.');
        }
        return $model;
    }

    private function requirePostRequest(): void
    {
        if (!Yii::$app->request->isPost) {
            throw new BadRequestHttpException('This action requires a POST request.');
        }
    }
}
