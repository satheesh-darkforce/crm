<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: bank_details — accounts printed on invoices as "Payment bank info".
 *
 * @property int    $id
 * @property string $acc_name
 * @property string $acc_no
 * @property string $bank_name
 * @property string $branch
 * @property string $ifsc
 * @property string $swift
 * @property int    $is_default  1 = listed first and pre-selected on new invoices
 * @property int    $status      0 = removed (soft delete)
 */
class BankDetail extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'bank_details';
    }

    public function rules(): array
    {
        // Format and duplicate checks run only on new or edited values, so legacy rows stay editable.
        $changed = fn(string $attr) => fn($m) => $m->isNewRecord || (string)$m->getOldAttribute($attr) !== (string)$m->$attr;

        return [
            [['acc_name', 'acc_no', 'bank_name', 'branch', 'ifsc', 'swift'], 'trim'],
            // People paste "0341 0900 0177 994" or "sbin0001234".
            ['acc_no', 'filter', 'filter' => fn($v) => preg_replace('/[\s-]+/', '', (string)$v)],
            [['ifsc', 'swift'], 'filter', 'filter' => fn($v) => strtoupper(preg_replace('/\s+/', '', (string)$v))],
            [['acc_name', 'acc_no', 'bank_name', 'branch', 'ifsc', 'swift'], 'required'],
            [['is_default'], 'boolean'],
            [['status'], 'integer'],
            [['acc_name', 'bank_name', 'branch'], 'string', 'max' => 255],
            [['acc_no', 'ifsc', 'swift'], 'string', 'max' => 50],
            [['acc_no'], 'match', 'pattern' => '/^[0-9]{6,20}$/', 'message' => 'Account number must be 6–20 digits.', 'when' => $changed('acc_no')],
            [['ifsc'], 'match', 'pattern' => '/^[A-Z]{4}0[A-Z0-9]{6}$/', 'message' => 'IFSC is 11 characters: 4 letters, a zero, then 6 letters or digits (e.g. SBIN0001234).', 'when' => $changed('ifsc')],
            [['swift'], 'match', 'pattern' => '/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', 'message' => 'SWIFT code is 8 or 11 letters/digits (e.g. HDFCINBB).', 'when' => $changed('swift')],
            [['acc_no'], 'unique', 'filter' => ['status' => 1], 'message' => 'This account number is already saved.', 'when' => $changed('acc_no')],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'acc_name'   => 'Account name',
            'acc_no'     => 'Account number',
            'bank_name'  => 'Bank name',
            'branch'     => 'Branch',
            'ifsc'       => 'IFSC code',
            'swift'      => 'SWIFT code',
            'is_default' => 'Default account',
        ];
    }

    /** Active invoices that print this account. */
    public function getInvoiceCount(): int
    {
        return (int)Invoice::find()->where(['bank_id' => $this->id, 'status' => 1])->count();
    }

    public static function defaultId(): ?int
    {
        $id = self::find()->select('id')->where(['status' => 1, 'is_default' => 1])->scalar();
        return $id ? (int)$id : null;
    }
}
