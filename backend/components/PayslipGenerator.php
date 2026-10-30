<?php

namespace backend\components;

use backend\models\DailyAttendance;
use backend\models\Developer;
use backend\models\DeveloperAllowanceDeduction;
use backend\models\Payslip;
use backend\models\PayslipDetail;
use backend\models\SalaryAllowance;
use backend\models\UploadPayslip;

/**
 * Shared payslip generation logic.
 *
 * Centralises the per-developer attendance/LOP maths and the Payslip +
 * PayslipDetail persistence so the manual flow (PayslipController) and the
 * monthly auto-generation cron (SiteController::actionMonthlypayslipcall)
 * never drift apart.
 *
 * The compute helpers mirror the original PayslipController privates exactly;
 * the controller now delegates to them.
 */
class PayslipGenerator
{
    // -------------------------------------------------------- compute helpers

    /** Days in the payslip month (28/29/30/31). Falls back to 30 for an empty/bad month. */
    public static function daysInMonth(string $month): int
    {
        $days = $month !== '' ? (int)date('t', strtotime($month)) : 0;
        return $days > 0 ? $days : 30;
    }

    /** Salary after LOP + deductions. Per-day rate is gross / days-in-month. */
    public static function calcSalary(float $gross, float $totalLop, array $devDedValues, array $deductionModels, int $daysInMonth = 30): float
    {
        if ($gross <= 0) { return 0; }
        $perDay          = $gross / max(1, $daysInMonth);
        $totalTax        = 0.0;
        $deductionAmount = 0.0;
        foreach ($deductionModels as $sad) {
            $val = (float)($devDedValues[$sad->id] ?? $sad->default_value ?? 0);
            if ($sad->type2 == 1) { $totalTax        += $val; }
            else                  { $deductionAmount += $val; }
        }
        $finalSalary = $gross - ($totalLop * $perDay) - $deductionAmount;
        if ($totalTax > 0) { $finalSalary -= ($finalSalary / 100) * $totalTax; }
        return (float)max(0, round($finalSalary));
    }

    /** True if the developer was present at least once in the period. */
    public static function presentAtLeastOnce(int $devId, string $from, string $to): bool
    {
        return DailyAttendance::find()
            ->where(['status' => 1, 'developer_id' => $devId, 'is_present' => 1])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->exists();
    }

    /** Leave days taken (CL full-day / half-day). */
    public static function leaveTaken(int $devId, string $from, string $to): float
    {
        $records = DailyAttendance::find()
            ->where(['status' => 1, 'is_holiday' => 0, 'developer_id' => $devId])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->all();
        $count = 0;
        foreach ($records as $r) {
            if ($r->leave_type == 7 && $r->total_lop == 1 && $r->is_present == 0)     { $count += 1; }
            if ($r->leave_type == 8 && $r->morning_leave == 1 && $r->is_present == 1) { $count += 0.5; }
            if ($r->leave_type == 8 && $r->evening_leave == 1 && $r->is_present == 1) { $count += 0.5; }
        }
        return $count;
    }

    /** Absent / LOP days (Yii1 get_absent with clbkp=0). Holidays excluded. */
    public static function absentLop(int $devId, string $from, string $to): float
    {
        $records = DailyAttendance::find()
            ->where(['status' => 1, 'is_holiday' => 0, 'developer_id' => $devId])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->all();
        $leave = 0;
        foreach ($records as $r) {
            if ($r->leave_type == 7 && $r->total_lop == 1 && $r->is_present == 0 && (!$r->cl_debited)) { $leave += 1; }
            if ($r->leave_type == 8 && $r->morning_leave == 1 && $r->is_present == 1)                  { $leave += 0.5; }
            if ($r->leave_type == 8 && $r->evening_leave == 1 && $r->is_present == 1)                  { $leave += 0.5; }
            if ($r->leave_type == 3)                                                                    { $leave += 1; }
        }
        return $leave;
    }

