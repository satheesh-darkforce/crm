<?php

namespace common\components;

use backend\models\AccJournal;
use backend\models\AccLedger;
use backend\models\AccTransactions;
use backend\models\AccTransactionsMaster;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Accounting utility — port of Yii1 Utility accounting functions.
 * Creates double-entry GL entries for invoices (outward and inward).
 */
class AccountingUtility
{
    /**
     * Get map of fixed ledger titles to IDs.
     * @return array ['IT Sale' => 123, 'OUT IGST' => 124, ...]
     */
    public static function getStaticLedgerIds(): array
    {
        return ArrayHelper::map(
            AccLedger::find()
                ->select(['id', 'title'])
                ->where(['status' => 1, 'is_fixed' => 1])
                ->orderBy('id ASC')
                ->all(),
            'title',
            'id'
        );
    }

    /**
     * Find ledger by entity reference.
     * @param int $id Entity ID (client_id, vendor_id, or employee_id)
     * @param int $type 1=client, 2=vendor, 3=employee
     */
    public static function getLedgerDetail(int $id, int $type): ?AccLedger
    {
        $condition = ['status' => 1];
        if ($type === 1) {
            $condition['client_id'] = $id;
        } elseif ($type === 2) {
            $condition['vendor_id'] = $id;
        } elseif ($type === 3) {
            $condition['employee_id'] = $id;
        }
        return AccLedger::findOne($condition);
    }

    /**
     * Create a transaction master record.
     * @return int|false The new record ID, or false on failure
     */
    public static function addTransactionMaster(
        int     $type,
        ?int    $refId = null,
        ?string $transactionDate = null,
        ?string $title = null,
        ?string $notes = null,
        ?string $refInvoiceNumber = null
    ): int|false {
        $model = new AccTransactionsMaster();
        $model->transaction_date    = $transactionDate ? date('Y-m-d H:i:s', strtotime($transactionDate)) : date('Y-m-d H:i:s');
        $model->title               = $title;
        $model->notes               = $notes;
        $model->ref_invoice_number  = $refInvoiceNumber;
        $model->type                = $type;
        $model->invoice_id          = ($type === 1) ? $refId : null;
        $model->inward_invoice_id   = ($type === 2) ? $refId : null;
        $model->employee_id         = ($type === 3) ? $refId : null;
        $model->payslip_id          = null;
        $model->created_at          = date('Y-m-d H:i:s');
        $model->created_by          = Yii::$app->user->id ?? null;
        $model->status              = 1;

        return $model->save(false) ? $model->id : false;
    }

    /**
     * Create a ledger transaction and update ledger balance.
     * @param int $transactionMasterId
     * @param int $transactionType 1=credit, 2=debit
     * @param int|null $ledgerId
     * @param float $amount
     * @return int|false The new transaction ID, or false on failure
     */
    public static function addLedgerTransaction(
        int     $transactionMasterId,
        int     $transactionType,
        ?int    $ledgerId,
        float   $amount,
        ?string $notes = null,
        ?string $refInvoiceNumber = null,
        ?string $transactionDate = null
    ): int|false {
        if (!$ledgerId) return false;

        $model = new AccTransactions();
        $model->transaction_master_id = $transactionMasterId;
        $model->transaction_type      = $transactionType;
        $model->ledger_id             = $ledgerId;
        $model->amount                = sprintf('%.2f', $amount);
        $model->notes                 = $notes;
        $model->ref_invoice_number    = $refInvoiceNumber;
        $model->transaction_date      = $transactionDate ? date('Y-m-d H:i:s', strtotime($transactionDate)) : date('Y-m-d H:i:s');
        $model->created_at            = date('Y-m-d H:i:s');
        $model->created_by            = Yii::$app->user->id ?? null;
        $model->status                = 1;

        if ($model->save(false)) {
            self::updateLedgerBalance($model);
            return $model->id;
        }
        return false;
    }

