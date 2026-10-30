<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Guard / audit row for the monthly +1 CL accrual.
 *
 * One row per (developer_id, credit_month). The UNIQUE KEY on that pair is what
 * makes the monthly credit duplicate-proof: the creditor inserts a row here
 * BEFORE bumping developer.cl_backup, so a second run (or a concurrent one) for
 * the same month hits the unique constraint and is skipped. credit_month is the
 * 'YYYY-MM' the CL was granted for.
 *
 * @property int    $id
 * @property int    $developer_id
 * @property string $credit_month  'YYYY-MM'
 * @property float  $cl_before
 * @property float  $cl_after
 * @property string $credited_at   'Y-m-d H:i:s'
 */
class MonthlyClCredit extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'monthly_cl_credit';
    }

    public function rules(): array
    {
        return [
            [['developer_id', 'credit_month', 'credited_at'], 'required'],
            [['developer_id'], 'integer'],
            [['cl_before', 'cl_after'], 'number'],
            [['credited_at'], 'safe'],
            [['credit_month'], 'string', 'max' => 7],
            [['developer_id', 'credit_month'], 'unique', 'targetAttribute' => ['developer_id', 'credit_month']],
        ];
    }
}
