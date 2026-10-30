<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $name
 * @property int    $status
 */
class IotCategory extends ActiveRecord
{
    public static function tableName(): string { return 'iot_category'; }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['status'], 'integer'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Category Name',
            'status' => 'Status',
        ];
    }

    public function getSubcategories(): \yii\db\ActiveQuery
    {
        return $this->hasMany(IotSubcategory::class, ['category_id' => 'id']);
    }

    public function getProducts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(IotProduct::class, ['cat_id' => 'id']);
    }
}
