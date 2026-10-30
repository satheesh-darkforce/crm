<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $category_id
 * @property string $name
 * @property string $created_at
 * @property int    $created_by
 * @property string $updated_at
 * @property int    $updated_by
 * @property int    $status
 */
class IotSubcategory extends ActiveRecord
{
    public static function tableName(): string { return 'iot_subcategory'; }

    public function rules(): array
    {
        return [
            [['name', 'category_id'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['category_id', 'created_by', 'updated_by', 'status'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Sub Category Name',
            'category_id' => 'Category',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
            'updated_at' => 'Updated At',
            'updated_by' => 'Updated By',
            'status' => 'Status',
        ];
    }

    public function getCategory(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotCategory::class, ['id' => 'category_id']);
    }

    public function getProducts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(IotProduct::class, ['subcat_id' => 'id']);
    }
}