    /** Late half-day count (morning_late + evening_late occurrences). */
    public static function lateCount(int $devId, string $from, string $to): float
    {
        $records = DailyAttendance::find()
            ->where(['status' => 1, 'developer_id' => $devId])
            ->andWhere(['or', ['morning_late' => 1], ['evening_late' => 1]])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->all();
        $total = 0;
        foreach ($records as $r) {
            // Office-work sessions are never counted as late.
            if ((int)$r->morning_late === 1 && (int)$r->morning_office_work !== 1) { $total++; }
            if ((int)$r->evening_late === 1 && (int)$r->evening_office_work !== 1) { $total++; }
        }
        return $total;
    }

    /** Permissions taken in period. */
    public static function permissionCount(int $devId, string $from, string $to): int
    {
        return (int)DailyAttendance::find()
            ->where(['status' => 1, 'is_permission' => 1, 'developer_id' => $devId])
            ->andWhere(['>=', 'DATE(attendance_date)', $from])
            ->andWhere(['<=', 'DATE(attendance_date)', $to])
            ->count();
    }

    /** Late LOP — delegates to the shared calculator. */
    public static function lateLop(int $devId, string $from, string $to): float
    {
        return LopCalculator::lateLop($devId, $from, $to);
    }

    /** Sandwich LOP — delegates to the shared calculator (currently a no-op). */
    public static function sandwichLop(int $devId, string $from, string $to): float
    {
        return LopCalculator::sandwichLop($devId, $from, $to);
    }

    /** Per-developer configured deduction values: [salaryAllowanceId => value]. */
    public static function deductionValuesFor(int $devId): array
    {
        $values = [];
        $rows = DeveloperAllowanceDeduction::find()
            ->innerJoinWith('allowanceDeduction')
            ->where([
                'developer_allowance_deduction.status'       => 1,
                'developer_allowance_deduction.developer_id' => $devId,
                'salary_allowance_deduction.status'          => 1,
                'salary_allowance_deduction.type'            => 2,
            ])
            ->all();
        foreach ($rows as $r) {
            $values[$r->allowance_deduction_id] = $r->value;
        }
        return $values;
    }

    // ------------------------------------------------------------- build one

