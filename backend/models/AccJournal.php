<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Model for table "acc_journal".
 *
 * @property int $id
 * @property int|null $transaction_master_id
 * @property int|null $ref_transaction_id
 * @property int|null $source_ledger_id
 * @property int|null $ledger_id
 * @property float|null $amount
 * @property int|null $entry_type
 * @property string|null $created_at
 * @property int|null $created_by
 * @property string|null $updated_at
 * @property int|null $updated_by
 * @property int $status
 *
 * @property AccTransactionsMaster $transactionMaster
 * @property AccTransactions $refTransaction
 * @property AccLedger $sourceLedger
 * @property AccLedger $ledger
 */
class AccJournal extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'acc_journal';
    }

    public function rules(): array
    {
        return [
            [['transaction_master_id', 'ref_transaction_id', 'source_ledger_id', 'ledger_id', 'entry_type', 'created_by', 'updated_by', 'status'], 'integer'],
            [['amount'], 'number', 'min' => 0],
            [['created_at', 'updated_at'], 'safe'],
            [['status'], 'default', 'value' => 1],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'transaction_master_id' => 'Transaction Master',
            'ref_transaction_id' => 'Ref Transaction',
            'source_ledger_id' => 'Source Ledger',
            'ledger_id' => 'Ledger',
            'amount' => 'Amount',
            'entry_type' => 'Entry Type',
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

    public function getRefTransaction(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccTransactions::class, ['id' => 'ref_transaction_id']);
    }

    public function getSourceLedger(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccLedger::class, ['id' => 'source_ledger_id']);
    }

    public function getLedger(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccLedger::class, ['id' => 'ledger_id']);
    }
}
