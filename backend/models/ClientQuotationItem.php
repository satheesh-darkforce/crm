<?php

namespace backend\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * A quotation line. Name, HSN/SAC, unit, unit price and GST are a snapshot taken
 * when the quote is saved (LineItems::read()), so product edits never change it.
 *
 * @property int         $id
 * @property int         $quote_id
 * @property int|null    $product_id
 * @property int         $sort_order
 * @property string      $description
 * @property string|null $hsn_sac
 * @property string|null $unit
 * @property string      $quantity
 * @property string      $unit_price
 * @property string      $discount_percent
 * @property string      $gst_rate
 * @property string      $taxable_amount
 * @property string      $gst_amount
 * @property string      $line_total
 * @property string|null $created_at
 */
class ClientQuotationItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'client_quotation_items';
    }

    public function getProduct(): ActiveQuery
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    /** Row in the shape the line editor and LineItems use. */
    public function toLine(): array
    {
        $gross = round((float)$this->quantity * (float)$this->unit_price, 2);
        return [
            'product_id'       => $this->product_id ? (int)$this->product_id : null,
            'description'      => (string)$this->description,
            'hsn_sac'          => (string)$this->hsn_sac,
            'unit'             => (string)$this->unit,
            'quantity'         => (float)$this->quantity,
            'unit_price'       => (float)$this->unit_price,
            'discount_percent' => (float)$this->discount_percent,
            'gst_rate'         => (float)$this->gst_rate,
            'gross'            => $gross,
            'discount_amount'  => round($gross - (float)$this->taxable_amount, 2),
            'taxable_amount'   => (float)$this->taxable_amount,
            'gst_amount'       => (float)$this->gst_amount,
            'line_total'       => (float)$this->line_total,
        ];
    }
}
