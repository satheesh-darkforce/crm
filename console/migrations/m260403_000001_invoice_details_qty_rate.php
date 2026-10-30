<?php

use yii\db\Migration;

/**
 * INV-011: Add quantity and unit_rate columns to invoice_details.
 * Allows storing line-item qty and rate separately from the computed amount.
 */
class m260403_000001_invoice_details_qty_rate extends Migration
{
    public function up(): void
    {
        $this->addColumn('invoice_details', 'quantity',  $this->decimal(12, 4)->null()->defaultValue(null)->after('sac'));
        $this->addColumn('invoice_details', 'unit_rate', $this->decimal(12, 2)->null()->defaultValue(null)->after('quantity'));
    }

    public function down(): void
    {
        $this->dropColumn('invoice_details', 'quantity');
        $this->dropColumn('invoice_details', 'unit_rate');
    }
}
