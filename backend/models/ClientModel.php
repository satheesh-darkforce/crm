<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Client model
 * Named ClientModel to avoid PHP reserved word conflict with 'Client'.
 *
 * Table: client
 *
 * client_type:
 *   1 = InterState
 *   2 = IntraState
 *   3 = International
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $client_name
 * @property string $company_name
 * @property string $address
 * @property string $phone
 * @property string $alt_phone
 * @property string $contact_person
 * @property string $email_id
 * @property string $logo
 * @property int    $gst_registration_type (1=Regular, 2=Consumer)
 * @property string $gst_no
 * @property int    $client_type
 * @property int    $tax_exemption
 * @property string $tax_exemption_reason
 * @property string $access_permissions
 * @property int    $status
 */
class ClientModel extends ActiveRecord
{
    const TYPE_INTERSTATE   = 1;
    const TYPE_INTRASTATE   = 2;
    const TYPE_INTERNATIONAL = 3;

    const GST_REG_REGULAR  = 1;
    const GST_REG_CONSUMER = 2;

    public static function tableName(): string
    {
        return 'client';
    }

    public function rules(): array
    {
        return [
            [['client_name', 'company_name', 'address', 'contact_person', 'email_id', 'phone', 'alt_phone', 'gst_no', 'tax_exemption_reason'], 'trim'],
            [['client_name', 'company_name', 'address', 'contact_person', 'phone', 'email_id', 'client_type', 'tax_exemption', 'gst_registration_type'], 'required'],
            [['user_id', 'client_type', 'tax_exemption', 'status', 'gst_registration_type'], 'integer'],
            [['gst_registration_type'], 'in', 'range' => [self::GST_REG_REGULAR, self::GST_REG_CONSUMER]],
            // GST No. is mandatory for "Regular" GST registration; optional
            // (skippable) for "Consumer". The whenClient mirror keeps the
            // ActiveForm client-side validation consistent with the server.
            [['gst_no'], 'required',
                'when'        => fn($m) => (int)$m->gst_registration_type === self::GST_REG_REGULAR,
                'whenClient'  => "function(attribute, value){ return $('#clientmodel-gst_registration_type').val() == '1'; }",
                'message'     => 'GST No. is required when Registration Type is Regular.'],
            [['address'], 'string', 'max' => 5000],
            [['access_permissions'], 'string'],
            [['client_name', 'company_name', 'contact_person', 'email_id', 'logo'], 'string', 'max' => 255],
            [['phone', 'alt_phone'], 'string', 'max' => 20],
            // International clients (TYPE_INTERNATIONAL) may use up to 15 digits;
            // domestic clients (Inter/Intra-State) must be exactly 10 digits.
            // 'whenClient' mirrors the PHP 'when' so client-side validation
            // doesn't fire the wrong pattern before the form is submitted.
            [['phone'], 'match', 'pattern' => '/^[0-9]{10}$/',
                'when' => fn($m) => (int)$m->client_type !== self::TYPE_INTERNATIONAL,
                'whenClient' => "function(){ return $('#clientmodel-client_type').val() != '3'; }",
                'message' => 'Phone must be exactly 10 digits.'],
            [['phone'], 'match', 'pattern' => '/^[0-9]{1,15}$/',
                'when' => fn($m) => (int)$m->client_type === self::TYPE_INTERNATIONAL,
                'whenClient' => "function(){ return $('#clientmodel-client_type').val() == '3'; }",
                'message' => 'International phone must be up to 15 digits.'],
            [['alt_phone'], 'match', 'pattern' => '/^[0-9]{10}$/', 'skipOnEmpty' => true,
                'when' => fn($m) => (int)$m->client_type !== self::TYPE_INTERNATIONAL,
                'whenClient' => "function(){ return $('#clientmodel-client_type').val() != '3'; }",
                'message' => 'Alt phone must be exactly 10 digits.'],
            [['alt_phone'], 'match', 'pattern' => '/^[0-9]{1,15}$/', 'skipOnEmpty' => true,
                'when' => fn($m) => (int)$m->client_type === self::TYPE_INTERNATIONAL,
                'whenClient' => "function(){ return $('#clientmodel-client_type').val() == '3'; }",
                'message' => 'Alt phone must be up to 15 digits.'],
            [['tax_exemption_reason'], 'string', 'max' => 255],
            [['gst_no'], 'string', 'max' => 15],
            [['gst_no'], 'match',
                'pattern' => '/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/',
                'message'  => 'Invalid GSTIN format (e.g. 29ABCDE1234F1Z5).',
                'skipOnEmpty' => true],
            [['email_id'], 'email'],
            [['client_name'], 'unique', 'message' => 'Client already exists.'],
            [['company_name'], 'unique', 'message' => 'Company already exists.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'                    => 'ID',
            'user_id'               => 'Username',
            'client_name'           => 'Client Name',
            'company_name'          => 'Company Name',
            'address'               => 'Address',
            'phone'                 => 'Phone',
            'alt_phone'             => 'Alt Phone',
            'contact_person'        => 'Contact Person',
            'email_id'              => 'Email',
            'logo'                  => 'Logo',
            'gst_registration_type' => 'GST Registration Type',
            'gst_no'                => 'GST No',
            'client_type'           => 'Client Type',
            'tax_exemption'         => 'Tax Exemption Applicable?',
            'tax_exemption_reason'  => 'Tax Exemption Reason',
            'access_permissions'    => 'Access Permissions',
            'status'                => 'Status',
        ];
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'user_id']);
    }

    public function getProjects(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Project::class, ['client_id' => 'id']);
    }

    public function getInvoices(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Invoice::class, ['client_id' => 'id']);
    }

    public function getDomains(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Domain::class, ['client_id' => 'id'])->where(['domains.status' => 1]);
    }

    public function getWorkReports(): \yii\db\ActiveQuery
    {
        return $this->hasMany(WorkReport::class, ['client_id' => 'id']);
    }

    public function getLedger(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccLedger::class, ['client_id' => 'id']);
    }

    public function getTypeName(): string
    {
        return match ((int)$this->client_type) {
            self::TYPE_INTERSTATE    => 'Inter-State',
            self::TYPE_INTRASTATE    => 'Intra-State',
            self::TYPE_INTERNATIONAL => 'International',
            default                  => 'Unknown',
        };
    }

    public function getRegistrationTypeName(): string
    {
        return match ((int)$this->gst_registration_type) {
            self::GST_REG_REGULAR  => 'Regular',
            self::GST_REG_CONSUMER => 'Consumer',
            default                => '—',
        };
    }
}