    /**
     * Build and persist ONE payslip (+ detail rows) for a developer/month.
     *
     * $dedValues maps salary_allowance_deduction.id => deduction value; any id
     * omitted falls back to that deduction's default_value.
     *
     * Duplicate handling depends on $overwrite:
     *   false (default) — if a payslip already exists for this developer/month
     *                     it is left untouched and null is returned (create-missing).
     *   true            — an existing payslip is recomputed in place from current
     *                     attendance: its header is refreshed and its detail rows
     *                     are replaced (old ones hard-deleted, since the details
     *                     relation has no status filter and would otherwise dup).
     *
     * @return Payslip|null the saved payslip, or null if skipped / header failed to save.
     */
    public static function buildPayslip(Developer $dev, string $month, int $createdBy, array $dedValues = [], int $workingDays = 30, bool $overwrite = false): ?Payslip
    {
        $from = $month;                               // YYYY-MM-01
        $to   = date('Y-m-t', strtotime($month));     // last day of month
        $daysInMonth = self::daysInMonth($month);     // real calendar days (28/29/30/31)

        $existing = Payslip::find()
            ->where(['developer_id' => $dev->id, 'month_year' => $month, 'status' => 1])
            ->one();
        if ($existing !== null && !$overwrite) {
            return null; // create-missing: leave the existing payslip alone
        }

        $absentLop   = self::absentLop($dev->id, $from, $to);
        $lateLop     = self::lateLop($dev->id, $from, $to);
        $sandwichLop = self::sandwichLop($dev->id, $from, $to);
        $totalLop    = $absentLop + $lateLop + $sandwichLop;

        // Base pay — mirrors the Create Payslip screen: net_salary, else gross_salary.
        $base = (float)$dev->net_salary > 0 ? (float)$dev->net_salary : (float)($dev->gross_salary ?? 0);

        // Central engine — SAME maths as the Create Payslip CSV flow. Earnings split
        // (Basic/DA/HRA/Others) + PF/ESI/PT come from Manage Deductions settings;
        // PF/ESI/PT applicability is driven by the employee's own flags.
        $cfg  = SalaryAllowance::activeByCode();
        $calc = PayrollCalculator::forDeveloper($dev, $base, $daysInMonth, $totalLop, $cfg);

        $allowance = (int)$dev->allowance_applicable === 1 ? round((float)$dev->allowance_amount, 2) : 0.0;
        $incentive = (int)$dev->incentive_status === 1     ? round((float)$dev->incentive_amount, 2) : 0.0;

        // Any custom (non-coded) active deductions still apply on top: fixed amount,
        // or a percentage of Pay After LOP.
        $customDeductions = SalaryAllowance::find()
            ->where(['status' => 1, 'type' => 2, 'code' => null])
            ->all();
        $customAmounts = [];
        $customTotal   = 0.0;
        foreach ($customDeductions as $cd) {
            $v   = (float)($dedValues[$cd->id] ?? $cd->default_value ?? 0);
            $amt = $cd->type2 == 1 ? round(($v / 100) * $calc['payAfterLop'], 2) : round($v, 2);
            $customAmounts[$cd->id] = ['model' => $cd, 'amount' => $amt, 'raw' => $v];
            $customTotal += $amt;
        }

        $netPay = round($calc['netPay'] + $allowance + $incentive - $customTotal, 2);

        $model                     = $existing ?? new Payslip();
        $model->month_year         = $month;
        $model->developer_id       = $dev->id;
        $model->name               = $dev->developer_name;
        $model->emp_id             = $dev->emp_code ?? '';
        $model->designation        = $dev->designation;
        $model->no_of_working_days = $workingDays;
        $model->leave_balance      = $dev->cl_backup;
        $model->leave_allocated    = 1;
        $model->leave_taken        = self::leaveTaken($dev->id, $from, $to);
        $model->leave_lop          = $absentLop;
        $model->late_permission    = self::lateCount($dev->id, $from, $to) + self::permissionCount($dev->id, $from, $to);
        $model->late_lop           = $lateLop;
        $model->additional_working = 0;
        $model->total_lop          = $totalLop;
        $model->gross_salary       = $base;
        $model->actual_salary      = max(0, $netPay);
        $model->remarks            = 'N/A';
        $model->created_by         = $createdBy;
        $model->created_date       = date('Y-m-d H:i:s');
        $model->status             = 1;

        if (!$model->save()) {
            return null;
        }

        // On recompute, clear the old detail rows first. Hard delete (not the
        // usual status=0 soft delete) because Payslip::getDetails() has no
        // status filter — soft-deleted rows would still show up and duplicate.
        if ($existing !== null) {
            PayslipDetail::deleteAll(['payslip_id' => $model->id]);
        }

        // Detail rows: earnings (type=1) then deductions (type=2), mirroring the
        // Create Payslip breakdown. [type, title, amount, deduction_type, percentage]
        $rows   = [];
        $rows[] = [1, 'Basic',  $calc['basic'],  null, null];
        $rows[] = [1, 'DA',     $calc['da'],     null, null];
        $rows[] = [1, 'HRA',    $calc['hra'],    null, null];
        $rows[] = [1, 'Others', $calc['others'], null, null];
        if ($allowance > 0) { $rows[] = [1, 'Allowance', $allowance, null, null]; }
        if ($incentive > 0) { $rows[] = [1, 'Incentive', $incentive, null, null]; }

        // Both employee + employer PF/ESI + PF admin charge are debited (confirmed rule).
        $pf  = $calc['employeePf'] + $calc['employerPf'] + $calc['pfAdmin'];
        $esi = $calc['employeeEsi'] + $calc['employerEsi'];
        if ($pf > 0)         { $rows[] = [2, 'PF',  $pf,  2, null]; }
        if ($esi > 0)        { $rows[] = [2, 'ESI', $esi, 2, null]; }
        if ($calc['pt'] > 0) { $rows[] = [2, 'Professional Tax', $calc['pt'], 2, null]; }
        foreach ($customAmounts as $c) {
            if ($c['amount'] > 0) {
                $rows[] = [2, $c['model']->title, $c['amount'], (int)$c['model']->type2, $c['model']->type2 == 1 ? $c['raw'] : null];
            }
        }

        foreach ($rows as [$type, $title, $amount, $dedType, $pct]) {
            $detail                       = new PayslipDetail();
            $detail->payslip_id           = $model->id;
            $detail->type                 = $type;
            $detail->title                = $title;
            $detail->amount               = round((float)$amount, 2);
            $detail->deduction_type       = $dedType;
            $detail->deduction_percentage = $pct;
            $detail->status               = 1;
            $detail->save(false);
        }

        return $model;
    }

