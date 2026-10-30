<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Model for table "acc_transactions".
 *
 * @property int $id
 * @property int|null $transaction_master_id
 * @property int|null $transaction_type
 * @property int|null $ledger_id
 * @property float|null $amount
 * @property string|null $notes
 * @property string|null $ref_invoice_number
 * @property string|null $transaction_date
 * @property string|null $created_at
 * @property int|null $created_by
 * @property string|null $updated_at
 * @property int|null $updated_by
 * @property int $status
 *
 * @property AccTransactionsMaster $transactionMaster
 * @property AccLedger $ledger
 * @property AccJournal[] $accJournals
 */
class AccTransactions extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'acc_transactions';
    }

    public function rules(): array
    {
        return [
            [['transaction_master_id', 'transaction_type', 'ledger_id', 'created_by', 'updated_by', 'status'], 'integer'],
            [['amount'], 'number', 'min' => 0],
            [['notes'], 'string', 'max' => 5000],
            [['ref_invoice_number'], 'string', 'max' => 50],
            [['transaction_date', 'created_at', 'updated_at'], 'safe'],
            [['status'], 'default', 'value' => 1],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'transaction_master_id' => 'Transaction Master',
            'transaction_type' => 'Transaction Type',
            'ledger_id' => 'Ledger',
            'amount' => 'Amount',
            'notes' => 'Notes',
            'ref_invoice_number' => 'Ref Invoice Number',
            'transaction_date' => 'Transaction Date',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
            'updated_at' => 'Updated At',
            'updated_by' => 'Updated By',
            'status' => 'Status',
        ];
    }

    public function getTransactionMaster(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccTransactionsMaster::class, ['id' => 'transaction_master_id']);
    }

    public function getLedger(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccLedger::class, ['id' => 'ledger_id']);
    }

    public function getAccJournals(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccJournal::class, ['ref_transaction_id' => 'id']);
    }
}
