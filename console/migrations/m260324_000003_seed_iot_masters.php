<?php

use yii\db\Migration;

class m260324_000003_seed_iot_masters extends Migration
{
    public function safeUp()
    {
        $now = date('Y-m-d H:i:s');

        // Category
        $this->insert('iot_category', ['name' => 'Networking', 'status' => 1, 'created_at' => $now]);
        $catId = $this->db->getLastInsertID();

        // Sub-categories
        $this->insert('iot_subcategory', ['name' => 'Routers', 'category_id' => $catId, 'status' => 1, 'created_at' => $now]);
        $this->insert('iot_subcategory', ['name' => 'Switches', 'category_id' => $catId, 'status' => 1, 'created_at' => $now]);

        // Brand
        $this->insert('iot_brand', ['brand_name' => 'TP-Link', 'status' => 1, 'created_at' => $now]);

        // Location
        $this->insert('iot_location', ['location' => 'Server Room', 'status' => 1, 'created_at' => $now]);

        // Tag
        $this->insert('iot_tags', ['name' => 'Network', 'status' => 1, 'created_at' => $now]);
    }

    public function safeDown()
    {
        echo "safeDown not supported for seed migration.\n";
    }
}
