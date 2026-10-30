<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Migrated from Yii1: Project model
 *
 * Table: project
 *
 * @property int    $id
 * @property int    $client_id
 * @property string $title
 * @property string $description
 * @property int    $project_type
 * @property int    $industry_type   1 = IT, 2 = IOT (IOT makes estimation hours optional)
 * @property float  $total_estimation_hours
 * @property float  $total_estimation_cost
 * @property string $employee_id    (text — stores multiple employee IDs)
 * @property int    $main_project_id
 * @property int    $phase_value
 * @property int    $milestone_value
 * @property int    $milestone_hours
 * @property int    $milestone_status
 * @property string $date
 * @property string $lastupdate
 * @property string $live_updated_date  date the project went live (entered when marked Completed); AMC start date defaults to this
 * @property int    $project_status
 * @property int    $amc            1 = enrolled in AMC program, 0 = not
 * @property string $color_code
 * @property int    $status
 */
class Project extends ActiveRecord
{
    const STATUS_ACTIVE   = 1;
    const STATUS_INACTIVE = 0;

    // project_status values
    const PROJECT_STATUS_ACTIVE     = 1;
    const PROJECT_STATUS_COMPLETED  = 2;
    const PROJECT_STATUS_ON_HOLD    = 3;
    const PROJECT_STATUS_CANCELLED  = 4;

    // industry_type values
    const INDUSTRY_IT  = 1;
    const INDUSTRY_IOT = 2;

    public static function tableName(): string
    {
        return 'project';
    }

    public function rules(): array
    {
        return [
            [['title', 'description', 'color_code'], 'trim'],
            [['client_id', 'title', 'description', 'project_type', 'industry_type'], 'required'],
            // Estimation hours required unless the project is IOT (then optional, defaults to 0).
            [['total_estimation_hours'], 'required',
                'when'       => function ($model) { return (int)$model->industry_type !== self::INDUSTRY_IOT; },
                'whenClient' => "function (attribute, value) { return jQuery('#project-industry_type').val() != '2'; }"],
            [['industry_type'], 'in', 'range' => [self::INDUSTRY_IT, self::INDUSTRY_IOT], 'message' => 'Industry Type must be IT or IOT.'],
            [['industry_type'], 'integer'],
            [['total_estimation_cost'], 'required',
                'when'       => function ($model) { return (int)$model->project_type !== 2; },
                'whenClient' => "function (attribute, value) { return jQuery('#project-project_type').val() != '2'; }"],
            [['total_estimation_cost'], 'compare', 'compareValue' => 0, 'operator' => '>', 'type' => 'number',
                'message'    => 'Total Estimation Cost must be greater than 0 for Productive projects.',
                'when'       => function ($model) { return (int)$model->project_type === 1; },
                'whenClient' => "function (attribute, value) { return jQuery('#project-project_type').val() == '1'; }"],
            [['client_id', 'project_type', 'main_project_id', 'phase_value', 'milestone_value', 'milestone_hours', 'milestone_status', 'project_status', 'status'], 'integer'],
            [['project_type'], 'in', 'range' => [1, 2], 'message' => 'Project Type must be Productive or Non Productive.'],
            // total_estimation_hours is stored in SECONDS (hours * 3600). Cap at
            // INT(11) safe max (2,147,483,647) so the DB never overflows.
            // 500,000 hours = 1,800,000,000 seconds — well under the limit.
            [['total_estimation_hours'], 'number', 'min' => 0, 'max' => 1800000000,
                'tooBig' => 'Total Estimation Hours is too large; must be at most 500,000 hours.'],
            [['total_estimation_cost'], 'number', 'min' => 0, 'max' => 9999999999,
                'tooBig' => 'Total Estimation Cost is too large; must be at most 9,999,999,999.'],
            [['phase_value', 'milestone_value', 'milestone_hours'], 'integer', 'min' => 0, 'max' => 2000000000],
            [['description', 'employee_id'], 'string'],
            [['date', 'lastupdate'], 'safe'],
            [['title', 'color_code'], 'string', 'max' => 255],
            ['title', 'unique', 'filter' => ['status' => 1], 'message' => 'Project title already exists.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'title'                   => 'Project Title',
            'client_id'               => 'Client',
            'project_type'            => 'Project Type',
            'industry_type'           => 'Industry Type',
            'project_status'          => 'Status',
            'total_estimation_hours'  => 'Estimation Hours',
            'total_estimation_cost'   => 'Estimation Cost',
            'date'                    => 'Start Date',
        ];
    }

    /**
     * For IOT projects estimation hours is optional and defaults to 0.
     */
    public function beforeValidate(): bool
    {
        if ((int)$this->industry_type === self::INDUSTRY_IOT
            && ($this->total_estimation_hours === null || $this->total_estimation_hours === '')) {
            $this->total_estimation_hours = 0;
        }
        return parent::beforeValidate();
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getTasks(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Task::class, ['project_id' => 'id']);
    }

    public function getWorkReports(): \yii\db\ActiveQuery
    {
        return $this->hasMany(WorkReport::class, ['project_id' => 'id']);
    }

    public function getAttachments(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ProjectAttachments::class, ['project_id' => 'id']);
    }

    public function getStatusLabel(): string
    {
        return match ((int)$this->project_status) {
            self::PROJECT_STATUS_ACTIVE    => 'Active',
            self::PROJECT_STATUS_COMPLETED => 'Completed',
            self::PROJECT_STATUS_ON_HOLD   => 'On Hold',
            self::PROJECT_STATUS_CANCELLED => 'Cancelled',
            default                        => 'Unknown',
        };
    }
}
