<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: employee
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $employee_name
 * @property string $designation
 * @property string $address
 * @property string $mobile
 * @property string $email_id
 * @property string $access_menus
 * @property string $created_at
 * @property string $updated_at
 * @property int    $status
 */
class Employee extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'employee';
    }

    public function rules(): array
    {
        return [
            // Required fields — matches Yii1
            [['employee_name', 'designation', 'address', 'mobile', 'email_id'], 'required'],
            // Lengths
            [['employee_name', 'designation', 'email_id'], 'string', 'min' => 2, 'max' => 255],
            [['address'], 'string', 'min' => 5, 'max' => 255],
            [['mobile'], 'string', 'length' => 10],
            // Formats
            [['email_id'], 'email'],
            [['mobile'], 'match', 'pattern' => '/^[0-9]{10}$/', 'message' => 'Mobile must be exactly 10 digits.'],
            [['employee_name'], 'match', 'pattern' => "/^[A-Za-z .'\\-]+$/",
                'message' => 'Employee Name may contain only letters, spaces, dot, apostrophe and hyphen.'],
            // Mobile and email should be unique across active employees.
            [['mobile'], 'unique', 'filter' => ['status' => 1],
                'message' => 'This mobile number is already used by another employee.'],
            [['email_id'], 'unique', 'filter' => ['status' => 1],
                'message' => 'This email is already used by another employee.'],
            // access_menus defaults to empty string (column has no DB default)
            [['access_menus'], 'default', 'value' => ''],
            [['access_menus'], 'string'],
            // Programmatically set
            [['user_id', 'status'], 'integer'],
            [['user_id', 'status'], 'safe'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'employee_name' => 'Employee Name',
            'designation'   => 'Designation',
            'address'       => 'Address',
            'mobile'        => 'Mobile',
            'email_id'      => 'Email',
            'status'        => 'Status',
        ];
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'user_id']);
    }
}
