<?php

namespace backend\controllers;

use backend\components\PayrollCalculator;
use backend\components\PayslipGenerator;
use backend\models\Developer;
use backend\models\DeveloperAllowanceDeduction;
use backend\models\Payslip;
use backend\models\PayslipDetail;
use backend\models\SalaryAllowance;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend Payslip management — mirrors Yii1 PayslipController
 */
class PayslipController extends Controller
{
    // ------------------------------------------------------------------ index (one row per distinct month)

    /**
     * Lists ONE ROW PER DISTINCT MONTH (like Yii1 naras listing).
     * Click View → view_month page showing all employees for that month.
     * Admin sees all months; developer sees only months they have a payslip in.
     */
    public function actionIndex(): string
    {
        /** @var \backend\models\User $user */
        $user    = Yii::$app->user->identity;
        $isAdmin = $user->isAdmin() || $user->isSubAdmin();

        $filterMonth = trim(Yii::$app->request->get('month_year', ''));
        $page        = max(0, (int)Yii::$app->request->get('page', 1) - 1);
        $limit       = 10;
        $db          = Yii::$app->db;

        // Shared base condition
        $where = ['status' => 1];
        if (!$isAdmin) {
            $dev = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            $where['developer_id'] = $dev ? $dev->id : 0;
        }

        // LIKE filter on YYYY-MM portion (matches Yii1 partial compare)
        $like = '';
        if ($filterMonth !== '' && preg_match('/(\d{4}-\d{2})/', $filterMonth, $m)) {
            $like = $m[1];
        }

        // Count distinct months
        $countQ = (new \yii\db\Query())->from('payslip')->where($where)->select('COUNT(DISTINCT month_year)');
        if ($like !== '') { $countQ->andWhere(['LIKE', 'month_year', $like]); }
        $total = (int)$countQ->scalar($db);

        // Fetch one row per distinct month
        $dataQ = (new \yii\db\Query())
            ->select(['month_year', 'COUNT(*) as emp_count', 'MAX(created_date) as generated_on'])
            ->from('payslip')
            ->where($where);
        if ($like !== '') { $dataQ->andWhere(['LIKE', 'month_year', $like]); }
        $dataQ->groupBy('month_year')->orderBy(['month_year' => SORT_DESC])->limit($limit)->offset($page * $limit);

        $months = $dataQ->all($db);

        return $this->render('index', compact('months', 'total', 'page', 'limit', 'isAdmin', 'filterMonth'));
    }

    // ---------------------------------------------------------- view by month (all employees for a month)

    /**
     * Shows all employees' payslips for the given month.
     * Accessible to admin; developer sees only their own row.
     */
    public function actionViewMonth(string $month): string
    {
        /** @var \backend\models\User $user */
        $user    = Yii::$app->user->identity;
        $isAdmin = $user->isAdmin() || $user->isSubAdmin();

        $query = Payslip::find()
            ->with('developer')
            ->where(['status' => 1, 'month_year' => $month])
            ->orderBy(['name' => SORT_ASC]);

        if (!$isAdmin) {
            $dev = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            if ($dev) { $query->andWhere(['developer_id' => $dev->id]); }
            else       { $query->andWhere('0=1'); }
        }

        $payslips = $query->all();

        return $this->render('view_month', compact('month', 'payslips', 'isAdmin'));
    }

    // ---------------------------------------------------------- delete whole month

