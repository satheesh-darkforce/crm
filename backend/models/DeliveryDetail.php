<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * ActiveRecord for `delivery_details` (per-DC shipping info).
 *
 * @property int    $id
 * @property int    $dc_id
 * @property string $consern_name
 * @property string $address
 * @property string $gst_no
 * @property string $contact_no
 * @property string $alt_contact_no
 * @property string $place
 * @property string $vehicle_no
 * @property string $created_at
 * @property int    $status       0=inactive, 1=active
 */
class DeliveryDetail extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'delivery_details';
    }

    public function rules(): array
    {
        return [
            [['consern_name', 'address', 'contact_no', 'place'], 'required'],
            [['id', 'dc_id', 'status'], 'integer'],
            [['created_at', 'vehicle_no'], 'safe'],
            [['consern_name', 'address', 'place'], 'string', 'max' => 255],
            [['gst_no'], 'string', 'min' => 2, 'max' => 50],
            [
                ['contact_no'], 'match',
                'pattern' => '/^[0-9]{10}$/',
                'message' => 'Contact Number must be exactly 10 digits',
            ],
            [
                ['alt_contact_no'], 'match',
                'pattern' => '/^[0-9]{10}$/',
                'skipOnEmpty' => true,
                'message' => 'Alt Contact Number must be exactly 10 digits',
            ],
            [['vehicle_no'], 'string', 'min' => 4, 'max' => 20],
            [['id'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'dc_id' => 'Dc ID',
            'consern_name' => 'Consignee Name',
            'address' => 'Address',
            'gst_no' => 'GST No',
            'contact_no' => 'Contact No',
            'alt_contact_no' => 'Alt Contact No',
            'place' => 'Place of Supply',
            'vehicle_no' => 'Vehicle No',
            'created_at' => 'Created At',
            'status' => 'Status',
        ];
    }

    public function getDc(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DcDetail::class, ['id' => 'dc_id']);
    }
}
