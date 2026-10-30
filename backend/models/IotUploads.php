<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $product_id
 * @property string $type
 * @property int    $upload_type
 * @property string $path
 * @property string $created_at
 * @property int    $created_by
 * @property string $updated_at
 * @property int    $updated_by
 * @property int    $status
 */
class IotUploads extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'iot_uploads';
    }

    public function rules(): array
    {
        return [
            [['product_id'], 'required'],
            [['product_id', 'upload_type', 'created_by', 'updated_by', 'status'], 'integer'],
            [['type'], 'string', 'max' => 50],
            [['path', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'product_id' => 'Product',
            'type' => 'File Type',
            'path' => 'File Path',
            'created_at' => 'Uploaded At',
            'created_by' => 'Uploaded By',
            'updated_at' => 'Updated At',
            'updated_by' => 'Updated By',
            'status' => 'Status',
        ];
    }

    public function getProduct(): \yii\db\ActiveQuery
    {
        return $this->hasOne(IotProduct::class, ['id' => 'product_id']);
    }

    public function getCreatedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getUpdatedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'updated_by']);
    }
}
