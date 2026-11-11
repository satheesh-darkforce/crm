<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Link between a lead and a product category it is interested in.
 *
 * @property int         $id
 * @property int         $lead_id
 * @property int         $category_id
 * @property string|null $created_at
 */
class LeadProductInterest extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'lead_product_interest';
    }

    public function rules(): array
    {
        return [
            [['lead_id', 'category_id'], 'required'],
            [['lead_id', 'category_id'], 'integer'],
        ];
    }
}
