<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: LeadMaster — prospect / pre-lead contact list.
 *
 * Table: lead_master
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
    public static function tableName(): string
    {
        return 'lead_master';
    }

    public function rules(): array
    {
        return [
            [['account_name', 'first_name', 'email_id', 'phone_number'], 'required'],
            [['created_by', 'updated_by', 'status'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['account_name'], 'string', 'max' => 200],
            [['first_name', 'last_name', 'email_id', 'phone_number', 'city'], 'string', 'max' => 100],
            [['designation'], 'string', 'max' => 250],
            [['email_id'], 'email'],
            [['phone_number'], 'match', 'pattern' => '/^[0-9]{10}$/', 'message' => 'Phone number must be exactly 10 digits.'],
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
            'status'       => 'Status',
        ];
    }

    public function getFullName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
