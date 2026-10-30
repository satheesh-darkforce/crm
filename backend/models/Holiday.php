<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: holidays_setup
 *
 * @property int    $id
 * @property string $date
 * @property string $reason
 * @property string $employee_id
 * @property int    $status
 */
class Holiday extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'holidays_setup';
    }

    public function rules(): array
    {
        return [
            [['date', 'reason'], 'required'],
            [['date'], 'date', 'format' => 'php:Y-m-d'],
            [['date'], 'unique', 'filter' => ['status' => 1], 'message' => 'A holiday already exists on this date.'],
            [['reason'], 'trim'],
            [['reason'], 'string', 'max' => 255],
            [['employee_id'], 'safe'],
            [['status'], 'integer'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'date'        => 'Date',
            'reason'      => 'Reason',
            'employee_id' => 'Employee',
        ];
    }
}
