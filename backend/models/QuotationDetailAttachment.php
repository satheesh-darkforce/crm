<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: quotation_detail_attachments
 *
 * @property int    $id
 * @property int    $quotation_detail_id
 * @property string $path
 * @property int    $status
 */
class QuotationDetailAttachment extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'quotation_detail_attachments';
    }

    public function getDetail(): \yii\db\ActiveQuery
    {
        return $this->hasOne(QuotationDetail::class, ['id' => 'quotation_detail_id']);
    }
}
