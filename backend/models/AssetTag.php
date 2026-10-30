<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $name
 * @property int    $status
 * @property int    $modified_by
 * @property string $modified
 */
class AssetTag extends ActiveRecord
{
    public static function tableName(): string { return 'assets_tags'; }

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
            'name'        => 'Tag Name',
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
            $this->addError($attribute, 'Tag Name already exists.');
        }
    }
}