    /**
     * Update the ledger's credit/debit/balance totals after a transaction.
     */
    public static function updateLedgerBalance(AccTransactions $txn): void
    {
        $ledger = AccLedger::findOne($txn->ledger_id);
        if (!$ledger) return;

        $credit = max((float)$ledger->credit_balance, 0);
        $debit  = max((float)$ledger->debit_balance, 0);

        if ((int)$txn->transaction_type === 1) { // credit
            $credit += (float)$txn->amount;
        } else { // debit
            $debit += (float)$txn->amount;
        }

        $ledger->credit_balance = sprintf('%.2f', $credit);
        $ledger->debit_balance  = sprintf('%.2f', $debit);
        $ledger->balance        = sprintf('%.2f', $debit - $credit);
        $ledger->save(false);
    }

    /**
     * Create journal entries for a set of journal data.
     * @param int $transactionMasterId
     * @param array $entries Array of ['ref_transaction_id'=>, 'source_ledger_id'=>, 'ledger_id'=>, 'amount'=>, 'entry_type'=>]
     */
    public static function addJournalEntries(int $transactionMasterId, array $entries): void
    {
        foreach ($entries as $entry) {
            $j = new AccJournal();
            $j->transaction_master_id = $transactionMasterId;
            $j->ref_transaction_id    = $entry['ref_transaction_id'];
            $j->source_ledger_id      = $entry['source_ledger_id'];
            $j->ledger_id             = $entry['ledger_id'];
            $j->amount                = sprintf('%.2f', $entry['amount']);
            $j->entry_type            = $entry['entry_type'];
            $j->created_at            = date('Y-m-d H:i:s');
            $j->created_by            = Yii::$app->user->id ?? null;
            $j->status                = 1;
            $j->save(false);
        }
    }

