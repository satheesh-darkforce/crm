<?php

use yii\db\Migration;

/**
 * Split PF and ESI salary-management amounts into Employee + Employer parts.
 *
 * PF / ESI each now carry two amounts on the employee (developer) record:
 *   *_employee_amount → deducted from the employee's pay (employee contribution)
 *   *_employer_amount → employer's matching contribution (not deducted)
 *
 * The legacy single pf_amount / esi_amount columns are kept (the employee
 * portion is copied into *_employee_amount) so nothing breaks if they are read.
 *
 * Guarded so the migration is safe to re-run.
 */
class m260610_000002_split_pf_esi_employee_employer extends Migration
{
    public function safeUp()
    {
        $dev    = 'developer';
        $amount = $this->decimal(10, 2)->notNull()->defaultValue(0);
        $schema = $this->db->getTableSchema($dev);

        $cols = [
            'pf_employee_amount'  => 'pf_amount',
            'pf_employer_amount'  => 'pf_amount',
            'esi_employee_amount' => 'esi_amount',
            'esi_employer_amount' => 'esi_amount',
        ];
        foreach ($cols as $name => $after) {
            if (!$schema->getColumn($name)) {
                $this->addColumn($dev, $name, $amount->after($after));
            }
        }

        // Preserve existing single amounts as the employee (deducted) portion.
        $this->execute('UPDATE developer SET pf_employee_amount  = pf_amount  WHERE pf_amount  > 0');
        $this->execute('UPDATE developer SET esi_employee_amount = esi_amount WHERE esi_amount > 0');
    }

    public function safeDown()
    {
        $dev    = 'developer';
        $schema = $this->db->getTableSchema($dev);
        if ($schema === null) {
            return;
        }
        foreach (['pf_employee_amount', 'pf_employer_amount', 'esi_employee_amount', 'esi_employer_amount'] as $col) {
            if ($schema->getColumn($col)) {
                $this->dropColumn($dev, $col);
            }
        }
    }
}
