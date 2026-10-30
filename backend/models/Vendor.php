<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: vendors
 *
 * @property int    $id
 * @property int    $vendor_type
 * @property string $company_name
 * @property string $address
 * @property string $gst_no
 * @property int    $created_by
 * @property string $created_at
 * @property int    $status
 */
class Vendor extends ActiveRecord
{
    const TYPE_INTER_STATE   = 1;
    const TYPE_INTRA_STATE   = 2;
    const TYPE_INTERNATIONAL = 3;

    public static function tableName(): string
    {
        return 'vendors';
    }

    public function beforeValidate(): bool
    {
        if (!empty($this->gst_no)) {
            $this->gst_no = strtoupper(trim($this->gst_no));
        }
        return parent::beforeValidate();
    }

    public function rules(): array
    {
        return [
            [['company_name', 'gst_no', 'address'], 'trim'],
            [['company_name', 'vendor_type'], 'required'],
            [['vendor_type', 'status', 'created_by'], 'integer'],
            [['vendor_type'], 'in', 'range' => [1, 2, 3]],
            [['company_name'], 'string', 'max' => 500],
            [['gst_no'], 'string', 'max' => 15],
            [['gst_no'], 'match',
                'pattern' => '/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/',
                'message'  => 'Invalid GSTIN format (e.g. 29ABCDE1234F1Z5).',
                'skipOnEmpty' => true],
            [['address'], 'string'],
            [['company_name'], 'unique', 'message' => 'Company name already exists.'],
            [['gst_no'], 'unique', 'message' => 'GST Number already exists.',
                'when' => fn($model) => !empty($model->gst_no)],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'company_name' => 'Company Name',
            'vendor_type'  => 'Vendor Type',
            'gst_no'       => 'GST Number',
            'address'      => 'Address',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_INTER_STATE   => 'Inter State',
            self::TYPE_INTRA_STATE   => 'Intra State',
            self::TYPE_INTERNATIONAL => 'International',
        ];
    }

    public function getTypeLabel(): string
    {
        return self::typeLabels()[$this->vendor_type] ?? '—';
    }

    public function getLedger(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccLedger::class, ['vendor_id' => 'id'])
            ->andWhere(['acc_ledger.status' => 1]);
    }

    public function getInwardInvoices(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoice::class, ['vendor_id' => 'id'])
            ->where(['inward_invoice.status' => 1])
            ->orderBy(['inward_invoice.id' => SORT_DESC]);
    }
}
