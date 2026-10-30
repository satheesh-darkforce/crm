<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $loc
 * @property string $modified
 * @property int    $status
 */
class AssetLocation extends ActiveRecord
{
    public static function tableName(): string { return 'assets_loc'; }

    public function rules(): array
    {
        return [
            [['loc', 'modified'], 'required'],
            [['status'], 'integer'],
            [['loc'], 'string', 'max' => 250],
            [['loc'], 'uniqueWhenNotDeleted'],
            [['modified'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'       => 'ID',
            'loc'      => 'Location Name',
            'modified' => 'Modified',
            'status'   => 'Status',
        ];
    }

    public function uniqueWhenNotDeleted(string $attribute): void
    {
        $query = static::find()
            ->where(['loc' => $this->loc, 'status' => 1])
            ->andFilterWhere(['!=', 'id', $this->id]);
        if ($query->exists()) {
            $this->addError($attribute, 'The Location Name already exists.');
        }
    }
}
