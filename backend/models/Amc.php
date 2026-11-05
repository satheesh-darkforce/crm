<?php

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * AMC (Annual Maintenance Charge) contract for a completed project.
 *
 * Table: amc
 *
 * The initial warranty period lives here: `warranty_period` (months) and
 * `end_date` (= start_date + warranty_period months). Once that warranty
 * period ends, the current end date is read from the latest amc_renew row.
 *
 * @property int    $id
 * @property int    $project_id
 * @property int    $client_id
 * @property string $start_date
 * @property int    $warranty_period   months
 * @property string $end_date          auto-calculated, not shown on the form
 * @property int    $charge_type       1 = Percentage, 2 = Amount
 * @property float  $percentage
 * @property float  $amount
 * @property float  $estimation_cost   snapshot of project.total_estimation_cost
 * @property string $description
 * @property int    $amc_status        1 = Active, 2 = Ended
 * @property int    $status            1 = active row, 0 = soft-deleted
 * @property int    $created_by
 * @property string $created_date
 * @property string $updated_date
 *
 * @property Project    $project
 * @property ClientModel $client
 * @property AmcRenew[]  $renews
 */
class Amc extends ActiveRecord
{
    const STATUS_ACTIVE   = 1;
    const STATUS_DELETED  = 0;

    // amc_status (lifecycle)
    const AMC_ACTIVE = 1;
    const AMC_ENDED  = 2;

    // charge_type
    const CHARGE_PERCENTAGE = 1;
    const CHARGE_AMOUNT     = 2;

    public static function tableName(): string
    {
        return 'amc';
    }

    public function rules(): array
    {
        return [
            [['project_id', 'client_id', 'start_date', 'warranty_period', 'charge_type'], 'required'],
            [['project_id', 'client_id', 'warranty_period', 'charge_type', 'amc_status', 'status', 'created_by'], 'integer'],
            // Warranty may be 0 = no warranty (AMC active immediately from start date).
            [['warranty_period'], 'integer', 'min' => 0, 'max' => 600,
                'tooSmall' => 'Warranty period cannot be negative.', 'tooBig' => 'Warranty period cannot exceed 600 months.'],
            [['description'], 'trim'],
            [['description'], 'string', 'max' => 2000],
            [['charge_type'], 'in', 'range' => [self::CHARGE_PERCENTAGE, self::CHARGE_AMOUNT]],

            // Percentage required only when charge_type = Percentage
            [['percentage'], 'required',
                'when'       => fn($m) => (int)$m->charge_type === self::CHARGE_PERCENTAGE,
                'whenClient' => "function(a,v){return jQuery('#amc-charge_type').val()=='1';}"],
            [['percentage'], 'number', 'min' => 0, 'max' => 100,
                'tooBig' => 'Percentage cannot exceed 100.'],

            // Amount required only when charge_type = Amount
            [['amount'], 'required',
                'when'       => fn($m) => (int)$m->charge_type === self::CHARGE_AMOUNT,
                'whenClient' => "function(a,v){return jQuery('#amc-charge_type').val()=='2';}"],
            [['amount'], 'number', 'min' => 0, 'max' => 9999999999],

            [['estimation_cost'], 'number'],
            [['description'], 'string'],
            [['start_date'], 'date', 'format' => 'php:Y-m-d'],
            [['end_date', 'created_date', 'updated_date'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'project_id'      => 'Project',
            'client_id'       => 'Client',
            'start_date'      => 'Start Date',
            'warranty_period' => 'Warranty Period (months)',
            'end_date'        => 'End Date',
            'charge_type'     => 'Charge Type',
            'percentage'      => 'Percentage (%)',
            'amount'          => 'Amount',
            'estimation_cost' => 'Total Estimation Cost',
            'description'     => 'Description',
        ];
    }

    /**
     * Compute end_date from start_date + warranty_period (months) before save,
     * since end_date is never entered on the form.
     */
    public function beforeValidate(): bool
    {
        // warranty_period may be 0 (no warranty) — end_date then equals start_date.
        if ($this->start_date !== null && $this->start_date !== '' && $this->warranty_period !== null && $this->warranty_period !== '') {
            $this->end_date = date('Y-m-d', strtotime('+' . (int)$this->warranty_period . ' months', strtotime($this->start_date)));
        }
        return parent::beforeValidate();
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_date = $now;
            if ($this->created_by === null) {
                $this->created_by = Yii::$app->user->id;
            }
        }
        $this->updated_date = $now;
        return true;
    }

    public function getProject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getRenews(): \yii\db\ActiveQuery
    {
        return $this->hasMany(AmcRenew::class, ['amc_id' => 'id'])
            ->andWhere(['status' => self::STATUS_ACTIVE])
            ->orderBy(['end_date' => SORT_ASC]);
    }

    public function getChargeTypeLabel(): string
    {
        return (int)$this->charge_type === self::CHARGE_PERCENTAGE ? 'Percentage' : 'Amount';
    }

    public function getChargeValueLabel(): string
    {
        if ((int)$this->charge_type === self::CHARGE_PERCENTAGE) {
            return rtrim(rtrim((string)$this->percentage, '0'), '.') . ' %';
        }
        return number_format((float)$this->amount, 2);
    }

    public function getAmcStatusLabel(): string
    {
        return (int)$this->amc_status === self::AMC_ENDED ? 'Ended' : 'Active';
    }

    /**
     * The current/live end date: the latest renewal end date if any renewals
     * exist, otherwise the original warranty end date.
     */
    public function getCurrentEndDate(): ?string
    {
        $latestRenew = AmcRenew::find()
            ->where(['amc_id' => $this->id, 'status' => self::STATUS_ACTIVE])
            ->orderBy(['end_date' => SORT_DESC])
            ->one();
        return $latestRenew ? $latestRenew->end_date : $this->end_date;
    }

    /**
     * Warranty status label based on today vs the original warranty end_date.
     * warranty_period 0 means there is no warranty — AMC is active immediately.
     */
    public function getWarrantyStatusLabel(): string
    {
        if ((int)$this->warranty_period === 0) {
            return 'No Warranty';
        }
        if (!$this->end_date) {
            return '—';
        }
        return strtotime($this->end_date) >= strtotime(date('Y-m-d'))
            ? 'Under Warranty'
            : 'Warranty Ended';
    }
}
