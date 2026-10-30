<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $asset_id       JSON array of asset IDs
 * @property int    $dev_id
 * @property string $in_date
 * @property string $out_date
 * @property string $asset_location
 * @property int    $asset_condition 1=Good, 2=Damaged, 3=Lost
 * @property string $description
 * @property int    $status
 */
class AssetHandover extends ActiveRecord
{
    public static function tableName(): string { return 'assets_handover'; }

    public function rules(): array
    {
        return [
            [['asset_id', 'dev_id', 'in_date', 'asset_location'], 'required'],
            [['dev_id', 'asset_condition', 'status'], 'integer'],
            [['in_date', 'out_date'], 'safe'],
            [['asset_location', 'description'], 'string', 'max' => 255],
            // asset_condition required when recording return (out_date is filled)
            ['asset_condition', 'required',
                'when' => fn($m) => !empty($m->out_date),
                'message' => 'Condition is required when recording return.',
            ],
            ['asset_condition', 'in', 'range' => [1, 2, 3]],
            ['out_date', 'compareDates'],
        ];
    }

    public function compareDates(string $attribute): void
    {
        if ($this->out_date && $this->in_date) {
            if (strtotime($this->out_date) < strtotime($this->in_date)) {
                $this->addError('out_date', 'Return date must be on or after the given date.');
            }
        }
    }

    public function attributeLabels(): array
    {
        return [
            'asset_id'       => 'Assets',
            'dev_id'         => 'Employee',
            'in_date'        => 'Given Date',
            'out_date'       => 'Return Date',
            'asset_location' => 'Asset Location',
            'asset_condition'=> 'Condition on Return',
            'description'    => 'Notes',
        ];
    }

    public static function conditionLabels(): array
    {
        return [1 => 'Good', 2 => 'Minor Damage', 3 => 'Major Damage'];
    }

    public function getDecodedAssetIds(): array
    {
        if (is_array($this->asset_id)) {
            return array_values(array_filter(array_map('intval', $this->asset_id)));
        }
        $ids = json_decode((string)$this->asset_id, true);
        return is_array($ids) ? $ids : [];
    }

    public function getAsset(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Asset::class, ['id' => 'asset_id']);
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'dev_id']);
    }
}
