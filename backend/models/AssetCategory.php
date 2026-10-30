<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $name
 * @property string $modified
 * @property int    $modified_by
 * @property int    $status
 */
class AssetCategory extends ActiveRecord
{
    public static function tableName(): string { return 'assets_category'; }

    public function rules(): array
    {
        return [
            [['name', 'modified_by', 'modified'], 'required'],
            [['status', 'modified_by'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['name'], 'uniqueWhenNotDeleted'],
            [['modified'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'          => 'ID',
            'name'        => 'Category Name',
            'status'      => 'Status',
            'modified_by' => 'Modified By',
            'modified'    => 'Modified',
        ];
    }

    public function uniqueWhenNotDeleted(string $attribute): void
    {
        $query = static::find()
            ->where(['name' => $this->name, 'status' => 1])
            ->andFilterWhere(['!=', 'id', $this->id]);
        if ($query->exists()) {
            $this->addError($attribute, 'The Category name already exists.');
        }
    }

    public function getSubcategories(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AssetSubcategory::class, ['category_id' => 'id']);
    }
}
