<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $brand_name
 * @property string $modified
 * @property int    $status
 */
class AssetBrand extends ActiveRecord
{
    public static function tableName(): string { return 'assets_brand'; }

    public function rules(): array
    {
        return [
            [['brand_name', 'modified'], 'required'],
            [['status'], 'integer'],
            [['brand_name'], 'string', 'max' => 250],
            [['brand_name'], 'uniqueWhenNotDeleted'],
            [['modified'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'         => 'ID',
            'brand_name' => 'Brand Name',
            'modified'   => 'Modified',
            'status'     => 'Status',
        ];
    }

    public function uniqueWhenNotDeleted(string $attribute): void
    {
        $query = static::find()
            ->where(['brand_name' => $this->brand_name, 'status' => 1])
            ->andFilterWhere(['!=', 'id', $this->id]);
        if ($query->exists()) {
            $this->addError($attribute, 'The Brand name already exists.');
        }
    }
}
