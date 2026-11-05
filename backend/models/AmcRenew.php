<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * A renewal of an AMC contract. Created after the parent AMC's warranty (or the
 * previous renewal) ends. Carries no active/ended lifecycle of its own — the
 * parent Amc.amc_status governs that.
 *
 * Table: amc_renew
 *
 * @property int    $id
 * @property int    $amc_id
 * @property int    $project_id
 * @property int    $client_id
 * @property string $start_date
 * @property int    $months
 * @property string $end_date      auto-calculated, not shown on the form
 * @property int    $charge_type   1 = Percentage, 2 = Amount
 * @property float  $percentage
 * @property float  $amount
 * @property string $description
 * @property int    $status        1 = active row, 0 = soft-deleted
 * @property int    $created_by
 * @property string $created_date
 *
 * @property Amc $amc
 */
class AmcRenew extends ActiveRecord
{
    const STATUS_ACTIVE  = 1;
    const STATUS_DELETED = 0;

    public static function tableName(): string
    {
        return 'amc_renew';
    }

    public function rules(): array
    {
        return [
            [['amc_id', 'project_id', 'client_id', 'start_date', 'months', 'charge_type'], 'required'],
            [['amc_id', 'project_id', 'client_id', 'months', 'charge_type', 'status', 'created_by'], 'integer'],
            [['months'], 'integer', 'min' => 1, 'max' => 600,
                'tooSmall' => 'Number of months must be at least 1.', 'tooBig' => 'Number of months cannot exceed 600.'],
            [['description'], 'trim'],
            [['description'], 'string', 'max' => 2000],
            [['start_date'], 'validateAfterContractStart', 'skipOnError' => true],
            [['charge_type'], 'in', 'range' => [Amc::CHARGE_PERCENTAGE, Amc::CHARGE_AMOUNT]],

            [['percentage'], 'required',
                'when'       => fn($m) => (int)$m->charge_type === Amc::CHARGE_PERCENTAGE,
                'whenClient' => "function(a,v){return jQuery('#amcrenew-charge_type').val()=='1';}"],
            [['percentage'], 'number', 'min' => 0, 'max' => 100, 'tooBig' => 'Percentage cannot exceed 100.'],

            [['amount'], 'required',
                'when'       => fn($m) => (int)$m->charge_type === Amc::CHARGE_AMOUNT,
                'whenClient' => "function(a,v){return jQuery('#amcrenew-charge_type').val()=='2';}"],
            [['amount'], 'number', 'min' => 0, 'max' => 9999999999],

            [['description'], 'string'],
            [['start_date'], 'date', 'format' => 'php:Y-m-d'],
            [['end_date', 'created_date'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'start_date'  => 'Renewal Start Date',
            'months'      => 'No. of Months',
            'end_date'    => 'End Date',
            'charge_type' => 'Charge Type',
            'percentage'  => 'Percentage (%)',
            'amount'      => 'Amount',
            'description' => 'Description',
        ];
    }

    /** A renewal cannot begin before the AMC contract itself starts. */
    public function validateAfterContractStart(string $attribute): void
    {
        $amc = Amc::findOne($this->amc_id);
        if ($amc && $amc->start_date && $this->$attribute < $amc->start_date) {
            $this->addError($attribute, 'Renewal Start Date cannot be before the AMC start date ('
                . date('d M Y', strtotime($amc->start_date)) . ').');
        }
    }

    public function beforeValidate(): bool
    {
        if ($this->start_date && $this->months) {
            $this->end_date = date('Y-m-d', strtotime('+' . (int)$this->months . ' months', strtotime($this->start_date)));
        }
        return parent::beforeValidate();
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert) {
            $this->created_date = date('Y-m-d H:i:s');
            if ($this->created_by === null) {
                $this->created_by = Yii::$app->user->id;
            }
        }
        return true;
    }

    public function getAmc(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Amc::class, ['id' => 'amc_id']);
    }

    public function getChargeValueLabel(): string
    {
        if ((int)$this->charge_type === Amc::CHARGE_PERCENTAGE) {
            return rtrim(rtrim((string)$this->percentage, '0'), '.') . ' %';
        }
        return number_format((float)$this->amount, 2);
    }
}
