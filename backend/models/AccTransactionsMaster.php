<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Model for table "acc_transactions_master".
 *
 * @property int $id
 * @property string|null $transaction_date
 * @property string|null $title
 * @property string|null $notes
 * @property string|null $ref_invoice_number
 * @property int|null $type
 * @property int|null $invoice_id
 * @property int|null $inward_invoice_id
 * @property int|null $employee_id
 * @property int|null $payslip_id
 * @property int|null $created_by
 * @property string|null $created_at
 * @property int|null $updated_by
 * @property string|null $updated_at
 * @property int $status
 *
 * @property AccTransactions[] $accTransactions
 * @property AccJournal[] $accJournals
 * @property Invoice $invoice
 * @property InwardInvoice $inwardInvoice
 * @property UploadPayslip $payslip
 * @property Developer $employee
 */
class AccTransactionsMaster extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'acc_transactions_master';
    }

    public function rules(): array
    {
        return [
            [['type', 'invoice_id', 'inward_invoice_id', 'employee_id', 'payslip_id', 'created_by', 'updated_by', 'status'], 'integer'],
            [['title'], 'string', 'max' => 255],
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
            'transaction_date' => 'Transaction Date',
            'title' => 'Title',
            'notes' => 'Notes',
            'ref_invoice_number' => 'Ref Invoice Number',
            'type' => 'Type',
            'invoice_id' => 'Invoice',
            'inward_invoice_id' => 'Inward Invoice',
            'employee_id' => 'Employee',
            'payslip_id' => 'Payslip',
            'created_by' => 'Created By',
            'created_at' => 'Created At',
            'updated_by' => 'Updated By',
            'updated_at' => 'Updated At',
            'status' => 'Status',
        ];
    }

    public function getAccTransactions(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccTransactions::class, ['transaction_master_id' => 'id']);
    }

    public function getAccJournals(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccJournal::class, ['transaction_master_id' => 'id']);
    }

    public function getInvoice(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Invoice::class, ['id' => 'invoice_id']);
    }

    public function getInwardInvoice(): \yii\db\ActiveQuery
    {
        return $this->hasOne(InwardInvoice::class, ['id' => 'inward_invoice_id']);
    }

    public function getPayslip(): \yii\db\ActiveQuery
    {
        return $this->hasOne(UploadPayslip::class, ['id' => 'payslip_id']);
    }

    public function getEmployee(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'employee_id']);
    }
}
