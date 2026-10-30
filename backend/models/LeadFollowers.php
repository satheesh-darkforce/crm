<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Lead followers / follow-up entries
 *
 * Table: lead_followers
 *
 * followup_status: 1=Active (upcoming), 0=Done
 * read_status:     1=Unread, 0=Read
 *
 * @property int    $id
 * @property int    $lead_id
 * @property string $follow_up_date
 * @property string $follow_up_time
 * @property string $subject
 * @property string $description
 * @property int    $assigned_to
 * @property int    $mode
 * @property string $other_mode
 * @property int    $created_by
 * @property string $created_date
 * @property int    $read_status
 * @property int    $followup_status
 * @property int    $status
 */
class LeadFollowers extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'lead_followers';
    }

    public function rules(): array
    {
        return [
            [['lead_id', 'status'], 'required'],
            [['lead_id', 'assigned_to', 'mode', 'created_by', 'read_status', 'followup_status', 'status'], 'integer'],
            [['description'], 'string'],
            [['follow_up_date', 'created_date'], 'safe'],
            [['follow_up_time'], 'string', 'max' => 10],
            [['subject', 'other_mode'], 'string', 'max' => 255],
        ];
    }

    public function getLead(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Lead::class, ['id' => 'lead_id']);
    }

    public function getAssignedTo(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'assigned_to']);
    }

    public function getCreatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }
}
