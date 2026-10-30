<?php

namespace backend\components;

use backend\models\Developer;
use backend\models\SalaryAllowance;

/**
 * Central payroll engine — the single source of truth for the salary breakdown.
 *
 * All PF / ESI / Professional-Tax rates, ceilings and the earnings split come
 * from the salary_allowance_deduction settings (Manage Deductions), NOT from
 * per-employee amounts. On the employee we only read the Applicable flags
 * (pf_status, esi_status, professional_tax_status, tds_status).
 *
 * Formula (decoded from ERP Formula.xlsx, with the confirmed business rule that
 * BOTH the employee and employer PF/ESI are deducted from net pay):
 *
 *   perDay        = totalPay / daysInMonth
 *   lopRs         = perDay * lopDays
 *   payAfterLop   = totalPay - lopRs
 *   basic/da/hra  = payAfterLop * their %        (Basic 50, DA 12, HRA 30)
 *   others        = payAfterLop - (basic+da+hra) (remainder; Others ≈ 8%)
 *   EPF salary    = min(Basic+DA, 15000)         (PF wage ceiling)
 *   ESIC salary   = payAfterLop, or 0 if > 21000 (ESI eligibility ceiling)
 *   employeePf    = EPF salary  * 12%            employerPf  = EPF salary * 13%
 *   employeeEsi   = ESIC salary * 0.75%          employerEsi = ESIC salary * 3.25%
 *   PT            = flat amount                  TDS = 0 (hidden for now)
 *   netPay        = payAfterLop - employeePf - employeeEsi
 *                              - employerPf - employerEsi - tds - pt
 *
 * netPay here excludes allowance/incentive — the caller adds those on top.
 */
