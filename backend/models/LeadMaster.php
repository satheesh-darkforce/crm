<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: LeadMaster — prospect / pre-lead contact list.
 *
 * Table: lead_master
 *
 * status: 1=Active, 0=Inactive, -1=Deleted (soft delete; also set once the
 *         prospect has been converted into a Lead)
 *
 * @property int    $id
 * @property string $account_name
 * @property string $first_name
 * @property string $last_name
 * @property string $designation
 * @property string $email_id
 * @property string $phone_number
 * @property string $city
 * @property string $created_at
 * @property int    $created_by
 * @property string $updated_at
 * @property int    $updated_by
 * @property int    $status
 */
class LeadMaster extends ActiveRecord
{
    const STATUS_DELETED  = -1;
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE   = 1;

    public static function tableName(): string
    {
        return 'lead_master';
    }

    public function rules(): array
    {
        return [
            [['account_name', 'first_name', 'last_name', 'designation', 'email_id', 'phone_number', 'city'], 'trim'],
            [['account_name', 'first_name', 'email_id', 'phone_number'], 'required'],
            [['email_id'], 'email'],
            // Also stops a re-uploaded sheet from duplicating prospects. Only checked
            // for new / changed emails so legacy duplicates stay editable.
            [['email_id'], 'unique', 'filter' => ['!=', 'status', self::STATUS_DELETED],
                'when' => fn($m) => $m->isNewRecord
                    || strtolower(trim((string)$m->getOldAttribute('email_id'))) !== strtolower((string)$m->email_id),
                'message' => 'A prospect with this email already exists.'],
            [['phone_number'], 'match', 'pattern' => Lead::PHONE_PATTERN, 'message' => 'Phone Number must be a valid phone number.'],
            [['status'], 'in', 'range' => [self::STATUS_DELETED, self::STATUS_INACTIVE, self::STATUS_ACTIVE]],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['account_name'], 'string', 'max' => 200],
            [['first_name', 'last_name', 'email_id', 'phone_number', 'city'], 'string', 'max' => 100],
            [['designation'], 'string', 'max' => 250],
            [['created_at', 'created_by', 'updated_at', 'updated_by'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'           => 'ID',
            'account_name' => 'Account Name',
            'first_name'   => 'First Name',
            'last_name'    => 'Last Name',
            'designation'  => 'Designation',
            'email_id'     => 'Email',
            'phone_number' => 'Phone Number',
            'city'         => 'City',
            'created_at'   => 'Created At',
            'created_by'   => 'Created By',
            'updated_at'   => 'Updated At',
            'updated_by'   => 'Updated By',
            'status'       => 'Status',
        ];
    }

    public static function statusOptions(): array
    {
        return [self::STATUS_ACTIVE => 'Active', self::STATUS_INACTIVE => 'Inactive'];
    }

    public function getCreatedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }

    public function getFullName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
