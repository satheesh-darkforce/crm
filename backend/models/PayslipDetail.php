<?php
namespace backend\models;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $payslip_id
 * @property int    $type              1=Earning, 2=Deduction
 * @property string $title
 * @property float  $amount
 * @property float  $deduction_percentage
 * @property int    $deduction_type    1=Percentage, 2=Fixed Amount
 * @property int    $status
 */
class PayslipDetail extends ActiveRecord
{
    public static function tableName(): string { return 'payslip_detail'; }

    public function rules(): array
    {
        return [
            [['payslip_id', 'type'], 'required'],
            [['payslip_id', 'type', 'deduction_type', 'status'], 'integer'],
            [['amount', 'deduction_percentage'], 'number', 'min' => 0],
            [['title'], 'string', 'max' => 255],
        ];
    }

    public function getPayslip(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Payslip::class, ['id' => 'payslip_id']);
    }
}