    // ---------------------------------------------------------- cron entry

    /**
     * Generate payslips for ALL active developers for $month using each
     * developer's default deduction values. Employees with no attendance still
     * get a (full-LOP) payslip.
     *
     * $refresh controls existing payslips:
     *   false (cron) — create-missing only; existing payslips are left untouched,
     *                  so the monthly job is safe to re-run and never clobbers edits.
     *   true  (manual "Create Payslip" button) — recompute every existing payslip
     *                  from current attendance AND create any missing ones, so
     *                  post-cron attendance corrections are reflected.
     *
     * @return string human-readable summary for the log / flash message.
     */
    public static function generateForMonth(string $month, int $createdBy, bool $refresh = false): string
    {
        $month      = date('Y-m-01', strtotime($month));
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        foreach ($developers as $dev) {
            $existed = Payslip::find()
                ->where(['developer_id' => $dev->id, 'month_year' => $month, 'status' => 1])
                ->exists();
            if ($existed && !$refresh) { $skipped++; continue; }

            $dedValues = self::deductionValuesFor($dev->id);
            if (self::buildPayslip($dev, $month, $createdBy, $dedValues, 30, $refresh) !== null) {
                if ($existed) { $updated++; } else { $created++; }
            }
        }

        return sprintf(
            'Payslips for %s — created: %d, updated: %d, skipped: %d, total active: %d.',
            date('M Y', strtotime($month)),
            $created,
            $updated,
            $skipped,
            count($developers)
        );
    }

