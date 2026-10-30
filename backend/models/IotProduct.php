<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $vendor_id
 * @property int    $brand_id
 * @property int    $cat_id
 * @property int    $subcat_id
 * @property int    $location_id
 * @property string $product_code
 * @property string $product_name
 * @property string $part_number
 * @property int    $stock_qty
 * @property int    $opening_stock_qty
 * @property string $tags
 * @property string $service_contact
 * @property string $description
 * @property string $created_at
 * @property int    $created_by
 * @property string $updated_at
 * @property int    $updated_by
 * @property int    $status
 */
class IotProduct extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'iot_products';
    }

    public function rules(): array
    {
        return [
            [['product_code', 'product_name', 'part_number', 'cat_id', 'subcat_id', 'stock_qty'], 'required'],
            [['product_code'], 'unique', 'message' => 'Product code already exists.'],
            [['brand_id', 'cat_id', 'subcat_id', 'location_id', 'stock_qty', 'opening_stock_qty', 'created_by', 'updated_by', 'status', 'vendor_id'], 'integer'],
            [['stock_qty'], 'integer', 'min' => 0, 'message' => 'Stock qty cannot be negative.'],
            [['product_code', 'product_name', 'part_number', 'service_contact'], 'string', 'max' => 250],
            [['tags', 'description', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'product_name'  => 'Product Name',
            'product_code'  => 'Product Code',
            'part_number'   => 'Part Number',
            'brand_id'      => 'Brand',
            'cat_id'        => 'Category',
            'subcat_id'     => 'Sub Category',
            'location_id'   => 'Location',
            'stock_qty'     => 'Stock Qty',
        ];
    }

    public function getBrand(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotBrand::class, ['id' => 'brand_id']);
    }

    public function getCategory(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotCategory::class, ['id' => 'cat_id']);
    }

    public function getSubcategory(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotSubcategory::class, ['id' => 'subcat_id']);
    }

    public function getLocation(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotLocation::class, ['id' => 'location_id']);
    }

    public function getVendor(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Vendor::class, ['id' => 'vendor_id']);
    }

    /**
     * Returns comma-separated tag names from stored tag IDs.
     */
    public function getTagNames(): string
    {
        if (empty($this->tags)) {
            return '';
        }
        $ids = array_filter(explode(',', $this->tags));
        if (empty($ids)) {
            return '';
        }
        $names = IotTags::find()
            ->select('name')
            ->where(['id' => $ids, 'status' => 1])
            ->column();
        return implode(', ', $names);
    }
}
