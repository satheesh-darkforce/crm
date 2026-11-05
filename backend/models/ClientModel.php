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
        // Format and uniqueness checks run for new records and for values the
        // user changed. Many clients migrated from Yii1 hold values the newer
        // rules reject (e.g. a 5-digit phone, duplicate company names); those
        // must not block an unrelated edit. The JS mirror compares the input
        // with its data-orig attribute (set by the form) for the same effect.
        $changed       = fn(string $attr) => fn($m) => $m->valueChanged($attr);
        $changedClient = fn(string $extra = 'true') =>
            "function(attribute, value){ var o = $(attribute.input).data('orig'); return (o === undefined || String(o) !== value) && ($extra); }";
        $domestic      = "$('#clientmodel-client_type').val() != '3'";
        $intl          = "$('#clientmodel-client_type').val() == '3'";

        return [
            [['client_name', 'company_name', 'address', 'contact_person', 'email_id', 'phone', 'alt_phone', 'gst_no', 'tax_exemption_reason'], 'trim'],
            [['gst_no'], 'filter', 'filter' => fn($v) => is_string($v) ? strtoupper($v) : $v],
            [['client_name', 'company_name', 'address', 'contact_person', 'phone', 'email_id', 'client_type', 'tax_exemption', 'gst_registration_type'], 'required'],
            [['user_id', 'client_type', 'tax_exemption', 'status', 'gst_registration_type'], 'integer'],
            [['gst_registration_type'], 'in', 'range' => [self::GST_REG_REGULAR, self::GST_REG_CONSUMER]],
            [['client_type'], 'in', 'range' => array_keys(self::typeOptions())],
            [['tax_exemption'], 'in', 'range' => [0, 1]],
            // GST No. is mandatory for "Regular" GST registration; optional
            // (skippable) for "Consumer". The whenClient mirror keeps the
            // ActiveForm client-side validation consistent with the server.
            [['gst_no'], 'required',
                'when'        => fn($m) => (int)$m->gst_registration_type === self::GST_REG_REGULAR,
                'whenClient'  => "function(attribute, value){ return $('#clientmodel-gst_registration_type').val() == '1'; }",
                'message'     => 'GST No. is required when Registration Type is Regular.'],
            [['tax_exemption_reason'], 'required',
                'when'        => fn($m) => (int)$m->tax_exemption === 1 && $m->valueChanged('tax_exemption'),
                'whenClient'  => "function(){ var s = $('#tax-exemption-select'); return s.val() == '1' && String(s.data('orig')) !== '1'; }",
                'message'     => 'Enter the reason for the tax exemption.'],
            [['address'], 'string', 'max' => 5000],
            [['access_permissions'], 'string'],
            [['client_name', 'company_name', 'contact_person', 'email_id', 'logo'], 'string', 'max' => 255],
            [['phone', 'alt_phone'], 'string', 'max' => 20],
            // International clients (TYPE_INTERNATIONAL) may use up to 15 digits;
            // domestic clients (Inter/Intra-State) must be exactly 10 digits.
            [['phone'], 'match', 'pattern' => '/^[0-9]{10}$/',
                'when' => fn($m) => (int)$m->client_type !== self::TYPE_INTERNATIONAL && $m->valueChanged('phone', 'client_type'),
                'whenClient' => $changedClient($domestic),
                'message' => 'Phone must be exactly 10 digits.'],
            [['phone'], 'match', 'pattern' => '/^[0-9]{1,15}$/',
                'when' => fn($m) => (int)$m->client_type === self::TYPE_INTERNATIONAL && $m->valueChanged('phone', 'client_type'),
                'whenClient' => $changedClient($intl),
                'message' => 'International phone must be up to 15 digits.'],
            [['alt_phone'], 'match', 'pattern' => '/^[0-9]{10}$/', 'skipOnEmpty' => true,
                'when' => fn($m) => (int)$m->client_type !== self::TYPE_INTERNATIONAL && $m->valueChanged('alt_phone', 'client_type'),
                'whenClient' => $changedClient($domestic),
                'message' => 'Alt phone must be exactly 10 digits.'],
            [['alt_phone'], 'match', 'pattern' => '/^[0-9]{1,15}$/', 'skipOnEmpty' => true,
                'when' => fn($m) => (int)$m->client_type === self::TYPE_INTERNATIONAL && $m->valueChanged('alt_phone', 'client_type'),
                'whenClient' => $changedClient($intl),
                'message' => 'Alt phone must be up to 15 digits.'],
            [['tax_exemption_reason'], 'string', 'max' => 255],
            [['gst_no'], 'string', 'max' => 15],
            [['gst_no'], 'match',
                'pattern' => '/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/',
                'message'  => 'Invalid GSTIN format (e.g. 29ABCDE1234F1Z5).',
                'skipOnEmpty' => true,
                'when' => $changed('gst_no'), 'whenClient' => $changedClient()],
            [['email_id'], 'email', 'when' => $changed('email_id'), 'whenClient' => $changedClient()],
            [['client_name'], 'unique', 'message' => 'Client already exists.', 'when' => $changed('client_name')],
            [['company_name'], 'unique', 'message' => 'Company already exists.', 'when' => $changed('company_name')],
        ];
    }

    /**
     * True for new records, or when any of the given attributes differs from
     * the stored value (ignoring surrounding spaces and letter case).
     */
    public function valueChanged(string ...$attributes): bool
    {
        if ($this->isNewRecord) {
            return true;
        }
        foreach ($attributes as $attr) {
            if (strcasecmp(trim((string)$this->getOldAttribute($attr)), trim((string)$this->$attr)) !== 0) {
                return true;
            }
        }
        return false;
    }

    /** Labels of required fields that are empty — legacy Yii1 records often lack them. */
    public function missingRequiredFields(): array
    {
        $missing = [];
        foreach (['client_name', 'company_name', 'contact_person', 'email_id', 'phone', 'address', 'client_type', 'gst_registration_type'] as $attr) {
            if (trim((string)$this->$attr) === '' || ($attr === 'client_type' && !isset(self::typeOptions()[(int)$this->$attr]))
                || ($attr === 'gst_registration_type' && !isset(self::registrationTypeOptions()[(int)$this->$attr]))) {
                $missing[] = $this->getAttributeLabel($attr);
            }
        }
        if ((int)$this->gst_registration_type === self::GST_REG_REGULAR && trim((string)$this->gst_no) === '') {
            $missing[] = $this->getAttributeLabel('gst_no');
        }
        return $missing;
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_INTERSTATE    => 'Inter-State',
            self::TYPE_INTRASTATE    => 'Intra-State',
            self::TYPE_INTERNATIONAL => 'International',
        ];
    }

    public static function registrationTypeOptions(): array
    {
        return [self::GST_REG_REGULAR => 'Regular', self::GST_REG_CONSUMER => 'Consumer'];
    }

    /**
     * Client-portal pages an admin can grant (Yii1 route names, matched by
     * UserAccess). The permissions page offers exactly these and the
     * controller stores nothing else.
     */
    public static function portalPermissionOptions(): array
    {
        return [
            'Invoices'     => ['invoice/clientinvoice' => 'Invoice List', 'invoice/view' => 'Invoice View'],
            'Work Reports' => ['workreport/clientworkreports' => 'Work Report List', 'workreport/clientview' => 'Work Report View'],
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

    public const PASSWORD_MIN = 6;
    public const PASSWORD_MAX = 128;

    /**
     * Validate a new client's portal login (Clients → Add, Leads → Add To Client).
     * Errors are added to $user; on success the password hash is set.
     * The password is trimmed, as client logins always have been.
     */
    public static function validateLogin(User $user, string $password): bool
    {
        $user->username = trim((string)$user->username);
        $valid = true;
        if ($user->username === '') {
            $user->addError('username', 'Username cannot be blank.');
            $valid = false;
        } elseif (!preg_match('/^[A-Za-z0-9._@-]{3,50}$/', $user->username)) {
            $user->addError('username', 'Username must be 3–50 characters: letters, numbers, and . _ @ - only.');
            $valid = false;
        }
        if ($error = self::passwordError($password)) {
            $user->addError('password', $error);
            return false;
        }
        $user->setPassword(trim($password));
        // Uniqueness and column limits (User rules), once the format is right.
        return $valid && $user->validate(['username', 'password', 'usertype', 'status']);
    }

    /** Why a portal password is unacceptable, or null when it is fine. */
    public static function passwordError(string $password): ?string
    {
        $password = trim($password);
        if ($password === '') {
            return 'Password cannot be blank.';
        }
        if (mb_strlen($password) < self::PASSWORD_MIN || mb_strlen($password) > self::PASSWORD_MAX) {
            return 'Password must be ' . self::PASSWORD_MIN . '–' . self::PASSWORD_MAX . ' characters.';
        }
        return null;
    }

    /**
     * Save a new (already validated) client together with its login user and,
     * when it has a company name, its Sundry Debtor ledger — mirrors Yii1.
     * All-or-nothing: rolls back and returns false if any step fails.
     */
    public function saveWithLogin(User $user): bool
    {
        $tx = static::getDb()->beginTransaction();
        try {
            if (!$user->save(false)) {
                throw new \RuntimeException('user');
            }
            $this->user_id = $user->user_id;
            if (!$this->save(false)) {
                throw new \RuntimeException('client');
            }
            if ($this->company_name !== '') {
                $ledger = new AccLedger();
                $ledger->category_id = 8; // Sundry Debtor
                $ledger->title       = $this->company_name;
                $ledger->type        = 1; // For client
                $ledger->is_fixed    = 0;
                $ledger->client_id   = $this->id;
                $ledger->created_at  = date('Y-m-d H:i:s');
                $ledger->created_by  = \Yii::$app->user->id;
                $ledger->status      = 1;
                $ledger->save(false);
            }
            $tx->commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollBack();
            \Yii::error('Client create failed: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
