<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: LeadAttachments model
 *
 * Table: lead_attachments
 *
 * @property int    $id
 * @property int    $lead_id
 * @property string $attachment_path
 * @property string $type
 * @property int    $created_by
 * @property int    $status
 */
class LeadAttachments extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'lead_attachments';
    }

    public function rules(): array
    {
        return [
            [['lead_id'], 'required'],
            [['lead_id', 'created_by', 'status'], 'integer'],
            [['attachment_path', 'type'], 'string', 'max' => 255],
        ];
    }

    public function getLead(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Lead::class, ['id' => 'lead_id']);
    }

    public function getCreatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }
}
