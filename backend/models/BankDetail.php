<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: bank_details
 *
 * @property int    $id
 * @property string $acc_name
 * @property string $acc_no
 * @property string $bank_name
 * @property string $branch
 * @property string $ifsc
 * @property string $swift
 * @property int    $is_default
 * @property int    $status
 */
class BankDetail extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'bank_details';
    }

    public function rules(): array
    {
        return [
            [['acc_name', 'acc_no', 'bank_name', 'branch', 'ifsc', 'swift'], 'trim'],
            [['acc_name', 'acc_no', 'bank_name', 'branch', 'ifsc', 'swift'], 'required'],
            [['is_default', 'status'], 'integer'],
            [['acc_name', 'bank_name', 'branch'], 'string', 'max' => 255],
            [['acc_no'], 'match', 'pattern' => '/^[0-9]{6,20}$/', 'message' => 'Account number must be 6–20 digits.'],
            [['ifsc'], 'match', 'pattern' => '/^[A-Z]{4}0[A-Z0-9]{6}$/', 'message' => 'IFSC must be 11 alphanumeric chars (e.g. SBIN0001234).'],
            [['swift'], 'match', 'pattern' => '/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', 'message' => 'SWIFT code must be 8 or 11 characters.'],
            [['acc_no', 'ifsc', 'swift'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'acc_name'   => 'Account Name',
            'acc_no'     => 'Account Number',
            'bank_name'  => 'Bank Name',
            'branch'     => 'Branch',
            'ifsc'       => 'IFSC Code',
            'swift'      => 'SWIFT Code',
            'is_default' => 'Default Account',
        ];
    }
}
