<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * ActiveRecord for `dc_document_files` — RENAMED on migration from delivery's
 * `quotation_details` table to avoid the hard collision with maestro_erp's
 * own `quotation_details` (which belongs to the ERP Quotation module).
 *
 * Stores file paths for the three supporting documents on each Delivery
 * Challan: Quotation PDF, Invoice PDF, PO PDF.
 *
 * @property int         $id
 * @property int         $dc_id
 * @property string      $quotation_no
 * @property string|null $quotation_path
 * @property string|null $invoice_no
 * @property string|null $invoice_path
 * @property string|null $po_no
 * @property string|null $po_path
 * @property int         $status         0=inactive, 1=active
 */
class DcDocumentFile extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'dc_document_files';
    }

    public function rules(): array
    {
        return [
            [['dc_id'], 'required'],
            [['quotation_no'], 'required', 'except' => 'return'],
            [
                ['quotation_path'],
                'required',
                'when' => fn($model) => !empty($model->quotation_no) && empty($model->quotation_path),
                'whenClient' => 'function () { return false; }',
            ],
            [
                ['invoice_path'],
                'required',
                'when' => fn($model) => !empty($model->invoice_no) && empty($model->invoice_path),
                'whenClient' => 'function () { return false; }',
            ],
            [
                ['po_path'],
                'required',
                'when' => fn($model) => !empty($model->po_no) && empty($model->po_path),
                'whenClient' => 'function () { return false; }',
            ],
            [['dc_id', 'status'], 'integer'],
            [['quotation_no', 'invoice_no', 'po_no'], 'string', 'min' => 2, 'max' => 100],
            [['quotation_path', 'invoice_path', 'po_path'], 'string', 'max' => 255],
            [['dc_id'], 'exist', 'skipOnError' => true, 'targetClass' => DcDetail::class, 'targetAttribute' => ['dc_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'dc_id' => 'Dc ID',
            'quotation_no' => 'Quotation No',
            'quotation_path' => 'Quotation PDF',
            'invoice_no' => 'Invoice No',
            'invoice_path' => 'Invoice PDF',
            'po_no' => 'PO No',
            'po_path' => 'PO PDF',
            'status' => 'Status',
        ];
    }

    public function getDc(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DcDetail::class, ['id' => 'dc_id']);
    }
}
