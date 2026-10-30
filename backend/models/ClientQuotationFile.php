<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * ActiveRecord for `client_quotation_files`.
 *
 * @property int    $id
 * @property int    $client_id
 * @property int    $quote_id
 * @property string $file_path
 * @property string $created_at
 * @property int    $status      0=inactive, 1=active
 *
 * @property ClientModel     $client
 * @property ClientQuotation $quote
 */
class ClientQuotationFile extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'client_quotation_files';
    }

    public function rules(): array
    {
        return [
            [['client_id', 'quote_id', 'file_path', 'created_at', 'status'], 'required'],
            [['client_id', 'quote_id', 'status'], 'integer'],
            [['created_at'], 'safe'],
            [['file_path'], 'string', 'max' => 500],
            [['client_id'], 'exist', 'skipOnError' => true, 'targetClass' => ClientModel::class,     'targetAttribute' => ['client_id' => 'id']],
            [['quote_id'],  'exist', 'skipOnError' => true, 'targetClass' => ClientQuotation::class, 'targetAttribute' => ['quote_id'  => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'client_id' => 'Client ID',
            'quote_id' => 'Quote ID',
            'file_path' => 'File Path',
            'created_at' => 'Created At',
            'status' => 'Status',
        ];
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getQuote(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientQuotation::class, ['id' => 'quote_id']);
    }
}
