<?php

namespace backend\models;

use backend\components\GuardedAttributes;
use Yii;
use yii\db\ActiveRecord;

/**
 * ActiveRecord for `po_details` — Purchase Order rows attached to a
 * client quotation (parent quote_id). Multiple POs can be raised under a
 * single quotation; each PO file is a PDF stored under
 * /uploads/po-details/.
 *
 * @property int         $id
 * @property int|null    $quote_id     FK -> client_quotation.id (null for standalone POs)
 * @property int|null    $client_id    FK -> client.id (required for standalone POs)
 * @property string      $po_number
 * @property string      $date
 * @property string      $particulars
 * @property string      $po_cost
 * @property string      $file
 * @property string|null $reason       Required only when po_cost < given_cost
 * @property int         $po_status    1=pending,2=in progress,3=delivered,4=cancelled,0=deleted
 * @property string|null $delivery_date
 * @property string|null $po_received_by
 * @property int         $po_incharge  FK -> user.user_id
 * @property string      $created_at
 * @property string      $modified_at
 * @property int         $status       0=deleted/soft, 1=active
 *
 * @property ClientQuotation $quote
 */
class PoDetail extends ActiveRecord
{
    use GuardedAttributes;

    /** Set by the controller, never by the form. */
    protected static function guardedAttributes(): array
    {
        return ['status', 'quote_id', 'file', 'created_at', 'modified_at'];
    }

    /** Form-only attribute: the uploaded PDF file. */
    public $po_file;

    /**
     * Form-only attribute (NOT persisted): the "given cost" used to drive the
     * conditional Reason validation. Auto-filled from the latest
     * `client_quotation.estimation_cost` under the parent quote at form-load
     * time. Re-computed at view time from the same source.
     */
    public $given_cost;

    public static function tableName(): string
    {
        return 'po_details';
    }

