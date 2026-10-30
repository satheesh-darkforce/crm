<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $product_id
 * @property int    $quantity
 * @property string $type
 * @property string $particular
 * @property string $price_per_qty
 * @property string $created_at
 * @property int    $status
 */
class IotReturnProductHistory extends ActiveRecord
{
    public static function tableName(): string { return 'iot_return_product_history'; }

    public function rules(): array
    {
        return [
            [['product_id', 'quantity', 'created_at'], 'required'],
            [['product_id', 'quantity', 'status'], 'integer'],
            [['type', 'particular'], 'string', 'max' => 255],
            [['price_per_qty'], 'number', 'min' => 0],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'product_id' => 'Product',
            'quantity' => 'Quantity',
            'type' => 'Type',
            'particular' => 'Particular',
            'price_per_qty' => 'Price Per Qty',
            'created_at' => 'Created At',
            'status' => 'Status',
        ];
    }

    public function getProduct(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotProduct::class, ['id' => 'product_id']);
    }
}
