<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: LeadFollowerAttachments model
 *
 * Table: lead_follower_attachments
 *
 * @property int    $id
 * @property int    $lead_follower_id
 * @property string $attachment_path
 * @property string $type
 * @property int    $created_by
 * @property int    $status
 */
class LeadFollowerAttachments extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'lead_follower_attachments';
    }

    public function rules(): array
    {
        return [
            [['lead_follower_id'], 'required'],
            [['lead_follower_id', 'created_by', 'status'], 'integer'],
            [['attachment_path', 'type'], 'string', 'max' => 255],
        ];
    }

    public function getFollower(): \yii\db\ActiveQuery
    {
        return $this->hasOne(LeadFollowers::class, ['id' => 'lead_follower_id']);
    }
}
