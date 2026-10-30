<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $project_id
 * @property int    $created_by
 * @property string $created_at
 * @property int    $status
 */
class IotNonPurchase extends ActiveRecord
{
    public static function tableName(): string { return 'iot_non_purchase'; }

    public function rules(): array
    {
        return [
            [['project_id'], 'required'],
            [['project_id', 'created_by', 'status'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'project_id' => 'Project',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
            'status' => 'Status',
        ];
    }

    public function getProject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'created_by']);
    }

    public function getCreatedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getItems(): \yii\db\ActiveQuery
    {
        return $this->hasMany(IotNonPurchaseProducts::class, ['non_purchase_id' => 'id']);
    }
}
