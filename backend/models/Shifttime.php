<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Shifttime.
 * Table: shifttime
 *
 * Each branch has one shift row; intime/outtime are TIME columns.
 *
 * @property int    $id
 * @property int    $branch
 * @property string $intime
 * @property string $outtime
 * @property string $created_date
 * @property int    $status
 */
class Shifttime extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'shifttime';
    }

    public function rules(): array
    {
        return [
            [['branch', 'intime', 'outtime'], 'required'],
            [['branch', 'status'], 'integer'],
            [['intime', 'outtime', 'created_date'], 'safe'],
        ];
    }
}
