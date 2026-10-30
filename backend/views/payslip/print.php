<?php
/**
 * @var \backend\models\Payslip $model
 * @var \backend\models\Developer $developer
 * @var bool $isPdf True when rendering for mPDF (optional, default false)
 */
$isPdf = $isPdf ?? false;

// For mPDF, images need absolute filesystem paths; for browser, use baseUrl
if ($isPdf) {
    $imgBasePath = Yii::getAlias('@backend/web') . '/';
} else {
    $imgBasePath = Yii::$app->request->baseUrl . '/';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Pay Slip - <?= date('F Y', strtotime($model->month_year)) ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body  { font-family: Arial, sans-serif; font-size: 12px; background: #fff; }
table { border-collapse: collapse !important; }
table td, table th { border: 1px solid black; border-spacing: 0 !important; padding: 5px 8px; }
.no-print { text-align: right; margin: 8px 20px; }
.no-print button { padding: 6px 18px; margin-left: 6px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
@media print { .no-print { display: none !important; } }
</style>
</head>
<body>

<?php if (!$isPdf): ?>
<div class="no-print">
    <button onclick="window.print()" style="background:#2c6a2f;color:#fff;"><i class="fas fa-comment"></i> Print / Download PDF</button>
    <button onclick="window.close()"  style="background:#888;color:#fff;">Close</button>
</div>
<?php endif; ?>

<?php
// ── number to words (Indian system) ──────────────────────────────────────────
function indianNumberToWords(int $n): string {
    if ($n === 0) return 'zero';
    $ones = ['','one','two','three','four','five','six','seven','eight','nine',
              'ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen',
              'seventeen','eighteen','nineteen'];
    $tens = ['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
    $w = '';
    if ($n >= 10000000) { $w .= indianNumberToWords((int)($n/10000000)).' crore '; $n %= 10000000; }
    if ($n >= 100000)   { $w .= indianNumberToWords((int)($n/100000)).' lakh ';   $n %= 100000;   }
    if ($n >= 1000)     { $w .= indianNumberToWords((int)($n/1000)).' thousand '; $n %= 1000;     }
    if ($n >= 100)      { $w .= $ones[(int)($n/100)].' hundred ';                 $n %= 100;      }
    if ($n >= 20)       { $w .= $tens[(int)($n/10)].' ';                          $n %= 10;       }
    if ($n > 0)         { $w .= $ones[$n].' '; }
    return trim($w);
}

// ── data ─────────────────────────────────────────────────────────────────────
$dev         = $developer;
$gross       = (float)$model->gross_salary;
$netPay      = (float)$model->actual_salary;
$totalLop    = (float)$model->total_lop;
$daysInMonth = $model->month_year ? ((int)date('t', strtotime($model->month_year)) ?: 30) : 30; // real calendar days
$lopAmount   = ($gross > 0 && $totalLop > 0) ? round(($gross / $daysInMonth) * $totalLop) : 0;
$earnedGross = round($gross - $lopAmount);

$allowanceArr  = [];
$deductionsArr = [];
foreach ($model->details as $d) {
    if ($d->type == 1) {
        $allowanceArr[] = ['title' => $d->title, 'amount' => (float)$d->amount];
    } else {
        $deductionsArr[] = [
            'title'      => $d->title,
            'percentage' => (float)($d->deduction_percentage ?? 0),
            'dtype'      => (int)($d->deduction_type ?? 2),
            'amount'     => (float)$d->amount,
        ];
    }
}

$totalRows   = max(count($allowanceArr), count($deductionsArr), 1);
$amountWords = ucwords(indianNumberToWords((int)round($netPay))) . ' Rupees Only';
$time        = strtotime($model->month_year);
$logoUrl     = $imgBasePath . 'images/maestro_logo.png';
?>

<table border="0" align="center" cellpadding="0" cellspacing="0" style="width:700px; margin-top:30px;">

    <!-- Column widths — set once here, apply to all rows -->
    <colgroup>
        <col style="width:20%">  <!-- col1: earnings label / emp left label -->
        <col style="width:13%">  <!-- col2: earnings amount / emp left value -->
        <col style="width:32%">  <!-- col3: deductions label / middle separator -->
        <col style="width:10%">  <!-- col4: in days / emp right label -->
        <col style="width:25%">  <!-- col5: deductions amount / emp right value -->
    </colgroup>

    <!-- Logo -->
    <tr>
        <th colspan="5" style="text-align:center; padding:10px;">
            <img src="<?= $logoUrl ?>" alt="Maestro" style="height:55px;">
        </th>
    </tr>

    <!-- Company name -->
    <tr>
        <th colspan="5" style="text-align:center; font-size:13px;">
            Maestro Technology Services Pvt.ltd,
        </th>
    </tr>

    <!-- Company address -->
    <tr>
        <td colspan="5" style="text-align:center; font-size:12px;">
            Site No. 4, Maestro Building, Sp Garden, Sathy road, Kaapi Kadai,<br>
            Vilankurichi, Coimbatore - 641035
        </td>
    </tr>

    <!-- Pay Slip title -->
    <tr>
        <th colspan="5" style="text-align:center;">
            Pay Slip - <?= date('F Y', $time) ?>
        </th>
    </tr>

    <!-- Employee details -->
    <tr>
        <th>Name</th>
        <td><?= htmlspecialchars($model->name ?? '') ?></td>
        <td></td>
        <th>Bank Name</th>
        <td><?= htmlspecialchars($dev->bank ?? '-') ?></td>
    </tr>
    <tr>
        <th>Date of Join</th>
        <td><?= ($dev && $dev->doj) ? date('d-m-Y', strtotime($dev->doj)) : '-' ?></td>
        <td></td>
        <th>Account Number</th>
        <td><?= htmlspecialchars($dev->account_number ?? '-') ?></td>
    </tr>
    <tr>
        <th>Designation</th>
        <td><?= htmlspecialchars($model->designation ?? '') ?></td>
        <td></td>
        <th>PF Number</th>
        <td><?= htmlspecialchars($dev->pf_details ?? '-') ?></td>
    </tr>
    <tr>
        <th>Emp.Code</th>
        <td><?= htmlspecialchars($model->emp_id ?? '') ?></td>
        <td></td>
        <th>ESI Number</th>
        <td><?= htmlspecialchars($dev->esi_details ?? '-') ?></td>
    </tr>
    <tr>
        <th>PAN Number</th>
        <td><?= htmlspecialchars($dev->pan ?? '-') ?></td>
        <td></td>
        <th>UAN Number</th>
        <td><?= htmlspecialchars($dev->uan_details ?? '-') ?></td>
    </tr>

    <!-- Earnings / Deductions header -->
    <tr>
        <th style="text-align:center;">Earnings</th>
        <th style="text-align:center;">In Rupees</th>
        <th style="text-align:center;">Deductions</th>
        <th style="text-align:center;">In days</th>
        <th style="text-align:center;">In Rupees</th>
    </tr>

    <!-- CTC vs LOP -->
    <tr>
        <td>Cost to Company(CTC)</td>
        <td style="text-align:right;"><?= number_format($gross) ?></td>
        <td>Loss of Pay</td>
        <td style="text-align:center;"><?= $totalLop > 0 ? $totalLop : '' ?></td>
        <td style="text-align:right;"><?= number_format($lopAmount) ?></td>
    </tr>

    <!-- Earned Gross Pay -->
    <tr>
        <th colspan="4" style="text-align:center;">Earned Gross Pay</th>
        <td style="text-align:right;"><?= number_format($earnedGross) ?></td>
    </tr>

    <!-- Earnings breakdown vs Deductions (parallel rows) -->
    <?php for ($i = 0; $i < $totalRows; $i++): ?>
    <tr>
        <td><?= isset($allowanceArr[$i]) ? htmlspecialchars($allowanceArr[$i]['title']) : '' ?></td>
        <td style="text-align:right;"><?= isset($allowanceArr[$i]) ? number_format($allowanceArr[$i]['amount']) : '' ?></td>
        <td><?= isset($deductionsArr[$i]) ? htmlspecialchars($deductionsArr[$i]['title']) : '' ?></td>
        <td style="text-align:center;">
            <?php if (isset($deductionsArr[$i]) && $deductionsArr[$i]['dtype'] == 1 && $deductionsArr[$i]['percentage'] > 0): ?>
                <?= $deductionsArr[$i]['percentage'] ?>
            <?php endif; ?>
        </td>
        <td style="text-align:right;"><?= isset($deductionsArr[$i]) ? number_format($deductionsArr[$i]['amount']) : '' ?></td>
    </tr>
    <?php endfor; ?>

    <!-- Net Pay -->
    <tr>
        <th colspan="2" style="text-align:center;">No signature is required</th>
        <th colspan="2" style="text-align:center;">Net Pay</th>
        <td style="text-align:right;"><?= number_format((int)round($netPay)) ?></td>
    </tr>

    <!-- Amount in words -->
    <tr>
        <td colspan="5" style="text-align:center; font-style:italic;">
            (<?= $amountWords ?>)
        </td>
    </tr>

</table>

</body>
</html>
