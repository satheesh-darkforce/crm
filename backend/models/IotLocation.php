<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $location
 * @property string $created_at
 * @property int    $created_by
 * @property string $updated_at
 * @property int    $updated_by
 * @property int    $status
 */
class IotLocation extends ActiveRecord
{
    public static function tableName(): string { return 'iot_location'; }

    public function rules(): array
    {
        return [
            [['location'], 'required'],
            [['location'], 'string', 'max' => 250],
            [['created_by', 'updated_by', 'status'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'location' => 'Location Name',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
            'updated_at' => 'Updated At',
            'updated_by' => 'Updated By',
            'status' => 'Status',
        ];
    }

    public function getProducts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(IotProduct::class, ['location_id' => 'id']);
    }
}
