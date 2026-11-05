<?php

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Purchase invoice received from a vendor.
 *
 * Totals are always computed on the server from the line items
 * (see applyLines()); the form only shows a live preview.
 *
 * @property int    $id
 * @property int    $vendor_id
 * @property string $invoice_number     the vendor's own number
 * @property string $invoice_date
 * @property string $title
 * @property float  $grand_total        sum of line item totals before tax
 * @property float  $sgst_amount
 * @property float  $cgst_amount
 * @property float  $igst_amount
 * @property float  $net_total          grand + taxes + extra expenses + round off
 * @property float  $round_off
 * @property int    $invoice_type
 * @property int    $invoice_role       1=Actual, 2=Provisional
 * @property int    $payment_status     0=Not paid, 1=Paid (NULL on legacy rows)
 * @property int    $payment_type       acc_ledger_category id (payment categories)
 * @property int    $payment_ledger_id
 * @property float  $payment_amount
 * @property float  $tds_amount
 * @property string $payment_date
 * @property string $payment_remarks
 * @property int    $created_by
 * @property string $created_at
 * @property int    $status
 */
class InwardInvoice extends ActiveRecord
{
    const ROLE_ACTUAL      = 1;
    const ROLE_PROVISIONAL = 2;

    const PAY_UNPAID = 0;
    const PAY_PAID   = 1;

    const MAX_ROUND_OFF = 1000;

    public static function tableName(): string
    {
        return 'inward_invoice';
    }

    public function rules(): array
    {
        // Legacy rows break several newer rules; check only new or edited values.
        $changed = fn(string $attr) => fn($m) => $m->isNewRecord || (string)$m->getOldAttribute($attr) !== (string)$m->$attr;
        $paid    = fn($m) => (string)$m->payment_status === (string)self::PAY_PAID;

        return [
            [['invoice_number', 'title', 'payment_remarks'], 'trim'],
            [['vendor_id', 'invoice_number', 'invoice_date', 'status'], 'required'],
            [['payment_status'], 'required', 'message' => 'Choose whether this invoice is paid.'],
            [['vendor_id', 'payment_type', 'payment_ledger_id', 'status', 'created_by'], 'integer'],
            [['invoice_role'], 'in', 'range' => [self::ROLE_ACTUAL, self::ROLE_PROVISIONAL]],
            [['payment_status'], 'in', 'range' => [self::PAY_UNPAID, self::PAY_PAID]],
            [['grand_total', 'sgst_amount', 'cgst_amount', 'igst_amount', 'payment_amount', 'tds_amount'], 'number', 'min' => 0],
            [['net_total'], 'number', 'min' => 0.01, 'tooSmall' => 'The net total must be more than zero.'],
            [['round_off'], 'number', 'min' => -self::MAX_ROUND_OFF, 'max' => self::MAX_ROUND_OFF,
                'tooSmall' => 'Round off must be between -1,000 and 1,000.', 'tooBig' => 'Round off must be between -1,000 and 1,000.'],
            [['invoice_number'], 'string', 'max' => 100],
            [['title', 'payment_remarks'], 'string', 'max' => 5000],
            [['created_at', 'invoice_type'], 'safe'],

            [['invoice_date'], 'date', 'format' => 'php:Y-m-d', 'message' => 'Enter the invoice date as YYYY-MM-DD.'],
            [['invoice_date'], 'compare', 'compareValue' => date('Y-m-d'), 'operator' => '<=', 'type' => 'string',
                'message' => 'The invoice date cannot be in the future.', 'when' => $changed('invoice_date')],
            // Vendors number their own invoices, so the number only has to be unique per vendor.
            [['invoice_number'], 'unique', 'targetAttribute' => ['vendor_id', 'invoice_number'], 'filter' => ['status' => 1],
                'message' => 'This vendor already has an invoice with this number.', 'when' => $changed('invoice_number')],

            // Payment details are required — and checked — only when the invoice is marked paid.
            [['payment_type', 'payment_ledger_id', 'payment_date'], 'required', 'when' => $paid, 'whenClient' => 'function(){return false;}'],
            [['payment_type'], 'exist', 'targetClass' => AccLedgerCategory::class, 'targetAttribute' => 'id',
                'filter' => ['status' => 1, 'is_payment_ledger' => 1], 'message' => 'Choose a valid payment type.', 'when' => $paid],
            [['payment_ledger_id'], 'validatePaymentLedger', 'when' => $paid],
            [['payment_amount'], 'number', 'min' => 0.01, 'tooSmall' => 'Enter the amount paid.', 'when' => $paid],
            [['payment_amount'], 'validatePaidTotal', 'when' => $paid],
            [['payment_date'], 'date', 'format' => 'php:Y-m-d', 'message' => 'Enter the payment date as YYYY-MM-DD.', 'when' => $paid],
            [['payment_date'], 'compare', 'compareValue' => date('Y-m-d'), 'operator' => '<=', 'type' => 'string',
                'message' => 'The payment date cannot be in the future.', 'when' => fn($m) => $paid($m) && $changed('payment_date')($m)],
            [['payment_date'], 'compare', 'compareAttribute' => 'invoice_date', 'operator' => '>=', 'type' => 'string',
                'message' => 'The payment date cannot be before the invoice date.', 'when' => fn($m) => $paid($m) && $changed('payment_date')($m)],
        ];
    }

