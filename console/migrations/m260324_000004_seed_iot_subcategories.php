<?php

use yii\db\Migration;

class m260324_000004_seed_iot_subcategories extends Migration
{
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');

        // Map: category_id => [subcategory names]
        $seeds = [
            1  => ['General', 'Accessories'],
            2  => ['General', 'Accessories'],
            3  => ['General', 'Accessories'],
            23 => ['Access Points', 'Modems', 'Cables & Connectors'],
        ];

        foreach ($seeds as $catId => $names) {
            // Verify category exists
            $exists = $this->db->createCommand(
                'SELECT COUNT(*) FROM iot_category WHERE id = :id AND status = 1',
                [':id' => $catId]
            )->queryScalar();
            if (!$exists) continue;

            foreach ($names as $name) {
                // Skip if already exists
                $dup = $this->db->createCommand(
                    'SELECT COUNT(*) FROM iot_subcategory WHERE category_id = :cid AND name = :name',
                    [':cid' => $catId, ':name' => $name]
                )->queryScalar();
                if ($dup) continue;

                $this->insert('iot_subcategory', [
                    'name'        => $name,
                    'category_id' => $catId,
                    'status'      => 1,
                    'created_at'  => $now,
                ]);
            }
        }
    }

    public function safeDown()
    {
        echo "safeDown not supported for seed migration.\n";
    }
}
