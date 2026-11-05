<?php

namespace backend\models;

use backend\components\GuardedAttributes;
use Yii;
use yii\db\ActiveRecord;

/**
 * ActiveRecord for `client_quotation` (merged from delivery DB).
 *
 * @property int         $id
 * @property int|null    $client_id
 * @property int|null    $parent_id
 * @property string      $quote_no
 * @property string|null $version
 * @property string      $quote_date
 * @property string      $estimation_cost
 * @property string      $address
 * @property int         $quoted_by
 * @property int|null    $alternate_quote_by
 * @property int         $quote_for       1=Software, 2=Hardware, 3=Hardware+Software
 * @property string|null $project_name
 * @property string      $created_at
 * @property string      $modified_at
 * @property string|null $cancel_reason
 * @property string|null $alter_reason
 * @property int         $completed_status 0=open, 1=completed (set via Complete Quote action)
 * @property int         $status          0=inactive, 1=active, 2=cancelled
 *
 * @property ClientModel           $client
 * @property ClientQuotationFile[] $files
 * @property PoDetail[]            $poDetails
 * @property ClientQuotation[]     $children
 * @property ClientQuotation       $parent
 */
class ClientQuotation extends ActiveRecord
{
    use GuardedAttributes;

    /** Set by the controller, never by the form. */
    protected static function guardedAttributes(): array
    {
        return ['status', 'completed_status', 'parent_id', 'quote_no', 'quoted_by', 'created_at', 'modified_at', 'cancel_reason'];
    }

    /** Deployment type values for `deployment_type`. */
    public const DEPLOYMENT = 1;
    public const TRIAL      = 2;

    public static function deploymentTypeOptions(): array
    {
        return [
            self::DEPLOYMENT => 'Deployment',
            self::TRIAL      => 'Trial',
        ];
    }

    /** True when this quote is a Trial (estimation cost is optional). */
    public function isTrial(): bool
    {
        return (int) $this->deployment_type === self::TRIAL;
    }

    public static function tableName(): string
    {
        return 'client_quotation';
    }

    public function rules(): array
    {
        return [
            [['client_id', 'parent_id'], 'default', 'value' => null],
            [['completed_status'], 'default', 'value' => 0],
            [['deployment_type'], 'default', 'value' => self::DEPLOYMENT],
            [['client_id', 'parent_id', 'quoted_by', 'alternate_quote_by', 'quote_for', 'completed_status', 'status', 'deployment_type'], 'integer'],
            [['deployment_type'], 'in', 'range' => [self::DEPLOYMENT, self::TRIAL]],
            [['quote_no', 'quote_date', 'quoted_by', 'quote_for', 'created_at',
              'client_id', 'project_name', 'modified_at', 'status', 'address'], 'required'],
            // Estimation cost is mandatory for a Deployment, optional for a Trial.
            [
                ['estimation_cost'], 'required',
                'when' => fn($m) => (int) $m->deployment_type !== self::TRIAL,
                'whenClient' => "function(attribute, value){
                    return $('#clientquotation-deployment_type').val() != '" . self::TRIAL . "';
                }",
            ],
            [
                ['alter_reason'], 'required',
                'when' => fn($m) => !empty($m->parent_id),
                'whenClient' => "function(attribute,value){
                    var p = $('#clientquotation-parent_id');
                    return p.length > 0 && p.val() !== '' && p.val() !== '0';
                }",
            ],
            [['quote_date', 'created_at', 'modified_at'], 'safe'],
            [['quote_no'], 'string', 'max' => 50],
            [['cancel_reason', 'version', 'alter_reason'], 'string', 'max' => 500],
            [['quote_no'], 'unique', 'message' => 'Quotation number must be unique.'],
            [['estimation_cost'], 'integer', 'min' => 1, 'max' => 999999999, 'tooSmall' => 'Estimation cost must be greater than 0.'],
            [['project_name'], 'string', 'max' => 255],
            [['client_id'],           'exist', 'skipOnError' => true, 'targetClass' => ClientModel::class,     'targetAttribute' => ['client_id' => 'id']],
            [['parent_id'],           'exist', 'skipOnError' => true, 'targetClass' => self::class,            'targetAttribute' => ['parent_id' => 'id']],
            [['alternate_quote_by'],  'exist', 'skipOnError' => true, 'targetClass' => User::class,            'targetAttribute' => ['alternate_quote_by' => 'user_id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'client_id' => 'Client Name',
            'parent_id' => 'Parent ID',
            'quote_no' => 'Quote No',
            'quote_date' => 'Quote Date',
            'estimation_cost' => 'Estimation Cost',
            'deployment_type' => 'Deployment Type',
            'quoted_by' => 'Quoted By',
            'quote_for' => 'Quote For',
            'project_name' => 'Project Name',
            'created_at' => 'Created At',
            'modified_at' => 'Modified At',
            'status' => 'Status',
            'address' => 'Address',
            'cancel_reason' => 'Cancel Reason',
            'version' => 'Version',
            'alter_reason' => 'Alter Reason',
            'alternate_quote_by' => 'Additional Quote By',
        ];
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getFiles(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ClientQuotationFile::class, ['quote_id' => 'id'])
                    ->andOnCondition(['status' => 1]);
    }

    public function getChildren(): \yii\db\ActiveQuery
    {
        return $this->hasMany(self::class, ['parent_id' => 'id']);
    }

    public function getParent(): \yii\db\ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    public function getPoDetails(): \yii\db\ActiveQuery
    {
        return $this->hasMany(PoDetail::class, ['quote_id' => 'id'])
                    ->andOnCondition(['po_details.status' => 1]);
    }
}
