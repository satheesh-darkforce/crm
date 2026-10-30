<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Model for table "acc_ledger_category".
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string|null $title
 * @property int|null $is_fixed
 * @property int $is_payment_ledger
 * @property string|null $created_at
 * @property int|null $created_by
 * @property string|null $updated_at
 * @property int|null $updated_by
 * @property int $status
 */
class AccLedgerCategory extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'acc_ledger_category';
    }

    public function rules(): array
    {
        return [
            [['parent_id', 'is_fixed', 'is_payment_ledger', 'created_by', 'updated_by', 'status'], 'integer'],
            [['title'], 'string', 'max' => 255],
            [['created_at', 'updated_at'], 'safe'],
            [['status'], 'default', 'value' => 1],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'parent_id' => 'Parent',
            'title' => 'Title',
            'is_fixed' => 'Is Fixed',
            'is_payment_ledger' => 'Is Payment Ledger',
            'created_at' => 'Created At',
            'created_by' => 'Created By',
            'updated_at' => 'Updated At',
            'updated_by' => 'Updated By',
            'status' => 'Status',
        ];
    }

    public function getParent(): \yii\db\ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getChildren(): \yii\db\ActiveQuery
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getLedgers(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AccLedger::class, ['category_id' => 'id']);
    }
}
