<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $invoice_id
 * @property string $receipt_no
 * @property int    $receipt_unique_no
 * @property string $receipt_date
 * @property float  $receipt_amount
 * @property float  $pending_amount
 * @property float  $paid_amount
 * @property float  $balance_amount
 * @property int    $payment_mode     1=Cash, 2=Cheque, 3=Bank, 4=PayPal, 5=Others
 * @property string $cash_date
 * @property string $cheque_sender_bank
 * @property string $cheque_no
 * @property string $cheque_date
 * @property float  $cheque_amount
 * @property string $cheque_received_bank
 * @property string $cheque_received_ac_no
 * @property string $cheque_deposit_date
 * @property string $bank_sender_bank
 * @property string $bank_ref_no
 * @property float  $bank_amount
 * @property string $bank_date
 * @property string $bank_received_bank
 * @property string $bank_received_ac_no
 * @property string $paypal_ref_no
 * @property float  $paypal_amount
 * @property string $paypal_payment_date
 * @property string $payment_remarks
 * @property string $created_date
 * @property int    $created_by
 * @property int    $status
 * @property int    $deleted_by
 * @property string $deleted_at
 */
class InvoiceReceipt extends ActiveRecord
{
    const MODE_CASH   = 1;
    const MODE_CHEQUE = 2;
    const MODE_BANK   = 3;
    const MODE_PAYPAL = 4;
    const MODE_OTHERS = 5;

    public static function tableName(): string
    {
        return 'invoice_receipt';
    }

    public function rules(): array
    {
        return [
            [['invoice_id', 'receipt_no', 'receipt_amount', 'receipt_date', 'status'], 'required'],
            [['invoice_id', 'receipt_unique_no', 'payment_mode', 'created_by', 'status', 'deleted_by'], 'integer'],
            [['deleted_at'], 'safe'],
            [['receipt_amount', 'pending_amount', 'paid_amount', 'balance_amount',
              'cheque_amount', 'bank_amount', 'paypal_amount'], 'number', 'min' => 0],
            [['receipt_date', 'cash_date', 'cheque_date', 'cheque_deposit_date',
              'bank_date', 'paypal_payment_date', 'created_date'], 'safe'],
            [['payment_remarks'], 'string', 'max' => 5000],
            [['receipt_amount'], 'number', 'min' => 0.01, 'tooSmall' => 'Enter the amount received.'],
            [['payment_mode'], 'in', 'range' => array_keys(self::modeOptions()), 'message' => 'Choose how the payment was made.'],
            [['receipt_date', 'cash_date', 'cheque_date', 'cheque_deposit_date', 'bank_date', 'paypal_payment_date'], 'date', 'format' => 'php:Y-m-d',
                'message' => '{attribute} must be a date (YYYY-MM-DD).'],
            [['receipt_date', 'cash_date', 'cheque_deposit_date', 'bank_date', 'paypal_payment_date'], 'compare', 'compareValue' => date('Y-m-d'),
                'operator' => '<=', 'type' => 'string', 'message' => '{attribute} cannot be in the future.'],
            [['receipt_no', 'cheque_sender_bank', 'cheque_no', 'cheque_received_bank',
              'cheque_received_ac_no', 'bank_sender_bank', 'bank_ref_no',
              'bank_received_bank', 'bank_received_ac_no',
              'paypal_ref_no'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'receipt_amount'  => 'Amount',
            'receipt_date'    => 'Receipt date',
            'cash_date'       => 'Cash date',
            'cheque_date'     => 'Cheque date',
            'cheque_deposit_date' => 'Deposit date',
            'bank_date'       => 'Transfer date',
            'paypal_payment_date' => 'PayPal date',
            'payment_mode'    => 'Payment Mode',
            'bank_ref_no'     => 'Transaction Ref No.',
            'payment_remarks' => 'Remarks',
        ];
    }

    public static function modeOptions(): array
    {
        return [self::MODE_BANK => 'Bank transfer', self::MODE_CHEQUE => 'Cheque', self::MODE_CASH => 'Cash', self::MODE_PAYPAL => 'PayPal', self::MODE_OTHERS => 'Others'];
    }

    public static function modeLabel(int $mode): string
    {
        return match ($mode) {
            self::MODE_CASH   => 'Cash',
            self::MODE_CHEQUE => 'Cheque',
            self::MODE_BANK   => 'Bank Transfer',
            self::MODE_PAYPAL => 'PayPal',
            self::MODE_OTHERS => 'Others',
            default           => '—',
        };
    }

    public function getInvoice(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Invoice::class, ['id' => 'invoice_id']);
    }

    public function getAttachments(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InvoiceReceiptAttachment::class, ['invoice_receipt_id' => 'id'])
            ->andWhere(['status' => 1]);
    }
}
