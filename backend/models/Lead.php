<?php

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Html;
use yii\helpers\HtmlPurifier;

/**
 * Migrated from Yii1: Lead model
 *
 * Table: lead
 *
 * lead_status:   1=Open, 2=Got Requirement, 3=Gave Quotation, 4=Waiting for Payment,
 *                5=Converted, 6=Dropped. Moves forward only; a Dropped lead can be re-opened.
 * status:        1=Active, 2=Inactive, 0=Deleted (soft delete)
 * priority:      1=Low, 2=Medium, 3=High
 * mode:          1=Mail, 2=Call, 3=Skype, 4=Direct Meeting, 5=Other, 6=Whatsapp
 * current_stage: 1..8 sales pipeline stage (see stageOptions())
 * read_status:   1=Unread, 2=Read (by the assignee)
 *
 * meeting_reported_date holds the NEXT follow-up date & time; it is kept in
 * sync with the latest lead_followers row.
 *
 * @property int    $id
 * @property string $client_name
 * @property string $contact_no
 * @property string $contact_no2
 * @property string $email
 * @property string $country       Labelled "City" in the UI (legacy column name)
 * @property string $company_name
 * @property string $address
 * @property string $contact_person_name
 * @property string $contact_person_mobile
 * @property string $contact_person_email
 * @property string $title         Labelled "Project" in the UI
 * @property string $subject
 * @property string $description
 * @property string $reason
 * @property string $meeting_attended_date
 * @property string $meeting_reported_date
 * @property int    $priority
 * @property int    $assigned_to
 * @property int    $mode
 * @property string $other_mode
 * @property int    $created_by
 * @property string $created_date
 * @property int    $lead_status
 * @property int    $is_client
 * @property int    $read_status
 * @property float  $lead_value
 * @property float  $expected_budget
 * @property int    $current_stage
 * @property string $industry
 * @property string $offering_service
 * @property string $converted_date_time
 * @property string $dropped_date_time
 * @property int    $status
 */
class Lead extends ActiveRecord
{
    const LEAD_STATUS_OPEN             = 1;
    const LEAD_STATUS_GOT_REQUIREMENT  = 2;
    const LEAD_STATUS_GAVE_QUOTATION   = 3;
    const LEAD_STATUS_WAITING_PAYMENT  = 4;
    const LEAD_STATUS_CONVERTED        = 5;
    const LEAD_STATUS_DROPPED          = 6;

    const STATUS_DELETED  = 0;
    const STATUS_ACTIVE   = 1;
    const STATUS_INACTIVE = 2;

    const PRIORITY_LOW    = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_HIGH   = 3;

    const MODE_OTHER = 5;

    const SCENARIO_CREATE = 'create';

    /** Phone format: digits with optional leading +, spaces, dashes, brackets (legacy data uses all of these). */
    const PHONE_PATTERN = '/^\+?[0-9][0-9\s\-()]{4,24}$/';

    /** Next follow-up date (Y-m-d) and time (H:i) — create form only; combined into meeting_reported_date. */
    public $nextfollowup_date;
    public $nextfollowup_time;

    /** Meeting attended — split into date + time inputs in the form. */
    public $attended_date;
    public $attended_time;

    public static function tableName(): string
    {
        return '{{%lead}}';
    }

