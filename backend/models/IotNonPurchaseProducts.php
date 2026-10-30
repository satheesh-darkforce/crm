<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $non_purchase_id
 * @property int    $product_id
 * @property int    $quantity
 * @property string $created_at
 * @property int    $status
 */
class IotNonPurchaseProducts extends ActiveRecord
{
    public static function tableName(): string { return 'iot_non_purchase_products'; }

    public function rules(): array
    {
        return [
            [['non_purchase_id', 'product_id', 'quantity'], 'required'],
            [['non_purchase_id', 'product_id', 'quantity', 'status'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'non_purchase_id' => 'Non-Purchase ID',
            'product_id' => 'Product',
            'quantity' => 'Quantity',
            'created_at' => 'Created At',
            'status' => 'Status',
        ];
    }

    public function getNonPurchase(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotNonPurchase::class, ['id' => 'non_purchase_id']);
    }

    public function getProduct(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotProduct::class, ['id' => 'product_id']);
    }
}
