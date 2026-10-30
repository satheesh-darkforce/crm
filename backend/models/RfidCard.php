<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: rfid_cards
 *
 * @property int    $id
 * @property string $code
 * @property int    $developer_id
 * @property string $assigned_from
 * @property string $created_at
 * @property int    $created_by
 * @property string $updated_at
 * @property int    $updated_by
 * @property int    $status        1=Active, 0=Inactive, -1=Deleted
 */
class RfidCard extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'rfid_cards';
    }

    public function rules(): array
    {
        return [
            [['code'], 'required'],
            [['code'], 'trim'],
            [['code'], 'string', 'max' => 100],
            // Uniqueness must ignore soft-deleted rows (status = -1) so a code
            // freed by deletion can be re-issued.
            [['code'], 'unique', 'filter' => ['!=', 'status', -1],
                'message' => 'This RFID code is already in use.'],
            [['developer_id', 'created_by', 'updated_by', 'status'], 'integer'],
            [['assigned_from', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'code'          => 'RFID Code',
            'developer_id'  => 'Assigned To',
            'assigned_from' => 'Assigned From',
        ];
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }

    public function isAssigned(): bool
    {
        return $this->developer_id !== null;
    }
}