    public const STATUS_PENDING     = 1;
    public const STATUS_IN_PROGRESS = 2;
    public const STATUS_DELIVERED   = 3;
    public const STATUS_CANCELLED   = 4;
    public const STATUS_DELETED     = 0;

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING     => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_DELIVERED   => 'Delivered',
            self::STATUS_CANCELLED   => 'Cancelled',
        ];
    }

    public function getStatusLabel(): string
    {
        return self::statusOptions()[$this->po_status] ?? '—';
    }

    public function rules(): array
    {
        return [
            [['po_number', 'date', 'particulars', 'po_cost'], 'required'],
            // Standalone POs require client_id; quotation POs require quote_id.
            [['client_id'], 'required', 'when' => fn($m) => empty($m->quote_id),
                'whenClient' => "function(a,v){return !jQuery('#podetail-quote_id').val();}"],
            [['quote_id', 'client_id', 'status', 'po_status', 'po_incharge'], 'integer'],
            [['date', 'created_at', 'modified_at', 'delivery_date'], 'safe'],
            [['po_number'], 'string', 'max' => 20],
            [['particulars', 'reason'], 'string', 'max' => 500],
            [['po_cost'], 'string', 'max' => 10],
            [['file'], 'string', 'max' => 150],
            [['po_received_by'], 'string', 'max' => 50],
            [['given_cost'], 'safe'],
            [['po_cost'], 'match', 'pattern' => '/^\d+(\.\d+)?$/', 'message' => '{attribute} must be a valid number.'],
            // Reason is required only when PO cost is less than given cost.
            [
                ['reason'], 'required',
                'when' => function ($model) {
                    return is_numeric($model->po_cost)
                        && is_numeric($model->given_cost)
                        && (float) $model->po_cost < (float) $model->given_cost;
                },
                'whenClient' => "function(attribute, value){
                    var po = parseFloat(jQuery('#podetail-po_cost').val() || '0');
                    var gc = parseFloat(jQuery('#podetail-given_cost').val() || '0');
                    return !isNaN(po) && !isNaN(gc) && po < gc;
                }",
                'message' => 'Reason is required when PO cost is less than the given cost.',
            ],
            // Accept PDF, common image formats and Word documents — required on insert.
            [
                ['po_file'], 'file',
                'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'],
                'mimeTypes'  => [
                    'application/pdf',
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                'maxSize'    => 10 * 1024 * 1024,
                'tooBig'     => 'PO file must not exceed 10 MB.',
                'wrongExtension' => 'Allowed file types: PDF, JPG, PNG, GIF, WebP, DOC, DOCX.',
                'wrongMimeType'  => 'Allowed file types: PDF, JPG, PNG, GIF, WebP, DOC, DOCX.',
                'skipOnEmpty'    => false,
                'on' => 'create',
            ],
            [['quote_id'], 'exist', 'skipOnError' => true,
             'targetClass' => ClientQuotation::class, 'targetAttribute' => ['quote_id' => 'id']],
            [['client_id'], 'exist', 'skipOnError' => true,
             'targetClass' => ClientModel::class, 'targetAttribute' => ['client_id' => 'id']],

            [['po_number', 'particulars', 'reason', 'po_received_by'], 'trim'],
            [['po_status'], 'in', 'range' => array_keys(self::statusOptions()), 'message' => 'Choose a valid PO status.'],
            [['po_cost'], 'compare', 'compareValue' => 0, 'operator' => '>', 'type' => 'number', 'message' => 'PO cost must be more than 0.'],
            [['date', 'delivery_date'], 'date', 'format' => 'php:Y-m-d H:i:s', 'message' => '{attribute} must be a valid date.'],
            [['delivery_date'], 'compare', 'compareAttribute' => 'date', 'operator' => '>=', 'type' => 'string',
                'message' => 'Delivery date cannot be before the PO date.', 'when' => fn($m) => !empty($m->delivery_date)],
            [['po_incharge'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['po_incharge' => 'user_id'],
                'filter' => ['status' => 1], 'message' => 'Choose an active person in charge.', 'when' => fn($m) => (int)$m->po_incharge > 0],
            // PO numbers come from the client, so they only need to be unique per client.
            [['po_number'], 'unique', 'targetAttribute' => ['po_number', 'client_id'], 'filter' => ['<>', 'status', 0],
                'message' => 'This client already has a PO with this number.',
                'when' => fn($m) => $m->isNewRecord || (string)$m->getOldAttribute('po_number') !== (string)$m->po_number],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id'            => 'ID',
            'quote_id'      => 'Quote',
            'client_id'     => 'Client',
            'po_number'     => 'PO Number',
            'date'          => 'Date',
            'particulars'   => 'Particulars',
            'given_cost'    => 'Given Cost',
            'po_cost'       => 'PO Cost',
            'po_file'       => 'PO File (PDF / Image / Word)',
            'file'          => 'File',
            'reason'         => 'Reason',
            'po_status'      => 'PO Status',
            'delivery_date'  => 'PO Delivery Date',
            'po_received_by' => 'Accountant',
            'po_incharge'    => 'PO In-charge',
            'created_at'    => 'Created At',
            'modified_at'   => 'Modified At',
            'status'        => 'Status',
        ];
    }

    public function scenarios(): array
    {
        $s = parent::scenarios();
        $s['create'] = ['quote_id', 'client_id', 'po_number', 'date', 'particulars',
                        'po_cost', 'po_file', 'given_cost', 'reason',
                        'po_status', 'delivery_date', 'po_received_by', 'po_incharge',
                        'created_at', 'modified_at', 'status'];
        return $s;
    }

    public function getQuote(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientQuotation::class, ['id' => 'quote_id']);
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getInvoices(): \yii\db\ActiveQuery
    {
        return $this->hasMany(PoInvoiceDetail::class, ['po_id' => 'id'])
            ->andWhere(['po_invoice_details.status' => 1]);
    }

    public function getPayments(): \yii\db\ActiveQuery
    {
        return $this->hasMany(PoPayment::class, ['po_id' => 'id'])
            ->andWhere(['po_payment.status' => 1]);
    }

    public function getInchargeUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['user_id' => 'po_incharge']);
    }

    /** Effective client — direct for standalone POs, via quotation otherwise. */
    public function getEffectiveClient(): ?ClientModel
    {
        if ($this->client_id) {
            return $this->client;
        }
        return $this->quote->client ?? null;
    }

    public function getTotalPaid(): float
    {
        return (float) PoPayment::find()
            ->where(['po_id' => $this->id, 'status' => 1])
            ->sum('amount');
    }

    public function getBalance(): float
    {
        return (float) $this->po_cost - $this->getTotalPaid();
    }

    public function getInvoiceCount(): int
    {
        return (int) PoInvoiceDetail::find()
            ->where(['po_id' => $this->id, 'status' => 1])
            ->count();
    }
}