    public function rules(): array
    {
        return [
            [['client_name', 'company_name', 'email', 'contact_no', 'contact_no2', 'country', 'address',
              'contact_person_name', 'contact_person_mobile', 'contact_person_email', 'title', 'subject',
              'other_mode', 'industry', 'offering_service', 'reason', 'description'], 'trim'],
            [['client_name', 'subject', 'assigned_to', 'mode', 'current_stage'], 'required'],
            [['nextfollowup_date', 'nextfollowup_time'], 'required', 'on' => self::SCENARIO_CREATE],
            [['nextfollowup_date', 'attended_date'], 'date', 'format' => 'php:Y-m-d', 'message' => 'Enter a valid date (YYYY-MM-DD).'],
            [['nextfollowup_time', 'attended_time'], 'match', 'pattern' => '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', 'message' => 'Enter a valid time.'],
            [['attended_date'], 'required', 'when' => fn($m) => (string)$m->attended_time !== '',
                'whenClient' => "function(){ return $('#lead-attended_time').val() !== ''; }",
                'message' => 'Enter the date for the meeting attended time.'],
            [['attended_date'], 'validateAttendedNotFuture'],
            [['nextfollowup_date'], 'validateNextFollowUpNotPast', 'on' => self::SCENARIO_CREATE],
            [['assigned_to'], 'validateAssignee'],
            [['other_mode'], 'required', 'when' => fn($m) => (int)$m->mode === self::MODE_OTHER,
                'whenClient' => "function(){ return $('#lead-mode').val() == '5'; }"],
            [['email', 'contact_person_email'], 'email'],
            [['contact_no', 'contact_no2', 'contact_person_mobile'], 'match', 'pattern' => self::PHONE_PATTERN,
                'message' => '{attribute} must be a valid phone number.'],
            [['priority'], 'in', 'range' => array_keys(self::priorityOptions())],
            [['mode'], 'in', 'range' => array_keys(self::modeOptions())],
            [['current_stage'], 'in', 'range' => array_keys(self::stageOptions())],
            [['lead_status'], 'in', 'range' => array_keys(self::leadStatusOptions())],
            [['status'], 'in', 'range' => [self::STATUS_DELETED, self::STATUS_ACTIVE, self::STATUS_INACTIVE]],
            [['assigned_to', 'created_by', 'is_client', 'read_status'], 'integer'],
            [['lead_value', 'expected_budget'], 'number', 'min' => 0, 'max' => 99999999.99,
                'tooBig' => '{attribute} cannot exceed 99,999,999.99.', 'tooSmall' => '{attribute} cannot be negative.'],
            [['lead_value', 'expected_budget'], 'default', 'value' => 0],
            [['priority'], 'default', 'value' => self::PRIORITY_LOW],
            [['client_name', 'company_name', 'email', 'contact_no', 'contact_no2', 'country', 'contact_person_name',
              'contact_person_mobile', 'contact_person_email', 'title', 'subject', 'other_mode', 'industry', 'offering_service'],
                'string', 'max' => 255],
            [['address', 'description', 'reason'], 'string'],
            [['meeting_attended_date', 'meeting_reported_date', 'created_date', 'converted_date_time', 'dropped_date_time'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_CREATE] = $scenarios[self::SCENARIO_DEFAULT];
        return $scenarios;
    }

    /** The meeting has already happened: its date & time cannot be in the future. */
    public function validateAttendedNotFuture(string $attribute): void
    {
        $at = $this->attendedTimestamp();
        if ($at !== null && $at > time()) {
            $this->addError($attribute, 'Meeting attended date & time cannot be in the future.');
        }
    }

    /**
     * The next follow-up is scheduled ahead (same rule as LeadFollowers), which
     * also guarantees it is after the meeting — that cannot be in the future.
     */
    public function validateNextFollowUpNotPast(string $attribute): void
    {
        if (!$this->nextfollowup_time || $this->hasErrors('nextfollowup_time')) {
            return;
        }
        $next = strtotime($this->nextfollowup_date . ' ' . $this->nextfollowup_time);
        if ($next !== false && $next < strtotime(date('Y-m-d H:i'))) {
            $this->addError($attribute, 'Next follow-up date & time cannot be in the past.');
        }
    }

    /** Assignee must be an active, non-client user (checked when it changes, so leads of since-deactivated users stay editable). */
    public function validateAssignee(string $attribute): void
    {
        if (!$this->isNewRecord && !$this->isAttributeChanged($attribute, false)) {
            return;
        }
        if (!self::isAssignableUser($this->$attribute)) {
            $this->addError($attribute, 'Select an active employee.');
        }
    }

    /** Active, non-client user (same rule as the "Assigned To" dropdown). */
    public static function isAssignableUser($userId): bool
    {
        return is_numeric($userId) && User::find()
            ->where(['user_id' => (int)$userId, 'status' => 1])
            ->andWhere(['!=', 'usertype', User::TYPE_CLIENT])
            ->exists();
    }

    private function attendedTimestamp(): ?int
    {
        if (!$this->attended_date || $this->hasErrors('attended_date') || $this->hasErrors('attended_time')) {
            return null;
        }
        $ts = strtotime($this->attended_date . ' ' . ($this->attended_time ?: '00:00'));
        return $ts === false ? null : $ts;
    }

    public function attributeLabels(): array
    {
        return [
            'client_name'           => 'Client Name',
            'contact_no'            => 'Contact No',
            'contact_no2'           => 'Contact No 2',
            'email'                 => 'Email',
            'country'               => 'City',
            'company_name'          => 'Company Name',
            'address'               => 'Address',
            'contact_person_name'   => 'Contact Person Name',
            'contact_person_mobile' => 'Contact Person Mobile',
            'contact_person_email'  => 'Contact Person Email',
            'title'                 => 'Project',
            'subject'               => 'Subject',
            'description'           => 'Description',
            'reason'                => 'Reason',
            'meeting_attended_date' => 'Meeting Attended Date & Time',
            'meeting_reported_date' => 'Next Follow Up Date & Time',
            'attended_date'         => 'Meeting Attended Date',
            'attended_time'         => 'Meeting Attended Time',
            'nextfollowup_date'     => 'Next Followup Date',
            'nextfollowup_time'     => 'Next Followup Time',
            'priority'              => 'Priority',
            'assigned_to'           => 'Assigned To',
            'mode'                  => 'Mode',
            'other_mode'            => 'If Other Mode',
            'lead_status'           => 'Lead Status',
            'status'                => 'Status',
            'lead_value'            => 'Lead Value (in dollars)',
            'expected_budget'       => 'Expected Budget',
            'current_stage'         => 'Current Stage',
            'industry'              => 'Industry',
            'offering_service'      => 'Offering Service',
            'created_by'            => 'Created By',
            'created_date'          => 'Created Date',
        ];
    }

    public function afterFind(): void
    {
        parent::afterFind();
        if ($this->meeting_attended_date) {
            $ts = strtotime($this->meeting_attended_date);
            $this->attended_date = date('Y-m-d', $ts);
            $this->attended_time = date('H:i', $ts);
        }
    }

    public function beforeValidate(): bool
    {
        // Combine the split date/time inputs into the stored datetime columns.
        $this->meeting_attended_date = $this->attended_date
            ? $this->attended_date . ' ' . ($this->attended_time ?: '00:00') . ':00'
            : null;
        if ($this->scenario === self::SCENARIO_CREATE && $this->nextfollowup_date && $this->nextfollowup_time) {
            $this->meeting_reported_date = $this->nextfollowup_date . ' ' . substr($this->nextfollowup_time, 0, 5) . ':00';
        }
        return parent::beforeValidate();
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function getAssignedTo(): ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'assigned_to']);
    }

