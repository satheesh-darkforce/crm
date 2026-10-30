<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: LeaveSettings model
 *
 * Table: leave_settings
 *
 * @property int    $id
 * @property int    $no_of_permissions
 * @property string $duration
 * @property int    $check_in_grace_time
 * @property int    $monthly_allowed_cl
 * @property string $created_date
 * @property int    $created_by
 * @property string $updated_date
 * @property int    $updated_by
 * @property int    $status
 */
class LeaveSettings extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'leave_settings';
    }

    public function rules(): array
    {
        return [
            [['no_of_permissions', 'duration', 'check_in_grace_time', 'monthly_allowed_cl'], 'required'],
            [['status', 'created_by', 'updated_by'], 'integer'],
            [['no_of_permissions'], 'integer', 'min' => 1, 'max' => 20],
            [['monthly_allowed_cl'], 'integer', 'min' => 1, 'max' => 10],
            [['check_in_grace_time'], 'integer', 'min' => 0, 'max' => 120],
            [['duration'], 'string', 'max' => 20],
            [['created_date', 'updated_date'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'                  => 'ID',
            'no_of_permissions'   => 'No. of Permissions Allowed Per Month',
            'duration'            => 'Duration Per Permission (in hours)',
            'check_in_grace_time' => 'Check-in Grace Time (in minutes)',
            'monthly_allowed_cl'  => 'Monthly Allowed CL',
            'created_date'        => 'Created Date',
            'created_by'          => 'Created By',
            'updated_date'        => 'Updated Date',
            'updated_by'          => 'Updated By',
            'status'              => 'Status',
        ];
    }

    public function getCreatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'created_by']);
    }

    public function getUpdatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'updated_by']);
    }
}
