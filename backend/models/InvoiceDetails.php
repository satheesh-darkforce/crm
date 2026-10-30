<?php
namespace backend\models;
use yii\db\ActiveRecord;

class InvoiceDetails extends ActiveRecord
{
    public static function tableName(): string { return 'invoice_details'; }

    public function rules(): array
    {
        return [
            [['invoice_id', 'status'], 'required'],
            [['invoice_id', 'status'], 'integer'],
            [['amount'], 'number', 'min' => 0],
            [['quantity', 'unit_rate'], 'number', 'min' => 0],
            [['model'], 'string', 'max' => 50],
            [['particulars', 'sac'], 'string', 'max' => 5000],
        ];
    }

    public function getInvoice(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Invoice::class, ['id' => 'invoice_id']);
    }
}
