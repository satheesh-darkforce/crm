<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Lead model
 *
 * Table: lead
 *
 * lead_status: 1=Open, 2=Follow-up, 3=Quoted, 4=Won, 5=Converted, 6=Dropped
 * priority:    1=High, 2=Medium, 3=Low
 * mode:        1=Online, 2=Offline, 3=Reference
 *
 * @property int    $id
 * @property string $client_name
 * @property string $contact_no
 * @property string $contact_no2
 * @property string $email
 * @property string $country
 * @property string $company_name
 * @property string $address
 * @property string $contact_person_name
 * @property string $contact_person_mobile
 * @property string $contact_person_email
 * @property string $title
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
    const LEAD_STATUS_OPEN      = 1;
    const LEAD_STATUS_FOLLOWUP  = 2;
    const LEAD_STATUS_QUOTED    = 3;
    const LEAD_STATUS_WON       = 4;
    const LEAD_STATUS_CONVERTED = 5;
    const LEAD_STATUS_DROPPED   = 6;

    const PRIORITY_HIGH   = 1;
    const PRIORITY_MEDIUM = 2;
    const PRIORITY_LOW    = 3;

    const MODE_ONLINE    = 1;
    const MODE_OFFLINE   = 2;
    const MODE_REFERENCE = 3;

    public static function tableName(): string
    {
        return '{{%lead}}';
    }

    public function rules(): array
    {
        return [
            [['client_name', 'email', 'company_name', 'contact_person_name', 'contact_person_email', 'subject', 'contact_no', 'address', 'description'], 'trim'],
            [['client_name', 'subject', 'meeting_reported_date', 'assigned_to', 'mode', 'current_stage', 'status'], 'required'],
            [['priority', 'assigned_to', 'mode', 'created_by', 'lead_status', 'is_client', 'read_status', 'current_stage', 'status'], 'integer'],
            [['lead_value', 'expected_budget'], 'default', 'value' => 0],
            [['lead_value', 'expected_budget'], 'number'],
            [['address', 'description', 'reason'], 'string'],
            [['meeting_attended_date', 'meeting_reported_date', 'created_date', 'converted_date_time', 'dropped_date_time'], 'safe'],
            [['client_name', 'email', 'country', 'company_name', 'contact_person_name', 'contact_person_email', 'title', 'subject', 'other_mode', 'industry', 'offering_service'], 'string', 'max' => 255],
            [['contact_no', 'contact_no2', 'contact_person_mobile'], 'string', 'max' => 20],
            [['contact_no'], 'match', 'pattern' => '/^[0-9]{7,15}$/', 'message' => 'Contact number must be 7–15 digits.'],
            [['contact_no2'], 'match', 'pattern' => '/^[0-9]{7,15}$/', 'message' => 'Alt contact must be 7–15 digits.'],
            [['contact_person_mobile'], 'match', 'pattern' => '/^[0-9]{7,15}$/', 'message' => 'Contact person mobile must be 7–15 digits.'],
            [['email'], 'email'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'client_name'  => 'Client Name',
            'subject'      => 'Subject',
            'lead_status'  => 'Status',
            'priority'     => 'Priority',
            'assigned_to'  => 'Assigned To',
            'mode'         => 'Source',
            'lead_value'   => 'Lead Value',
            'created_date' => 'Created Date',
        ];
    }

    public function getAssignedTo(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'assigned_to']);
    }

    public function getCreatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getFollowers(): \yii\db\ActiveQuery
    {
        return $this->hasMany(LeadFollowers::class, ['lead_id' => 'id']);
    }

    public function getAttachments(): \yii\db\ActiveQuery
    {
        return $this->hasMany(LeadAttachments::class, ['lead_id' => 'id']);
    }

    public function getStatusLabel(): string
    {
        return match ((int)$this->lead_status) {
            self::LEAD_STATUS_OPEN      => 'Open',
            self::LEAD_STATUS_FOLLOWUP  => 'Follow-up',
            self::LEAD_STATUS_QUOTED    => 'Quoted',
            self::LEAD_STATUS_WON       => 'Won',
            self::LEAD_STATUS_CONVERTED => 'Converted',
            self::LEAD_STATUS_DROPPED   => 'Dropped',
            default                     => 'Unknown',
        };
    }
}