    public function validatePaymentLedger(string $attr): void
    {
        $ok = AccLedger::find()->where(['id' => (int)$this->$attr, 'status' => 1, 'category_id' => (int)$this->payment_type])->exists();
        if (!$ok) {
            $this->addError($attr, 'Choose a payment ledger from the selected payment type.');
        }
    }

    public function validatePaidTotal(string $attr): void
    {
        if ((float)$this->payment_amount + (float)$this->tds_amount > (float)$this->net_total + 0.005) {
            $this->addError($attr, 'Payment plus TDS (₹' . number_format((float)$this->payment_amount + (float)$this->tds_amount, 2)
                . ') cannot be more than the net total (₹' . number_format((float)$this->net_total, 2) . ').');
        }
    }

    /** Clear payment details that do not apply to an unpaid invoice. */
    public function beforeValidate(): bool
    {
        if ((string)$this->payment_status !== (string)self::PAY_PAID) {
            $this->payment_type = $this->payment_ledger_id = $this->payment_date = null;
            $this->payment_amount = $this->tds_amount = 0;
        }
        return parent::beforeValidate();
    }

    /**
     * Compute line and invoice totals on the server.
     *
     * @param InwardInvoiceDetails[] $lines    line_item_type 1 with qty / price / tax percents set
     * @param InwardInvoiceDetails[] $expenses line_item_type 2 with item_total set
     */
    public function applyLines(array $lines, array $expenses): void
    {
        $grand = $sgst = $cgst = $igst = $extra = 0.0;
        foreach ($lines as $d) {
            $d->item_total   = round((float)$d->qty * (float)$d->price_per_qty, 2);
            $d->sgst_amount  = round($d->item_total * (float)$d->sgst_percent / 100, 2);
            $d->cgst_amount  = round($d->item_total * (float)$d->cgst_percent / 100, 2);
            $d->igst_amount  = round($d->item_total * (float)$d->igst_percent / 100, 2);
            $d->total_amount = round($d->item_total + $d->sgst_amount + $d->cgst_amount + $d->igst_amount, 2);
            $grand += $d->item_total; $sgst += $d->sgst_amount; $cgst += $d->cgst_amount; $igst += $d->igst_amount;
        }
        foreach ($expenses as $e) {
            $e->item_total = $e->total_amount = round((float)$e->item_total, 2);
            $extra += $e->item_total;
        }
        $this->grand_total = round($grand, 2);
        $this->sgst_amount = round($sgst, 2);
        $this->cgst_amount = round($cgst, 2);
        $this->igst_amount = round($igst, 2);
        $this->net_total   = round($grand + $sgst + $cgst + $igst + $extra + (float)$this->round_off, 2);
    }

    public static function roleOptions(): array
    {
        return [self::ROLE_ACTUAL => 'Actual', self::ROLE_PROVISIONAL => 'Provisional'];
    }

    public static function paymentOptions(): array
    {
        return [self::PAY_UNPAID => 'Not paid', self::PAY_PAID => 'Paid'];
    }

    public function getPaymentLabel(): string
    {
        return $this->payment_status === null || $this->payment_status === '' ? 'Not set' : (self::paymentOptions()[(int)$this->payment_status] ?? '—');
    }

    public function getPaymentTone(): string
    {
        return $this->payment_status === null || $this->payment_status === '' ? 'neutral' : ((int)$this->payment_status === self::PAY_PAID ? 'success' : 'warning');
    }

    public function attributeLabels(): array
    {
        return [
            'vendor_id'         => 'Vendor',
            'invoice_number'    => 'Vendor invoice number',
            'invoice_date'      => 'Invoice date',
            'title'             => 'Title',
            'grand_total'       => 'Sub total',
            'net_total'         => 'Net total',
            'round_off'         => 'Round off',
            'payment_status'    => 'Payment status',
            'payment_type'      => 'Payment type',
            'payment_ledger_id' => 'Payment ledger',
            'payment_amount'    => 'Amount paid',
            'tds_amount'        => 'TDS deducted',
            'payment_date'      => 'Payment date',
            'payment_remarks'   => 'Remarks',
        ];
    }

    public function getVendor(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Vendor::class, ['id' => 'vendor_id']);
    }

    public function getPaymentLedger(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccLedger::class, ['id' => 'payment_ledger_id']);
    }

    public function getPaymentCategory(): \yii\db\ActiveQuery
    {
        return $this->hasOne(AccLedgerCategory::class, ['id' => 'payment_type']);
    }

    public function getDetails(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceDetails::class, ['inward_invoice_id' => 'id'])
            ->andWhere(['inward_invoice_details.status' => 1]);
    }

    public function getLineItems(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceDetails::class, ['inward_invoice_id' => 'id'])
            ->andWhere(['inward_invoice_details.status' => 1, 'inward_invoice_details.line_item_type' => 1]);
    }

    public function getExpenses(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceDetails::class, ['inward_invoice_id' => 'id'])
            ->andWhere(['inward_invoice_details.status' => 1, 'inward_invoice_details.line_item_type' => 2]);
    }

    public function getAttachments(): \yii\db\ActiveQuery
    {
        return $this->hasMany(InwardInvoiceAttachments::class, ['inward_invoice_id' => 'id'])
            ->andWhere(['status' => 1]);
    }
}
