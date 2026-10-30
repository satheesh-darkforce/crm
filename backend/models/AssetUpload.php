<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $asset_id
 * @property string $type        file extension
 * @property int    $upload_type 1=Bill, 2=Warranty, 3=AMC, 4=Product Image
 * @property string $path        relative file path
 * @property string $modified
 * @property int    $status
 */
class AssetUpload extends ActiveRecord
{
    public static function tableName(): string { return 'assets_uploads'; }

    public function rules(): array
    {
        return [
            [['asset_id', 'modified'], 'required'],
            [['asset_id', 'status', 'upload_type'], 'integer'],
            [['type'], 'string', 'max' => 100],
            [['path', 'modified'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'          => 'ID',
            'asset_id'    => 'Asset',
            'type'        => 'File Type',
            'upload_type' => 'Upload Type',
            'path'        => 'File Path',
            'modified'    => 'Modified',
            'status'      => 'Status',
        ];
    }

    public static function uploadTypeLabels(): array
    {
        return [
            1 => 'Purchase Bill',
            2 => 'Warranty Document',
            3 => 'AMC Document',
            4 => 'Product Image',
        ];
    }

    public function getAsset(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Asset::class, ['id' => 'asset_id']);
    }
}
