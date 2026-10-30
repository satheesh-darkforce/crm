<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: currencies
 *
 * @property int    $id
 * @property string $title
 * @property string $currency
 * @property string $in_words
 * @property int    $is_default
 * @property int    $status
 */
class Currency extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'currencies';
    }
}