    public function getCreatedBy(): ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getFollowers(): ActiveQuery
    {
        return $this->hasMany(LeadFollowers::class, ['lead_id' => 'id']);
    }

    /** Most recent follow-up by scheduled date/time (drives the "Subject" column, like Yii1 next_subject()). */
    public function getLatestFollower(): ActiveQuery
    {
        return $this->hasOne(LeadFollowers::class, ['lead_id' => 'id'])
            ->orderBy(['follow_up_date' => SORT_DESC, 'follow_up_time' => SORT_DESC]);
    }

    public function getAttachments(): ActiveQuery
    {
        return $this->hasMany(LeadAttachments::class, ['lead_id' => 'id'])->andWhere(['status' => 1]);
    }

    // ── Option lists ─────────────────────────────────────────────────────────

    public static function leadStatusOptions(): array
    {
        return [
            self::LEAD_STATUS_OPEN            => 'Open',
            self::LEAD_STATUS_GOT_REQUIREMENT => 'Got Requirement',
            self::LEAD_STATUS_GAVE_QUOTATION  => 'Gave Quotation',
            self::LEAD_STATUS_WAITING_PAYMENT => 'Waiting for Payment',
            self::LEAD_STATUS_CONVERTED       => 'Converted',
            self::LEAD_STATUS_DROPPED         => 'Dropped',
        ];
    }

    /** Statuses a lead still in the pipeline can have (used by list filters). */
    public static function openLeadStatusOptions(): array
    {
        return array_slice(self::leadStatusOptions(), 0, 4, true);
    }

    public static function priorityOptions(): array
    {
        return [self::PRIORITY_LOW => 'Low', self::PRIORITY_MEDIUM => 'Medium', self::PRIORITY_HIGH => 'High'];
    }

    public static function modeOptions(): array
    {
        return [1 => 'Mail', 2 => 'Call', 3 => 'Skype', 4 => 'Direct Meeting', 6 => 'Whatsapp', self::MODE_OTHER => 'Other'];
    }

    public static function stageOptions(): array
    {
        return [
            1 => 'Lead',
            2 => 'Qualified Lead',
            3 => 'First meeting with stakeholder',
            4 => 'Solutioning complete',
            5 => 'Commercial proposal',
            6 => 'Legal contract',
            7 => 'Final Disc',
            8 => 'Closed won',
        ];
    }

