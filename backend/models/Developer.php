<?php

namespace backend\models;

use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * Migrated from Yii1: Developer model
 *
 * Table: developer
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $developer_name
 * @property string $designation
 * @property string $address
 * @property string $mobile
 * @property string $dob
 * @property string $email_id
 * @property string $official_mobile_no
 * @property string $official_email_id
 * @property string $qualification
 * @property string $profile_photo
 * @property string $account_number
 * @property string $ifsc_code
 * @property string $bank
 * @property string $bank_branch
 * @property float  $gross_salary
 * @property float  $net_salary
 * @property float  $cl_backup
 * @property float  $cl_allotted
 * @property int    $employment_type  (1=Full-time, 2=Part-time, 3=Contract, 4=Intern)
 * @property string $emp_code
 * @property string $emp_pin
 * @property int    $team
 * @property int    $department
 * @property int    $leader
 * @property int    $branch
 * @property string $doj
 * @property string $dor
 * @property string $access_permissions
 * @property string $access_permissions_new
 * @property string $apk_access_permissions
 * @property string $esi_details
 * @property string $pf_details
 * @property string $uan_details
 * @property string $hiring_mode
 * @property string $rfid
 * @property string $pan
 * @property int    $status
 * @property int    $is_salary_applicable
 * @property int    $pf_status
 * @property float  $pf_amount
 * @property float  $pf_employee_amount
 * @property float  $pf_employer_amount
 * @property int    $esi_status
 * @property float  $esi_amount
 * @property float  $esi_employee_amount
 * @property float  $esi_employer_amount
 * @property int    $tds_status
 * @property float  $tds_amount
 * @property int    $professional_tax_status
 * @property float  $professional_tax_amount
 * @property int    $incentive_status
 * @property float  $incentive_amount
 * @property int    $allowance_applicable
 * @property float  $allowance_amount
 */
class Developer extends ActiveRecord
{
    use \backend\components\GuardedAttributes;

    /** Set by the permission screens / controllers, never by the profile form. */
    protected static function guardedAttributes(): array
    {
        return ['access_permissions', 'access_permissions_new', 'apk_access_permissions', 'user_id', 'status', 'emp_pin'];
    }

    public static function tableName(): string
    {
        return 'developer';
    }

    // Branch, Department, Team lookup maps — matches Yii1 hard-coded values
    // Green Divine (Bengaluru). Keys are stored on developer rows — add new keys, do not renumber.
    public static array $branches   = [1 => 'Bengaluru (Koramangala)'];
    public static array $departments = [
        1 => 'Sales', 2 => 'Site Survey & Design', 3 => 'Admin', 4 => 'Accounts',
        5 => 'HR', 6 => 'Installation', 7 => 'Operations & Stores', 8 => 'Customer Support',
    ];
    public static array $teams       = [1 => 'Office', 2 => 'Field / Installation'];
    public static array $employmentTypes = [1 => 'Trainee', 2 => 'Employed'];

    // Emp code series — codes look like MAE001 … MAE244 (prefix + zero-padded number).
    const EMP_CODE_PREFIX = 'MAE';
    const EMP_CODE_DIGITS = 3;

    /**
     * Next free code in the MAE series: highest existing number + 1.
     *
     * Scans every row (active AND ex-employees) so a code is never reused after
     * someone leaves. Rows with no code, or with a code outside this series, are
     * ignored — they don't own a number here. The trailing loop steps over any
     * code that is somehow already taken, so a padding oddity or a manually-typed
     * duplicate can't hand back a used code. Uniqueness is enforced here and by
     * the `unique` rule on emp_code — the column has no DB unique index.
     */
    /** Employee code prefix: params['numbering.employee'], else EMP_CODE_PREFIX. */
    public static function empCodePrefix(): string
    {
        $p = \Yii::$app->params['numbering.employee'] ?? '';
        return is_string($p) && preg_match('/^[A-Za-z]{1,10}$/', $p) ? $p : self::EMP_CODE_PREFIX;
    }

    public static function nextEmpCode(): string
    {
        $prefix = self::empCodePrefix();
        $start  = strlen($prefix) + 1;

        $max = static::find()
            ->where(new Expression('emp_code REGEXP :re', [':re' => '^' . $prefix . '[0-9]+$']))
            ->max(new Expression("CAST(SUBSTRING(emp_code, $start) AS UNSIGNED)"));

        $n = (int)$max + 1;
        do {
            $code = self::formatEmpCode($n);
            $n++;
        } while (static::find()->where(['emp_code' => $code])->exists());

        return $code;
    }

