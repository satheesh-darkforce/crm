<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * ActiveRecord for `dc_products` (line items on a delivery challan).
 *
 * @property int         $id
 * @property int         $dc_id
 * @property int         $product_id
 * @property int|null    $quantity
 * @property string|null $serial_nos
 * @property string      $created_at
 * @property int         $status       -1=deleted, 0=inactive, 1=active
 *
 * @property DcDetail        $dc
 * @property DeliveryProduct $product
 */
class DcProduct extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'dc_products';
    }

    public function rules(): array
    {
        return [
            [['dc_id', 'product_id', 'status', 'quantity'], 'integer'],
            [['serial_nos'], 'string'],
            [['created_at'], 'safe'],
            [['dc_id'],      'exist', 'skipOnError' => true, 'targetClass' => DcDetail::class,        'targetAttribute' => ['dc_id' => 'id']],
            [['product_id'], 'exist', 'skipOnError' => true, 'targetClass' => DeliveryProduct::class, 'targetAttribute' => ['product_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'dc_id' => 'Dc ID',
            'product_id' => 'Product',
            'quantity' => 'Quantity',
            'serial_nos' => 'Serial Numbers',
            'created_at' => 'Created At',
            'status' => 'Status',
        ];
    }

    public function getDc(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DcDetail::class, ['id' => 'dc_id']);
    }

    public function getProduct(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DeliveryProduct::class, ['id' => 'product_id']);
    }
}
