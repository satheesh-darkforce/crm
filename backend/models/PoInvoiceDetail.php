<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * ActiveRecord for `po_invoice_details` — invoices raised against a PO.
 *
 * @property int    $id
 * @property int    $po_id          FK -> po_details.id
 * @property string $invoice_number
 * @property string $invoice_date
 * @property string $invoice_file   Relative path under uploads/po_invoices/
 * @property string $created_at
 * @property int    $status         0=inactive, 1=active
 *
 * @property PoDetail $po
 */
class PoInvoiceDetail extends ActiveRecord
{
    /** Form-only attribute: the uploaded PDF file. */
    public $invoice_file_upload;

    public static function tableName(): string
    {
        return 'po_invoice_details';
    }

    public function rules(): array
    {
        return [
            [['po_id', 'invoice_number', 'invoice_date'], 'required'],
            [['po_id', 'status'], 'integer'],
            [['invoice_date', 'created_at'], 'safe'],
            [['invoice_number'], 'string', 'max' => 100],
            [['invoice_file'], 'string', 'max' => 250],
            [
                ['invoice_file_upload'], 'file',
                'extensions' => ['pdf'],
                'mimeTypes'  => ['application/pdf'],
                'maxSize'    => 10 * 1024 * 1024,
                'tooBig'     => 'Invoice file must not exceed 10 MB.',
                'wrongExtension' => 'Only PDF files are allowed.',
                'skipOnEmpty'    => true,
            ],
            [['po_id'], 'exist', 'skipOnError' => true,
             'targetClass' => PoDetail::class, 'targetAttribute' => ['po_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'             => 'ID',
            'po_id'          => 'PO',
            'invoice_number' => 'Invoice Number',
            'invoice_date'   => 'Invoice Date',
            'invoice_file'   => 'Invoice File',
            'created_at'     => 'Created At',
            'status'         => 'Status',
        ];
    }

    public function getPo(): \yii\db\ActiveQuery
    {
        return $this->hasOne(PoDetail::class, ['id' => 'po_id']);
    }
}