    /** Format a series number as a code: 245 → "MAE245", 7 → "MAE007". */
    public static function formatEmpCode(int $n): string
    {
        return self::empCodePrefix() . str_pad((string)$n, self::EMP_CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    public function rules(): array
    {
        return [
            [['developer_name', 'designation', 'address', 'mobile', 'email_id', 'qualification', 'official_email_id', 'emp_code'], 'trim'],
            // Required — matches Yii1: developer_name, designation, address, mobile, department, team, branch
            [['developer_name', 'designation', 'address', 'mobile', 'department', 'team', 'branch'], 'required'],

            // Unique — matches Yii1
            [['developer_name'], 'unique', 'message' => 'Employee name already exists.'],
            [['emp_code'], 'unique', 'skipOnEmpty' => true, 'message' => 'Emp code already exists.'],
            [['emp_code'], 'match', 'pattern' => '/^0+$/', 'not' => true, 'message' => 'Emp code cannot be zero.', 'skipOnEmpty' => true],

            // Programmatically set
            [['user_id'], 'safe'],
            [['status'], 'default', 'value' => 1],

            // Defaults
            [['employment_type'], 'default', 'value' => 1],
            [['gross_salary', 'net_salary', 'cl_backup', 'cl_allotted'], 'default', 'value' => 0],

            // Salary Management (requirement #1) — applicable flags + amounts
            [[
                'is_salary_applicable', 'pf_status', 'esi_status', 'tds_status',
                'professional_tax_status', 'incentive_status', 'allowance_applicable',
            ], 'boolean'],
            [[
                'is_salary_applicable', 'pf_status', 'esi_status', 'tds_status',
                'professional_tax_status', 'incentive_status', 'allowance_applicable',
                'pf_amount', 'pf_employee_amount', 'pf_employer_amount',
                'esi_amount', 'esi_employee_amount', 'esi_employer_amount',
                'tds_amount', 'professional_tax_amount',
                'incentive_amount', 'allowance_amount',
            ], 'default', 'value' => 0],
            [[
                'pf_amount', 'pf_employee_amount', 'pf_employer_amount',
                'esi_amount', 'esi_employee_amount', 'esi_employer_amount',
                'tds_amount', 'professional_tax_amount',
                'incentive_amount', 'allowance_amount',
            ], 'number', 'min' => 0],

            // Types — matches Yii1: branch, status are integer; uan_details is integer
            [['user_id', 'employment_type', 'team', 'department', 'leader', 'branch', 'status'], 'integer'],
            [['uan_details'], 'integer', 'skipOnEmpty' => true],
            [['gross_salary', 'net_salary', 'cl_backup', 'cl_allotted'], 'number'],

            // Dates — safe (not required, just accepted)
            [['dob', 'doj', 'dor'], 'safe'],

            // Strings
            [['access_permissions', 'access_permissions_new', 'apk_access_permissions'], 'string'],
            [['mobile'], 'match', 'pattern' => '/^[0-9]{10}$/', 'message' => 'Mobile number must be exactly 10 digits.'],
            [['official_mobile_no'], 'match', 'pattern' => '/^[0-9]{10}$/', 'skipOnEmpty' => true, 'message' => 'Alt phone number must be exactly 10 digits.'],
            [['developer_name', 'designation', 'address', 'mobile', 'email_id', 'qualification', 'profile_photo', 'hiring_mode', 'rfid'], 'string', 'max' => 255],
            [['account_number', 'ifsc_code', 'bank', 'bank_branch', 'esi_details', 'pf_details', 'pan'], 'string', 'max' => 50],
            [['official_mobile_no'], 'string', 'max' => 20],
            [['official_email_id'], 'string', 'max' => 50],
            [['emp_code'], 'string', 'max' => 20],
            [['emp_pin'], 'string', 'max' => 50],

            // Email format — not required in Yii1, but validate format if provided
            [['email_id'], 'email', 'skipOnEmpty' => true],
            [['official_email_id'], 'email', 'skipOnEmpty' => true],

            // Identity / bank formats and date sanity — checked only on new or edited values,
            // so the few legacy rows that predate these rules stay editable.
            [['pan', 'ifsc_code'], 'filter', 'filter' => fn($v) => $v === null ? null : strtoupper(preg_replace('/\s+/', '', (string)$v))],
            [['account_number'], 'filter', 'filter' => fn($v) => $v === null ? null : preg_replace('/[\s-]+/', '', (string)$v)],
            [['pan'], 'match', 'pattern' => '/^[A-Z]{5}\d{4}[A-Z]$/', 'skipOnEmpty' => true, 'message' => 'PAN is 10 characters, e.g. ABCDE1234F.', 'when' => $this->changed('pan')],
            [['ifsc_code'], 'match', 'pattern' => '/^[A-Z]{4}0[A-Z0-9]{6}$/', 'skipOnEmpty' => true, 'message' => 'IFSC is 11 characters, e.g. SBIN0001234.', 'when' => $this->changed('ifsc_code')],
            [['account_number'], 'match', 'pattern' => '/^\d{6,20}$/', 'skipOnEmpty' => true, 'message' => 'Account number must be 6–20 digits.', 'when' => $this->changed('account_number')],
            [['uan_details'], 'match', 'pattern' => '/^\d{12}$/', 'skipOnEmpty' => true, 'message' => 'UAN is 12 digits.', 'when' => fn($m) => (string)$m->uan_details !== '0' && $this->changed('uan_details')($m)],
            [['dob', 'doj', 'dor'], 'date', 'format' => 'php:Y-m-d', 'skipOnEmpty' => true, 'message' => '{attribute} must be a date (YYYY-MM-DD).'],
            [['dob'], 'compare', 'compareAttribute' => 'doj', 'operator' => '<', 'type' => 'string', 'skipOnEmpty' => true,
                'message' => 'Date of birth must be before the joining date.', 'when' => fn($m) => !empty($m->doj) && ($this->changed('dob')($m) || $this->changed('doj')($m))],
            [['dor'], 'compare', 'compareAttribute' => 'doj', 'operator' => '>=', 'type' => 'string', 'skipOnEmpty' => true,
                'message' => 'Relieving date cannot be before the joining date.', 'when' => fn($m) => !empty($m->doj) && !empty($m->dor) && $m->dor !== '0000-00-00'],
            [['gross_salary', 'net_salary'], 'number', 'min' => 0, 'tooSmall' => '{attribute} cannot be negative.'],
        ];
    }

    /** Closure for 'when': the attribute is new or its value changed. */
    private function changed(string $attr): \Closure
    {
        return fn($m) => $m->isNewRecord || (string)$m->getOldAttribute($attr) !== (string)$m->$attr;
    }

    public function attributeLabels(): array
    {
        return [
            'id'              => 'ID',
            'developer_name'  => 'Employee Name',
            'designation'     => 'Designation',
            'dob'             => 'DOB',
            'branch'          => 'Branch',
            'mobile'          => 'Mobile',
            'official_mobile_no' => 'Alt Phone Number',
            'email_id'        => 'Email',
            'official_email_id' => 'Official Email',
            'qualification'   => 'Qualification',
            'employment_type' => 'Employment Type',
            'department'      => 'Department',
            'team'            => 'Team',
            'emp_code'        => 'Emp Code',
            'doj'             => 'Date of Join',
            'dor'             => 'Date of Resignation',
            'account_number'  => 'Account Number',
            'ifsc_code'       => 'IFSC Code',
            'bank'            => 'Bank',
            'bank_branch'     => 'Bank Branch',
            'gross_salary'    => 'Gross Salary (CTC per month)',
            'net_salary'      => 'Net Salary',
            'cl_backup'       => 'CL Backup',
            'cl_allotted'     => 'CL Allotted (per month)',
            'hiring_mode'     => 'Hiring Mode',
            'esi_details'     => 'ESI Number',
            'pf_details'      => 'PF Number',
            'uan_details'     => 'UAN Number',
            'pan'             => 'PAN Number',
            'address'         => 'Address',
            'status'          => 'Status',
            // Salary Management
            'is_salary_applicable'    => 'Salary Applicable',
            'pf_status'               => 'PF Applicable',
            'pf_amount'               => 'PF Amount',
            'pf_employee_amount'      => 'PF Employee Amount',
            'pf_employer_amount'      => 'PF Employer Amount',
            'esi_status'              => 'ESI Applicable',
            'esi_amount'              => 'ESI Amount',
            'esi_employee_amount'     => 'ESI Employee Amount',
            'esi_employer_amount'     => 'ESI Employer Amount',
            'tds_status'              => 'TDS Applicable',
            'tds_amount'              => 'TDS Amount',
            'professional_tax_status' => 'Professional Tax Applicable',
            'professional_tax_amount' => 'Professional Tax Amount',
            'incentive_status'        => 'Incentive Applicable',
            'incentive_amount'        => 'Incentive Amount',
            'allowance_applicable'    => 'Allowance Applicable',
            'allowance_amount'        => 'Allowance Amount',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'user_id']);
    }

    public function getWorkReports(): \yii\db\ActiveQuery
    {
        return $this->hasMany(WorkReport::class, ['developer_id' => 'id']);
    }

    public function getAttendances(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Attendance::class, ['developer_id' => 'id']);
    }

    public function getLeaveRequests(): \yii\db\ActiveQuery
    {
        return $this->hasMany(LeaveRequests::class, ['developer_id' => 'id']);
    }

    public function getPayslips(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Payslip::class, ['developer_id' => 'id']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function getFullName(): string
    {
        return $this->developer_name;
    }

    public function getProfilePhotoUrl(): string
    {
        if ($this->profile_photo) {
            return \yii\helpers\Url::to('@web/uploads/profileimages/' . $this->profile_photo, true);
        }
        return \yii\helpers\Url::to('@web/images/default-avatar.png', true);
    }
}
