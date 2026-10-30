<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Payslip model
 *
 * Table: payslip
 *
 * @property int    $id
 * @property string $month_year      (date — YYYY-MM-01 stored as date type)
 * @property int    $developer_id
 * @property string $name
 * @property string $emp_id
 * @property string $designation
 * @property int    $no_of_working_days
 * @property float  $leave_balance
 * @property float  $leave_allocated
 * @property float  $leave_taken
 * @property float  $leave_lop
 * @property float  $late_permission
 * @property float  $late_lop
 * @property float  $additional_working
 * @property float  $total_lop
 * @property float  $gross_salary
 * @property float  $actual_salary
 * @property string $remarks
 * @property string $created_date
 * @property int    $created_by
 * @property int    $status
 */
class Payslip extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'payslip';
    }

    public function rules(): array
    {
        return [
            [['developer_id', 'month_year', 'created_date', 'created_by', 'status'], 'required'],
            [['developer_id', 'no_of_working_days', 'created_by', 'status'], 'integer'],
            [['leave_balance', 'leave_allocated', 'leave_taken', 'leave_lop', 'late_permission', 'late_lop', 'additional_working', 'total_lop'], 'number'],
            [['gross_salary', 'actual_salary'], 'number', 'min' => 0],
            [['month_year', 'created_date'], 'safe'],
            [['name', 'designation', 'remarks'], 'string', 'max' => 255],
            [['emp_id'], 'string', 'max' => 20],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'month_year'    => 'Month',
            'developer_id'  => 'Developer',
            'gross_salary'  => 'Gross Salary',
            'actual_salary' => 'Net Salary',
            'total_lop'     => 'Total LOP',
        ];
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }

    public function getDetails(): \yii\db\ActiveQuery
    {
        return $this->hasMany(PayslipDetail::class, ['payslip_id' => 'id']);
    }

    /**
     * Yii1 parity: Utility::checkPayslipGeneratedStatus().
     * Once any payslip exists for a month (status=1), that month is locked
     * for attendance edits / remark additions across the whole company.
     *
     * @param string $monthOrDate  "YYYY-MM", "YYYY-MM-DD" or any parseable date
     */
    public static function isGeneratedForMonth(string $monthOrDate): bool
    {
        $ts = strtotime($monthOrDate);
        if (!$ts) {
            return false;
        }
        $monthStart = date('Y-m-01', $ts);
        return self::find()
            ->where(['month_year' => $monthStart, 'status' => 1])
            ->exists();
    }
}
