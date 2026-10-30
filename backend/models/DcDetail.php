<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * ActiveRecord for `dc_details` (delivery challan header).
 *
 * @property int         $id
 * @property int|null    $dc_no
 * @property int         $client_id
 * @property string|null $ewbc_no
 * @property string      $dc_date
 * @property int         $dc_type                 1=Returnable, 2=Sale, 3=Service
 * @property string|null $dc_return_date
 * @property string|null $dc_returned_date
 * @property string|null $dc_return_description
 * @property int         $dc_delivery_type
 * @property string|null $dc_delivery_description
 * @property string|null $inward_dc_no            Inward DC number (set when type=2 or when a normal DC was generated from an inward return)
 * @property int|null    $inward_dc_id            FK to dc_details.id of the source inward DC (set on return DCs)
 * @property int|null    $type                    1=Normal DC, 2=Inward DC
 * @property string      $dc_created_by
 * @property int         $dc_created_by_id
 * @property string      $created_at
 * @property string      $modified_at
 * @property string|null $cancel_reason
 * @property int         $status                  0=inactive, 1=active, 2=cancelled
 *
 * @property ClientModel       $client
 * @property DeliveryDetail    $delivery
 * @property DcDocumentFile    $documents
 * @property DcProduct[]       $dcProducts
 * @property DeliveryProduct[] $products
 */
class DcDetail extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'dc_details';
    }

    public function rules(): array
    {
        return [
            [['client_id', 'dc_date', 'dc_type', 'dc_delivery_type'], 'required'],

            [
                ['dc_return_date'], 'required',
                'when' => fn($m) => (int)$m->dc_type === 1,
                'whenClient' => "function(a,v){return $('#dc-type').val()=='1';}",
                'except' => 'return_outbound',
            ],
            [
                ['ewbc_no'], 'required',
                'when' => fn($m) => (int)$m->dc_type === 2,
                'whenClient' => "function(a,v){return $('#dc-type').val()=='2';}",
                'except' => 'return_outbound',
            ],
            [['dc_no', 'client_id', 'dc_type', 'dc_delivery_type', 'status', 'dc_created_by_id', 'type', 'inward_dc_id'], 'integer'],
            [['dc_return_description', 'dc_delivery_description', 'dc_returned_date', 'dc_created_by', 'cancel_reason'], 'string'],
            [['dc_date', 'dc_return_date', 'created_at', 'modified_at'], 'safe'],
            [['dc_return_date'], 'validateReturnDate'],
            [['ewbc_no'], 'string', 'min' => 2, 'max' => 50],
            [['inward_dc_no'], 'string', 'max' => 10],
            [
                ['inward_dc_no'], 'required',
                'when' => fn($m) => (int)$m->type === 2,
                'whenClient' => "function(){return false;}",
                'message' => 'Inward DC No is required.',
            ],
            [['dc_no'], 'unique'],
            [['client_id'], 'exist', 'skipOnError' => true, 'targetClass' => ClientModel::class, 'targetAttribute' => ['client_id' => 'id']],
        ];
    }

    public function validateReturnDate(string $attribute): void
    {
        if ((int)$this->dc_type !== 1) {
            return;
        }
        if (empty($this->dc_date) || empty($this->$attribute)) {
            return;
        }
        $dcDate     = strtotime($this->dc_date);
        $returnDate = strtotime($this->$attribute);
        if ($dcDate && $returnDate && $returnDate < $dcDate) {
            $this->addError($attribute, 'DC return date cannot be less than DC date.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'dc_no' => 'Dc No',
            'client_id' => 'Client',
            'ewbc_no' => 'EWAY No',
            'dc_date' => 'Dc Date',
            'dc_type' => 'Dc Type',
            'dc_return_date' => 'Dc Return Date',
            'dc_returned_date' => 'Returned Date',
            'created_at' => 'Created At',
            'modified_at' => 'Modified At',
            'inward_dc_no' => 'Inward DC No',
            'type' => 'Type',
            'status' => 'Status',
        ];
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getDelivery(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DeliveryDetail::class, ['dc_id' => 'id']);
    }

    public function getDocuments(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DcDocumentFile::class, ['dc_id' => 'id']);
    }

    public function getDcProducts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(DcProduct::class, ['dc_id' => 'id']);
    }

    public function getProducts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(DeliveryProduct::class, ['id' => 'product_id'])
                    ->viaTable('dc_products', ['dc_id' => 'id']);
    }
}