    public static function statusOptions(): array
    {
        return [self::STATUS_ACTIVE => 'Active', self::STATUS_INACTIVE => 'Inactive'];
    }

    /**
     * Lead statuses selectable from the current one. Mirrors Yii1: status only
     * moves forward; Converted is final; Dropped can only be re-opened, and only
     * when $canReopen (admin / sub-admin).
     */
    public function nextLeadStatusOptions(bool $canReopen): array
    {
        $current = (int)$this->lead_status;
        $all     = self::leadStatusOptions();
        if ($current === self::LEAD_STATUS_CONVERTED) {
            return [];
        }
        if ($current === self::LEAD_STATUS_DROPPED) {
            return $canReopen ? [self::LEAD_STATUS_DROPPED => 'Dropped', self::LEAD_STATUS_OPEN => 'Re-open'] : [];
        }
        return array_filter($all, fn($k) => $k >= $current, ARRAY_FILTER_USE_KEY);
    }

    public function canChangeLeadStatusTo(int $status, bool $canReopen): bool
    {
        return $status !== (int)$this->lead_status
            && array_key_exists($status, $this->nextLeadStatusOptions($canReopen));
    }

    // ── Labels / display helpers ─────────────────────────────────────────────

    public function getLeadStatusLabel(): string
    {
        return self::leadStatusOptions()[(int)$this->lead_status] ?? '-';
    }

    public function getPriorityLabel(): string
    {
        return self::priorityOptions()[(int)$this->priority] ?? '-';
    }

    public function getModeLabel(): string
    {
        return self::modeLabel($this->mode);
    }

    public static function modeLabel($mode): string
    {
        return self::modeOptions()[(int)$mode] ?? '-';
    }

    public function getStageLabel(): string
    {
        return self::stageOptions()[(int)$this->current_stage] ?? '-';
    }

    public function getNextSubject(): string
    {
        return $this->latestFollower->subject ?? $this->subject ?? '';
    }

    /**
     * Colour tone for a lead status pill (see .lead-pill in css/leads.css):
     * new → neutral, in progress → accent, awaiting payment → warning,
     * converted → success, dropped → danger.
     */
    public static function leadStatusTone(int $status): string
    {
        return [
            self::LEAD_STATUS_OPEN            => 'neutral',
            self::LEAD_STATUS_GOT_REQUIREMENT => 'accent',
            self::LEAD_STATUS_GAVE_QUOTATION  => 'accent',
            self::LEAD_STATUS_WAITING_PAYMENT => 'warning',
            self::LEAD_STATUS_CONVERTED       => 'success',
            self::LEAD_STATUS_DROPPED         => 'danger',
        ][$status] ?? 'neutral';
    }

    /**
     * Whether a follow-up date is overdue, today or tomorrow (Yii1 highlighted
     * these rows). Returns '' for later, empty or unparseable dates.
     */
    public static function followUpState(?string $date): string
    {
        $ts = $date ? strtotime($date) : false;
        if ($ts === false || (int)date('Y', $ts) < 1971) { // also skips legacy 0000-00-00 values
            return '';
        }
        $d = date('Y-m-d', $ts);
        return match (true) {
            $d === date('Y-m-d')                      => 'today',
            $d === date('Y-m-d', strtotime('+1 day')) => 'tomorrow',
            $d < date('Y-m-d')                        => 'overdue',
            default                                   => '',
        };
    }

    /**
     * Username for display. Deactivated accounts are renamed
     * "name - deleted @<timestamp>"; show them as "name (deleted)".
     */
    public static function userLabel(?User $user): string
    {
        if (!$user) {
            return '-';
        }
        return preg_replace('/\s*-\s*deleted\s*@.*$/i', ' (deleted)', (string)$user->username);
    }

    public static function formatDateTime(?string $value): string
    {
        $ts = $value ? strtotime($value) : false;
        return ($ts !== false && (int)date('Y', $ts) >= 1971) ? date('d-m-Y h:i a', $ts) : '-';
    }

    /**
     * Descriptions written by the Yii1 CKEditor are HTML; new ones are plain text.
     * Purify HTML so legacy formatting survives without allowing script injection.
     */
    public static function renderRichText(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '-';
        }
        return $text !== strip_tags($text) ? HtmlPurifier::process($text) : nl2br(Html::encode($text));
    }
}
