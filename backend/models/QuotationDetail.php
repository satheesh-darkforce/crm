<?php

namespace backend\models;

use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Table: quotation_details
 *
 * @property int    $id
 * @property int    $quotation_id
 * @property int    $currency_id
 * @property float  $quote_amount
 * @property string $proposal_send_date
 * @property int    $created_by
 * @property string $created_date
 * @property int    $status
 */
class QuotationDetail extends ActiveRecord
{
    /** @var UploadedFile[] */
    public array $attachmentFiles = [];

    public static function tableName(): string
    {
        return 'quotation_details';
    }

    public function rules(): array
    {
        return [
            [['currency_id', 'quote_amount', 'proposal_send_date'], 'required'],
            [['currency_id', 'quotation_id', 'created_by', 'status'], 'integer'],
            [['quote_amount'], 'number', 'min' => 0],
            [['proposal_send_date', 'created_date'], 'safe'],
            [['attachmentFiles'], 'file', 'skipOnEmpty' => true,
                'extensions' => 'pdf,doc,docx,ppt,pptx',
                'maxSize' => 20 * 1024 * 1024, 'maxFiles' => 5],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'currency_id'        => 'Currency',
            'quote_amount'       => 'Quote Amount',
            'proposal_send_date' => 'Proposal Send Date',
        ];
    }

    public function getCurrency(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Currency::class, ['id' => 'currency_id']);
    }

    public function getQuotation(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Quotation::class, ['id' => 'quotation_id']);
    }

    public function getAttachments(): \yii\db\ActiveQuery
    {
        return $this->hasMany(QuotationDetailAttachment::class, ['quotation_detail_id' => 'id'])
            ->where(['quotation_detail_attachments.status' => 1]);
    }

    public function getCreatedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'created_by']);
    }
}
