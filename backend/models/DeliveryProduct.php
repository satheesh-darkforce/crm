<?php

namespace backend\models;

use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;

/**
 * ActiveRecord for `delivery_product_details` — RENAMED on migration from
 * delivery's `product_details`. Product catalogue used by the Delivery
 * Challan module.
 *
 * @property int         $id
 * @property string|null $serial_no
 * @property string      $item_name
 * @property string      $hsn_code
 * @property string      $units
 * @property string      $created_at
 * @property int         $status      0=inactive, 1=active
 */
class DeliveryProduct extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'delivery_product_details';
    }

    public function rules(): array
    {
        return [
            [['item_name', 'hsn_code', 'units'], 'required'],
            [['created_at', 'serial_no'], 'safe'],
            [['status'], 'integer'],
            [['item_name', 'units'], 'string', 'max' => 255],
            [['hsn_code'], 'string', 'max' => 50],
            [
                ['item_name', 'hsn_code'], 'unique',
                'targetAttribute' => ['item_name', 'hsn_code'],
                'filter' => fn($q) => $q->andWhere(['status' => 1]),
                'message' => 'Item Name + HSN Code already exists for an active record.',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'item_name' => 'Item Name',
            'hsn_code' => 'HSN Code',
            'units' => 'Units',
            'created_at' => 'Created At',
            'status' => 'Status',
            'serial_no' => 'Serial Number',
        ];
    }

    public function getAllProducts(?int $id = null): ActiveDataProvider
    {
        $query = self::find()->where(['status' => 1])->andFilterWhere(['id' => $id]);

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 10],
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
        ]);
    }
}
