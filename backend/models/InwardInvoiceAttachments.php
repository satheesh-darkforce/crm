<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $inward_invoice_id
 * @property string $path
 * @property int    $status
 */
class InwardInvoiceAttachments extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'inward_invoice_attachments';
    }

    public function rules(): array
    {
        return [
            [['inward_invoice_id', 'path', 'status'], 'required'],
            [['inward_invoice_id', 'status'], 'integer'],
            [['path'], 'string', 'max' => 5000],
            [['status'], 'default', 'value' => 1],
        ];
    }

    public function getInwardInvoice(): \yii\db\ActiveQuery
    {
        return $this->hasOne(InwardInvoice::class, ['id' => 'inward_invoice_id']);
    }
}
