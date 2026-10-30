<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: CrmToken model
 * Stores API Bearer tokens for mobile/API authentication.
 *
 * Table: crm_token
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $token
 * @property int    $device_type   (1=iOS, 2=Android, 3=Web)
 * @property string $device_id
 * @property string $push_id
 * @property string $created_at
 * @property int    $status
 */
class CrmToken extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'crm_token';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'status'], 'required'],
            [['user_id', 'device_type', 'status'], 'integer'],
            [['created_at'], 'safe'],
            [['token', 'device_id', 'push_id'], 'string', 'max' => 255],
        ];
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'user_id']);
    }
}