class PayrollCalculator
{
    /**
     * @param array $cfg  code => SalaryAllowance (from SalaryAllowance::activeByCode())
     * @return array{
     *   perDay:float, lopRs:float, payAfterLop:float,
     *   basic:float, da:float, hra:float, others:float, grossSalary:float,
     *   epfSalary:float, esicSalary:float,
     *   employeePf:float, employerPf:float, pfAdmin:float, employeeEsi:float, employerEsi:float,
     *   tds:float, pt:float, totalDeductions:float, netPay:float
     * }
     */
    public static function compute(
        float $totalPay,
        int $daysInMonth,
        float $lopDays,
        array $cfg,
        bool $pfApplicable,
        bool $esiApplicable,
        bool $ptApplicable,
        bool $tdsApplicable = false
    ): array {
        $daysInMonth = max(1, $daysInMonth);
        $perDay      = $totalPay > 0 ? $totalPay / $daysInMonth : 0.0;
        $lopRs       = round($perDay * $lopDays, 2);
        $payAfterLop = round($totalPay - $lopRs, 2);

        // --- Earnings split (% of Pay After LOP) --------------------------------
        $basicPct = self::pct($cfg, SalaryAllowance::CODE_BASIC, 50);
        $daPct    = self::pct($cfg, SalaryAllowance::CODE_DA, 12);
        $hraPct   = self::pct($cfg, SalaryAllowance::CODE_HRA, 30);

        $basic  = round($payAfterLop * $basicPct / 100, 2);
        $da     = round($payAfterLop * $daPct / 100, 2);
        $hra    = round($payAfterLop * $hraPct / 100, 2);
        $others = round($payAfterLop - ($basic + $da + $hra), 2); // remainder avoids drift
        $grossSalary = round($basic + $da + $hra + $others, 2);

        $basicPlusDa = round($basic + $da, 2);

        // --- PF (employee + employer + admin charge), base capped by wage ceiling
        $employeePf = $employerPf = $pfAdmin = $epfSalary = 0.0;
        $pf = $cfg[SalaryAllowance::CODE_PF] ?? null;
        if ($pfApplicable && $pf) {
            $base      = self::basisValue($pf->calc_basis, $payAfterLop, $basicPlusDa);
            $epfSalary = ($pf->wage_ceiling !== null && $pf->wage_ceiling !== '')
                ? min($base, (float)$pf->wage_ceiling)
                : $base;
            $employeePf = round($epfSalary * (float)$pf->employee_value / 100, 2);
            $employerPf = round($epfSalary * (float)$pf->employer_value / 100, 2);
            $pfAdmin    = round($epfSalary * (float)($pf->admin_charge ?? 0) / 100, 2);
        }

        // --- ESI (employee + employer). Ceiling is an ELIGIBILITY threshold ------
        $employeeEsi = $employerEsi = $esicSalary = 0.0;
        $esi = $cfg[SalaryAllowance::CODE_ESI] ?? null;
        if ($esiApplicable && $esi) {
            $base       = self::basisValue($esi->calc_basis, $payAfterLop, $basicPlusDa);
            $esicSalary = ($esi->wage_ceiling !== null && $esi->wage_ceiling !== '' && $base > (float)$esi->wage_ceiling)
                ? 0.0
                : $base;
            $employeeEsi = round($esicSalary * (float)$esi->employee_value / 100, 2);
            $employerEsi = round($esicSalary * (float)$esi->employer_value / 100, 2);
        }

        // --- Professional tax (flat amount) -------------------------------------
        $pt = 0.0;
        $ptCfg = $cfg[SalaryAllowance::CODE_PT] ?? null;
        if ($ptApplicable && $ptCfg) {
            $pt = round((float)$ptCfg->default_value, 2);
        }

        // --- TDS (hidden for now — 0 unless explicitly applicable) --------------
        $tds = 0.0;
        $tdsCfg = $cfg[SalaryAllowance::CODE_TDS] ?? null;
        if ($tdsApplicable && $tdsCfg) {
            $tds = round((float)$tdsCfg->default_value, 2);
        }

        // Confirmed rule: subtract BOTH employee and employer PF/ESI, plus the PF admin charge.
        $totalDeductions = round($employeePf + $employeeEsi + $employerPf + $employerEsi + $pfAdmin + $tds + $pt, 2);
        $netPay          = round($payAfterLop - $totalDeductions, 2);

        return [
            'perDay'          => round($perDay, 2),
            'lopRs'           => $lopRs,
            'payAfterLop'     => $payAfterLop,
            'basic'           => $basic,
            'da'              => $da,
            'hra'             => $hra,
            'others'          => $others,
            'grossSalary'     => $grossSalary,
            'epfSalary'       => round($epfSalary, 2),
            'esicSalary'      => round($esicSalary, 2),
            'employeePf'      => $employeePf,
            'employerPf'      => $employerPf,
            'pfAdmin'         => $pfAdmin,
            'employeeEsi'     => $employeeEsi,
            'employerEsi'     => $employerEsi,
            'tds'             => $tds,
            'pt'              => $pt,
            'totalDeductions' => $totalDeductions,
            'netPay'          => $netPay,
        ];
    }

    /** Convenience: pull the Applicable flags off a Developer. */
    public static function forDeveloper(
        Developer $dev,
        float $totalPay,
        int $daysInMonth,
        float $lopDays,
        ?array $cfg = null
    ): array {
        $cfg ??= SalaryAllowance::activeByCode();
        return self::compute(
            $totalPay,
            $daysInMonth,
            $lopDays,
            $cfg,
            (int)$dev->pf_status === 1,
            (int)$dev->esi_status === 1,
            (int)$dev->professional_tax_status === 1,
            (int)$dev->tds_status === 1
        );
    }

    private static function pct(array $cfg, string $code, float $default): float
    {
        $row = $cfg[$code] ?? null;
        return $row && $row->default_value !== null && $row->default_value !== ''
            ? (float)$row->default_value
            : $default;
    }

    private static function basisValue(?int $basis, float $payAfterLop, float $basicPlusDa): float
    {
        return (int)$basis === SalaryAllowance::BASIS_BASIC_PLUS_DA ? $basicPlusDa : $payAfterLop;
    }
}
