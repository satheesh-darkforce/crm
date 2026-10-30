<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $invoice_receipt_id
 * @property string $path
 * @property int    $status  0=inactive, 1=active
 */
class InvoiceReceiptAttachment extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'invoice_receipt_attachments';
    }

    public function rules(): array
    {
        return [
            [['invoice_receipt_id', 'path'], 'required'],
            [['invoice_receipt_id'], 'integer'],
            [['status'], 'integer'],
            [['status'], 'default', 'value' => 1],
            [['path'], 'string', 'max' => 5000],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'                  => 'ID',
            'invoice_receipt_id'  => 'Invoice Receipt',
            'path'                => 'File Path',
            'status'              => 'Status',
        ];
    }

    public function getReceipt(): \yii\db\ActiveQuery
    {
        return $this->hasOne(InvoiceReceipt::class, ['id' => 'invoice_receipt_id']);
    }
}