    /**
     * Create GL entries for an outward invoice (Tax Invoice only).
     * Port of Yii1 Utility::accountsLedgerEntryForOutInvoice().
     *
     * @param \backend\models\Invoice $invoice
     * @return bool
     */
    public static function accountsLedgerEntryForOutInvoice($invoice): bool
    {
        if ((int)$invoice->invoice_role !== 1) {
            return false; // Only for Tax Invoices
        }

        $tmId = self::addTransactionMaster(
            1,
            $invoice->id,
            $invoice->invoice_date,
            $invoice->title,
            null,
            $invoice->invoice_no
        );
        if (!$tmId) return false;

        $clientLedger = self::getLedgerDetail((int)$invoice->client_id, 1);
        if (!$clientLedger) return false;

        $clientLedgerId = $clientLedger->id;
        $staticLedgers  = self::getStaticLedgerIds();

        $itSale  = $staticLedgers['Sales'] ?? $staticLedgers['IT Sale'] ?? null; // sales ledger (older databases call it "IT Sale")
        $outIgst = $staticLedgers['OUT IGST'] ?? null;
        $outCgst = $staticLedgers['OUT CGST'] ?? null;
        $outSgst = $staticLedgers['OUT SGST'] ?? null;

        $grandAmount = (float)$invoice->grand_total;
        $netAmount   = (float)$invoice->net_total;

        // International invoice: convert to INR
        if ((int)$invoice->invoice_type === 3 && (float)$invoice->currency_exchange_rate > 0) {
            $netAmount   *= (float)$invoice->currency_exchange_rate;
            $grandAmount *= (float)$invoice->currency_exchange_rate;
        }

        $journalEntries     = [];
        $lastDebitTxnId     = 0;

        // Debit client ledger with net amount (includes GST)
        if ($netAmount > 0) {
            $lastDebitTxnId = self::addLedgerTransaction(
                $tmId, 2, $clientLedgerId, $netAmount,
                null, $invoice->invoice_no, $invoice->invoice_date
            );
        }

        // Credit IT Sales ledger with grand total (before GST)
        if ($grandAmount > 0 && $itSale) {
            $lastCreditTxnId = self::addLedgerTransaction(
                $tmId, 1, $itSale, $grandAmount,
                null, $invoice->invoice_no, $invoice->invoice_date
            );
            if ($lastCreditTxnId) {
                if ($lastDebitTxnId) {
                    $journalEntries[] = [
                        'ref_transaction_id' => $lastDebitTxnId,
                        'source_ledger_id'   => $clientLedgerId,
                        'ledger_id'          => $itSale,
                        'amount'             => $grandAmount,
                        'entry_type'         => 2,
                    ];
                }
                $journalEntries[] = [
                    'ref_transaction_id' => $lastCreditTxnId,
                    'source_ledger_id'   => $itSale,
                    'ledger_id'          => $clientLedgerId,
                    'amount'             => $grandAmount,
                    'entry_type'         => 1,
                ];
            }
        }

        // Interstate client (type=1): IGST entry
        if ((int)$invoice->invoice_type === 1 && $outIgst && (float)$invoice->igst_amount > 0) {
            $igstAmt = (float)$invoice->igst_amount;
            if ((int)$invoice->invoice_type === 3) {
                $igstAmt *= (float)$invoice->currency_exchange_rate;
            }
            $creditTxnId = self::addLedgerTransaction(
                $tmId, 1, $outIgst, $igstAmt,
                null, $invoice->invoice_no, $invoice->invoice_date
            );
            if ($creditTxnId) {
                if ($lastDebitTxnId) {
                    $journalEntries[] = [
                        'ref_transaction_id' => $lastDebitTxnId,
                        'source_ledger_id'   => $clientLedgerId,
                        'ledger_id'          => $outIgst,
                        'amount'             => $igstAmt,
                        'entry_type'         => 2,
                    ];
                }
                $journalEntries[] = [
                    'ref_transaction_id' => $creditTxnId,
                    'source_ledger_id'   => $outIgst,
                    'ledger_id'          => $clientLedgerId,
                    'amount'             => $igstAmt,
                    'entry_type'         => 1,
                ];
            }
        }

        // Intrastate client (type=2): CGST + SGST entries
        if ((int)$invoice->invoice_type === 2) {
            // CGST
            if ($outCgst && (float)$invoice->cgst_amount > 0) {
                $creditTxnId = self::addLedgerTransaction(
                    $tmId, 1, $outCgst, (float)$invoice->cgst_amount,
                    null, $invoice->invoice_no, $invoice->invoice_date
                );
                if ($creditTxnId) {
                    if ($lastDebitTxnId) {
                        $journalEntries[] = [
                            'ref_transaction_id' => $lastDebitTxnId,
                            'source_ledger_id'   => $clientLedgerId,
                            'ledger_id'          => $outCgst,
                            'amount'             => (float)$invoice->cgst_amount,
                            'entry_type'         => 2,
                        ];
                    }
                    $journalEntries[] = [
                        'ref_transaction_id' => $creditTxnId,
                        'source_ledger_id'   => $outCgst,
                        'ledger_id'          => $clientLedgerId,
                        'amount'             => (float)$invoice->cgst_amount,
                        'entry_type'         => 1,
                    ];
                }
            }
            // SGST
            if ($outSgst && (float)$invoice->sgst_amount > 0) {
                $creditTxnId = self::addLedgerTransaction(
                    $tmId, 1, $outSgst, (float)$invoice->sgst_amount,
                    null, $invoice->invoice_no, $invoice->invoice_date
                );
                if ($creditTxnId) {
                    if ($lastDebitTxnId) {
                        $journalEntries[] = [
                            'ref_transaction_id' => $lastDebitTxnId,
                            'source_ledger_id'   => $clientLedgerId,
                            'ledger_id'          => $outSgst,
                            'amount'             => (float)$invoice->sgst_amount,
                            'entry_type'         => 2,
                        ];
                    }
                    $journalEntries[] = [
                        'ref_transaction_id' => $creditTxnId,
                        'source_ledger_id'   => $outSgst,
                        'ledger_id'          => $clientLedgerId,
                        'amount'             => (float)$invoice->sgst_amount,
                        'entry_type'         => 1,
                    ];
                }
            }
        }

        // Create all journal entries
        if (!empty($journalEntries)) {
            self::addJournalEntries($tmId, $journalEntries);
        }

        return true;
    }
}
