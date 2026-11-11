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
        return ['status', 'completed_status', 'parent_id', 'quote_no', 'quoted_by', 'created_at', 'modified_at', 'cancel_reason',
            // set by the server from the line items / status actions
            'quote_status', 'sub_total', 'discount_total', 'taxable_total', 'gst_total', 'grand_total', 'invoice_id'];
    }

    /** Deployment type values for `deployment_type`. */
    /** Sales status of the quote (quote_status). "Expired" is shown when a Draft/Sent quote is past valid_until. */
    public const QS_DRAFT    = 1;
    public const QS_SENT     = 2;
    public const QS_ACCEPTED = 3;
    public const QS_REJECTED = 4;
    public const QS_INVOICED = 5;

    /** Default terms and notes for new quotes (Green Divine's own wording). */
    public const DEFAULT_TERMS = "70% advance payment for dispatch of the material.\n30% payment on the day the work is completed (against the invoice).\nPrices are valid until the date shown on this quotation.";
    public const DEFAULT_NOTES = "Includes cutting and laying.\nIncludes fixing labour.";

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
                'when' => fn($m) => (int) $m->deployment_type !== self::TRIAL && !\backend\components\Crm::simple(),
                'whenClient' => \backend\components\Crm::simple() ? 'function(){ return false; }' : "function(attribute, value){
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
            [['valid_until'], 'date', 'format' => 'php:Y-m-d', 'message' => 'Enter a valid date (YYYY-MM-DD).'],
            [['valid_until'], 'default', 'value' => null],
            [['valid_until'], 'compare', 'compareAttribute' => 'quote_date', 'operator' => '>=', 'type' => 'string', 'skipOnEmpty' => true,
                'message' => 'Valid until cannot be before the quote date.', 'when' => fn($m) => (string)$m->quote_date !== ''],
            [['tax_mode'], 'in', 'range' => [1, 2, 3]],
            [['terms', 'notes'], 'trim'],
            [['terms', 'notes'], 'string', 'max' => 5000],
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
            'quote_status' => 'Quote Status',
            'valid_until' => 'Valid Until',
            'tax_mode' => 'GST Type',
            'terms' => 'Terms & Conditions',
            'notes' => 'Notes',
        ];
    }

    public static function quoteStatusOptions(): array
    {
        return [self::QS_DRAFT => 'Draft', self::QS_SENT => 'Sent', self::QS_ACCEPTED => 'Accepted', self::QS_REJECTED => 'Rejected', self::QS_INVOICED => 'Invoiced'];
    }

    /** Draft / Sent quote past its validity date. */
    public function isExpired(): bool
    {
        return in_array((int)$this->quote_status, [self::QS_DRAFT, self::QS_SENT], true) && $this->valid_until && $this->valid_until < date('Y-m-d');
    }

    /** [label, tone] for the status pill. */
    public function getQuoteStatusPill(): array
    {
        if ($this->isExpired()) {
            return ['Expired', 'neutral'];
        }
        return match ((int)$this->quote_status) {
            self::QS_SENT     => ['Sent', 'accent'],
            self::QS_ACCEPTED => ['Accepted', 'success'],
            self::QS_REJECTED => ['Rejected', 'danger'],
            self::QS_INVOICED => ['Invoiced', 'success'],
            default           => ['Draft', 'warning'],
        };
    }

    public function hasItems(): bool
    {
        return $this->getItems()->exists();
    }

    public function getItems(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ClientQuotationItem::class, ['quote_id' => 'id'])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getInvoice(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Invoice::class, ['id' => 'invoice_id']);
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
