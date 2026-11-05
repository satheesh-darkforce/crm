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

    // project_status values (Yii1: 1=Inprogress, 2=Waiting to assign, 3=Completed, 4=Hold)
    const PROJECT_STATUS_IN_PROGRESS = 1;
    const PROJECT_STATUS_WAITING     = 2;
    const PROJECT_STATUS_COMPLETED   = 3;
    const PROJECT_STATUS_HOLD        = 4;
    /** Default status for new projects (kept for existing callers). */
    const PROJECT_STATUS_ACTIVE      = self::PROJECT_STATUS_IN_PROGRESS;

    const TYPE_PRODUCTIVE     = 1;
    const TYPE_NON_PRODUCTIVE = 2;

    /** Hours are entered in hours and stored in seconds; cap keeps INT(11) safe. */
    const MAX_ESTIMATION_HOURS = 500000;
    const MAX_ESTIMATION_COST  = 9999999999;
    const MAX_PHASE            = 15;
    const MAX_MILESTONE        = 15;

    // industry_type values
    const INDUSTRY_IT  = 1;
    const INDUSTRY_IOT = 2;

    /** Add/Edit Project form: only these fields can be posted (status, phase links etc. are server-set). */
    const SCENARIO_FORM = 'form';

    public static function tableName(): string
    {
        return 'project';
    }

    public function scenarios(): array
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_FORM] = [
            'client_id', 'title', 'description', 'project_type', 'industry_type',
            'total_estimation_hours', 'total_estimation_cost', 'employee_id',
        ];
        return $scenarios;
    }

    public function rules(): array
    {
        // Most projects migrated from Yii1 have no estimation hours/cost (Yii1 never
        // required them). They are required for new projects and cannot be blanked
        // once set, but a legacy project without them stays editable. The form
        // marks legacy-empty inputs with data-legacy-empty for the JS mirror.
        $estimateRequired = fn(string $attr) => fn($m) => $m->isNewRecord
            || ($m->getOldAttribute($attr) !== null && $m->getOldAttribute($attr) !== '' && (float)$m->getOldAttribute($attr) > 0);
        $estimateRequiredClient = fn(string $id, string $extra) =>
            "function (attribute, value) { return !jQuery('#$id').data('legacy-empty') && ($extra); }";

        return [
            [['title', 'description', 'color_code'], 'trim'],
            [['client_id', 'title', 'description', 'project_type', 'industry_type'], 'required'],
            // Estimation hours required unless the project is IOT (then optional, defaults to 0).
            [['total_estimation_hours'], 'required',
                'when'       => fn($m) => (int)$m->industry_type !== self::INDUSTRY_IOT && $estimateRequired('total_estimation_hours')($m),
                'whenClient' => $estimateRequiredClient('total_estimation_hours', "jQuery('#project-industry_type').val() != '2'")],
            [['industry_type'], 'in', 'range' => [self::INDUSTRY_IT, self::INDUSTRY_IOT], 'message' => 'Industry Type must be IT or IOT.'],
            [['industry_type'], 'integer'],
            [['total_estimation_cost'], 'required',
                'when'       => fn($m) => (int)$m->project_type !== self::TYPE_NON_PRODUCTIVE && $estimateRequired('total_estimation_cost')($m),
                'whenClient' => $estimateRequiredClient('total_estimation_cost', "jQuery('#project-project_type').val() != '2'")],
            [['total_estimation_cost'], 'compare', 'compareValue' => 0, 'operator' => '>', 'type' => 'number',
                'message'    => 'Total Estimation Cost must be greater than 0 for Productive projects.',
                'when'       => fn($m) => (int)$m->project_type === self::TYPE_PRODUCTIVE
                    && ($estimateRequired('total_estimation_cost')($m) || ($m->total_estimation_cost !== null && $m->total_estimation_cost !== '')),
                'whenClient' => "function (attribute, value) { return jQuery('#project-project_type').val() == '1' && (value !== '' || !jQuery('#total_estimation_cost').data('legacy-empty')); }"],
            [['client_id', 'project_type', 'main_project_id', 'phase_value', 'milestone_value', 'milestone_hours', 'milestone_status', 'project_status', 'status'], 'integer'],
            [['project_type'], 'in', 'range' => [1, 2], 'message' => 'Project Type must be Productive or Non Productive.'],
            [['project_status'], 'in', 'range' => array_keys(self::statusOptions())],
            [['client_id'], 'exist', 'targetClass' => ClientModel::class, 'targetAttribute' => 'id', 'filter' => ['status' => 1],
                'message' => 'Select an active client.', 'when' => fn($m) => $m->isNewRecord || (int)$m->getOldAttribute('client_id') !== (int)$m->client_id],
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
            ['title', 'unique', 'filter' => ['status' => 1], 'message' => 'Project title already exists.',
                'when' => fn($m) => $m->isNewRecord || strcasecmp(trim((string)$m->getOldAttribute('title')), (string)$m->title) !== 0],
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

    public function getStatusLabel(): string
    {
        return self::statusOptions()[(int)$this->project_status] ?? 'Unknown';
    }

    public static function statusOptions(): array
    {
        return [
            self::PROJECT_STATUS_IN_PROGRESS => 'In progress',
            self::PROJECT_STATUS_WAITING     => 'Waiting to assign',
            self::PROJECT_STATUS_COMPLETED   => 'Completed',
            self::PROJECT_STATUS_HOLD        => 'Hold',
        ];
    }

    /** Pill tone for a project status (see .ui-pill in page-ui.css). */
    public static function statusTone(int $status): string
    {
        return [
            self::PROJECT_STATUS_IN_PROGRESS => 'accent',
            self::PROJECT_STATUS_WAITING     => 'warning',
            self::PROJECT_STATUS_COMPLETED   => 'success',
            self::PROJECT_STATUS_HOLD        => 'danger',
        ][$status] ?? 'neutral';
    }

    public static function typeOptions(): array
    {
        return [self::TYPE_PRODUCTIVE => 'Productive', self::TYPE_NON_PRODUCTIVE => 'Non Productive'];
    }

    public static function industryOptions(): array
    {
        return [self::INDUSTRY_IT => 'IT', self::INDUSTRY_IOT => 'IOT'];
    }

    /** Phase / milestone progress (milestone_status). */
    public static function milestoneStatusOptions(): array
    {
        return [0 => 'Not started', 1 => 'In progress', 2 => 'Delivered', 3 => 'Closed'];
    }

    public static function milestoneStatusTone(int $status): string
    {
        return [0 => 'neutral', 1 => 'accent', 2 => 'success', 3 => 'neutral'][$status] ?? 'neutral';
    }

    /** Estimation hours for display/editing (stored in seconds); raw value if it is not numeric. */
    public function getEstimationHoursInput(): string
    {
        $v = $this->total_estimation_hours;
        if ($v === null || $v === '') {
            return '';
        }
        return is_numeric($v) ? rtrim(rtrim(number_format((float)$v / 3600, 2, '.', ''), '0'), '.') : (string)$v;
    }
}
