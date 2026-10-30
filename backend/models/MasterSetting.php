<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Table: master_settings — single-row configuration (id=1 always)
 *
 * Note: sgst_percent / cgst_percent / igst_percent columns are NOT yet present
 * in the master_settings table; the accessors below fall back to defaults when
 * the columns are absent (use hasAttribute() so we don't trigger __get()).
 *
 * @property int    $id
 * @property string $invoice_lut_arn
 * @property int    $updated_by
 * @property string $updated_date
 * @property int    $status
 */
class MasterSetting extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'master_settings';
    }

    public function rules(): array
    {
        return [
            [['invoice_lut_arn'], 'string', 'max' => 50],
            [['updated_by', 'status'], 'integer'],
            [['updated_date'], 'safe'],
        ];
    }

    /**
     * Get SGST rate — falls back to 9 if column not yet migrated to DB.
     */
    public function getSgstRate(): float
    {
        if ($this->hasAttribute('sgst_percent')) {
            $v = $this->getAttribute('sgst_percent');
            if ($v !== null && $v !== '') {
                return (float)$v;
            }
        }
        return 9.0;
    }

    /**
     * Get CGST rate — falls back to 9 if column not yet migrated to DB.
     */
    public function getCgstRate(): float
    {
        if ($this->hasAttribute('cgst_percent')) {
            $v = $this->getAttribute('cgst_percent');
            if ($v !== null && $v !== '') {
                return (float)$v;
            }
        }
        return 9.0;
    }

    /**
     * Get IGST rate — falls back to 18 if column not yet migrated to DB.
     */
    public function getIgstRate(): float
    {
        if ($this->hasAttribute('igst_percent')) {
            $v = $this->getAttribute('igst_percent');
            if ($v !== null && $v !== '') {
                return (float)$v;
            }
        }
        return 18.0;
    }

    public function attributeLabels(): array
    {
        return [
            'invoice_lut_arn' => 'Invoice LUT / ARN Number',
        ];
    }

    public static function getInstance(): self
    {
        $model = self::findOne(1);
        if (!$model) {
            $model = new self();
            $model->id     = 1;
            $model->status = 1;
            $model->save(false);
        }
        return $model;
    }
}
