<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $quotation_id
 * @property string $remarks
 * @property int    $created_by
 * @property string $created_date
 * @property int    $status
 */
class QuotationRemark extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'quotation_remarks';
    }

    public function rules(): array
    {
        return [
            [['quotation_id', 'remarks'], 'required'],
            [['quotation_id', 'created_by', 'status'], 'integer'],
            [['remarks'], 'string'],
            [['created_date'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return ['remarks' => 'Remarks'];
    }

    public function getQuotation(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Quotation::class, ['id' => 'quotation_id']);
    }

    public function getCreatedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }
}
