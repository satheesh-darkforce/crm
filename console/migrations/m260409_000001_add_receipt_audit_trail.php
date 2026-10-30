<?php

use yii\db\Migration;

/**
 * Add deleted_by and deleted_at columns to invoice_receipt for audit trail.
 */
class m260409_000001_add_receipt_audit_trail extends Migration
{
    public function up(): void
    {
        $this->addColumn('invoice_receipt', 'deleted_by', $this->integer()->null()->defaultValue(null)->after('status'));
        $this->addColumn('invoice_receipt', 'deleted_at', $this->dateTime()->null()->defaultValue(null)->after('deleted_by'));
    }

    public function down(): void
    {
        $this->dropColumn('invoice_receipt', 'deleted_at');
        $this->dropColumn('invoice_receipt', 'deleted_by');
    }
}
