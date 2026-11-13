<?php

namespace backend\controllers;

use common\components\Utility;
use backend\models\Developer;
use backend\models\UploadPayslip;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class PayslipUploadController extends Controller
{
    /** Full pages get the HR module stylesheet and script. */
    public function render($view, $params = []): string
    {
        \backend\assets\HrAsset::register($this->getView());
        return parent::render($view, $params);
    }

    /**
     * Admin list — matches Yii1 uploadpayslip/admin.
     * Filters: month_year (YYYY-MM).
     */
    public function actionIndex(): string
    {
        $query = UploadPayslip::find()->orderBy(['month_year' => SORT_DESC]);

        $monthYear = Yii::$app->request->get('month_year');
        if (!empty($monthYear)) {
            $start = date('Y-m-01', strtotime($monthYear));
            $end   = date('Y-m-t', strtotime($monthYear));
            $query->andWhere(['between', 'month_year', $start, $end]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 10],
        ]);

        return $this->render('index', compact('dataProvider', 'monthYear'));
    }

    /**
     * Validate an uploaded payslip sheet before it is accepted. Returns a list of
     * HTML-safe error messages; an empty list means the sheet passed every check.
     *
     * Checks performed:
     *  - "EMP CODE" header is present (row-level checks are skipped without it);
     *  - every value in a numeric column is actually numeric (no stray text);
     *  - no numeric column holds a negative value;
     *  - no duplicate serial numbers (serial column, when present);
     *  - no duplicate employee codes (EMP CODE column).
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @return string[]
     */
    private function validatePayslipSheet($sheet): array
    {
        $errors = [];

        $normaliseHeader = static function ($v): string {
            if (!is_string($v)) { return ''; }
            $v = preg_replace('/^\xEF\xBB\xBF/u', '', $v);   // strip UTF-8 BOM
            $v = preg_replace('/\s+/u', ' ', trim($v));      // collapse whitespace
            return strtoupper($v);
        };

        $highestCol = $sheet->getHighestColumn();
        $lastCol    = Coordinate::columnIndexFromString($highestCol);
        $lastRow    = $sheet->getHighestRow();
        $maxRows    = min($lastRow, 1000);

        // Map every column letter to its normalised header, and locate the
        // EMP CODE and serial-number columns.
        $colHeader   = [];
        $empCodeCol  = null;
        $serialCol   = null;
        $serialNames = ['S.NO', 'SL.NO', 'S NO', 'SL NO', 'SR.NO', 'SR NO',
                        'SNO', 'SLNO', 'SERIAL', 'SERIAL NO', 'SERIAL NUMBER', '#'];
        $empNameCol  = null;
        for ($ci = 0; $ci < $lastCol; $ci++) {
            $col = Coordinate::stringFromColumnIndex($ci + 1);
            $h   = $normaliseHeader($sheet->getCell($col . '1')->getValue());
            $colHeader[$col] = $h;
            if ($h === 'EMP CODE') { $empCodeCol = $col; }
            if ($h === 'EMP NAME') { $empNameCol = $col; }
            if ($serialCol === null && in_array($h, $serialNames, true)) { $serialCol = $col; }
        }

        if ($empCodeCol === null) {
            $errors[] = 'Column name "EMP CODE" missing or incorrect in your file. '
                . 'Headers detected: ' . Html::encode(implode(' | ', array_filter(array_values($colHeader))));
            return $errors;
        }

        // Fall back to column A as the serial column when it isn't the EMP CODE
        // column and no named serial header was found (the standard layout is
        // S.NO | EMP CODE | EMP NAME | …).
        if ($serialCol === null && isset($colHeader['A']) && 'A' !== $empCodeCol && $colHeader['A'] !== '') {
            $serialCol = 'A';
        }

        $seenEmp    = [];   // normalised emp code => first row it appeared on
        $seenSerial = [];   // serial value       => first row it appeared on

        for ($row = 2; $row <= $maxRows; $row++) {
            // Decide whether this row carries real data, and read its EMP CODE up
            // front. A row holding only a running serial number is treated as blank
            // so trailing filler rows don't trip the "missing code" check.
            $rowHasData = false;
            foreach ($colHeader as $col => $h) {
                if ($h === '' || $col === $serialCol) { continue; }
                $v = $sheet->getCell($col . $row)->getValue();
                if ($v !== '' && $v !== null && !(is_string($v) && trim($v) === '')) {
                    $rowHasData = true;
                    break;
                }
            }
            $empRaw   = $sheet->getCell($empCodeCol . $row)->getValue();
            $empEmpty = ($empRaw === '' || $empRaw === null || (is_string($empRaw) && trim($empRaw) === ''));

            // A trailing "TOTAL" / summary row carries aggregate numbers but no
            // employee code — it is not a real employee record, so skip every
            // row-level check for it (missing code, duplicates, numeric, negative).
            // Detected as: empty EMP CODE together with an empty EMP NAME or a name
            // that reads like a total ("TOTAL", "GRAND TOTAL", "SUB TOTAL", …).
            if ($empEmpty) {
                $nameRaw  = $empNameCol !== null ? $sheet->getCell($empNameCol . $row)->getValue() : '';
                $nameNorm = is_string($nameRaw) ? strtoupper(trim($nameRaw)) : (string)$nameRaw;
                if ($nameNorm === '' || strpos($nameNorm, 'TOTAL') !== false) {
                    continue;
                }
            }

            // A data row with no employee code is a hard error — nothing can be
            // matched to an employee, so the whole upload is rejected.
            if ($rowHasData && $empEmpty) {
                $errors[] = "Employee code (EMP CODE) is missing in row {$row}.";
            }

            foreach ($colHeader as $col => $h) {
                if ($h === '') { continue; }
                $cellValue = $sheet->getCell($col . $row)->getValue();

                // Duplicate serial number.
                if ($col === $serialCol) {
                    if ($cellValue !== '' && $cellValue !== null) {
                        $key = is_string($cellValue) ? trim($cellValue) : $cellValue;
                        if (isset($seenSerial[$key])) {
                            $errors[] = 'Duplicate serial number (' . Html::encode((string)$key)
                                . ") in rows {$seenSerial[$key]} and {$row}.";
                        } else {
                            $seenSerial[$key] = $row;
                        }
                    }
                    continue;
                }

                // Duplicate employee code.
                if ($col === $empCodeCol) {
                    if ($cellValue !== '' && $cellValue !== null) {
                        $key = is_string($cellValue) ? strtoupper(trim($cellValue)) : $cellValue;
                        if (isset($seenEmp[$key])) {
                            $errors[] = 'Duplicate employee code (' . Html::encode((string)$key)
                                . ") in rows {$seenEmp[$key]} and {$row}.";
                        } else {
                            $seenEmp[$key] = $row;
                        }
                    }
                    continue;
                }

                // Text columns are not numeric-validated.
                if ($h === 'EMP NAME') { continue; }

                // Numeric columns: must be numeric and non-negative.
                if (is_string($cellValue)) {
                    $cellValue = str_replace(',', '', $cellValue);
                }
                if ($cellValue !== '' && $cellValue !== null) {
                    if (!is_numeric($cellValue)) {
                        $errors[] = "Column {$h} Row {$row} has a non-numeric value ("
                            . Html::encode((string)$cellValue) . ').';
                    } elseif ((float)$cellValue < 0) {
                        $errors[] = "Column {$h} Row {$row} has a negative value ("
                            . Html::encode((string)$cellValue) . ').';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Bulk CSV upload — matches Yii1 uploadpayslip/create.
     * Reads EMP CODE column from CSV, collects developer user IDs, saves file.
     */
    public function actionCreate(): string|Response
    {
        $model = new UploadPayslip();

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());

            // Set month to last day of selected month (per Yii1 behaviour)
            if (!empty($model->month_year)) {
                $model->month_year = date('Y-m-t', strtotime($model->month_year));
            }

            $file = UploadedFile::getInstance($model, 'file_path');

            if ($file && !in_array(strtolower((string) $file->extension), ['csv', 'xls', 'xlsx'], true)) {
                Yii::$app->session->setFlash('error', 'Upload the payslip sheet as CSV, XLS or XLSX.');
                return $this->render('create', compact('model'));
            }

            if ($file) {
                // Validate CSV via PhpSpreadsheet
                $spreadsheet = IOFactory::load($file->tempName);
                $sheet       = $spreadsheet->getActiveSheet();
                $lastRow     = $sheet->getHighestRow();

                // Only accept the file when every validation check passes; otherwise
                // list each problem back to the user and re-render the form.
                $errors = $this->validatePayslipSheet($sheet);
                if (!empty($errors)) {
                    Yii::$app->session->setFlash('error', nl2br(implode("\n", $errors)));
                    return $this->render('create', compact('model'));
                }

                // Save file
                $dir = Yii::getAlias('@backend/web/uploads/upload_payslip/');
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
                $filename = preg_replace('/[^0-9-]/', '', (string) $model->month_year) . '_' . date('Y-m-d-h-i-s-A') . '.' . strtolower((string) $file->extension);
                $file->saveAs($dir . $filename);
                $model->file_path = '/uploads/upload_payslip/' . $filename;

                // Collect developer user IDs from EMP CODE column (column B)
                $developerIds = [];
                for ($row = 2; $row <= $lastRow; $row++) {
                    $empCode = $sheet->getCell('B' . $row)->getValue();
                    if (!empty($empCode) && !is_array($empCode)) {
                        $empCode   = is_string($empCode) ? strtoupper(trim($empCode)) : $empCode;
                        $developer = Developer::find()
                            ->select(['id', 'user_id', 'emp_code'])
                            ->where(['emp_code' => $empCode])
                            ->one();
                        if ($developer && !empty($developer->user_id)) {
                            $developerIds[] = json_encode($developer->user_id);
                        }
                    }
                }
                $model->developer_id = !empty($developerIds) ? json_encode($developerIds) : null;
            }

            $model->uploaded_by   = Yii::$app->user->id;
            $model->uploaded_date = date('Y-m-d H:i:s');

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Payslip uploaded successfully.');
                return $this->redirect(['index']);
            }
        }

        return $this->render('create', compact('model'));
    }

    /**
     * Update uploaded payslip — mirrors Yii1 uploadpayslip/update.
     * Allows re-uploading a new CSV file for an existing record.
     */
    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());

            // Keep existing month_year — update only formats it for last day of month
            if (!empty($model->month_year)) {
                $model->month_year = date('Y-m-t', strtotime($model->month_year));
            }

            $file = UploadedFile::getInstance($model, 'file_path');

            if ($file && !in_array(strtolower((string) $file->extension), ['csv', 'xls', 'xlsx'], true)) {
                Yii::$app->session->setFlash('error', 'Upload the payslip sheet as CSV, XLS or XLSX.');
                return $this->render('update', compact('model'));
            }

            if ($file) {
                // Validate CSV via PhpSpreadsheet
                $spreadsheet = IOFactory::load($file->tempName);
                $sheet       = $spreadsheet->getActiveSheet();
                $lastRow     = $sheet->getHighestRow();

                // Only accept the file when every validation check passes; otherwise
                // list each problem back to the user and re-render the form.
                $errors = $this->validatePayslipSheet($sheet);
                if (!empty($errors)) {
                    Yii::$app->session->setFlash('error', nl2br(implode("\n", $errors)));
                    return $this->render('update', compact('model'));
                }

                // Save new file
                $dir = Yii::getAlias('@backend/web/uploads/upload_payslip/');
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
                $filename = uniqid() . '_' . bin2hex(random_bytes(3)) . '.' . strtolower((string) $file->extension);
                $file->saveAs($dir . $filename);
                $model->file_path = '/uploads/upload_payslip/' . $filename;

                // Collect developer user IDs from EMP CODE column
                $developerIds = [];
                for ($row = 2; $row <= $lastRow; $row++) {
                    $empCode = $sheet->getCell('B' . $row)->getValue();
                    if (!empty($empCode) && !is_array($empCode)) {
                        $empCode   = is_string($empCode) ? strtoupper(trim($empCode)) : $empCode;
                        $developer = Developer::find()
                            ->select(['id', 'user_id', 'emp_code'])
                            ->where(['emp_code' => $empCode])
                            ->one();
                        if ($developer && !empty($developer->user_id)) {
                            $developerIds[] = json_encode($developer->user_id);
                        }
                    }
                }
                $model->developer_id = !empty($developerIds) ? json_encode($developerIds) : $model->developer_id;
            } else {
                // No new file — keep existing file_path
                $model->file_path = $model->getOldAttribute('file_path');
            }

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Payslip updated successfully.');
                return $this->redirect(['index']);
            }
        }

        return $this->render('update', compact('model'));
    }

    /**
     * View CSV contents — matches Yii1 uploadpayslip/view.
     * Reads the uploaded CSV and displays it as a table.
     */
    public function actionView(int $id): string
    {
        $model     = $this->findModel($id);
        $header    = [];
        $data      = [];
        $fileError = null;

        if (empty($model->file_path)) {
            $fileError = 'No file is attached to this payslip record.';
        } else {
            $filePath = $this->resolveFilePath($model->file_path);
            if (!$filePath) {
                $fileError = 'The uploaded file could not be located on disk (stored path: '
                           . $model->file_path . '). It may have been uploaded under the '
                           . 'legacy Yii1 system and migrated incorrectly, or it has been '
                           . 'deleted from the server.';
            } else {
                $fh     = fopen($filePath, 'r');
                $header = fgetcsv($fh) ?: [];
                while (($row = fgetcsv($fh)) !== false) {
                    if (!empty(array_filter($row))) {
                        $data[] = $row;
                    }
                }
                fclose($fh);
            }
        }

        return $this->render('view', compact('model', 'header', 'data', 'fileError'));
    }

    /**
     * Download the uploaded CSV file.
     */
    public function actionDownload(int $id): Response
    {
        $model = $this->findModel($id);

        if (empty($model->file_path)) {
            Yii::$app->session->setFlash('error', 'No file is attached to this payslip record.');
            return $this->redirect(['index']);
        }
        $path = $this->resolveFilePath($model->file_path);
        if (!$path) {
            Yii::$app->session->setFlash('error',
                'The uploaded file could not be located on disk (stored path: '
                . $model->file_path . '). It may have been removed from the server.');
            return $this->redirect(['index']);
        }
        return Yii::$app->response->sendFile($path);
    }

    /**
     * Delete only the uploaded file, keep the record — mirrors Yii1 deleteupload action.
     * AJAX endpoint: returns JSON response.
     */
    public function actionDeleteUpload(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['status' => 0, 'msg' => 'Invalid request.'];
        }

        $id    = (int)Yii::$app->request->post('id', 0);
        $model = UploadPayslip::findOne($id);
        if (!$model) {
            return ['status' => 0, 'msg' => 'Record not found.'];
        }

        // Delete file from disk
        if ($model->file_path) {
            $path = $this->resolveFilePath($model->file_path);
            if ($path && file_exists($path)) {
                @unlink($path);
            }
        }

        $model->file_path = null;
        if ($model->save(false)) {
            return ['status' => 1, 'msg' => 'File deleted successfully.'];
        }

        return ['status' => 0, 'msg' => 'Failed to delete file.'];
    }

    /**
     * Delete payslip record.
     */
    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST only.');
        }
        $this->findModel($id)->delete();
        Yii::$app->session->setFlash('success', 'Payslip record deleted.');
        return $this->redirect(['index']);
    }

    /**
     * View individual employee payslip — matches Yii1 uploadpayslip/viewpayslip.
     * Yii1 uses renderPartial (standalone HTML, no layout); we mirror that by
     * disabling the layout and returning the raw payslip markup so the wrapper
     * sidebar/topbar don't appear inside the popup window.
     */
    public function actionViewPayslip(int $id, string $emp_code = ''): string
    {
        $emp_code = $this->allowedEmpCode($emp_code);
        $this->layout = false;
        $model = $this->findModel($id);
        return $this->generatePayslipHtml($model, $emp_code);
    }

    /**
     * Download individual employee payslip as PDF — matches Yii1 uploadpayslip/downloadpayslip.
     */
    public function actionDownloadPayslip(int $id, string $emp_code = '')
    {
        $emp_code = $this->allowedEmpCode($emp_code);
        $model = $this->findModel($id);
        $html  = $this->generatePayslipHtml($model, $emp_code, true);

        $tempDir = Yii::getAlias('@runtime/mpdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $mpdf = new \Mpdf\Mpdf([
            'tempDir'       => $tempDir,
            'format'        => 'A4',
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 40,
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

    /**
     * Generate payslip HTML for an employee from uploaded CSV — ported from Yii1 UploadPayslip::payslip().
     */
    private function generatePayslipHtml(UploadPayslip $model, string $empCode, bool $isPdf = false): string
    {
        $filePath = $model->file_path ? $this->resolveFilePath($model->file_path) : null;

        // Find employee by emp_code or by logged-in user
        if (!empty($empCode)) {
            $employee = Developer::find()
                ->select(['emp_code', 'developer_name', 'designation', 'doj', 'account_number', 'pf_details', 'esi_details', 'uan_details', 'pan', 'bank'])
                ->where(['emp_code' => $empCode])
                ->one();
        } else {
            $employee = Developer::find()
                ->select(['emp_code', 'developer_name', 'designation', 'doj', 'account_number', 'pf_details', 'esi_details', 'uan_details', 'pan', 'bank'])
                ->where(['user_id' => Yii::$app->user->id])
                ->one();
            $empCode = $employee->emp_code ?? '';
        }

        $devName     = $employee->developer_name ?? '-';
        $designation = $employee->designation ?? '-';
        $pfNumber    = $employee->pf_details ?? '-';
        $uanNumber   = $employee->uan_details ?? '-';
        $panNumber   = $employee->pan ?? '-';
        $esiNumber   = $employee->esi_details ?? '-';
        $dateOfJoin  = $employee->doj ?? '-';
        $accNumber   = $employee->account_number ?? '-';
        $bankName    = $employee->bank ?? '-';
        // Match Yii1 Utility::formatDatetimeToShortDate($month, 'F Y') → e.g. "March 2026"
        $monthData   = !empty($model->month_year) ? date('F Y', strtotime($model->month_year)) : '-';

        $salary = $DA = $HRA = $others = $payAfterLop = $LOP = $Late = $PF = $TDS = $ESI = $totalDeduction = $otherDeduction = $netPay = 0;
        $employerPf = $employerEsi = $employeePf = $employeeEsi = $PT = $totalPay = 0;
        $allowance = $incentive = 0;
        $name = '';

        if (file_exists($filePath)) {
            $fh      = fopen($filePath, 'r');
            $headers = fgetcsv($fh);
            while (($data = fgetcsv($fh)) !== false) {
                $row = [];
                foreach ($headers as $i => $header) {
                    $row[strtoupper($header)] = $data[$i] ?? '';
                }
                if (isset($row['EMP CODE']) && trim($row['EMP CODE']) === $empCode) {
                    $s = function ($v) { return floatval(str_replace(',', '', trim($v ?? ''))); };
                    $name          = $devName;
                    $salary        = !empty($row['BASIC']) ? $s($row['BASIC']) : 0;
                    $DA            = !empty($row['DA']) ? $s($row['DA']) : 0;
                    $HRA           = !empty($row['HRA']) ? $s($row['HRA']) : 0;
                    $others        = !empty($row['OTHERS']) ? $s($row['OTHERS']) : 0;
                    $allowance     = !empty($row['ALLOWANCE']) ? $s($row['ALLOWANCE']) : 0;
                    $incentive     = !empty($row['INCENTIVE']) ? $s($row['INCENTIVE']) : 0;
                    $totalPay      = !empty($row['TOTAL PAY']) ? $s($row['TOTAL PAY']) : 0;
                    $payAfterLop   = !empty($row['PAY AFTER LOP']) ? $s($row['PAY AFTER LOP']) : 0;
                    $Late          = !empty($row['LOP']) ? $s($row['LOP']) : 0;
                    $TDS           = !empty($row['TDS']) ? $s($row['TDS']) : 0;
                    $ESI           = !empty($row['ESI']) ? $s($row['ESI']) : 0;
                    $PF            = !empty($row['PF']) ? $s($row['PF']) : 0;
                    $PT            = !empty($row['PT']) ? $s($row['PT']) : 0;
                    $LOP           = !empty($row['LOP (RS)']) ? $s($row['LOP (RS)']) : 0;
                    $otherDeduction = !empty($row['OTHER DEDUCTION']) ? $s($row['OTHER DEDUCTION']) : 0;
                    $totalDeduction = !empty($row['TOTAL DEDUCTIONS']) ? $s($row['TOTAL DEDUCTIONS']) : 0;
                    $netPay        = !empty($row['NET PAY']) ? $s($row['NET PAY']) : 0;

                    $pfCalc          = round($PF / 2, 2);
                    $employeeEsiCalc = round(($ESI / 4) * 0.75, 2);
                    $employerEsiCalc = round($ESI - $employeeEsiCalc, 2);

                    $employerPf  = !empty($row['EMPLOYER EPF CONTRIBUTION']) ? $s($row['EMPLOYER EPF CONTRIBUTION']) : $pfCalc;
                    $employeePf  = !empty($row['EMPLOYEE EPF CONTRIBUTION']) ? $s($row['EMPLOYEE EPF CONTRIBUTION']) : $pfCalc;
                    $employerEsi = !empty($row['EMPLOYER ESI CONTRIBUTION']) ? $s($row['EMPLOYER ESI CONTRIBUTION']) : $employerEsiCalc;
                    $employeeEsi = !empty($row['EMPLOYEE ESI CONTRIBUTION']) ? $s($row['EMPLOYEE ESI CONTRIBUTION']) : $employeeEsiCalc;
                    break;
                }
            }
            fclose($fh);
        }

        if ($LOP < 1 && $totalPay > $payAfterLop) {
            $LOP = $totalPay - $payAfterLop;
        }
        $grossPay = $totalPay - ($LOP + $employerEsi + $employerPf + $otherDeduction);
        $dojFormatted = ($dateOfJoin !== '-') ? date('d-m-Y', strtotime($dateOfJoin)) : '-';

        // Logo path: absolute file path for PDF, relative URL for browser view
        if ($isPdf) {
            $logoPath = \backend\components\Company::logoPath();
        } else {
            $logoPath = \backend\components\Company::logoUrl();
        }

        $netPayWords = ucfirst(Utility::convertNumberToWords($netPay));

        // Allowance / Incentive fill the empty earning cells just below "Cost to
        // Company" (beside the Employers EPF / ESI Contribution rows). Shown only
        // for applicable employees — the CSV carries 0 for non-applicable ones, so
        // the cell stays blank as before.
        $allowanceLabel = $allowance > 0 ? 'Allowance' : '';
        $allowanceCell  = $allowance > 0 ? $allowance : '';
        $incentiveLabel = $incentive > 0 ? 'Incentive' : '';
        $incentiveCell  = $incentive > 0 ? $incentive : '';

        $html = '
        <html><head><style>
            .payslip-container table { border-collapse: collapse !important; }
            .payslip-container table td, .payslip-container table th { border: 1px solid black; border-spacing: 0 !important; padding: 5px; }
        </style></head><body>
        <div class="wrapper payslip-container"><div class="data-table">
        <table border="0" align="center" cellpadding="0" cellspacing="0" style="width:700px;">
            <tr>
                <th colspan="5">
                    <center><img src="' . $logoPath . '" style="height: 60px; padding: 7px 0px;"></center>
                </th>
            </tr>
            <tr>
                <th colspan="5">
                    <center>' . \yii\helpers\Html::encode(\backend\components\Company::get('legalName')) . '</center>
                    <center>' . \yii\helpers\Html::encode(\backend\components\Company::get('address')) . '</center>
                </th>
            </tr>
            <tr><th colspan="5"><center>Pay Slip - ' . $monthData . '</center></th></tr>
            <tr>
                <th style="text-align: left;">Name</th>
                <td>' . htmlspecialchars($name) . '</td>
                <th style="text-align: left;" colspan="2">Bank Name</th>
                <td>' . htmlspecialchars($bankName) . '</td>
            </tr>
            <tr>
                <th style="text-align: left;">Date of Join</th>
                <td>' . $dojFormatted . '</td>
                <th style="text-align: left;" colspan="2">Account Number</th>
                <td>' . htmlspecialchars($accNumber) . '</td>
            </tr>
            <tr>
                <th style="text-align: left;">Designation</th>
                <td>' . htmlspecialchars($designation) . '</td>
                <th style="text-align: left;" colspan="2">PF Number</th>
                <td>' . htmlspecialchars($pfNumber) . '</td>
            </tr>
            <tr>
                <th style="text-align: left;">Emp.Code</th>
                <td>' . htmlspecialchars($empCode) . '</td>
                <th style="text-align: left;" colspan="2">ESI Number</th>
                <td>' . htmlspecialchars($esiNumber) . '</td>
            </tr>
            <tr>
                <th style="text-align: left;">PAN Number</th>
                <td>' . htmlspecialchars($panNumber) . '</td>
                <th style="text-align: left;" colspan="2">UAN Number</th>
                <td>' . htmlspecialchars($uanNumber) . '</td>
            </tr>
            <tr>
                <th>Earnings</th>
                <th>In Rupees</th>
                <th>Deductions</th>
                <th>In days</th>
                <th>In Rupees</th>
            </tr>
            <tr>
                <td>Cost to Company(CTC)</td>
                <td style="text-align: right;">' . $totalPay . '</td>
                <td>Loss of Pay</td>
                <td style="text-align: right;">' . $Late . '</td>
                <td style="text-align: right;">' . $LOP . '</td>
            </tr>
            <tr>
                <td>' . $allowanceLabel . '</td>
                <td style="text-align: right;">' . $allowanceCell . '</td>
                <td colspan="2">Employers EPF Contribution</td>
                <td style="text-align: right;">' . $employerPf . '</td>
            </tr>
            <tr>
                <td>' . $incentiveLabel . '</td>
                <td style="text-align: right;">' . $incentiveCell . '</td>
                <td colspan="2">Employers ESI Contribution</td>
                <td style="text-align: right;">' . $employerEsi . '</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td colspan="2">Other Deduction</td>
                <td style="text-align: right;">' . $otherDeduction . '</td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: center; font-weight: bold;">Earned Gross Pay</td>
                <td style="text-align: right;">' . $grossPay . '</td>
            </tr>
            <tr>
                <td>BASIC</td>
                <td style="text-align: right;">' . $salary . '</td>
                <td colspan="2">Employees EPF Contribution</td>
                <td style="text-align: right;">' . $employeePf . '</td>
            </tr>
            <tr>
                <td>DA</td>
                <td style="text-align: right;">' . $DA . '</td>
                <td colspan="2">Employees ESI Contribution</td>
                <td style="text-align: right;">' . $employeeEsi . '</td>
            </tr>
            <tr>
                <td>HRA</td>
                <td style="text-align: right;">' . $HRA . '</td>
                <td colspan="2">TDS</td>
                <td style="text-align: right;">' . $TDS . '</td>
            </tr>
            <tr>
                <td>All Other allowance</td>
                <td style="text-align: right;">' . $others . '</td>
                <td colspan="2">Profession Tax</td>
                <td style="text-align: right;">' . $PT . '</td>
            </tr>
            <tr>
                <th colspan="4" style="text-align: center; font-weight: bold; border: 1px solid #000; padding: 5px 3px;">Net Pay</th>
                <td style="text-align: right; border: 1px solid #000; padding: 5px 3px;">' . $netPay . '</td>
            </tr>
            <tr>
                <th colspan="5" style="text-align: center; font-weight: bold; padding-top: 5px;">(' . $netPayWords . ' rupees only)</th>
            </tr>
        </table></div></div></body></html>';

        return $html;
    }

    /**
     * Developer portal — shows payslips for the logged-in developer.
     */
    public function actionDeveloper(): string
    {
        $userId = Yii::$app->user->id;

        $query = UploadPayslip::find()
            ->where("JSON_CONTAINS(developer_id, '\"$userId\"')")
            ->orderBy(['month_year' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 10],
        ]);

        return $this->render('developer', compact('dataProvider'));
    }

    /**
     * view-payslip / download-payslip are developer defaults: anyone without upload
     * rights only ever gets their own employee code, whatever the URL says.
     */
    private function allowedEmpCode(string $empCode): string
    {
        if (\backend\components\HrAccess::canManage(['payslip-upload/index', 'payslip-upload/create', 'payslip-upload/update'])) {
            return $empCode;
        }
        $own = \backend\models\Developer::find()->select('emp_code')->where(['user_id' => Yii::$app->user->id, 'status' => 1])->scalar();
        if (!$own) {
            throw new NotFoundHttpException('Payslip not found.');
        }
        return (string)$own;
    }

    private function findModel(int $id): UploadPayslip
    {
        $model = UploadPayslip::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Payslip upload not found.');
        }
        return $model;
    }

    /**
     * Resolve the actual file path on disk.
     * Files uploaded via Yii1 live under the Yii1 webroot; new uploads go to @backend/web.
     */
    private function resolveFilePath(string $relativePath): ?string
    {
        // Normalise the stored path — accept both "uploads/..." and "/uploads/..."
        // forms, and also paths that may have been stored with the legacy
        // Yii1 base URL accidentally prefixed.
        $rel = '/' . ltrim($relativePath, '/');
        $rel = preg_replace('#^/maestro_erp/#', '/', $rel);

        $candidates = [
            // 1) Yii2 backend/web (new uploads)
            Yii::getAlias('@backend/web') . $rel,
            // 2) Legacy Yii1 webroot — ../../../maestro_erp + /uploads/...
            dirname(Yii::getAlias('@backend'), 3) . '/maestro_erp' . $rel,
            // 3) Legacy Yii1 webroot — same parent without /maestro_erp prefix
            dirname(Yii::getAlias('@backend'), 3) . $rel,
            // 4) Path stored as already-absolute
            $relativePath,
        ];

        foreach ($candidates as $p) {
            if ($p && file_exists($p) && is_file($p)) {
                return $p;
            }
        }
        return null;
    }
}
