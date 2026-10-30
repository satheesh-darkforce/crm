<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: salary_allowance_deduction
 * type:  1=Allowance, 2=Deduction, 3=Gross, 4=Net
 * type2: 1=Percentage, 2=Fixed Amount
 *
 * Central payroll configuration. PF/ESI carry an employee + employer rate and a
 * wage ceiling; earnings (Basic/DA/HRA/Others) and PF/ESI carry a calc_basis
 * that says what their percentage applies on. See PayrollCalculator.
 *
 * @property int    $id
 * @property string $title
 * @property string $code            Machine key: BASIC, DA, HRA, OTHERS, PF, ESI, PT, TDS
 * @property int    $type
 * @property int    $type2
 * @property float  $default_value
 * @property float  $employee_value  Employee-side rate (%) — PF 12, ESI 0.75
 * @property float  $employer_value  Employer-side rate (%) — PF 13, ESI 3.25
 * @property float  $admin_charge    PF admin charge (%) on the EPF salary; deducted from net pay
 * @property float  $wage_ceiling    PF cap 15000; ESI eligibility 21000; null = none
 * @property int    $calc_basis      1=Pay After LOP, 2=Basic+DA
 * @property int    $status
 */
class SalaryAllowance extends ActiveRecord
{
    // ---- machine codes ------------------------------------------------------
    public const CODE_BASIC  = 'BASIC';
    public const CODE_DA     = 'DA';
    public const CODE_HRA    = 'HRA';
    public const CODE_OTHERS = 'OTHERS';
    public const CODE_PF     = 'PF';
    public const CODE_ESI    = 'ESI';
    public const CODE_PT     = 'PT';
    public const CODE_TDS    = 'TDS';

    // ---- calc_basis ---------------------------------------------------------
    public const BASIS_PAY_AFTER_LOP = 1;
    public const BASIS_BASIC_PLUS_DA = 2;

    public static function tableName(): string
    {
        return 'salary_allowance_deduction';
    }

    public function rules(): array
    {
        return [
            [['title', 'type2'], 'required'],
            // default_value is required for everything EXCEPT PF/ESI rows, which carry
            // employee_value + employer_value instead of a single value.
            [['default_value'], 'required', 'when' => fn($m) => empty($m->employee_value) && empty($m->employer_value),
                'whenClient' => "function(a,v){return !jQuery('#salaryallowance-employee_value').val() && !jQuery('#salaryallowance-employer_value').val();}"],
            [['type', 'type2', 'status', 'calc_basis'], 'integer'],
            [['title'], 'string', 'max' => 255],
            [['code'], 'string', 'max' => 30],
            [['code'], 'unique'],
            [['default_value', 'employee_value', 'employer_value', 'admin_charge', 'wage_ceiling'], 'number', 'min' => 0],
            // Percentage inputs cannot exceed 100.
            [['default_value'], 'validatePercentageCap'],
            [['employee_value', 'employer_value', 'admin_charge'], 'validateRateCap'],
        ];
    }

    /** default_value is a percentage only when type2 = 1 (Percentage). */
    public function validatePercentageCap(string $attribute): void
    {
        if ($this->type2 == 1 && (float)$this->$attribute > 100) {
            $this->addError($attribute, 'Percentage cannot exceed 100.');
        }
    }

    /** employee_value / employer_value are always percentages. */
    public function validateRateCap(string $attribute): void
    {
        if ($this->$attribute !== null && $this->$attribute !== '' && (float)$this->$attribute > 100) {
            $this->addError($attribute, $this->getAttributeLabel($attribute) . ' cannot exceed 100%.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'title'          => 'Title',
            'code'           => 'Code',
            'type2'          => 'Value Type',
            'default_value'  => 'Default Value',
            'employee_value' => 'Employee Rate (%)',
            'employer_value' => 'Employer Rate (%)',
            'admin_charge'   => 'Admin Charge (%)',
            'wage_ceiling'   => 'Wage Ceiling',
            'calc_basis'     => 'Calculated On',
        ];
    }

    public function getValueTypeLabel(): string
    {
        return $this->type2 == 1 ? 'Percentage (%)' : 'Fixed Amount';
    }

    public function getCalcBasisLabel(): string
    {
        return match ((int)$this->calc_basis) {
            self::BASIS_PAY_AFTER_LOP => 'Pay After LOP',
            self::BASIS_BASIC_PLUS_DA => 'Basic + DA',
            default                   => '—',
        };
    }

    /** Fetch one active config row by its machine code (BASIC, PF, ESI, PT, …). */
    public static function findByCode(string $code): ?self
    {
        return static::findOne(['code' => $code, 'status' => 1]);
    }

    /** All active config rows keyed by code, for a single bulk load. */
    public static function activeByCode(): array
    {
        $out = [];
        foreach (static::find()->where(['status' => 1])->andWhere(['not', ['code' => null]])->all() as $row) {
            $out[$row->code] = $row;
        }
        return $out;
    }
}
