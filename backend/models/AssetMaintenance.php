<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $asset_id
 * @property float  $service_cost
 * @property string $service_provider
 * @property string $service_provider_name
 * @property string $sp_number
 * @property int    $attended           FK → developers.id
 * @property int    $payment_type       1=Free, 2=Paid
 * @property int    $payment_mode       1=Cash, 2=GPay, 3=Bank
 * @property string $gpay_number
 * @property string $bank_number
 * @property string $invoice_bill       file path
 * @property int    $status
 */
class AssetMaintenance extends ActiveRecord
{
    use \backend\components\GuardedAttributes;

    /** Set by the controller, never by the form. */
    protected static function guardedAttributes(): array
    {
        return ['status', 'invoice_bill'];
    }

    public static function tableName(): string { return 'assets_maintenance'; }

    public function rules(): array
    {
        return [
            [['asset_id', 'service_provider', 'service_provider_name', 'sp_number', 'attended', 'payment_type'], 'required'],
            [['asset_id', 'attended', 'payment_type', 'payment_mode', 'status'], 'integer'],
            [['service_cost'], 'number', 'min' => 0],
            [['service_provider', 'service_provider_name'], 'string', 'max' => 250],
            [['sp_number'], 'string', 'min' => 10, 'max' => 15, 'tooShort' => 'Contact number must be at least 10 digits.'],
            [['sp_number'], 'match', 'pattern' => '/^\d+$/', 'message' => 'Contact number must contain only digits.'],
            [['gpay_number', 'bank_number'], 'string', 'max' => 100],
            [['invoice_bill'], 'string'],
            // Paid: payment_mode and service_cost required
            ['payment_mode', 'required',
                'when' => fn($m) => (int)$m->payment_type === 2,
                'whenClient' => "function(attr,val){ return parseInt($('#maintenance-payment-type').val()) === 2; }",
                'message' => 'Payment mode is required for paid service.',
            ],
            ['service_cost', 'required',
                'when' => fn($m) => (int)$m->payment_type === 2,
                'whenClient' => "function(attr,val){ return parseInt($('#maintenance-payment-type').val()) === 2; }",
            ],
            ['service_cost', 'validateCostNotZero'],
            // GPay number
            ['gpay_number', 'required',
                'when' => fn($m) => (int)$m->payment_type === 2 && (int)$m->payment_mode === 2,
                'whenClient' => "function(attr,val){ return parseInt($('#maintenance-payment-type').val())===2 && parseInt($('#maintenance-payment-mode').val())===2; }",
            ],
            // Bank number
            ['bank_number', 'required',
                'when' => fn($m) => (int)$m->payment_type === 2 && (int)$m->payment_mode === 3,
                'whenClient' => "function(attr,val){ return parseInt($('#maintenance-payment-type').val())===2 && parseInt($('#maintenance-payment-mode').val())===3; }",
            ],
        ];
    }

    public function validateCostNotZero(string $attribute): void
    {
        if ((int)$this->payment_type === 2 && (float)$this->$attribute == 0) {
            $this->addError($attribute, 'Service cost cannot be zero for paid service.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'asset_id'              => 'Asset',
            'service_provider'      => 'Service Type / Provider',
            'service_provider_name' => 'Service Agent Name',
            'sp_number'             => 'Contact Number',
            'service_cost'          => 'Service Cost (₹)',
            'payment_type'          => 'Payment Type',
            'payment_mode'          => 'Payment Mode',
            'attended'              => 'Attended By',
            'gpay_number'           => 'GPay Number',
            'bank_number'           => 'Bank Account Details',
            'invoice_bill'          => 'Invoice Bill',
        ];
    }

    public function getAsset(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Asset::class, ['id' => 'asset_id']);
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'attended']);
    }

    public static function paymentTypeLabels(): array
    {
        return [1 => 'Free', 2 => 'Paid'];
    }

    public static function paymentModeLabels(): array
    {
        return [1 => 'Cash', 2 => 'GPay', 3 => 'Bank Transfer'];
    }
}
