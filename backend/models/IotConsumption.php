<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $project_id
 * @property int    $developer_id
 * @property int    $consumption_type   (1=Issued, 2=Return)
 * @property int    $consumption_status (0=Pending, 1=Approved, 2=Rejected)
 * @property string $created_at
 * @property string $requested_date
 * @property string $approved_at
 * @property int    $approved_by
 * @property int    $status
 */
class IotConsumption extends ActiveRecord
{
    public static function tableName(): string { return 'iot_consumption'; }

    public function rules(): array
    {
        return [
            [['project_id', 'developer_id', 'consumption_type', 'requested_date'], 'required'],
            [['project_id', 'developer_id', 'consumption_type', 'consumption_status', 'approved_by', 'status'], 'integer'],
            [['consumption_type'], 'in', 'range' => [1, 2]],
            [['consumption_status'], 'in', 'range' => [0, 1, 2]],
            [['created_at', 'approved_at'], 'safe'],
            [['requested_date'], 'date', 'format' => 'php:Y-m-d'],
            [['requested_date'], 'compare', 'compareValue' => date('Y-m-d'), 'operator' => '>=', 'message' => 'Required date cannot be in the past.'],
        ];
    }

    /**
     * Normalize requested_date to a plain Y-m-d before validation so the
     * `date` rule and the DatePicker (yyyy-mm-dd) always agree, regardless of
     * whether a datetime arrived from the DB (afterFind) or a prior submit.
     * Defaults to today when empty — matches Yii1 (which had no required rule
     * and fell back to date('Y-m-d')).
     */
    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }
        if (empty($this->requested_date)) {
            $this->requested_date = date('Y-m-d');
        } elseif (($ts = strtotime((string)$this->requested_date)) !== false) {
            $this->requested_date = date('Y-m-d', $ts);
        }
        return true;
    }

    /**
     * Store requested_date as a full datetime (Y-m-d H:i:s) to match the Yii1
     * column format. Conversion happens after validation has already passed
     * on the plain date.
     */
    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if (!empty($this->requested_date) && ($ts = strtotime((string)$this->requested_date)) !== false) {
            $this->requested_date = date('Y-m-d H:i:s', $ts);
        }
        return true;
    }

    /**
     * Strip the time portion when loading so edit forms / listings show a
     * clean Y-m-d that the DatePicker can parse.
     */
    public function afterFind(): void
    {
        parent::afterFind();
        if (!empty($this->requested_date) && ($ts = strtotime((string)$this->requested_date)) !== false) {
            $this->requested_date = date('Y-m-d', $ts);
        }
    }

    public function attributeLabels(): array
    {
        return [
            'project_id' => 'Project',
            'developer_id' => 'Developer',
            'consumption_type' => 'Type',
            'consumption_status' => 'Status',
            'requested_date' => 'Required Date',
            'approved_at' => 'Approved At',
            'approved_by' => 'Approved By',
        ];
    }

    public function getProject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getDeveloper(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Developer::class, ['id' => 'developer_id']);
    }

    public function getApprovedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'approved_by']);
    }

    public function getProducts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(IotConsumptionProducts::class, ['consumption_id' => 'id']);
    }
}
