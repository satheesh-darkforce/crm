<?php
namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Model for table "upload_payslip".
 *
 * @property int    $id
 * @property string $month_year
 * @property string $file_path
 * @property int    $uploaded_by
 * @property string $uploaded_date
 * @property string $developer_id   JSON-encoded array of developer user IDs
 */
class UploadPayslip extends ActiveRecord
{
    /** @var \yii\web\UploadedFile Virtual attribute for CSV file upload validation */
    public $csvFile;

    public static function tableName(): string { return 'upload_payslip'; }

    public function rules(): array
    {
        return [
            [['month_year', 'file_path'], 'required'],
            [['month_year', 'uploaded_date', 'developer_id'], 'safe'],
            [['uploaded_by'], 'integer'],
            [['file_path'], 'string', 'max' => 500],
            [['month_year'], 'validateMonth'],
            [['csvFile'], 'file', 'extensions' => 'csv', 'skipOnEmpty' => true],
        ];
    }

    public function validateMonth($attribute, $params): void
    {
        if (empty($this->month_year)) {
            $this->addError('month_year', 'Month cannot be empty.');
            return;
        }
        $exists = static::find()
            ->where(['month_year' => $this->month_year])
            ->andFilterWhere(['!=', 'id', $this->id])
            ->exists();
        if ($exists) {
            $this->addError('month_year', 'Payslip already uploaded for "' . $this->month_year . '".');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'month_year'    => 'Month',
            'file_path'     => 'Upload Payslip',
            'uploaded_by'   => 'Uploaded By',
            'uploaded_date' => 'Uploaded Date',
            'developer_id'  => 'Developer IDs',
        ];
    }

    public function getUploadedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'uploaded_by']);
    }
}
