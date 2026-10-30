<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Domain follow-up log entry.
 *
 * followup_status: 1 = Open (sets domain_status → 3 "Need Follow"), 2 = Closed (sets domain_status → 1 "Live")
 *
 * @property int    $id
 * @property int    $domain_id
 * @property string $contacted_date
 * @property string $description
 * @property string $next_followup
 * @property int    $followup_status
 * @property int    $created_by
 * @property string $created_at
 * @property int    $updated_by
 * @property string $updated_at
 */
class DomainFollowUp extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'follow_up';
    }

    public function rules(): array
    {
        return [
            [['domain_id', 'contacted_date', 'description', 'followup_status'], 'required'],
            [['domain_id', 'followup_status', 'created_by', 'updated_by'], 'integer'],
            [['description'], 'string'],
            [['contacted_date', 'next_followup', 'created_at', 'updated_at'], 'safe'],
            ['next_followup', 'compare', 'compareAttribute' => 'contacted_date',
                'operator' => '>=', 'type' => 'string',
                'message' => 'Next follow-up date cannot be before contacted date.',
                'skipOnEmpty' => true,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'contacted_date'  => 'Contacted Date',
            'description'     => 'Description / Notes',
            'next_followup'   => 'Next Follow-up Date',
            'followup_status' => 'Follow-up Status',
        ];
    }

    public static function statusLabels(): array
    {
        return [1 => 'Open', 2 => 'Closed'];
    }

    public function getDomain(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Domain::class, ['id' => 'domain_id']);
    }

    public function getCreatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }
}
