<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: inward_invoice_details
 *
 * @property int    $id
 * @property int    $inward_invoice_id
 * @property int    $ledger_id
 * @property int    $line_item_type  (1=regular item, 2=expense)
 * @property string $product
 * @property string $hsn
 * @property float  $qty
 * @property float  $price_per_qty
 * @property float  $item_total
 * @property float  $sgst_percent
 * @property float  $sgst_amount
 * @property float  $cgst_percent
 * @property float  $cgst_amount
 * @property float  $igst_percent
 * @property float  $igst_amount
 * @property float  $total_amount
 * @property int    $status
 */
class InwardInvoiceDetails extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'inward_invoice_details';
    }

    public function rules(): array
    {
        return [
            [['inward_invoice_id'], 'required'],
            [['inward_invoice_id', 'status', 'ledger_id', 'line_item_type'], 'integer'],
            [['qty', 'price_per_qty', 'item_total', 'sgst_percent', 'sgst_amount',
              'cgst_percent', 'cgst_amount', 'igst_percent', 'igst_amount', 'total_amount'], 'number', 'min' => 0],
            [['sgst_percent', 'cgst_percent', 'igst_percent'], 'number', 'max' => 100],
            [['qty'], 'number', 'max' => 1000000],
            [['product'], 'trim'],
            [['product'], 'string', 'max' => 5000],
            [['hsn'], 'string', 'max' => 500],
            [['line_item_type'], 'default', 'value' => 1],
        ];
    }

    public function getLedger(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccLedger::class, ['id' => 'ledger_id']);
    }

    public function getInwardInvoice(): \yii\db\ActiveQuery
    {
        return $this->hasOne(InwardInvoice::class, ['id' => 'inward_invoice_id']);
    }
}
