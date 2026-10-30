<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Model for table "acc_ledger".
 *
 * @property int $id
 * @property int|null $category_id
 * @property string|null $title
 * @property float $opening_balance
 * @property string|null $opening_balance_date
 * @property float $credit_balance
 * @property float $debit_balance
 * @property float $balance
 * @property int|null $type
 * @property int|null $is_fixed
 * @property int|null $client_id
 * @property int|null $vendor_id
 * @property int|null $employee_id
 * @property string|null $created_at
 * @property int|null $created_by
 * @property string|null $updated_at
 * @property int|null $updated_by
 * @property int $status
 *
 * @property AccLedgerCategory $category
 * @property ClientModel $client
 * @property Vendor $vendor
 * @property Developer $employee
 * @property AccTransactions[] $accTransactions
 * @property AccJournal[] $accJournalsBySource
 * @property AccJournal[] $accJournalsByLedger
 * @property InwardInvoice[] $inwardInvoices
 * @property InwardInvoiceDetails[] $inwardInvoiceDetails
 */
class AccLedger extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'acc_ledger';
    }

    public function rules(): array
    {
        return [
            [['category_id', 'type', 'is_fixed', 'client_id', 'vendor_id', 'employee_id', 'created_by', 'updated_by', 'status'], 'integer'],
            [['title'], 'string', 'max' => 255],
            [['opening_balance', 'credit_balance', 'debit_balance', 'balance'], 'number'],
            [['opening_balance_date', 'created_at', 'updated_at'], 'safe'],
            [['status'], 'default', 'value' => 1],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'category_id' => 'Category',
            'title' => 'Title',
            'opening_balance' => 'Opening Balance',
            'opening_balance_date' => 'Opening Balance Date',
            'credit_balance' => 'Credit Balance',
            'debit_balance' => 'Debit Balance',
            'balance' => 'Balance',
            'type' => 'Type',
            'is_fixed' => 'Is Fixed',
            'client_id' => 'Client',
            'vendor_id' => 'Vendor',
            'employee_id' => 'Employee',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
            'updated_at' => 'Updated At',
            'updated_by' => 'Updated By',
            'status' => 'Status',
        ];
    }

    public function getCategory(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccLedgerCategory::class, ['id' => 'category_id']);
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getVendor(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Vendor::class, ['id' => 'vendor_id']);
    }

    public function getEmployee(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'employee_id']);
    }

    public function getAccTransactions(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccTransactions::class, ['ledger_id' => 'id']);
    }

    public function getAccJournalsBySource(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccJournal::class, ['source_ledger_id' => 'id']);
    }

    public function getAccJournalsByLedger(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccJournal::class, ['ledger_id' => 'id']);
    }

    public function getInwardInvoices(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoice::class, ['payment_ledger_id' => 'id']);
    }

    public function getInwardInvoiceDetails(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceDetails::class, ['ledger_id' => 'id']);
    }
}
