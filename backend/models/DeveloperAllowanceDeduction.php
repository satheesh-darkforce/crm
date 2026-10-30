<?php
namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $developer_id
 * @property int    $allowance_deduction_id
 * @property float  $value
 * @property int    $status
 */
class DeveloperAllowanceDeduction extends ActiveRecord
{
    public static function tableName(): string { return 'developer_allowance_deduction'; }

    public function rules(): array
    {
        return [
            [['developer_id', 'allowance_deduction_id'], 'required'],
            [['developer_id', 'allowance_deduction_id', 'status'], 'integer'],
            [['value'], 'number'],
        ];
    }

    public function getAllowanceDeduction(): \yii\db\ActiveQuery
    {
        return $this->hasOne(SalaryAllowance::class, ['id' => 'allowance_deduction_id']);
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }
}
