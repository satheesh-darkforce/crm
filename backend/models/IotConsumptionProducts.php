<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $consumption_id
 * @property int    $product_id
 * @property int    $quantity
 * @property string $returned_quantity
 * @property int    $status
 */
class IotConsumptionProducts extends ActiveRecord
{
    public static function tableName(): string { return 'iot_consumption_products'; }

    public function rules(): array
    {
        return [
            [['consumption_id', 'product_id', 'quantity'], 'required'],
            [['consumption_id', 'product_id', 'quantity', 'status'], 'integer'],
            [['returned_quantity'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'consumption_id' => 'Consumption Request',
            'product_id' => 'Product',
            'quantity' => 'Issued Quantity',
            'returned_quantity' => 'Returned Quantity',
            'status' => 'Status',
        ];
    }

    public function getConsumption(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotConsumption::class, ['id' => 'consumption_id']);
    }

    public function getProduct(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotProduct::class, ['id' => 'product_id']);
    }
}
