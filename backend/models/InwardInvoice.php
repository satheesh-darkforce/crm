<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $vendor_id
 * @property string $invoice_number
 * @property string $invoice_date
 * @property string $title
 * @property float  $grand_total
 * @property float  $sgst_amount
 * @property float  $cgst_amount
 * @property float  $igst_amount
 * @property float  $net_total
 * @property float  $round_off
 * @property int    $invoice_type
 * @property int    $invoice_role       1=Actual, 2=Provisional
 * @property int    $payment_status     0=Not Paid, 1=Paid
 * @property int    $payment_type
 * @property int    $payment_ledger_id
 * @property float  $payment_amount
 * @property float  $tds_amount
 * @property string $payment_date
 * @property string $payment_remarks
 * @property int    $created_by
 * @property string $created_at
 * @property int    $status
 */
class InwardInvoice extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'inward_invoice';
    }

    public function rules(): array
    {
        return [
            [['vendor_id', 'invoice_number', 'invoice_date', 'status'], 'required'],
            [['vendor_id', 'payment_type', 'payment_status', 'payment_ledger_id',
              'status', 'invoice_role', 'created_by'], 'integer'],
            [['grand_total', 'sgst_amount', 'cgst_amount', 'igst_amount', 'net_total',
              'payment_amount', 'tds_amount'], 'number', 'min' => 0],
            [['round_off'], 'number'],
            [['invoice_date', 'payment_date', 'created_at', 'invoice_type'], 'safe'],
            [['invoice_number'], 'string', 'max' => 255],
            // Inward invoice number must be unique across active (non-deleted) records.
            // Soft-deletes use status=0; the filter ensures cancelled rows do not block reuse.
            [['invoice_number'], 'unique',
                'targetClass'   => self::class,
                'targetAttribute' => ['invoice_number'],
                'filter'        => ['status' => 1],
                'message'       => 'This inward invoice number is already used by another invoice.',
            ],
            [['title', 'payment_remarks'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'vendor_id'      => 'Vendor',
            'invoice_number' => 'Invoice Number',
            'invoice_date'   => 'Invoice Date',
            'title'          => 'Title / Description',
            'grand_total'    => 'Grand Total',
            'net_total'      => 'Net Total',
            'payment_type'   => 'Payment Type',
            'payment_status' => 'Payment Status',
        ];
    }

    public function getVendor(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Vendor::class, ['id' => 'vendor_id']);
    }

    public function getDetails(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceDetails::class, ['inward_invoice_id' => 'id'])
            ->andWhere(['inward_invoice_details.status' => 1]);
    }

    public function getLineItems(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceDetails::class, ['inward_invoice_id' => 'id'])
            ->andWhere(['inward_invoice_details.status' => 1, 'inward_invoice_details.line_item_type' => 1]);
    }

    public function getExpenses(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceDetails::class, ['inward_invoice_id' => 'id'])
            ->andWhere(['inward_invoice_details.status' => 1, 'inward_invoice_details.line_item_type' => 2]);
    }

    public function getAttachments(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceAttachments::class, ['inward_invoice_id' => 'id'])
            ->andWhere(['status' => 1]);
    }
}
