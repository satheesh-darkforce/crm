<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $domain_id
 * @property float  $amount_received
 * @property string $amount_received_date
 * @property string $expiry_date
 * @property string $notes
 * @property string $invoice_attachment
 * @property string $recived_filename
 * @property string $created_at
 * @property int    $created_by
 * @property string $updated_at
 * @property int    $updated_by
 * @property string $update_history
 * @property int    $status
 */
class DomainRenewalHistory extends ActiveRecord
{
    use \backend\components\GuardedAttributes;

    /** Set by the controller, never by the form. */
    protected static function guardedAttributes(): array
    {
        return ['domain_id', 'created_by', 'updated_by', 'status', 'created_at', 'updated_at', 'update_history', 'invoice_attachment'];
    }

    public static function tableName(): string { return 'domain_renewal_history'; }

    public function rules(): array
    {
        return [
            // Dates must be real YYYY-MM-DD values (checked when new or edited).
            [['renewed_date', 'expiry_date', 'amount_received_date'], 'date', 'format' => 'php:Y-m-d', 'message' => '{attribute} must be a date (YYYY-MM-DD).',
                'when' => fn($m, $a) => $m->isNewRecord || $m->isAttributeChanged($a, false)],
            [['domain_id', 'renewed_date', 'expiry_date'], 'required'],
            [['amount_received'], 'number', 'min' => 0],
            [['domain_id', 'created_by', 'updated_by', 'with_gst', 'mode_of_payment', 'status'], 'integer'],
            [['amount_received'], 'string', 'max' => 8],
            [['amount_received_by'], 'string', 'max' => 100],
            [['invoice_attachment'], 'string', 'max' => 255],
            [['invoice_number'], 'string', 'max' => 200],
            [['renewed_date', 'expiry_date', 'amount_received_date', 'created_at', 'updated_at', 'update_history', 'notes'], 'safe'],
            ['renewed_date', 'compareDateRange'],
            // Defaults for NOT NULL columns that have no DB default — avoids
            // integrity-violation errors when the form posts empty values.
            [['mode_of_payment', 'with_gst'], 'default', 'value' => 0],
            [['amount_received_by', 'invoice_number', 'invoice_attachment', 'notes'], 'default', 'value' => ''],
            ['amount_received_date', 'default', 'value' => date('Y-m-d')],
        ];
    }

    public function compareDateRange(string $attribute): void
    {
        if (!empty($this->renewed_date) && !empty($this->expiry_date)) {
            if (strtotime($this->expiry_date) < strtotime($this->renewed_date)) {
                $this->addError($attribute, 'The expired date can not be less/before renewed date.');
            }
        }
    }

    public function attributeLabels(): array
    {
        return [
            'domain_id'            => 'Domain',
            'renewed_date'         => 'Renewed Date',
            'expiry_date'          => 'Expiry Date',
            'amount_received'      => 'Amount Received',
            'amount_received_by'   => 'Amount Received By',
            'with_gst'             => 'With GST',
            'mode_of_payment'      => 'Mode Of Payment',
            'amount_received_date' => 'Amount Received Date',
            'invoice_attachment'   => 'Invoice Attachment',
            'invoice_number'       => 'Invoice Number',
            'notes'                => 'Notes',
        ];
    }

    public function getDomain(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Domain::class, ['id' => 'domain_id']);
    }

    public function getCreatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(\backend\models\User::class, ['user_id' => 'created_by']);
    }

    public function getUpdatedBy(): \yii\db\ActiveQuery
    {
        return $this->hasOne(\backend\models\User::class, ['user_id' => 'updated_by']);
    }
}