    /**
     * Build the monthly payslip CSV and register it in `upload_payslip`, so
     * cron-generated payslips also appear in the "Manage Payslips" (payslip-upload)
     * screen — exactly like the "Create Payslip" button does. Same employee list,
     * same columns and same PayrollCalculator maths as
     * DeveloperController::actionGeneratePayslip; LOP comes from attendance.
     *
     * @return string|null relative file path, or null if there were no employees.
     */
    public static function generateCsvFile(string $month, int $uploadedBy): ?string
    {
        $from      = date('Y-m-01', strtotime($month));
        $to        = date('Y-m-t', strtotime($from));
        $monthYear = $to;                       // upload_payslip stores last day of month
        $days      = self::daysInMonth($from);
        $cfg       = SalaryAllowance::activeByCode();

        // Same list as the Create Payslip screen: active salary-applicable employees
        // plus ex-employees who checked in during the month.
        $exDevIds = (new \yii\db\Query())
            ->select('developer_id')->distinct()->from('dailyattendance')
            ->where(['between', 'attendance_date', $from, $to])
            ->andWhere(['not', ['in_time' => null]])
            ->column();
        $developers = Developer::find()
            ->andWhere(['or',
                ['status' => 1, 'is_salary_applicable' => 1],
                ['and', ['status' => 0], ['id' => $exDevIds]],
            ])
            ->orderBy(['developer_name' => SORT_ASC])
            ->all();

        $headers = [
            'S.No', 'EMP CODE', 'EMP NAME', 'TOTAL PAY', 'LOP', 'LOP (RS)', 'PAY AFTER LOP',
            'BASIC', 'DA', 'HRA', 'OTHERS', 'ALLOWANCE', 'INCENTIVE',
            'TDS', 'PT', 'PF', 'ESI', 'OTHER DEDUCTION', 'NET PAY',
            'EMPLOYER EPF CONTRIBUTION', 'EMPLOYER ESI CONTRIBUTION',
            'EMPLOYEE EPF CONTRIBUTION', 'EMPLOYEE ESI CONTRIBUTION', 'ACTION',
        ];
        $rows    = [];
        $userIds = [];
        $totals  = array_fill(0, count($headers), 0);

        foreach ($developers as $i => $dev) {
            $base = (float)$dev->net_salary > 0 ? (float)$dev->net_salary : (float)($dev->gross_salary ?? 0);
            $lop  = LopCalculator::totalLop($dev->id, $from, $to);
            $calc = PayrollCalculator::forDeveloper($dev, $base, $days, $lop, $cfg);

            $allowance = (int)$dev->allowance_applicable === 1 ? round((float)$dev->allowance_amount, 2) : 0.0;
            $incentive = (int)$dev->incentive_status === 1     ? round((float)$dev->incentive_amount, 2) : 0.0;

            $pf     = round($calc['employeePf'] + $calc['employerPf'] + $calc['pfAdmin'], 2);
            $esi    = round($calc['employeeEsi'] + $calc['employerEsi'], 2);
            $netPay = round($calc['netPay'] + $allowance + $incentive, 2);

            $row = [
                $i + 1, $dev->emp_code ?? '', $dev->developer_name, round($base, 2),
                $lop, $calc['lopRs'], $calc['payAfterLop'],
                $calc['basic'], $calc['da'], $calc['hra'], $calc['others'], $allowance, $incentive,
                $calc['tds'], $calc['pt'], $pf, $esi, 0.0, $netPay,
                $calc['employerPf'], $calc['employerEsi'], $calc['employeePf'], $calc['employeeEsi'], '',
            ];
            $rows[] = $row;
            if (!empty($dev->user_id)) { $userIds[] = json_encode($dev->user_id); }
            foreach ($row as $ci => $val) {
                if ($ci >= 3 && $ci <= count($headers) - 2 && is_numeric($val)) { $totals[$ci] += (float)$val; }
            }
        }

        if (empty($rows)) { return null; }

        $totalRow = array_fill(0, count($headers), '');
        $totalRow[2] = 'Total';
        for ($ci = 3; $ci <= count($headers) - 2; $ci++) { $totalRow[$ci] = round($totals[$ci], 2); }

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $r) { fputcsv($fh, $r); }
        fputcsv($fh, $totalRow);
        rewind($fh);
        $content = stream_get_contents($fh);
        fclose($fh);

        $dir = \Yii::getAlias('@backend/web/uploads/upload_payslip/');
        if (!is_dir($dir)) { mkdir($dir, 0750, true); }
        // Stable per-month filename so repeated cron runs overwrite rather than pile up.
        $filename = 'payslip_' . $monthYear . '.csv';
        file_put_contents($dir . $filename, $content);
        $relPath = '/uploads/upload_payslip/' . $filename;

        $record = UploadPayslip::find()->where(['month_year' => $monthYear])->one() ?? new UploadPayslip();
        $record->month_year    = $monthYear;
        $record->file_path     = $relPath;
        $record->uploaded_by   = $uploadedBy;
        $record->uploaded_date = date('Y-m-d H:i:s');
        $record->developer_id  = !empty($userIds) ? json_encode($userIds) : null;
        $record->save(false);

        return $relPath;
    }
}
