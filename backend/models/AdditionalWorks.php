<?php
namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $developer_id
 * @property string $date
 * @property string $description
 * @property int    $hours
 * @property int    $created_by
 * @property string $created_date
 * @property int    $status
 */
class AdditionalWorks extends ActiveRecord
{
    public static function tableName(): string { return 'additional_works'; }

    public function rules(): array
    {
        return [
            [['developer_id', 'date', 'hours', 'description'], 'required'],
            [['developer_id', 'hours', 'created_by', 'status'], 'integer'],
            [['date', 'created_date'], 'safe'],
            [['description'], 'string'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'developer_id' => 'Employee',
            'date'         => 'Date',
            'description'  => 'Description',
            'hours'        => 'Hours',
            'created_date' => 'Created Date',
        ];
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }
}
