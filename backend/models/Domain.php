<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $client_id
 * @property string $domain_name
 * @property float  $price
 * @property string $date_of_purchase
 * @property string $service_provider
 * @property string $domain_username
 * @property string $domain_password
 * @property string $hosting_server
 * @property string $description
 * @property string $expiry_date
 * @property int    $domain_status  (1=Live, 2=Expired, 3=Need to follow, 4=Pending Payment, 5=Pending Renewal)
 * @property string $created_at
 * @property int    $created_by
 * @property string $updated_at
 * @property int    $updated_by
 * @property string $update_history
 * @property int    $status
 */
class Domain extends ActiveRecord
{
    use \backend\components\GuardedAttributes;

    /** Set by the controller, never by the form. */
    protected static function guardedAttributes(): array
    {
        return ['created_by', 'updated_by', 'status', 'created_at', 'updated_at', 'update_history'];
    }

    public static function tableName(): string { return 'domains'; }

    public function rules(): array
    {
        return [
            // Dates must be real YYYY-MM-DD values (checked when new or edited).
            [['date_of_purchase', 'expiry_date'], 'date', 'format' => 'php:Y-m-d', 'message' => '{attribute} must be a date (YYYY-MM-DD).',
                'when' => fn($m, $a) => $m->isNewRecord || $m->isAttributeChanged($a, false)],
            [['domain_name', 'service_provider', 'domain_username', 'hosting_server', 'domain_password', 'description'], 'trim'],
            [['client_id', 'domain_name', 'date_of_purchase', 'service_provider', 'expiry_date', 'domain_status'], 'required'],
            [['client_id', 'domain_status', 'created_by', 'updated_by', 'status'], 'integer'],
            [['price'], 'number', 'min' => 0, 'max' => 999999.99],
            [['date_of_purchase', 'expiry_date', 'created_at', 'updated_at'], 'safe'],
            [['domain_name', 'service_provider', 'domain_username', 'hosting_server'], 'string', 'max' => 255],
            [['domain_password'], 'string', 'max' => 255],
            [['description', 'update_history'], 'string'],
            [['domain_status'], 'in', 'range' => [1, 2, 3, 4, 5], 'message' => 'Please select a valid status (1-5).'],
            [['client_id'], 'exist', 'targetClass' => ClientModel::class, 'targetAttribute' => 'id', 'message' => 'Select a valid client.',
                'when' => fn($m) => $m->isNewRecord || $m->isAttributeChanged('client_id', false)],
            ['domain_name', 'match', 'pattern' => '/^(https?:\/\/)?(www\.)?([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}(\/.?)?$/i', 'message' => 'Enter a valid domain name (e.g. example.com or http://example.com/ or https://www.example.com/).'],
            ['date_of_purchase', 'validatePurchaseNotFuture'],
            ['expiry_date', 'compareDateRange'],
        ];
    }

    public function validatePurchaseNotFuture(string $attribute): void
    {
        if ($this->date_of_purchase) {
            $ts = strtotime($this->date_of_purchase);
            if ($ts !== false && $ts > strtotime(date('Y-m-d'))) {
                $this->addError($attribute, 'Purchase date cannot be in the future.');
            }
        }
    }

    public function compareDateRange(string $attribute): void
    {
        if ($this->expiry_date && $this->date_of_purchase) {
            if (strtotime($this->expiry_date) < strtotime($this->date_of_purchase)) {
                $this->addError($attribute, 'Expiry date cannot be before the purchase date.');
            }
        }
    }

    public function attributeLabels(): array
    {
        return [
            'domain_name'    => 'Domain Name',
            'client_id'      => 'Client',
            'domain_status'  => 'Status',
            'date_of_purchase' => 'Purchase Date',
            'expiry_date'    => 'Expiry Date',
            'domain_password' => 'Password',
        ];
    }

    public static function domainStatusLabels(): array
    {
        return [
            1 => 'Live',
            2 => 'Expired',
            3 => 'Need to Follow',
            4 => 'Pending Payment',
            5 => 'Pending Renewal',
        ];
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getRenewalHistories(): \yii\db\ActiveQuery
    {
        return $this->hasMany(DomainRenewalHistory::class, ['domain_id' => 'id']);
    }

    public function getFollowups(): \yii\db\ActiveQuery
    {
        return $this->hasMany(DomainFollowUp::class, ['domain_id' => 'id']);
    }

}