    public function actionDeleteMonth(string $month): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }
        Payslip::updateAll(['status' => 0], ['month_year' => $month, 'status' => 1]);
        Yii::$app->session->setFlash('success', 'Payslips for ' . date('M Y', strtotime($month)) . ' deleted.');
        return $this->redirect(['index']);
    }

    // ---------------------------------------------------------- select month

    /**
     * "Create Payslip" button: generate/sync the PREVIOUS month immediately.
     *
     * No month picker — it always targets the month that just closed. Recomputes
     * every payslip for that month from CURRENT attendance and creates any
     * missing employees (same logic as the monthly cron, run here in refresh
     * mode). This lets an admin re-run it after the cron when an employee asks
     * for a correction — the corrected attendance flows straight through. Lands
     * on the month view showing the refreshed data.
     */
    public function actionSelectmonth(): Response
    {
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['index']);
        }

        $date = date('Y-m-01', strtotime('first day of last month'));

        $summary = PayslipGenerator::generateForMonth($date, (int)Yii::$app->user->id, true);
        Yii::$app->session->setFlash('success', $summary);
        return $this->redirect(['view-month', 'month' => $date]);
    }

    // --------------------------------------------------------- create payslip

    /**
     * Step 2: show table of all active developers with precomputed attendance + editable deduction inputs
     */
    public function actionCreatepayslip(string $month): string|Response
    {
        // Re-check if already generated
        $exists = Payslip::find()->where(['month_year' => $month, 'status' => 1])->exists();
        if ($exists) {
            Yii::$app->session->setFlash('error', 'Payslip already generated for ' . date('M Y', strtotime($month)));
            return $this->redirect(['index']);
        }

        $fromDate = $month;                                // YYYY-MM-01
        $toDate   = date('Y-m-t', strtotime($month));     // last day of month

        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        // All active allowances (type=1) and deductions (type=2)
        $allowances = SalaryAllowance::find()->where(['status' => 1, 'type' => 1])->orderBy('id')->all();
        $deductions = SalaryAllowance::find()->where(['status' => 1, 'type' => 2])->orderBy('id')->all();

        // Per-developer allowance values: devAllowances[devId][sadId] = value
        $devAllowances = [];
        $devDeductions = [];
        foreach ($developers as $dev) {
            $rows = DeveloperAllowanceDeduction::find()
                ->innerJoinWith('allowanceDeduction')
                ->where([
                    'developer_allowance_deduction.status'      => 1,
                    'developer_allowance_deduction.developer_id' => $dev->id,
                    'salary_allowance_deduction.status'         => 1,
                ])
                ->all();
            foreach ($rows as $r) {
                if ($r->allowanceDeduction->type == 1) {
                    $devAllowances[$dev->id][$r->allowance_deduction_id] = $r->value;
                } else {
                    $devDeductions[$dev->id][$r->allowance_deduction_id] = $r->value;
                }
            }
        }

        // Pre-compute attendance + salary per developer
        $devData = [];
        foreach ($developers as $dev) {
            if (!$this->chkPresentOneTimeMonth($dev->id, $fromDate, $toDate)) {
                continue; // skip those who weren't present at all
            }
            $leaveTaken  = $this->getLeaveTaken($dev->id, $fromDate, $toDate);
            $absentLop   = $this->getAbsentLop($dev->id, $fromDate, $toDate);
            $lateCount   = $this->getLateCount($dev->id, $fromDate, $toDate);
            $permCount   = $this->getPermissionCount($dev->id, $fromDate, $toDate);
            $lateLop     = $this->getLateLop($dev->id, $fromDate, $toDate);
            $sandwichLop = $this->getSandwichLop($dev->id, $fromDate, $toDate);
            $totalLop    = $absentLop + $lateLop + $sandwichLop;

            // Mirror the saved payslip (PayslipGenerator::buildPayslip): base pay is
            // net_salary else gross; PF/ESI/PT come from Manage Deductions settings.
            $base = (float)$dev->net_salary > 0 ? (float)$dev->net_salary : (float)($dev->gross_salary ?? 0);
            $calc = PayrollCalculator::forDeveloper($dev, $base, PayslipGenerator::daysInMonth($month), $totalLop);
            $allowanceAmt = (int)$dev->allowance_applicable === 1 ? (float)$dev->allowance_amount : 0.0;
            $incentiveAmt = (int)$dev->incentive_status === 1     ? (float)$dev->incentive_amount : 0.0;
            $actualSalary = max(0, round($calc['netPay'] + $allowanceAmt + $incentiveAmt, 2));

            $devData[$dev->id] = [
                'leave_balance'    => $dev->cl_backup,
                'leave_allocated'  => 1,
                'leave_taken'      => $leaveTaken,
                'leave_lop'        => $absentLop,
                'late_permission'  => $lateCount + $permCount,
                'late_lop'         => $lateLop,
                'additional_working' => 0,
                'total_lop'        => $totalLop,
                'actual_salary'    => $actualSalary,
            ];
        }

        return $this->render('create_payslip', compact(
            'month', 'developers', 'allowances', 'deductions',
            'devAllowances', 'devDeductions', 'devData'
        ));
    }

    // ------------------------------------------------------- generate payslip

    /**
     * Step 3: POST from create_payslip form → bulk save payslips
     */
    public function actionGeneratepayslip(): Response
    {
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['index']);
        }
        $post = Yii::$app->request->post();
        if (empty($post['generatepayslip']) || empty($post['date'])) {
            Yii::$app->session->setFlash('error', 'Invalid request.');
            return $this->redirect(['index']);
        }

        $monthYear = $post['date'];
        $exists = Payslip::find()->where(['month_year' => $monthYear, 'status' => 1])->exists();
        if ($exists) {
            Yii::$app->session->setFlash('error', 'Payslip already created for this month.');
            return $this->redirect(['index']);
        }

        $fromDate    = $monthYear;
        $toDate      = date('Y-m-t', strtotime($monthYear));
        $developers  = Developer::find()->where(['status' => 1])->all();
        $allDedPost  = $post['deduction'] ?? [];
        $workingDays = $post['workingDays'] ?? [];
        $createdBy   = (int)Yii::$app->user->id;

        foreach ($developers as $dev) {
            // Manual flow keeps its original guard: skip employees who were not
            // present at all during the month.
            if (!$this->chkPresentOneTimeMonth($dev->id, $fromDate, $toDate)) {
                continue;
            }

            // Use the admin-adjusted POST deduction values + working days.
            $devDedPost  = $allDedPost[$dev->id] ?? [];
            $devWorkDays = isset($workingDays[$dev->id]) ? (int)$workingDays[$dev->id] : 30;

            PayslipGenerator::buildPayslip($dev, $monthYear, $createdBy, $devDedPost, $devWorkDays);
        }

        Yii::$app->session->setFlash('success', 'Payslips generated successfully.');
        return $this->redirect(['index']);
    }

    // --------------------------------------------------- AJAX: calculate salary

    /**
     * AJAX for create_payslip form: returns plain text rounded salary.
     * POST: id (developer_id), data (array of {deductionID, deductionValue}), date_month
     */
    public function actionCalculatesalary(): void
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        if (!Yii::$app->request->isPost) { echo 0; return; }

        $devId         = (int)Yii::$app->request->post('id');
        $monthYear     = Yii::$app->request->post('date_month', '');
        $deductionData = Yii::$app->request->post('data', []);

        $dev = Developer::findOne($devId);
        if (!$dev) { echo 0; return; }

        // Base pay: net_salary else gross_salary (matches buildPayslip / Create Payslip).
        $base = (float)$dev->net_salary > 0 ? (float)$dev->net_salary : (float)($dev->gross_salary ?? 0);
        if ($base <= 0) { echo 0; return; }

        $fromDate = date('Y-m-01', strtotime($monthYear . '-01'));
        $toDate   = date('Y-m-t', strtotime($monthYear . '-01'));

        $absentLop = $this->getAbsentLop($devId, $fromDate, $toDate);
        $lateLop   = $this->getLateLop($devId, $fromDate, $toDate);
        $totalLop  = $absentLop + $lateLop + $this->getSandwichLop($devId, $fromDate, $toDate);

        // PF/ESI/PT + earnings from the central settings; applicability from the
        // employee's flags. The posted $deductionData is no longer used.
        $days = PayslipGenerator::daysInMonth($fromDate);
        $calc = PayrollCalculator::forDeveloper($dev, $base, $days, $totalLop);
        $allowanceAmt = (int)$dev->allowance_applicable === 1 ? (float)$dev->allowance_amount : 0.0;
        $incentiveAmt = (int)$dev->incentive_status === 1     ? (float)$dev->incentive_amount : 0.0;

        echo max(0, round($calc['netPay'] + $allowanceAmt + $incentiveAmt, 2));
    }

    // --------------------------------------------------------------- create (manual)

    public function actionCreate(): string|Response
    {
        $payslip    = new Payslip();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        // Default the month to the PREVIOUS month — payslips are generated after the
        // month closes, never for the current (in-progress) month.
        $payslip->month_year = date('Y-m-01', strtotime('first day of last month'));

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $payslip->load($post, 'Payslip');
            $payslip->created_by   = Yii::$app->user->id;
            $payslip->created_date = date('Y-m-d H:i:s');
            $payslip->status       = 1;

            if ($payslip->save()) {
                foreach ($post['items'] ?? [] as $item) {
                    if (empty($item['label'])) { continue; }
                    $detail             = new PayslipDetail();
                    $detail->payslip_id = $payslip->id;
                    $detail->type       = (int)($item['item_type'] ?? 1);
                    $detail->title      = $item['label'];
                    $detail->amount     = (float)($item['amount'] ?? 0);
                    $detail->status     = 1;
                    $detail->save(false);
                }
                Yii::$app->session->setFlash('success', 'Payslip created successfully.');
                return $this->redirect(['view', 'id' => $payslip->id]);
            }
        }

        return $this->render('create', compact('payslip', 'developers'));
    }

    // --------------------------------------------------------------- update

    public function actionUpdate(int $id): string|Response
    {
        $model     = $this->findModel($id);
        $developer = $model->developer;

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();

            // Update each PayslipDetail amount
            foreach ($post['allowances'] ?? [] as $detailId => $amount) {
                $detail = PayslipDetail::findOne(['id' => (int)$detailId, 'payslip_id' => $model->id]);
                if ($detail) { $detail->amount = round((float)$amount, 2); $detail->save(false); }
            }
            foreach ($post['deductions'] ?? [] as $detailId => $amount) {
                $detail = PayslipDetail::findOne(['id' => (int)$detailId, 'payslip_id' => $model->id]);
                if ($detail) {
                    if ($detail->deduction_type == 1) {
                        $detail->deduction_percentage = (float)$amount;
                        $detail->amount = round(((float)$amount / 100) * (float)$model->gross_salary, 2);
                    } else {
                        $detail->amount               = round((float)$amount, 2);
                        $detail->deduction_percentage = 0;
                    }
                    $detail->save(false);
                }
            }

            $model->total_lop          = (float)($post['no_of_lop']       ?? $model->total_lop);
            $model->additional_working = (float)($post['no_of_extra']      ?? $model->additional_working);
            $model->actual_salary      = (float)($post['actual_salary']    ?? $model->actual_salary);
            $model->save(false);

            Yii::$app->session->setFlash('success', 'Payslip updated successfully.');
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', ['model' => $model, 'developer' => $developer]);
    }

    // --------------------------------------------------------------- view

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionPrint(int $id): string
    {
        $this->layout = false;
        $model     = $this->findModel($id);
        $developer = $model->developer;
        return $this->render('print', ['model' => $model, 'developer' => $developer]);
    }

    /**
     * Download payslip as PDF via mPDF — mirrors Yii1 downloadpayslip action.
     */
    public function actionDownloadPdf(int $id)
    {
        $model     = $this->findModel($id);
        $developer = $model->developer;

        $this->layout = false;
        $html = $this->renderPartial('print', ['model' => $model, 'developer' => $developer, 'isPdf' => true]);

        $tempDir = Yii::getAlias('@runtime/mpdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'tempDir'       => $tempDir,
            'format'        => 'A4',
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 0,
            'margin_bottom' => 0,
        ]);
        $mpdf->WriteHTML($html);
        $pdfContent = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        $fileName = 'PaySlip_' . date('M_Y', strtotime($model->month_year)) . '.pdf';
        $response = Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->content = $pdfContent;
        return $response;
    }

    // --------------------------------------------------------------- delete

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }
        $payslip         = $this->findModel($id);
        $payslip->status = 0;
        $payslip->save(false);
        Yii::$app->session->setFlash('success', 'Payslip removed.');
        return $this->redirect(['index']);
    }

    // --------------------------------------------------------------- export

    public function actionExportPayslip(): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST only.');
        }

        $devIds    = Yii::$app->request->post('employee', []);
        $filterMon = (int)Yii::$app->request->post('filter_month', 0);
        $filterYr  = (int)Yii::$app->request->post('filter_year', 0);

        if (empty($devIds)) {
            Yii::$app->session->setFlash('error', 'Select at least one employee.');
            return $this->redirect(['index']);
        }

        $query = Payslip::find()->with('developer')->where(['status' => 1]);
        if ($filterYr && $filterMon) {
            $query->andWhere(['month_year' => sprintf('%04d-%02d-01', $filterYr, $filterMon)]);
        } elseif ($filterYr) {
            $query->andWhere(['LIKE', 'month_year', $filterYr]);
        }
        $query->andWhere(['developer_id' => array_map('intval', (array)$devIds)]);
        $payslips = $query->orderBy(['developer_id' => SORT_ASC])->all();

        if (empty($payslips)) {
            Yii::$app->session->setFlash('error', 'No payslips found for export.');
            return $this->redirect(['index']);
        }

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $headers     = [
            '#', 'Employees Name', 'No Of Working Days',
            'Leave Balance', 'Leave Allocated', 'Leave Taken', 'Leave Lop',
            'Late / Permission', 'Late Lop', 'Additional Working', 'Total Lop',
            'Gross Salary', 'Actual Salary', 'Remarks',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        foreach ($sheet->getColumnIterator('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
        }
        // Bold headers
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);

        $row = 2;
        $grossTotal  = 0;
        $actualTotal = 0;
        foreach ($payslips as $i => $p) {
            $grossTotal  += (float)$p->gross_salary;
            $actualTotal += (float)$p->actual_salary;
            $sheet->fromArray([
                $i + 1,
                $p->name ?? ($p->developer->developer_name ?? ''),
                $p->no_of_working_days ?? 30,
                $p->leave_balance ?? 0,
                $p->leave_allocated ?? 0,
                $p->leave_taken ?? 0,
                $p->leave_lop ?? 0,
                $p->late_permission ?? 0,
                $p->late_lop ?? 0,
                $p->additional_working ?? 0,
                $p->total_lop ?? 0,
                (float)$p->gross_salary,
                (float)$p->actual_salary,
                $p->remarks ?? '',
            ], null, 'A' . $row++);
        }
        // Totals row
        $sheet->setCellValue('K' . $row, 'Total Amount');
        $sheet->setCellValue('L' . $row, $grossTotal);
        $sheet->setCellValue('M' . $row, $actualTotal);
        $sheet->getStyle('K' . $row . ':M' . $row)->getFont()->setBold(true);

        $label  = ($filterYr && $filterMon) ? date('M_Y', strtotime(sprintf('%04d-%02d-01', $filterYr, $filterMon))) : 'All';
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return Yii::$app->response->sendContentAsFile(
            $content,
            'Employee_Payslip_' . $label . '.xlsx',
            ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    // ---------------------------------------------------------- private helpers

    private function findModel(int $id): Payslip
    {
        $model = Payslip::findOne(['id' => $id, 'status' => 1]);
        if (!$model) { throw new NotFoundHttpException('Payslip not found.'); }
        return $model;
    }

    /** Calculate net salary from gross, total LOP days, and deduction POST values */
    // Computation helpers now live in PayslipGenerator so the manual flow and
    // the monthly auto-generation cron share one source of truth. These thin
    // wrappers are kept so the preview (actionCreatepayslip) and the AJAX
    // salary calc (actionCalculatesalary) read unchanged.

    private function calcSalary(float $gross, float $totalLop, array $devDedValues, array $deductionModels, int $daysInMonth = 30): float
    {
        return PayslipGenerator::calcSalary($gross, $totalLop, $devDedValues, $deductionModels, $daysInMonth);
    }

    private function chkPresentOneTimeMonth(int $devId, string $fromDate, string $toDate): bool
    {
        return PayslipGenerator::presentAtLeastOnce($devId, $fromDate, $toDate);
    }

    private function getLeaveTaken(int $devId, string $fromDate, string $toDate): float
    {
        return PayslipGenerator::leaveTaken($devId, $fromDate, $toDate);
    }

    private function getAbsentLop(int $devId, string $fromDate, string $toDate): float
    {
        return PayslipGenerator::absentLop($devId, $fromDate, $toDate);
    }

    private function getLateCount(int $devId, string $fromDate, string $toDate): float
    {
        return PayslipGenerator::lateCount($devId, $fromDate, $toDate);
    }

    private function getPermissionCount(int $devId, string $fromDate, string $toDate): int
    {
        return PayslipGenerator::permissionCount($devId, $fromDate, $toDate);
    }

    private function getLateLop(int $devId, string $fromDate, string $toDate): float
    {
        return PayslipGenerator::lateLop($devId, $fromDate, $toDate);
    }

    private function getSandwichLop(int $devId, string $fromDate, string $toDate): float
    {
        return PayslipGenerator::sandwichLop($devId, $fromDate, $toDate);
    }
}
