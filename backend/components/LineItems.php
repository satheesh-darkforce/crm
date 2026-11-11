<?php

namespace backend\components;

use backend\models\ClientModel;
use backend\models\Product;

/**
 * Line items shared by quotations and invoices (PROJECT_ANALYSIS.md §6).
 *
 * read()  validates the posted rows (product link, description, HSN/SAC, unit,
 *         quantity, unit price, discount, GST) and calculates every amount on the
 *         server — browser totals are never trusted.
 * Each line keeps a snapshot of the product values, so editing a product later
 * never changes a saved document.
 *
 * Amounts per line:  gross = qty × unit price;  discount = gross × disc %;
 *                    taxable = gross − discount;  GST = taxable × rate (0 when no GST);
 *                    total = taxable + GST.  Every amount is rounded to paise.
 */
class LineItems
{
    const TAX_INTRA = 1; // CGST + SGST (same state — Karnataka)
    const TAX_INTER = 2; // IGST (other state)
    const TAX_NONE  = 3; // export / exempt

    const MAX_LINES = 100;

    public static function taxModeOptions(): array
    {
        return [self::TAX_INTRA => 'CGST + SGST (within the state)', self::TAX_INTER => 'IGST (other state)', self::TAX_NONE => 'No GST (exempt / export)'];
    }

    /** Default tax mode for a client: exempt / international → none, other state → IGST, else CGST+SGST. */
    public static function taxModeFor(?ClientModel $client): int
    {
        if (!$client) {
            return self::TAX_INTRA;
        }
        if ((int)$client->tax_exemption === 1 || (int)$client->client_type === ClientModel::TYPE_INTERNATIONAL) {
            return self::TAX_NONE;
        }
        return (int)$client->client_type === ClientModel::TYPE_INTERSTATE ? self::TAX_INTER : self::TAX_INTRA;
    }

