<?php

namespace backend\models;

use yii\db\ActiveRecord;
use backend\models\BankDetail;

/**
 * Migrated from Yii1: Invoice model
 *
 * Table: invoice
 *
 * invoice_category: 1=Service, 2=Product
 * invoice_type:     Inherited from client.client_type at creation time:
 *                   1=Inter-State (IGST), 2=Intra-State (SGST+CGST), 3=International (no tax)
 * invoice_role:     1=Tax Invoice, 2=Proforma
 * payment_status:   0=Unpaid, 1=Partial, 2=Paid
 * invoice_status:   1=Active, 0=Cancelled
 *
 * @property int    $id
 * @property string $invoice_no
 * @property int    $invoice_unique_no
 * @property int    $client_id
 * @property string $lut_arn
 * @property string $to_address
 * @property string $invoice_date
 * @property string $invoice_prefix
 * @property string $title
 * @property int    $invoice_category
 * @property int    $invoice_type
 * @property int    $tax_exempted
 * @property string $tax_exempt_reason
 * @property int    $invoice_role
 * @property float  $transaction_charge_percent
 * @property float  $grand_total
 * @property float  $sgst_percent
 * @property float  $sgst_amount
 * @property float  $cgst_percent
 * @property float  $cgst_amount
 * @property float  $igst_percent
 * @property float  $igst_amount
 * @property float  $tds_percent
 * @property float  $tds_amount
 * @property float  $sub_total
 * @property float  $round_off
 * @property float  $net_total
 * @property float  $paid_amount
 * @property string $currency
 * @property string $currency_in_words
 * @property float  $currency_exchange_rate
 * @property int    $invoice_status
 * @property int    $payment_status
 * @property int    $payment_type
 * @property string $payment_reference_no
 * @property string $payment_cheque_no
 * @property string $payment_date
 * @property string $created_date
 * @property int    $created_by
 * @property string $updated_date
 * @property int    $updated_by
 * @property int    $bank_id
 * @property int    $status
 * @property int    $project_id
 * @property int    $phase_value
 * @property int    $milestone_value
 */
class Invoice extends ActiveRecord
{
    const PAYMENT_STATUS_UNPAID  = 0;
    const PAYMENT_STATUS_PARTIAL = 1;
    const PAYMENT_STATUS_PAID    = 2;

    const CATEGORY_SERVICE = 1;
    const CATEGORY_PRODUCT = 2;

    // invoice_role values — the kind of invoice (matches Yii1 invoice_role field)
    const ROLE_TAX_INVOICE = 1;
    const ROLE_PROFORMA    = 2;

    // invoice_type values — inherited from client.client_type (matches Yii1 invoice_type field)
    // These determine tax display: 1=Inter-State(IGST), 2=Intra-State(SGST+CGST), 3=International(0%)
    const TYPE_INTERSTATE    = 1;
    const TYPE_INTRASTATE    = 2;
    const TYPE_INTERNATIONAL = 3;

    public static function tableName(): string
    {
        return 'invoice';
    }

    public function rules(): array
    {
        return [
            [['invoice_no', 'title', 'lut_arn', 'to_address', 'tax_exempt_reason', 'payment_reference_no', 'payment_cheque_no', 'currency_in_words'], 'trim'],
            [['client_id', 'title', 'invoice_category', 'invoice_type', 'tax_exempted', 'currency', 'currency_exchange_rate', 'status'], 'required'],
            [['currency_exchange_rate'], 'number', 'min' => 0.0001, 'max' => 100000],
            [['transaction_charge_percent'], 'number', 'min' => 0, 'max' => 100],
            [['client_id', 'invoice_unique_no', 'invoice_category', 'invoice_type', 'tax_exempted', 'invoice_role', 'invoice_status', 'payment_status', 'payment_type', 'created_by', 'updated_by', 'bank_id', 'status', 'project_id', 'phase_value', 'milestone_value'], 'integer'],
            [['grand_total', 'sgst_amount', 'cgst_amount', 'igst_amount', 'tds_amount', 'sub_total', 'net_total', 'paid_amount'], 'number', 'min' => 0],
            [['sgst_percent', 'cgst_percent', 'igst_percent', 'tds_percent'], 'number', 'min' => 0, 'max' => 100],
            [['round_off'], 'number'],
            [['invoice_date', 'payment_date', 'created_date', 'updated_date'], 'safe'],
            [['to_address', 'tax_exempt_reason', 'terms_and_conditions'], 'string'],
            [['invoice_no', 'lut_arn', 'payment_reference_no', 'payment_cheque_no', 'currency', 'currency_in_words', 'invoice_prefix'], 'string', 'max' => 255],
            [['title'], 'string', 'max' => 500],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'invoice_no'           => 'Invoice No.',
            'client_id'            => 'Client',
            'invoice_date'         => 'Invoice Date',
            'title'                => 'Title',
            'grand_total'          => 'Grand Total',
            'payment_status'       => 'Payment Status',
            'invoice_status'       => 'Invoice Status',
            'terms_and_conditions' => 'Terms & Conditions',
        ];
    }

    public function getClient(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClientModel::class, ['id' => 'client_id']);
    }

    public function getDetails(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InvoiceDetails::class, ['invoice_id' => 'id'])->andWhere(['invoice_details.status' => 1]);
    }

    public function getBank(): \yii\db\ActiveQuery
    {
        return $this->hasOne(BankDetail::class, ['id' => 'bank_id']);
    }

    public function getReceipts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InvoiceReceipt::class, ['invoice_id' => 'id']);
    }

    public function getPaymentStatusLabel(): string
    {
        return match ((int)$this->payment_status) {
            self::PAYMENT_STATUS_UNPAID  => 'Unpaid',
            self::PAYMENT_STATUS_PARTIAL => 'Partial',
            self::PAYMENT_STATUS_PAID    => 'Paid',
            default                      => 'Unknown',
        };
    }

    public function getBalanceAmount(): float
    {
        return max(0.0, (float)$this->net_total - (float)$this->tds_amount - (float)$this->paid_amount);
    }
}
