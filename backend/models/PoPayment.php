<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * ActiveRecord for `po_payment` — payment receipts received against a PO.
 *
 * @property int    $id
 * @property int    $po_id         FK -> po_details.id
 * @property string $payment_date
 * @property string $amount
 * @property int    $mode          1=Cash, 2=UPI, 3=Bank Transfer, 4=Cheque
 * @property string $note
 * @property string $created_at
 * @property int    $status        0=inactive, 1=active
 *
 * @property PoDetail $po
 */
class PoPayment extends ActiveRecord
{
    public const MODE_CASH          = 1;
    public const MODE_UPI           = 2;
    public const MODE_BANK_TRANSFER = 3;
    public const MODE_CHEQUE        = 4;

    public static function modeOptions(): array
    {
        return [
            self::MODE_CASH          => 'Cash',
            self::MODE_UPI           => 'UPI',
            self::MODE_BANK_TRANSFER => 'Bank Transfer',
            self::MODE_CHEQUE        => 'Cheque',
        ];
    }

    public static function tableName(): string
    {
        return 'po_payment';
    }

    public function rules(): array
    {
        return [
            [['po_id', 'payment_date', 'amount', 'mode'], 'required'],
            [['po_id', 'mode', 'status'], 'integer'],
            [['payment_date', 'created_at'], 'safe'],
            [['amount'], 'string', 'max' => 20],
            [['amount'], 'match', 'pattern' => '/^\d+(\.\d+)?$/', 'message' => 'Amount must be a valid number.'],
            [['note'], 'string', 'max' => 500],
            [['note'], 'default', 'value' => ''],
            [['mode'], 'in', 'range' => array_keys(self::modeOptions())],
            [['po_id'], 'exist', 'skipOnError' => true,
             'targetClass' => PoDetail::class, 'targetAttribute' => ['po_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'           => 'ID',
            'po_id'        => 'PO',
            'payment_date' => 'Payment Date',
            'amount'       => 'Amount (₹)',
            'mode'         => 'Mode of Payment',
            'note'         => 'Note',
            'created_at'   => 'Created At',
            'status'       => 'Status',
        ];
    }

    public function getModeLabel(): string
    {
        return self::modeOptions()[$this->mode] ?? '—';
    }

    public function getPo(): \yii\db\ActiveQuery
    {
        return $this->hasOne(PoDetail::class, ['id' => 'po_id']);
    }
}