    /**
     * @param array $rows posted rows: product_id, description, hsn_sac, unit, quantity, unit_price, discount_percent, gst_rate
     * @return array{0: array<int,array>, 1: array, 2: string[]} [lines, totals, errors]
     */
    public static function read(array $rows, int $taxMode): array
    {
        $taxMode = in_array($taxMode, [self::TAX_INTRA, self::TAX_INTER, self::TAX_NONE], true) ? $taxMode : self::TAX_INTRA;
        $lines = $errors = [];
        $rowNo = 0;
        $productIds = [];
        foreach ($rows as $r) {
            if (is_array($r) && isset($r['product_id']) && is_scalar($r['product_id']) && ctype_digit((string)$r['product_id'])) {
                $productIds[] = (int)$r['product_id'];
            }
        }
        $products = $productIds ? Product::find()->where(['id' => array_unique($productIds), 'status' => 1])->indexBy('id')->all() : [];

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $v = fn(string $k) => isset($r[$k]) && is_scalar($r[$k]) ? trim((string)$r[$k]) : '';
            $row = [
                'product_id'       => ctype_digit($v('product_id')) ? (int)$v('product_id') : null,
                'description'      => $v('description'),
                'hsn_sac'          => $v('hsn_sac'),
                'unit'             => $v('unit'),
                'quantity'         => $v('quantity'),
                'unit_price'       => $v('unit_price'),
                'discount_percent' => $v('discount_percent'),
                'gst_rate'         => $v('gst_rate'),
            ];
            if ($row['product_id'] === null && $row['description'] === '' && $row['quantity'] === '' && $row['unit_price'] === '') {
                continue; // blank row
            }
            $n = ++$rowNo; // position among the filled rows, as the user sees them
            $bad = function (string $msg) use (&$errors, $n): void { $errors[] = "Line $n: $msg"; };

            if ($row['product_id'] !== null && !isset($products[$row['product_id']])) {
                $bad('the product no longer exists — pick another one.');
                continue;
            }
            $p = $row['product_id'] !== null ? $products[$row['product_id']] : null;
            // Empty fields fall back to the product values (snapshot).
            if ($p) {
                $row['description'] = $row['description'] !== '' ? $row['description'] : (string)$p->item_name;
                $row['hsn_sac']     = $row['hsn_sac'] !== '' ? $row['hsn_sac'] : (string)$p->hsn_code;
                $row['unit']        = $row['unit'] !== '' ? $row['unit'] : (string)$p->units;
                if ($row['unit_price'] === '' && $p->sale_price !== null) {
                    $row['unit_price'] = (string)$p->sale_price;
                }
                if ($row['gst_rate'] === '' && $p->gst_rate !== null) {
                    $row['gst_rate'] = (string)(float)$p->gst_rate;
                }
            }
            $row['unit'] = $row['unit'] === '' ? '' : (string)Product::normaliseUnit($row['unit']);

            if ($row['description'] === '') {
                $bad('enter a description or pick a product.');
                continue;
            }
            if (mb_strlen($row['description']) > 500) {
                $bad('the description is too long (500 characters max).');
                continue;
            }
            if ($row['hsn_sac'] !== '' && !preg_match('/^\d{4,8}$/', $row['hsn_sac'])) {
                $bad('HSN / SAC must be 4 to 8 digits.');
                continue;
            }
            if ($row['unit'] !== '' && !isset(Product::unitOptions()[$row['unit']])) {
                $bad('pick a valid unit.');
                continue;
            }
            if (!is_numeric($row['quantity']) || (float)$row['quantity'] <= 0 || (float)$row['quantity'] > 9999999) {
                $bad('quantity must be more than 0.');
                continue;
            }
            if (!Product::unitAllowsDecimals($row['unit'] ?: null) && floor((float)$row['quantity']) != (float)$row['quantity']) {
                $bad('quantity in ' . $row['unit'] . ' must be a whole number.');
                continue;
            }
            if ($row['unit_price'] === '') {
                $bad('enter the unit price' . ($p && (int)$p->price_on_request ? ' — this product is priced on request.' : '.'));
                continue;
            }
            if (!is_numeric($row['unit_price']) || (float)$row['unit_price'] < 0 || (float)$row['unit_price'] > 99999999) {
                $bad('the unit price must be a number of 0 or more.');
                continue;
            }
            if ($row['discount_percent'] === '') {
                $row['discount_percent'] = '0';
            }
            if (!is_numeric($row['discount_percent']) || (float)$row['discount_percent'] < 0 || (float)$row['discount_percent'] > 100) {
                $bad('discount must be between 0 and 100 %.');
                continue;
            }
            if ($row['gst_rate'] === '') {
                $row['gst_rate'] = '0';
            }
            if (!in_array((string)(float)$row['gst_rate'], Product::GST_RATES, true)) {
                $bad('pick a valid GST rate (' . implode(', ', Product::GST_RATES) . ' %).');
                continue;
            }

            $qty      = round((float)$row['quantity'], 3);
            $price    = round((float)$row['unit_price'], 2);
            $disc     = round((float)$row['discount_percent'], 2);
            $rate     = $taxMode === self::TAX_NONE ? 0.0 : (float)$row['gst_rate'];
            $gross    = round($qty * $price, 2);
            $discAmt  = round($gross * $disc / 100, 2);
            $taxable  = round($gross - $discAmt, 2);
            $gst      = round($taxable * $rate / 100, 2);
            $lines[] = [
                'product_id'       => $row['product_id'],
                'description'      => $row['description'],
                'hsn_sac'          => $row['hsn_sac'] !== '' ? $row['hsn_sac'] : null,
                'unit'             => $row['unit'] !== '' ? $row['unit'] : null,
                'quantity'         => $qty,
                'unit_price'       => $price,
                'discount_percent' => $disc,
                'gst_rate'         => (float)$row['gst_rate'],
                'gross'            => $gross,
                'discount_amount'  => $discAmt,
                'taxable_amount'   => $taxable,
                'gst_amount'       => $gst,
                'line_total'       => round($taxable + $gst, 2),
            ];
        }
        if (!$lines && !$errors) {
            $errors[] = 'Add at least one item.';
        }
        if (count($lines) > self::MAX_LINES) {
            $errors[] = 'A document can have at most ' . self::MAX_LINES . ' items.';
        }
        return [$lines, self::totals($lines, $taxMode), $errors];
    }

    /**
     * Totals and the GST summary by rate, split CGST/SGST or IGST.
     * @return array{sub_total: float, discount_total: float, taxable_total: float, gst_total: float, cgst: float, sgst: float, igst: float,
     *               grand_total: float, rounded_total: float, round_off: float, by_rate: array<string,array{taxable: float, gst: float}>, tax_mode: int}
     */
    public static function totals(array $lines, int $taxMode): array
    {
        $t = ['sub_total' => 0.0, 'discount_total' => 0.0, 'taxable_total' => 0.0, 'gst_total' => 0.0, 'by_rate' => []];
        foreach ($lines as $l) {
            $t['sub_total']      += (float)$l['gross'];
            $t['discount_total'] += (float)$l['discount_amount'];
            $t['taxable_total']  += (float)$l['taxable_amount'];
            $t['gst_total']      += (float)$l['gst_amount'];
            if ((float)$l['gst_amount'] > 0) {
                $key = (string)(float)$l['gst_rate'];
                $t['by_rate'][$key]['taxable'] = round(($t['by_rate'][$key]['taxable'] ?? 0) + (float)$l['taxable_amount'], 2);
                $t['by_rate'][$key]['gst']     = round(($t['by_rate'][$key]['gst'] ?? 0) + (float)$l['gst_amount'], 2);
            }
        }
        ksort($t['by_rate'], SORT_NUMERIC);
        foreach (['sub_total', 'discount_total', 'taxable_total', 'gst_total'] as $k) {
            $t[$k] = round($t[$k], 2);
        }
        // CGST = half (rounded), SGST = the rest, so the two always add up to the GST total.
        $t['cgst'] = $taxMode === self::TAX_INTRA ? round($t['gst_total'] / 2, 2) : 0.0;
        $t['sgst'] = $taxMode === self::TAX_INTRA ? round($t['gst_total'] - $t['cgst'], 2) : 0.0;
        $t['igst'] = $taxMode === self::TAX_INTER ? $t['gst_total'] : 0.0;
        $t['grand_total']   = round($t['taxable_total'] + $t['gst_total'], 2);
        $t['rounded_total'] = round($t['grand_total']);
        $t['round_off']     = round($t['rounded_total'] - $t['grand_total'], 2);
        $t['tax_mode']      = $taxMode;
        return $t;
    }

    /** Indian-style amount in words: 1,25,000.50 → "Rupees One Lakh Twenty Five Thousand and Fifty Paise Only". */
    public static function amountInWords(float $amount): string
    {
        $rupees = (int)floor(round($amount, 2));
        $paise  = (int)round((round($amount, 2) - $rupees) * 100);
        $words  = $rupees === 0 ? 'Zero' : self::words($rupees);
        return 'Rupees ' . $words . ($paise ? ' and ' . self::words($paise) . ' Paise' : '') . ' Only';
    }

    private static function words(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
                 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two  = fn(int $x) => $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)] . ' ' . $ones[$x % 10]);
        $three = fn(int $x) => trim(($x >= 100 ? $ones[intdiv($x, 100)] . ' Hundred ' : '') . $two($x % 100));
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$div, $name]) {
            if ($n >= $div) {
                $parts[] = ($div === 10000000 ? self::words(intdiv($n, $div)) : $two(intdiv($n, $div))) . ' ' . $name;
                $n %= $div;
            }
        }
        if ($n > 0) {
            $parts[] = $three($n);
        }
        return implode(' ', $parts);
    }
}
