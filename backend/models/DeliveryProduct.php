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
        // Format / duplicate checks only on new or edited values, so legacy rows stay editable.
        $changed = fn(string $attr) => fn($m) => $m->isNewRecord || (string)$m->getOldAttribute($attr) !== (string)$m->$attr;

        return [
            [['item_name', 'hsn_code', 'units', 'serial_no'], 'trim'],
            [['item_name', 'hsn_code', 'units'], 'required'],
            [['created_at'], 'safe'],
            [['status'], 'integer'],
            [['item_name', 'units'], 'string', 'max' => 255],
            [['hsn_code'], 'string', 'max' => 50],
            [['serial_no'], 'string', 'max' => 100],
            [['hsn_code'], 'match', 'pattern' => '/^\d{4,8}$/', 'message' => 'HSN code is 4 to 8 digits (e.g. 90328990).', 'when' => $changed('hsn_code')],
            [['item_name'], 'validateUniqueName', 'when' => $changed('item_name')],
        ];
    }

    /** Names are unique among active products, ignoring case. */
    public function validateUniqueName(string $attr): void
    {
        $exists = self::find()->where(['status' => 1])->andWhere('LOWER(item_name) = :n', [':n' => mb_strtolower((string)$this->$attr)])
            ->andFilterWhere(['<>', 'id', $this->id])->exists();
        if ($exists) {
            $this->addError($attr, 'A product called "' . $this->$attr . '" already exists.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'item_name' => 'Product name',
            'hsn_code' => 'HSN code',
            'units' => 'Units',
            'created_at' => 'Created At',
            'status' => 'Status',
            'serial_no' => 'Serial number',
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
