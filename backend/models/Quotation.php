<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: quotations
 *
 * @property int    $id
 * @property string $unique_id
 * @property string $client_name
 * @property string $email
 * @property string $mobile
 * @property string $project_name
 * @property int    $created_by
 * @property string $created_date
 * @property int    $status
 */
class Quotation extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'quotations';
    }

    public function rules(): array
    {
        return [
            [['client_name', 'email', 'project_name'], 'required'],
            [['client_name', 'email', 'mobile', 'project_name', 'unique_id'], 'string', 'max' => 255],
            [['created_by', 'status'], 'integer'],
            [['created_date'], 'safe'],
            ['email', 'email'],
            [['mobile'], 'match', 'pattern' => '/^[0-9]{7,15}$/', 'skipOnEmpty' => true, 'message' => 'Mobile must be 7–15 digits.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'unique_id'    => 'Quotation ID',
            'client_name'  => 'Client Name',
            'email'        => 'Email',
            'mobile'       => 'Mobile',
            'project_name' => 'Project Name',
            'created_date' => 'Created Date',
        ];
    }

    public function getDetails(): \yii\db\ActiveQuery
    {
        return $this->hasMany(QuotationDetail::class, ['quotation_id' => 'id'])
            ->where(['quotation_details.status' => 1])
            ->orderBy(['quotation_details.id' => SORT_DESC]);
    }

    public function getLatestDetail(): \yii\db\ActiveQuery
    {
        return $this->hasOne(QuotationDetail::class, ['quotation_id' => 'id'])
            ->where(['quotation_details.status' => 1])
            ->orderBy(['quotation_details.id' => SORT_DESC]);
    }

    public function getCreatedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getRemarks(): \yii\db\ActiveQuery
    {
        return $this->hasMany(QuotationRemark::class, ['quotation_id' => 'id'])
            ->where(['quotation_remarks.status' => 1])
            ->orderBy(['quotation_remarks.id' => SORT_ASC]);
    }
}
