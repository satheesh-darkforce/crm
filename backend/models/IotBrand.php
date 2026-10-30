<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $brand_name
 * @property int    $status
 */
class IotBrand extends ActiveRecord
{
    public static function tableName(): string { return 'iot_brand'; }

    public function rules(): array
    {
        return [
            [['brand_name'], 'required'],
            [['brand_name'], 'string', 'max' => 250],
            [['status'], 'integer'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'brand_name' => 'Brand Name',
            'status' => 'Status',
        ];
    }
}
