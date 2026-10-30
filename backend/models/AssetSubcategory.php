<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $name
 * @property int    $category_id
 * @property string $modified
 * @property int    $modified_by
 * @property int    $status
 */
class AssetSubcategory extends ActiveRecord
{
    public static function tableName(): string { return 'assets_subcategory'; }

    public function rules(): array
    {
        return [
            [['name', 'category_id', 'modified_by', 'modified'], 'required'],
            [['category_id', 'status', 'modified_by'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['name'], 'uniqueWhenNotDeleted'],
            [['modified'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'          => 'ID',
            'name'        => 'Subcategory Name',
            'category_id' => 'Category',
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
            $this->addError($attribute, 'The Sub category name already exists.');
        }
    }

    public function getCategory(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AssetCategory::class, ['id' => 'category_id']);
    }
}
