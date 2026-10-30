<?php

use yii\db\Migration;

/**
 * Adds `deployment_type` to `client_quotation`.
 *
 *   1 = Deployment (estimation cost mandatory)
 *   2 = Trial       (estimation cost optional — when blank it is hidden
 *                    from the preview / downloaded PDF)
 *
 * Default 1 so every existing quotation is treated as a Deployment.
 * Guarded so the migration is safe to re-run (the column is already applied
 * to the live DB via ALTER TABLE — see CLAUDE.md note that `yii migrate` is
 * broken; this file exists for schema parity only).
 */
class m260612_000001_add_deployment_type_to_client_quotation extends Migration
{
    public function safeUp()
    {
        $table  = 'client_quotation';
        $schema = $this->db->getTableSchema($table);

        if ($schema && !$schema->getColumn('deployment_type')) {
            $this->addColumn(
                $table,
                'deployment_type',
                $this->tinyInteger(1)->notNull()->defaultValue(1)
                    ->comment('1=Deployment, 2=Trial')
                    ->after('quote_for')
            );
        }
    }

    public function safeDown()
    {
        $table  = 'client_quotation';
        $schema = $this->db->getTableSchema($table);
        if ($schema && $schema->getColumn('deployment_type')) {
            $this->dropColumn($table, 'deployment_type');
        }
    }
}
