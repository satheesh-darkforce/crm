<?php

namespace backend\controllers;

use backend\assets\InvoiceAsset;
use backend\components\SafeUpload;
use backend\models\BankDetail;
use backend\models\ClientModel;
use backend\models\Currency;
use backend\models\Invoice;
use backend\models\InvoiceDetails;
use backend\models\InvoiceReceipt;
use backend\models\InvoiceReceiptAttachment;
use backend\models\InwardInvoice;
use backend\models\MasterSetting;
use backend\models\Project;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use common\components\UserAccess;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend CRUD for Invoice (Finance module).
 */
class InvoiceController extends Controller
{
    /** Most line items an invoice may have. */
    const MAX_ITEMS = 100;

    /**
     * The draft preview / confirm / discard actions are part of the create flow and have
     * no permission of their own: whoever may create or update invoices may use them.
     * "Back & Edit" leads to invoice/update, so create also grants update on drafts.
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $canWrite = fn() => UserAccess::checkAccess('invoice/create') || UserAccess::checkAccess('invoice/update');
        $behaviors['access']['rules'] = array_merge(
            [
                [
                    'allow'         => true,
                    'roles'         => ['@'],
                    'actions'       => ['preview-proforma', 'confirm-proforma', 'discard-proforma', 'preview-invoice', 'confirminvoice', 'update'],
                    'matchCallback' => $canWrite,
                ],
            ],
            $behaviors['access']['rules']
        );
        $behaviors['verbs'] = ['class' => VerbFilter::class, 'actions' => array_fill_keys([
            'confirm-proforma', 'discard-proforma', 'convert-to-invoice', 'confirminvoice', 'update-tds',
            'add-receipt', 'delete-receipt', 'cancel',
        ], ['POST'])];
        return $behaviors;
    }

    /**
     * Clean list / export filters once: dates must be Y-m-d, ids and statuses digits,
     * arrays from tampered URLs are dropped and a reversed date range is swapped.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $req     = Yii::$app->request;
        $params  = $req->getQueryParams();
        $ignored = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                unset($params[$k]);
            }
        }
        foreach (['from_date', 'to_date'] as $k) {
            if (isset($params[$k]) && trim((string)$params[$k]) !== '') {
                $d = \DateTime::createFromFormat('!Y-m-d', trim((string)$params[$k]));
                if ($d && $d->format('Y-m-d') === trim((string)$params[$k])) {
                    $params[$k] = $d->format('Y-m-d');
                } else {
                    $ignored[] = str_replace('_', ' ', $k);
                    unset($params[$k]);
                }
            }
        }
        foreach (['client_id', 'payment_status', 'invoice_status', 'invoice_category', 'download_id'] as $k) {
            if (isset($params[$k]) && $params[$k] !== '' && !ctype_digit((string)$params[$k])) {
                $ignored[] = str_replace('_', ' ', $k);
                unset($params[$k]);
            }
        }
        if (isset($params['invoice_no'])) {
            $params['invoice_no'] = mb_substr(trim((string)$params['invoice_no']), 0, 100);
        }
        if (!empty($params['from_date']) && !empty($params['to_date']) && $params['to_date'] < $params['from_date']) {
            [$params['from_date'], $params['to_date']] = [$params['to_date'], $params['from_date']];
        }
        $req->setQueryParams($params);
        if ($ignored && !$req->isAjax) {
            Yii::$app->session->setFlash('warning', 'Ignored an invalid filter value (' . implode(', ', array_unique($ignored)) . ').');
        }
        return true;
    }

    /** Full pages get the Invoices module stylesheet and script (printable pages render partials). */
    public function render($view, $params = []): string
    {
        InvoiceAsset::register($this->getView());
        return parent::render($view, $params);
    }

    public function actionIndex(): string
    {
        $query = Invoice::find()
            ->with('client')
            ->where(['invoice.status' => 1, 'invoice.invoice_role' => Invoice::ROLE_TAX_INVOICE])
            ->orderBy(['invoice.id' => SORT_DESC]);

        if ($clientId = Yii::$app->request->get('client_id')) {
            $query->andWhere(['client_id' => (int)$clientId]);
        }
        if (($companyName = Yii::$app->request->get('company_name')) !== null && $companyName !== '') {
            $query->joinWith('client')->andWhere(['client.company_name' => $companyName]);
        }
        $paymentStatus = Yii::$app->request->get('payment_status');
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $query->andWhere(['payment_status' => (int)$paymentStatus]);
        }
        $invoiceStatus = Yii::$app->request->get('invoice_status');
        if ($invoiceStatus !== null && $invoiceStatus !== '') {
            $query->andWhere(['invoice.invoice_status' => (int)$invoiceStatus]);
        }
        if ($fromDate = Yii::$app->request->get('from_date')) {
            $query->andWhere(['>=', 'invoice_date', $fromDate]);
        }
        if ($toDate = Yii::$app->request->get('to_date')) {
            $query->andWhere(['<=', 'invoice_date', $toDate]);
        }
        if ($invoiceNo = Yii::$app->request->get('invoice_no')) {
            $query->andWhere(['like', 'invoice_no', $invoiceNo]);
        }
        if ($category = Yii::$app->request->get('invoice_category')) {
            $query->andWhere(['invoice_category' => (int)$category]);
        }

        // Totals for footer (aggregate over the full filtered result set, no eager loading).
        // Cancelled invoices (invoice_status=0) are excluded from the footer totals.
        $totalsRow = (clone $query)
            ->with([])
            ->andWhere(['<>', 'invoice.invoice_status', 0])
            ->select([
                'SUM(grand_total) AS sum_grand',
                'SUM(sgst_amount) AS sum_sgst',
                'SUM(cgst_amount) AS sum_cgst',
                'SUM(igst_amount) AS sum_igst',
                'SUM(net_total)   AS sum_net',
                'SUM(tds_amount)  AS sum_tds',
                'SUM(paid_amount) AS sum_paid',
            ])
            ->asArray()
            ->one();
        $totals = $totalsRow ? [
            'grand' => (float)($totalsRow['sum_grand'] ?? 0),
            'sgst'  => (float)($totalsRow['sum_sgst']  ?? 0),
            'cgst'  => (float)($totalsRow['sum_cgst']  ?? 0),
            'igst'  => (float)($totalsRow['sum_igst']  ?? 0),
            'net'   => (float)($totalsRow['sum_net']   ?? 0),
            'tds'   => (float)($totalsRow['sum_tds']   ?? 0),
            'paid'  => (float)($totalsRow['sum_paid']  ?? 0),
        ] : array_fill_keys(['grand','sgst','cgst','igst','net','tds','paid'], 0.0);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('index', compact('dataProvider', 'clients', 'totals'));
    }

    public function actionProforma(): string
    {
        $query = Invoice::find()
            ->with('client')
            ->where(['invoice.status' => 1, 'invoice.invoice_role' => Invoice::ROLE_PROFORMA])
            ->orderBy(['invoice.id' => SORT_DESC]);

        if ($clientId = Yii::$app->request->get('client_id')) {
            $query->andWhere(['client_id' => (int)$clientId]);
        }
        if (($companyName = Yii::$app->request->get('company_name')) !== null && $companyName !== '') {
            $query->joinWith('client')->andWhere(['client.company_name' => $companyName]);
        }
        $invoiceStatus = Yii::$app->request->get('invoice_status');
        if ($invoiceStatus !== null && $invoiceStatus !== '') {
            $query->andWhere(['invoice.invoice_status' => (int)$invoiceStatus]);
        }
        if ($fromDate = Yii::$app->request->get('from_date')) {
            $query->andWhere(['>=', 'invoice_date', $fromDate]);
        }
        if ($toDate = Yii::$app->request->get('to_date')) {
            $query->andWhere(['<=', 'invoice_date', $toDate]);
        }
        if ($invoiceNo = Yii::$app->request->get('invoice_no')) {
            $query->andWhere(['like', 'invoice_no', $invoiceNo]);
        }
        if ($category = Yii::$app->request->get('invoice_category')) {
            $query->andWhere(['invoice_category' => (int)$category]);
        }

        // Cancelled invoices (invoice_status=0) are excluded from the footer totals.
        $totalsRow = (clone $query)
            ->with([])
            ->andWhere(['<>', 'invoice.invoice_status', 0])
            ->select([
                'SUM(grand_total) AS sum_grand',
                'SUM(sgst_amount) AS sum_sgst',
                'SUM(cgst_amount) AS sum_cgst',
                'SUM(igst_amount) AS sum_igst',
                'SUM(net_total)   AS sum_net',
                'SUM(tds_amount)  AS sum_tds',
                'SUM(paid_amount) AS sum_paid',
            ])
            ->asArray()
            ->one();
        $totals = $totalsRow ? [
            'grand' => (float)($totalsRow['sum_grand'] ?? 0),
            'sgst'  => (float)($totalsRow['sum_sgst']  ?? 0),
            'cgst'  => (float)($totalsRow['sum_cgst']  ?? 0),
            'igst'  => (float)($totalsRow['sum_igst']  ?? 0),
            'net'   => (float)($totalsRow['sum_net']   ?? 0),
            'tds'   => (float)($totalsRow['sum_tds']   ?? 0),
            'paid'  => (float)($totalsRow['sum_paid']  ?? 0),
        ] : array_fill_keys(['grand','sgst','cgst','igst','net','tds','paid'], 0.0);

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);
        $clients    = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $isProforma = true;

        return $this->render('index', compact('dataProvider', 'clients', 'totals', 'isProforma'));
    }

    // --------------------------------------------------------------------
    // Export invoice listing (Tax or Proforma) to XLSX. Mirrors the same
    // filter logic as actionIndex / actionProforma. When no filter at all
    // is supplied, defaults the date range to the current calendar month.
    // --------------------------------------------------------------------
    public function actionExport()
    {
        $req        = Yii::$app->request;
        $role       = $req->get('role') === 'proforma' ? 'proforma' : 'tax';
        $isProforma = ($role === 'proforma');

        $filterKeys = ['client_id', 'company_name', 'invoice_no', 'invoice_category', 'payment_status',
                       'invoice_status', 'from_date', 'to_date'];
        $hasFilter = false;
        foreach ($filterKeys as $k) {
            $v = $req->get($k);
            if ($v !== null && $v !== '') {
                $hasFilter = true;
                break;
            }
        }

        $fromDate = $req->get('from_date');
        $toDate   = $req->get('to_date');
        if (!$hasFilter) {
            $fromDate = date('Y-m-01');
            $toDate   = date('Y-m-t');
        }

        $invoiceRole = $isProforma ? Invoice::ROLE_PROFORMA : Invoice::ROLE_TAX_INVOICE;
        $query = Invoice::find()
            ->with('client')
            ->where(['invoice.status' => 1, 'invoice.invoice_role' => $invoiceRole])
            ->orderBy(['invoice.invoice_date' => SORT_ASC, 'invoice.id' => SORT_ASC]);

        if ($clientId = $req->get('client_id')) {
            $query->andWhere(['client_id' => (int)$clientId]);
        }
        if (($companyName = $req->get('company_name')) !== null && $companyName !== '') {
            $query->joinWith('client')->andWhere(['client.company_name' => $companyName]);
        }
        $paymentStatus = $req->get('payment_status');
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $query->andWhere(['payment_status' => (int)$paymentStatus]);
        }
        $invoiceStatus = $req->get('invoice_status');
        if ($invoiceStatus !== null && $invoiceStatus !== '') {
            $query->andWhere(['invoice.invoice_status' => (int)$invoiceStatus]);
        }
        if ($fromDate) {
            $query->andWhere(['>=', 'invoice_date', $fromDate]);
        }
        if ($toDate) {
            $query->andWhere(['<=', 'invoice_date', $toDate]);
        }
        if ($invoiceNo = $req->get('invoice_no')) {
            $query->andWhere(['like', 'invoice_no', $invoiceNo]);
        }
        if ($category = $req->get('invoice_category')) {
            $query->andWhere(['invoice_category' => (int)$category]);
        }

        $invoices = $query->all();

        $categoryLabel = [1 => 'Service', 2 => 'Sales'];
        $typeLabel     = [1 => 'Inter State', 2 => 'Intra State', 3 => 'International'];
        $paymentLabel  = [0 => 'Unpaid', 1 => 'Partial', 2 => 'Paid'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle($isProforma ? 'Proforma Invoices' : 'Tax Invoices');

        // Columns. "Type" only applies to tax invoices, but we keep the same
        // column layout (just blank for proforma) so the file structure is stable.
        $headers = [
            '#', 'Invoice No', 'Company Name', 'GST No', 'Title', 'Category', 'Type',
            'Date', 'Currency',
            'Taxable', 'SGST', 'CGST', 'IGST', 'Net Total',
            'TDS %', 'TDS Amt', 'Paid', 'Pending',
            'Payment', 'Status',
        ];
        $lastCol     = 'T'; // 20 columns A..T
        $headerStart = 'A3';
        $headerEnd   = $lastCol . '3';

        // Title row
        $rangeLabel = ($fromDate && $toDate)
            ? date('d M Y', strtotime($fromDate)) . ' to ' . date('d M Y', strtotime($toDate))
            : 'All dates';
        $title = ($isProforma ? 'Proforma Invoices' : 'Tax Invoices') . ' — ' . $rangeLabel;
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Headers
        $sheet->fromArray($headers, null, $headerStart);
        $sheet->getStyle($headerStart . ':' . $headerEnd)->getFont()->setBold(true);
        $sheet->getStyle($headerStart . ':' . $headerEnd)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
        $sheet->getStyle($headerStart . ':' . $headerEnd)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(3)->setRowHeight(20);

        $row = 4;
        $sumGrand = $sumSgst = $sumCgst = $sumIgst = $sumNet = $sumTds = $sumPaid = $sumPending = 0.0;

        foreach ($invoices as $i => $inv) {
            $grand   = (float)$inv->grand_total;
            $sgst    = (float)$inv->sgst_amount;
            $cgst    = (float)$inv->cgst_amount;
            $igst    = (float)$inv->igst_amount;
            $net     = (float)$inv->net_total;
            $tdsAmt  = (float)$inv->tds_amount;
            $paid    = (float)$inv->paid_amount;
            $pending = max(0, $net - $tdsAmt - $paid);

            $isCancelled = (int)$inv->invoice_status === 0;

            // Cancelled invoices are shown as rows but excluded from the totals row.
            if (!$isCancelled) {
                $sumGrand   += $grand;
                $sumSgst    += $sgst;
                $sumCgst    += $cgst;
                $sumIgst    += $igst;
                $sumNet     += $net;
                $sumTds     += $tdsAmt;
                $sumPaid    += $paid;
                $sumPending += $pending;
            }

            $client      = $inv->client;
            $companyName = $client->company_name ?? $client->client_name ?? '';

            $sheet->fromArray([
                $i + 1,
                $inv->invoice_no,
                $companyName,
                $client->gst_no ?? '',
                $inv->title ?: '',
                $categoryLabel[(int)$inv->invoice_category] ?? '',
                $isProforma ? '' : ($typeLabel[(int)$inv->invoice_type] ?? ''),
                $inv->invoice_date ? date('d M Y', strtotime($inv->invoice_date)) : '',
                $inv->currency ?: '',
                $grand,
                $sgst,
                $cgst,
                $igst,
                $net,
                (float)$inv->tds_percent,
                $tdsAmt,
                $paid,
                $pending,
                $paymentLabel[(int)$inv->payment_status] ?? '',
                $isCancelled ? 'Cancelled' : 'Active',
            ], null, 'A' . $row);

            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('J' . $row . ':R' . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('J' . $row . ':R' . $row)
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('S' . $row . ':T' . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        if (empty($invoices)) {
            $sheet->setCellValue('A4', 'No invoices found for the selected criteria.');
            $sheet->mergeCells('A4:' . $lastCol . '4');
            $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A4')->getFont()->setItalic(true);
            $row = 5;
        } else {
            // Totals row
            $sheet->setCellValue('I' . $row, 'TOTAL');
            $sheet->setCellValue('J' . $row, $sumGrand);
            $sheet->setCellValue('K' . $row, $sumSgst);
            $sheet->setCellValue('L' . $row, $sumCgst);
            $sheet->setCellValue('M' . $row, $sumIgst);
            $sheet->setCellValue('N' . $row, $sumNet);
            $sheet->setCellValue('P' . $row, $sumTds);
            $sheet->setCellValue('Q' . $row, $sumPaid);
            $sheet->setCellValue('R' . $row, $sumPending);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F4FF');
            $sheet->getStyle('I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('J' . $row . ':R' . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('J' . $row . ':R' . $row)
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }

        $lastRow = $row - 1;
        $sheet->getStyle($headerStart . ':' . $lastCol . $lastRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Column widths
        $widths = [
            'A' => 6,  'B' => 16, 'C' => 32, 'D' => 20, 'E' => 28, 'F' => 12,
            'G' => 14, 'H' => 12, 'I' => 10, 'J' => 14, 'K' => 12, 'L' => 12,
            'M' => 12, 'N' => 14, 'O' => 8,  'P' => 12, 'Q' => 12, 'R' => 12,
            'S' => 11, 'T' => 11,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->freezePane('A4');

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        $fileLabel = ($fromDate && $toDate)
            ? date('Y-m-d', strtotime($fromDate)) . '_to_' . date('Y-m-d', strtotime($toDate))
            : date('Y-m-d');
        $filename  = ($isProforma ? 'Proforma_Invoices_' : 'Tax_Invoices_') . $fileLabel . '.xlsx';

        return Yii::$app->response->sendContentAsFile(
            $content,
            $filename,
            ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function actionView(int $id): string
    {
        $invoice = $this->findModel($id);
        // Render standalone (no admin layout) — matches Yii1 actionView which
        // used renderPartial so the invoice opens as a clean printable page.
        $this->layout = false;
        return $this->renderPartial('view', ['model' => $invoice]);
    }

    /**
     * Renders the same print template used by actionDownloadPdf, but as HTML
     * with the browser print dialog auto-opened on load. Lets users print the
     * exact layout of the downloaded PDF.
     */
    public function actionPrint(int $id): string
    {
        $invoice = $this->findModel($id);
        $this->layout = false;
        return $this->renderPartial('print', [
            'model'     => $invoice,
            'isPdf'     => false,
            'autoPrint' => true,
        ]);
    }

    public function actionDownloadPdf(int $id)
    {
        $invoice = $this->findModel($id);
        $this->layout = false;

        $html = $this->renderPartial('print', [
            'model' => $invoice,
            'isPdf' => true,
        ]);

        // Build filename matching Yii1: "INV-NO / date / client (title).pdf"
        $clientName = '';
        if ($invoice->client) {
            $clientName = $invoice->client->company_name
                ?? ($invoice->client->client_name ?? '');
        }
        $fileName = $invoice->invoice_no
            . ' / ' . date('Y-m-d', strtotime($invoice->invoice_date))
            . ' / ' . mb_substr($clientName, 0, 50)
            . ' (' . mb_substr($invoice->title, 0, 100) . ')';

        $tempDir = Yii::getAlias('@runtime/mpdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Zero margins matching Yii1 exactly
        $mpdf = new \Mpdf\Mpdf([
            'tempDir'       => $tempDir,
            'format'        => 'A4',
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 0,
            'margin_bottom' => 0,
        ]);

        $mpdf->WriteHTML($html);

        // Use 'S' to get PDF as string, then send via Yii response to avoid "headers already sent"
        $pdfContent = $mpdf->Output($fileName . '.pdf', \Mpdf\Output\Destination::STRING_RETURN);

        // Slashes, quotes and control characters are not allowed in a download name.
        $safeName = trim(preg_replace('~[/:*?"<>|\x00-\x1F' . preg_quote('\\', '~') . ']+~u', '-', $fileName), " -.");
        return Yii::$app->response->sendContentAsFile($pdfContent, ($safeName !== '' ? $safeName : 'invoice-' . $invoice->id) . '.pdf', [
            'mimeType' => 'application/pdf',
        ]);
    }

    public function actionCreate(): string|Response
    {
        $invoice = new Invoice();

        $roleParam      = (int)Yii::$app->request->get('invoice_role', Yii::$app->request->get('type', 1));
        $clientIdParam  = (int)Yii::$app->request->get('client_id', 0);
        $projectIdParam = (int)Yii::$app->request->get('project_id', Yii::$app->request->get('project', 0));
        $invoiceRole    = ($roleParam === 2) ? Invoice::ROLE_PROFORMA : Invoice::ROLE_TAX_INVOICE;
        $invoice->invoice_role = $invoiceRole;

        // Support both invoice_category and invoice_category_id (Yii1 compat)
        $invoiceCategoryParam = Yii::$app->request->get('invoice_category', Yii::$app->request->get('invoice_category_id', Invoice::CATEGORY_SERVICE));

        // Load dropdown data up-front so they're available on both GET and early-return POST paths
        $clients  = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $banks    = BankDetail::find()->where(['status' => 1])->orderBy(['is_default' => SORT_DESC, 'bank_name' => SORT_ASC])->all();

        // Load currencies from DB (matches Yii1 behaviour)
        $preClient = $clientIdParam ? ClientModel::findOne(['id' => $clientIdParam, 'status' => 1]) : null;
        $isInternational = $preClient && (int)$preClient->client_type === ClientModel::TYPE_INTERNATIONAL;
        $currencies = Currency::find()
            ->where(['status' => 1, 'is_default' => $isInternational ? 0 : 1])
            ->orderBy('id')
            ->all();

        // HSN/SAC codes (matches Yii1 Utility::get_hsn_codes)
        $catId = (int)$invoiceCategoryParam;
        $hsnCodes = match ($catId) {
            Invoice::CATEGORY_SERVICE => ['998314'],
            Invoice::CATEGORY_PRODUCT => ['90328990', '85414019', '85340000'],
            default => [],
        };

        // Tax rates and LUT ARN from master settings (H19: no longer hardcoded)
        $settings = MasterSetting::getInstance();
        $dbSgst   = $settings->getSgstRate();
        $dbCgst   = $settings->getCgstRate();
        $dbIgst   = $settings->getIgstRate();
        $lutArn   = $settings->invoice_lut_arn ?? '';
        $clientTypes = [];
        foreach ($clients as $cl) {
            $clientTypes[$cl->id] = (int)$cl->client_type;
        }
        // Pre-populate for GET request when client_id is passed in URL
        $preClient = $clientIdParam ? ClientModel::findOne(['id' => $clientIdParam, 'status' => 1]) : null;
        $nextNo    = $this->previewInvoiceNumber(
            $invoiceRole,
            (int)$invoiceCategoryParam,
            $preClient ? (int)$preClient->client_type : 1
        );
        if ($preClient && !Yii::$app->request->isPost) {
            $invoice->client_id = $clientIdParam;
            if ($preClient->tax_exemption == 1) {
                $invoice->sgst_percent = 0;
                $invoice->cgst_percent = 0;
                $invoice->igst_percent = 0;
            } elseif ($preClient->client_type == ClientModel::TYPE_INTRASTATE) {
                $invoice->sgst_percent = $dbSgst;
                $invoice->cgst_percent = $dbCgst;
                $invoice->igst_percent = 0;
            } elseif ($preClient->client_type == ClientModel::TYPE_INTERSTATE) {
                $invoice->sgst_percent = 0;
                $invoice->cgst_percent = 0;
                $invoice->igst_percent = $dbIgst;
            } else {
                $invoice->sgst_percent = 0;
                $invoice->cgst_percent = 0;
                $invoice->igst_percent = 0;
            }
        }
        // Pre-populate from modal GET params (matches Yii1 addInvoiceModal redirect)
        if (!Yii::$app->request->isPost) {
            if ($projectIdParam) {
                $invoice->project_id = $projectIdParam;
            }
            if ($invoiceCategoryParam !== null && $invoiceCategoryParam !== '') {
                $invoice->invoice_category = (int)$invoiceCategoryParam;
            }
            $phaseParam = Yii::$app->request->get('phase_value');
            if ($phaseParam !== null && $phaseParam !== '') {
                $invoice->phase_value = $phaseParam;
            }
            $milestoneParam = Yii::$app->request->get('milestone_value');
            if ($milestoneParam !== null && $milestoneParam !== '') {
                $invoice->milestone_value = $milestoneParam;
            }
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $this->loadForm($invoice, $post);
            $invoice->invoice_date   = date('Y-m-d H:i:s');
            $invoice->created_date   = date('Y-m-d H:i:s');
            $invoice->created_by     = Yii::$app->user->id;
            $invoice->invoice_status = 1;
            $invoice->invoice_role   = $invoiceRole;
            // C6: Tax Invoices start as draft (status=-1), confirmed via Confirm action.
            // Proforma also start as draft so the Preview page can show them without
            // exposing the row in the active proforma listing until the user clicks
            // "Save and Download". A "Back and Exit" hard-deletes the draft.
            $invoice->status         = -1;
            $invoice->payment_status = Invoice::PAYMENT_STATUS_UNPAID;
            $invoice->paid_amount    = 0;

            // Client validation — matches Yii1: client_type and address must be set
            $c = $invoice->client_id ? ClientModel::findOne(['id' => $invoice->client_id, 'status' => 1]) : null;
            if ($c && (empty($c->client_type) || !in_array((int)$c->client_type, [1, 2, 3]))) {
                Yii::$app->session->setFlash('error', 'Client type not set. Please update the client before creating an invoice.');
                return $this->redirect(['/client/update', 'id' => $c->id]);
            }
            if ($c && empty(trim((string)$c->address))) {
                Yii::$app->session->setFlash('error', 'Client address is required. Please update the client before creating an invoice.');
                return $this->redirect(['/client/update', 'id' => $c->id]);
            }
            if ($c) {
                // invoice_type stores client_type — matches Yii1 (determines tax display in print view)
                $invoice->invoice_type = (int)$c->client_type;
                if (empty(trim((string)$invoice->to_address))) {
                    $invoice->to_address = implode("\n", array_filter([
                        $c->company_name,
                        $c->address,
                        $c->gst_no ? 'GST: ' . $c->gst_no : '',
                    ]));
                }
                // Tax exemption always comes from client record
                $invoice->tax_exempted      = (int)$c->tax_exemption;
                $invoice->tax_exempt_reason = $c->tax_exemption_reason;
                // Server-side tax rate enforcement based on client_type — reads from DB settings
                if ($c->tax_exemption == 1) {
                    $invoice->sgst_percent = 0;
                    $invoice->cgst_percent = 0;
                    $invoice->igst_percent = 0;
                } elseif ((int)$c->client_type === ClientModel::TYPE_INTRASTATE) {
                    $invoice->sgst_percent = $dbSgst;
                    $invoice->cgst_percent = $dbCgst;
                    $invoice->igst_percent = 0;
                } elseif ((int)$c->client_type === ClientModel::TYPE_INTERSTATE) {
                    $invoice->sgst_percent = 0;
                    $invoice->cgst_percent = 0;
                    $invoice->igst_percent = $dbIgst;
                } elseif ((int)$c->client_type === ClientModel::TYPE_INTERNATIONAL) {
                    $invoice->sgst_percent = 0;
                    $invoice->cgst_percent = 0;
                    $invoice->igst_percent = 0;
                }
            }

            // Line items, currency and referenced records — all checked on the server.
            [$validItems, $grandTotal, $formErrors] = $this->readItems($post, (float)$invoice->transaction_charge_percent);
            $this->resolveCurrency($invoice);
            $formErrors = array_merge($this->checkReferences($invoice), $formErrors);
            if ($formErrors) {
                foreach ($formErrors as $msg) {
                    Yii::$app->session->addFlash('error', $msg);
                }
                return $this->render('create', compact('invoice', 'clients', 'projects', 'banks', 'nextNo', 'clientTypes', 'preClient', 'currencies', 'hsnCodes', 'lutArn', 'invoiceCategoryParam'));
            }

            $sgstAmt = round($grandTotal * (float)($invoice->sgst_percent ?? 0) / 100, 2);
            $cgstAmt = round($grandTotal * (float)($invoice->cgst_percent ?? 0) / 100, 2);
            $igstAmt = round($grandTotal * (float)($invoice->igst_percent ?? 0) / 100, 2);

            // grand_total = items sum; sub_total = grand_total + taxes (before rounding)
            $invoice->grand_total = $grandTotal;
            $invoice->sgst_amount = $sgstAmt;
            $invoice->cgst_amount = $cgstAmt;
            $invoice->igst_amount = $igstAmt;
            $rawNet               = $grandTotal + $sgstAmt + $cgstAmt + $igstAmt;
            $invoice->sub_total   = round($rawNet, 2);
            $invoice->net_total   = round($rawNet);
            $invoice->round_off   = round($invoice->net_total - $rawNet, 2);

            // Set currency_in_words from selected currency (skip if already set from DB lookup above)
            if (empty($invoice->currency_in_words)) {
                $currencyWords = [
                    'INR' => 'Indian Rupees', 'USD' => 'US Dollars', 'EUR' => 'Euros',
                    'GBP' => 'British Pounds', 'AED' => 'UAE Dirhams',
                    'SGD' => 'Singapore Dollars', 'AUD' => 'Australian Dollars',
                ];
                $invoice->currency_in_words = $currencyWords[$invoice->currency] ?? ($invoice->currency ?? '');
            }

            // C6: Draft tax invoices get no invoice number yet; proforma get number immediately
            $db          = Yii::$app->db;
            $transaction = $db->beginTransaction();
            try {
                $isDraft = ((int)$invoice->status === -1);
                if (!$isDraft && $invoiceRole === Invoice::ROLE_TAX_INVOICE && empty(trim((string)$invoice->invoice_no))) {
                    [$invoice->invoice_no, $invoice->invoice_prefix, $invoice->invoice_unique_no] = $this->generateInvoiceNumberLocked(
                        $invoiceRole,
                        $db,
                        (int)($invoice->invoice_category ?? Invoice::CATEGORY_SERVICE),
                        $c ? (int)$c->client_type : 1
                    );
                }
                if ($invoice->save()) {
                    $this->saveItems($invoice, $validItems);
                    $transaction->commit();
                    // Both proforma and tax-invoice drafts go to a preview page
                    // with Save & Download / Back & Edit buttons.
                    if ($invoiceRole === Invoice::ROLE_PROFORMA) {
                        return $this->redirect(['preview-proforma', 'id' => $invoice->id]);
                    }
                    return $this->redirect(['preview-invoice', 'id' => $invoice->id]);
                }
                $transaction->rollBack();
            } catch (\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        }

        return $this->render('create', compact('invoice', 'clients', 'projects', 'banks', 'nextNo', 'clientTypes', 'preClient', 'currencies', 'hsnCodes', 'lutArn', 'invoiceCategoryParam'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $invoice = $this->findModel($id);
        $this->ensureNotCancelled($invoice);

        // Confirmed tax invoices cannot be edited; drafts (status=-1) can (matches Yii1)
        if ((int)$invoice->invoice_role === Invoice::ROLE_TAX_INVOICE && (int)$invoice->status !== -1) {
            Yii::$app->session->setFlash('error', 'Tax invoices cannot be edited. Record payments via the receipt form.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $clients  = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $banks    = BankDetail::find()->where(['status' => 1])->orderBy(['is_default' => SORT_DESC, 'bank_name' => SORT_ASC])->all();
        $clientTypes = [];
        foreach ($clients as $c) {
            $clientTypes[$c->id] = (int)$c->client_type;
        }

        // Additional data for the Yii1-style form layout
        $preClient = $invoice->client_id ? ClientModel::findOne(['id' => $invoice->client_id, 'status' => 1]) : null;
        $isInternational = $preClient && (int)$preClient->client_type === ClientModel::TYPE_INTERNATIONAL;
        $currencies = Currency::find()
            ->where(['status' => 1, 'is_default' => $isInternational ? 0 : 1])
            ->orderBy('id')
            ->all();
        $catId = (int)($invoice->invoice_category ?? Invoice::CATEGORY_SERVICE);
        $hsnCodes = match ($catId) {
            Invoice::CATEGORY_SERVICE => ['998314'],
            Invoice::CATEGORY_PRODUCT => ['90328990', '85414019', '85340000'],
            default => [],
        };
        // Tax rates from master settings (H19: no longer hardcoded)
        $settingsU = MasterSetting::getInstance();
        $dbSgstU   = $settingsU->getSgstRate();
        $dbCgstU   = $settingsU->getCgstRate();
        $dbIgstU   = $settingsU->getIgstRate();
        $lutArn    = $settingsU->invoice_lut_arn ?? '';
        $invoiceCategoryParam = $catId;

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $this->loadForm($invoice, $post);
            $invoice->updated_date = date('Y-m-d H:i:s');
            $invoice->updated_by   = Yii::$app->user->id;

            // Server-side tax rate enforcement based on client_type — reads from DB settings
            $c = $invoice->client_id ? ClientModel::findOne(['id' => $invoice->client_id, 'status' => 1]) : null;
            if ($c) {
                $invoice->invoice_type      = (int)$c->client_type;
                $invoice->tax_exempted      = (int)$c->tax_exemption;
                $invoice->tax_exempt_reason = $c->tax_exemption_reason;
                if ($c->tax_exemption == 1) {
                    $invoice->sgst_percent = 0;
                    $invoice->cgst_percent = 0;
                    $invoice->igst_percent = 0;
                } elseif ((int)$c->client_type === ClientModel::TYPE_INTRASTATE) {
                    $invoice->sgst_percent = $dbSgstU;
                    $invoice->cgst_percent = $dbCgstU;
                    $invoice->igst_percent = 0;
                } elseif ((int)$c->client_type === ClientModel::TYPE_INTERSTATE) {
                    $invoice->sgst_percent = 0;
                    $invoice->cgst_percent = 0;
                    $invoice->igst_percent = $dbIgstU;
                } elseif ((int)$c->client_type === ClientModel::TYPE_INTERNATIONAL) {
                    $invoice->sgst_percent = 0;
                    $invoice->cgst_percent = 0;
                    $invoice->igst_percent = 0;
                }
            }

            // Line items, currency and referenced records — all checked on the server.
            [$validItems, $grandTotal, $formErrors] = $this->readItems($post, (float)$invoice->transaction_charge_percent);
            $this->resolveCurrency($invoice);
            $formErrors = array_merge($this->checkReferences($invoice), $formErrors);
            if ($formErrors) {
                foreach ($formErrors as $msg) {
                    Yii::$app->session->addFlash('error', $msg);
                }
                return $this->render('update', compact('invoice', 'clients', 'projects', 'banks', 'clientTypes', 'preClient', 'currencies', 'hsnCodes', 'lutArn', 'invoiceCategoryParam'));
            }
            $sgstAmt = round($grandTotal * (float)($invoice->sgst_percent ?? 0) / 100, 2);
            $cgstAmt = round($grandTotal * (float)($invoice->cgst_percent ?? 0) / 100, 2);
            $igstAmt = round($grandTotal * (float)($invoice->igst_percent ?? 0) / 100, 2);

            $invoice->grand_total = $grandTotal;
            $invoice->sgst_amount = $sgstAmt;
            $invoice->cgst_amount = $cgstAmt;
            $invoice->igst_amount = $igstAmt;
            $rawNet               = $grandTotal + $sgstAmt + $cgstAmt + $igstAmt;
            $invoice->sub_total   = round($rawNet, 2);
            $invoice->net_total   = round($rawNet);
            $invoice->round_off   = round($invoice->net_total - $rawNet, 2);

            // Header and lines change together or not at all.
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $saved = $invoice->save();
                if ($saved) {
                    InvoiceDetails::updateAll(['status' => 0], ['invoice_id' => $invoice->id, 'status' => 1]);
                    $this->saveItems($invoice, $validItems);
                    $transaction->commit();
                } else {
                    $transaction->rollBack();
                }
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
            if ($saved) {
                $label = (int)$invoice->invoice_role === Invoice::ROLE_PROFORMA ? 'Proforma' : 'Invoice';
                Yii::$app->session->setFlash('success', $label . ' updated.');
                // Drafts (status=-1) opened from the preview page's "Back & Edit"
                // return to the preview page so the user can save and download.
                if ((int)$invoice->status === -1) {
                    return $this->redirect([
                        (int)$invoice->invoice_role === Invoice::ROLE_PROFORMA ? 'preview-proforma' : 'preview-invoice',
                        'id' => $invoice->id,
                    ]);
                }
                return $this->redirect(['view', 'id' => $invoice->id]);
            }
        }

        return $this->render('update', compact('invoice', 'clients', 'projects', 'banks', 'clientTypes', 'preClient', 'currencies', 'hsnCodes', 'lutArn', 'invoiceCategoryParam'));
    }

    /**
     * Preview a freshly-created Proforma draft (status=-1) before committing.
     * Renders the print template with two action buttons: Save & Download (commits)
     * and Back & Exit (discards the draft).
     */
    public function actionPreviewProforma(int $id): string
    {
        $invoice = $this->findModel($id);
        if ((int)$invoice->invoice_role !== Invoice::ROLE_PROFORMA) {
            throw new \yii\web\BadRequestHttpException('Not a proforma invoice.');
        }
        if ((int)$invoice->status !== -1) {
            return $this->redirect(['view', 'id' => $invoice->id])->send() ? '' : '';
        }
        $this->layout = false;
        $previewInvoiceNo = $invoice->invoice_no ?: $this->previewInvoiceNumber(
            Invoice::ROLE_PROFORMA,
            (int)($invoice->invoice_category ?? Invoice::CATEGORY_SERVICE),
            (int)($invoice->invoice_type ?? 1)
        );
        return $this->renderPartial('preview-draft', [
            'model' => $invoice,
            'previewInvoiceNo' => $previewInvoiceNo,
        ]);
    }

    /**
     * Tax-invoice equivalent of actionPreviewProforma — shows the same Save & Download /
     * Back & Edit page for a freshly created tax invoice draft (status=-1).
     */
    public function actionPreviewInvoice(int $id): string
    {
        $invoice = $this->findModel($id);
        if ((int)$invoice->invoice_role !== Invoice::ROLE_TAX_INVOICE) {
            throw new \yii\web\BadRequestHttpException('Not a tax invoice.');
        }
        if ((int)$invoice->status !== -1) {
            return $this->redirect(['view', 'id' => $invoice->id])->send() ? '' : '';
        }
        $this->layout = false;
        $previewInvoiceNo = $invoice->invoice_no ?: $this->previewInvoiceNumber(
            Invoice::ROLE_TAX_INVOICE,
            (int)($invoice->invoice_category ?? Invoice::CATEGORY_SERVICE),
            (int)($invoice->invoice_type ?? 1)
        );
        return $this->renderPartial('preview-draft', [
            'model' => $invoice,
            'previewInvoiceNo' => $previewInvoiceNo,
        ]);
    }


    /**
     * "Save and Download" — promote a Proforma draft (status=-1) to active (status=1)
     * and immediately stream the PDF download. Mirrors the DC pattern: redirect to
     * a small bridge page that auto-loads download-pdf in an iframe and returns to
     * the proforma listing.
     */
    public function actionConfirmProforma(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        // Resilient lookup — if the draft was discarded in another tab the
        // user gets a friendly flash instead of a hard 404.
        $invoice = Invoice::find()->where(['id' => $id])->andWhere(['!=', 'status', 0])->one();
        if (!$invoice) {
            Yii::$app->session->setFlash('error', 'This proforma is no longer available. It may have been discarded.');
            return $this->redirect(['proforma']);
        }
        if ((int)$invoice->invoice_role !== Invoice::ROLE_PROFORMA) {
            Yii::$app->session->setFlash('error', 'Not a proforma invoice.');
            return $this->redirect(['proforma']);
        }
        if ((int)$invoice->status === -1) {
            $invoice->status       = 1;
            $invoice->updated_date = date('Y-m-d H:i:s');
            $invoice->updated_by   = Yii::$app->user->id;
            $invoice->save(false);
        }
        Yii::$app->session->setFlash('success', 'Proforma saved.');
        // Redirect to the proforma listing with a download-trigger flag so the
        // listing page auto-fires the PDF download in an iframe.
        return $this->redirect(['proforma', 'download_id' => $invoice->id]);
    }

    /**
     * "Back and Exit" — discard a Proforma draft (status=-1) and its line items,
     * returning to the proforma listing.
     */
    public function actionDiscardProforma(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        // Resilient lookup — silently no-op if the draft is already gone.
        $invoice = Invoice::find()->where(['id' => $id])->andWhere(['!=', 'status', 0])->one();
        if (!$invoice) {
            return $this->redirect(['proforma']);
        }
        if ((int)$invoice->invoice_role !== Invoice::ROLE_PROFORMA) {
            return $this->redirect(['proforma']);
        }
        if ((int)$invoice->status === -1) {
            InvoiceDetails::deleteAll(['invoice_id' => $invoice->id]);
            $invoice->delete();
        }
        return $this->redirect(['proforma']);
    }

    /**
     * Convert a Proforma invoice to a Tax Invoice.
     * Prompts for client GST number if missing.
     */
    public function actionConvertToInvoice(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $invoice = $this->findModel($id);
        $this->ensureNotCancelled($invoice);

        if ((int)$invoice->invoice_role !== Invoice::ROLE_PROFORMA) {
            Yii::$app->session->setFlash('error', 'Only Proforma invoices can be converted.');
            return $this->redirect(['view', 'id' => $id]);
        }

        // Check the GSTIN and date before anything changes.
        $gstNo = strtoupper(preg_replace('/\s+/', '', (string)Yii::$app->request->post('client_gst_no', '')));
        if ($gstNo !== '' && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z0-9]Z[A-Z0-9]$/', $gstNo)) {
            Yii::$app->session->setFlash('error', 'That GSTIN is not valid. It has 15 characters, e.g. 33ABCDE1234F1Z5.');
            return $this->redirect(['proforma']);
        }
        $invoiceDateRaw = trim((string)Yii::$app->request->post('invoice_date', ''));
        $parsedDate     = \DateTime::createFromFormat('!Y-m-d', $invoiceDateRaw);
        if ($invoiceDateRaw !== '' && (!$parsedDate || $parsedDate->format('Y-m-d') !== $invoiceDateRaw || $invoiceDateRaw > date('Y-m-d'))) {
            Yii::$app->session->setFlash('error', 'Pick a valid invoice date that is not in the future.');
            return $this->redirect(['proforma']);
        }

        // Update client GST if supplied
        if ($gstNo && $invoice->client && empty($invoice->client->gst_no)) {
            $invoice->client->gst_no = $gstNo;
            $invoice->client->save(false);
            // Refresh billing address snapshot
            $invoice->to_address = implode("\n", array_filter([
                $invoice->client->company_name,
                $invoice->client->address,
                'GST: ' . $gstNo,
            ]));
        }

        // INV-012: use the (already checked) date, fallback to today
        $invoiceDate = $invoiceDateRaw !== '' ? $invoiceDateRaw : date('Y-m-d');

        $db          = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            $invoice->invoice_role = Invoice::ROLE_TAX_INVOICE;
            [$invoice->invoice_no, $invoice->invoice_prefix, $invoice->invoice_unique_no] = $this->generateInvoiceNumberLocked(
                Invoice::ROLE_TAX_INVOICE,
                $db,
                (int)($invoice->invoice_category ?? Invoice::CATEGORY_SERVICE),
                (int)($invoice->client->client_type ?? 1)
            );
            $invoice->invoice_date = $invoiceDate;
            $invoice->status       = 1;
            $invoice->updated_date = date('Y-m-d H:i:s');
            $invoice->updated_by   = Yii::$app->user->id;
            $invoice->save(false);
            // C5: ledger entries in the same transaction — a failure leaves the invoice unconfirmed.
            \common\components\AccountingUtility::accountsLedgerEntryForOutInvoice($invoice);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        Yii::$app->session->setFlash('success', 'Proforma converted to Tax Invoice: ' . $invoice->invoice_no);
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * H28/C5/C6: Confirm a draft invoice — assigns invoice number and creates GL entries.
     * Matches Yii1 actionConfirminvoice.
     */
    public function actionConfirminvoice(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $invoice = $this->findModel($id);

        if ((int)$invoice->status !== -1) {
            Yii::$app->session->setFlash('error', 'Only draft invoices can be confirmed.');
            return $this->redirect(['view', 'id' => $id]);
        }

        $db          = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            // Lock the row: a double click or a second tab must not number the draft twice.
            $locked = Invoice::findBySql('SELECT * FROM invoice WHERE id = :id FOR UPDATE', [':id' => $invoice->id])->one();
            if (!$locked || (int)$locked->status !== -1) {
                $transaction->rollBack();
                Yii::$app->session->setFlash('info', 'This invoice was already saved' . ($locked && $locked->invoice_no ? ' as ' . $locked->invoice_no : '') . '.');
                return $this->redirect(['index']);
            }
            $invoice = $locked;
            // Generate invoice number
            $c = $invoice->client_id ? ClientModel::findOne(['id' => $invoice->client_id, 'status' => 1]) : null;
            [$invoice->invoice_no, $invoice->invoice_prefix, $invoice->invoice_unique_no] = $this->generateInvoiceNumberLocked(
                (int)$invoice->invoice_role,
                $db,
                (int)($invoice->invoice_category ?? Invoice::CATEGORY_SERVICE),
                $c ? (int)$c->client_type : 1
            );

            $invoice->status       = 1;
            $invoice->updated_date = date('Y-m-d H:i:s');
            $invoice->updated_by   = Yii::$app->user->id;
            $invoice->save(false);
            // C5: ledger entries in the same transaction — a failure leaves the invoice unconfirmed.
            \common\components\AccountingUtility::accountsLedgerEntryForOutInvoice($invoice);
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        Yii::$app->session->setFlash('success', 'Invoice saved: ' . $invoice->invoice_no);
        // Redirect to the tax-invoice listing with a download_id flag so the listing
        // page auto-fires the PDF download in an iframe (mirrors the proforma flow).
        return $this->redirect(['index', 'download_id' => $id]);
    }

    /**
     * Update TDS and recalculate payment_status.
     */
    public function actionUpdateTds(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $invoice = $this->findModel($id);
        $this->ensureNotCancelled($invoice);

        $pct = trim((string)Yii::$app->request->post('tds_percent', '0'));
        $amt = trim((string)Yii::$app->request->post('tds_amount', '0'));
        $max = round((float)$invoice->net_total - (float)$invoice->paid_amount, 2);
        $error = null;
        if (!is_numeric($pct === '' ? '0' : $pct) || !is_numeric($amt === '' ? '0' : $amt)) {
            $error = 'TDS percent and amount must be numbers.';
        } elseif ((float)$pct < 0 || (float)$pct > 100) {
            $error = 'TDS percent must be between 0 and 100.';
        } elseif ((float)$amt < 0) {
            $error = 'TDS amount cannot be negative.';
        } elseif ((float)$amt > $max + 0.005) {
            $error = 'TDS cannot be more than the unpaid amount (' . number_format(max(0, $max), 2) . ').';
        }

        if ($error) {
            Yii::$app->session->setFlash('error', $error);
        } else {
            $invoice->tds_percent = round((float)$pct, 2);
            $invoice->tds_amount  = round((float)$amt, 2);
            $this->recalcPaymentStatus($invoice);
            $invoice->save(false);
            Yii::$app->session->setFlash('success', 'TDS updated for ' . ($invoice->invoice_no ?: '#' . $id) . '.');
        }

        // _from lets callers redirect back to the list pages (index / proforma) instead of view
        $from = Yii::$app->request->post('_from', 'view');
        if ($from === 'index') {
            return $this->redirect(['index']);
        }
        if ($from === 'proforma') {
            return $this->redirect(['proforma']);
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Show all receipts for an invoice, with a form to add a new one.
     */
    public function actionReceipts(int $id): string
    {
        $invoice  = $this->findModel($id);
        $receipts = InvoiceReceipt::find()
            ->where(['invoice_id' => $id, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $receipt = new InvoiceReceipt();
        $receipt->payment_mode = InvoiceReceipt::MODE_BANK;

        return $this->render('receipts', compact('invoice', 'receipts', 'receipt'));
    }

    /**
     * Create Receipt page — matches Yii1 invoicereceipt/create with invoice info card + form.
     */
    public function actionCreateReceipt(int $invoice_id): string|Response
    {
        $invoice = Invoice::findOne(['id' => $invoice_id, 'status' => 1]);
        if (!$invoice) {
            throw new NotFoundHttpException('Invoice not found.');
        }

        $receipt = new InvoiceReceipt();
        $receipt->receipt_date = date('Y-m-d');

        return $this->render('create-receipt', compact('invoice', 'receipt'));
    }

    /**
     * Save a new receipt (POST from receipts page or view page).
     */
    public function actionAddReceipt(int $id): Response
    {
        $invoice = $this->findModel($id);
        $this->ensureNotCancelled($invoice);
        $req  = Yii::$app->request;
        $data = is_array($req->post('InvoiceReceipt')) ? $req->post('InvoiceReceipt') : [];
        $val  = fn($k) => isset($data[$k]) && is_scalar($data[$k]) ? trim((string)$data[$k]) : (is_scalar($req->post($k)) ? trim((string)$req->post($k)) : '');
        $back = in_array($req->post('_from'), ['receipts', 'create-receipt'], true) ? ['receipts', 'id' => $id] : ['view', 'id' => $id];
        if ($req->post('_from') === 'create-receipt') {
            $back = ['create-receipt', 'invoice_id' => $id];
        }

        $mode    = ctype_digit($val('payment_mode')) ? (int)$val('payment_mode') : InvoiceReceipt::MODE_BANK;
        $receipt = new InvoiceReceipt([
            'invoice_id'      => $invoice->id,
            'receipt_date'    => $val('receipt_date') !== '' ? $val('receipt_date') : date('Y-m-d'),
            'receipt_amount'  => is_numeric($val('receipt_amount')) ? round((float)$val('receipt_amount'), 2) : null,
            'payment_mode'    => $mode,
            'payment_remarks' => $val('payment_remarks'),
            'created_date'    => date('Y-m-d H:i:s'),
            'created_by'      => Yii::$app->user->id,
            'status'          => 1,
            'receipt_no'      => 'pending', // real number is assigned under lock below
        ]);
        // Only the fields of the chosen payment mode are kept.
        $modeFields = [
            InvoiceReceipt::MODE_CASH   => ['cash_date'],
            InvoiceReceipt::MODE_CHEQUE => ['cheque_sender_bank', 'cheque_no', 'cheque_date', 'cheque_amount', 'cheque_received_bank', 'cheque_received_ac_no', 'cheque_deposit_date'],
            InvoiceReceipt::MODE_BANK   => ['bank_sender_bank', 'bank_ref_no', 'bank_amount', 'bank_date', 'bank_received_bank', 'bank_received_ac_no'],
            InvoiceReceipt::MODE_PAYPAL => ['paypal_ref_no', 'paypal_amount', 'paypal_payment_date'],
        ][$mode] ?? [];
        foreach ($modeFields as $f) {
            $v = $val($f);
            $receipt->$f = $v === '' ? null : (str_ends_with($f, '_amount') ? (is_numeric($v) ? round((float)$v, 2) : $v) : $v);
        }

        if (!$receipt->validate()) {
            Yii::$app->session->setFlash('error', implode(' ', $receipt->getErrorSummary(false)));
            return $this->redirect($back);
        }

        $db          = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            // Lock the invoice so two people cannot record the same balance at once.
            $locked  = Invoice::findBySql('SELECT * FROM invoice WHERE id = :id FOR UPDATE', [':id' => $invoice->id])->one();
            $balance = round((float)$locked->net_total - (float)$locked->tds_amount - (float)$locked->paid_amount, 2);
            if ((int)$locked->payment_status === Invoice::PAYMENT_STATUS_PAID || $balance <= 0) {
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', 'This invoice is already fully paid. No further payments can be recorded.');
                return $this->redirect($back);
            }
            if ((float)$receipt->receipt_amount > $balance + 0.005) {
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', 'The amount (' . number_format((float)$receipt->receipt_amount, 2) . ') is more than the balance due ('
                    . number_format($balance, 2) . '). Record TDS separately if it was deducted.');
                return $this->redirect($back);
            }
            $invoice = $locked;

            // Receipt numbers never repeat, including numbers of deleted receipts.
            $lastNo = (int)$db->createCommand('SELECT COALESCE(MAX(receipt_unique_no), 0) FROM invoice_receipt FOR UPDATE')->queryScalar();
            $receipt->receipt_unique_no = $lastNo + 1;
            $receipt->receipt_no        = 'REC-' . date('Y') . '-' . str_pad((string)($lastNo + 1), 4, '0', STR_PAD_LEFT);
            $receipt->pending_amount    = $balance;
            $receipt->paid_amount       = round((float)$invoice->paid_amount + (float)$receipt->receipt_amount, 2);
            $receipt->balance_amount    = max(0, round($balance - (float)$receipt->receipt_amount, 2));
            $receipt->save(false);

            $invoice->paid_amount = (float)InvoiceReceipt::find()->where(['invoice_id' => $invoice->id, 'status' => 1])->sum('receipt_amount');
            $this->recalcPaymentStatus($invoice);
            $invoice->save(false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Files after the commit: a rejected file never loses the payment.
        SafeUpload::flashRejected(SafeUpload::save('attachments', 'invoice_receipt_attachments', 'receipt_' . $receipt->id,
            function (string $path) use ($receipt) {
                (new InvoiceReceiptAttachment(['invoice_receipt_id' => $receipt->id, 'path' => $path, 'status' => 1]))->save(false);
            }));

        $curSym = (string)($invoice->currency !== '' ? $invoice->currency : '₹');
        Yii::$app->session->setFlash('success', 'Payment of ' . $curSym . ' ' . number_format((float)$receipt->receipt_amount, 2) . ' recorded as ' . $receipt->receipt_no . '.');
        return $this->redirect($req->post('_from') === 'create-receipt' ? ['receipts', 'id' => $id] : $back);
    }

    /**
     * Delete a receipt and recalculate invoice payment_status.
     */
    public function actionDeleteReceipt(int $id): Response
    {
        $receipt = InvoiceReceipt::findOne(['id' => $id, 'status' => 1]);
        if (!$receipt) {
            Yii::$app->session->setFlash('info', 'That receipt was already deleted.');
            return $this->redirect(Yii::$app->request->referrer ?: ['index']);
        }
        $invoice = $this->findModel((int)$receipt->invoice_id);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $receipt->updateAttributes(['status' => 0, 'deleted_by' => Yii::$app->user->id, 'deleted_at' => date('Y-m-d H:i:s')]);
            $invoice->paid_amount = (float)InvoiceReceipt::find()->where(['invoice_id' => $invoice->id, 'status' => 1])->sum('receipt_amount');
            $this->recalcPaymentStatus($invoice);
            $invoice->save(false);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Yii::$app->session->setFlash('success', 'Receipt ' . $receipt->receipt_no . ' deleted.');
        return $this->redirect(['receipts', 'id' => $invoice->id]);
    }

    /**
     * Client portal — logged-in client sees only their own invoices.
     */
    public function actionClientInvoice(): string
    {
        $clientModel = ClientModel::findOne(['user_id' => Yii::$app->user->id, 'status' => 1]);
        if (!$clientModel) {
            throw new NotFoundHttpException('Client record not found.');
        }

        $query = Invoice::find()
            ->with('client')
            ->where([
                'invoice.status'         => 1,
                'invoice.invoice_status' => 1,
                'invoice.client_id'      => $clientModel->id,
            ])
            ->andWhere(['invoice.invoice_role' => Invoice::ROLE_TAX_INVOICE])
            ->orderBy(['invoice.id' => SORT_DESC]);

        $dataProvider = new \yii\data\ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('client-invoice', ['dataProvider' => $dataProvider, 'client' => $clientModel]);
    }

    public function actionCancel(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST request required.');
        }
        $invoice                 = $this->findModel($id);
        $this->ensureNotCancelled($invoice);
        $isProforma              = (int)$invoice->invoice_role === Invoice::ROLE_PROFORMA;
        $invoice->invoice_status = 0;
        $invoice->save(false);

        Yii::$app->session->setFlash('success', 'Invoice cancelled.');
        return $this->redirect([$isProforma ? 'proforma' : 'index']);
    }

    public function actionMonthlyReport(): string
    {
        $year  = (int)Yii::$app->request->get('year', date('Y'));
        $month = (int)Yii::$app->request->get('month', date('n'));
        if ($year < 2000 || $year > (int)date('Y') + 1 || $month < 1 || $month > 12) {
            Yii::$app->session->setFlash('warning', 'That month is not available; showing the current month.');
            [$year, $month] = [(int)date('Y'), (int)date('n')];
        }

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        // Outbound invoices (Tax only, status=1, invoice_status=1)
        $outInvoices = Invoice::find()
            ->with('client')
            ->where(['invoice.status' => 1, 'invoice_status' => 1, 'invoice_role' => Invoice::ROLE_TAX_INVOICE])
            ->andWhere(['between', 'invoice_date', $from, $to])
            ->orderBy(['invoice_date' => SORT_ASC])
            ->all();

        $outTotals = [
            'grand'  => array_sum(array_column($outInvoices, 'grand_total')),
            'sgst'   => array_sum(array_column($outInvoices, 'sgst_amount')),
            'cgst'   => array_sum(array_column($outInvoices, 'cgst_amount')),
            'igst'   => array_sum(array_column($outInvoices, 'igst_amount')),
            'net'    => array_sum(array_column($outInvoices, 'net_total')),
            'paid'   => array_sum(array_column($outInvoices, 'paid_amount')),
        ];

        // Inward invoices (vendor purchases, status=1)
        $inInvoices = InwardInvoice::find()
            ->with('vendor')
            ->where(['status' => 1])
            ->andWhere(['between', 'invoice_date', $from, $to])
            ->orderBy(['invoice_date' => SORT_ASC])
            ->all();

        $inTotals = [
            'grand' => array_sum(array_column($inInvoices, 'grand_total')),
            'sgst'  => array_sum(array_column($inInvoices, 'sgst_amount')),
            'cgst'  => array_sum(array_column($inInvoices, 'cgst_amount')),
            'igst'  => array_sum(array_column($inInvoices, 'igst_amount')),
            'net'   => array_sum(array_column($inInvoices, 'net_total')),
        ];

        // Net GST payable = outbound tax - inward tax (input credit)
        $netTax = [
            'sgst'  => $outTotals['sgst'] - $inTotals['sgst'],
            'cgst'  => $outTotals['cgst'] - $inTotals['cgst'],
            'igst'  => $outTotals['igst'] - $inTotals['igst'],
        ];
        $netTax['total'] = $netTax['sgst'] + $netTax['cgst'] + $netTax['igst'];

        return $this->render('monthly-report', compact(
            'outInvoices', 'outTotals',
            'inInvoices', 'inTotals',
            'netTax', 'year', 'month'
        ));
    }

    private function recalcPaymentStatus(Invoice $invoice): void
    {
        $effective = (float)$invoice->net_total - (float)$invoice->tds_amount;
        $paid      = (float)$invoice->paid_amount;
        if ($effective <= 0 || $paid >= $effective) {
            $invoice->payment_status = Invoice::PAYMENT_STATUS_PAID;
            if (empty($invoice->payment_date)) {
                $invoice->payment_date = date('Y-m-d');
            }
        } elseif ($paid > 0) {
            $invoice->payment_status = Invoice::PAYMENT_STATUS_PARTIAL;
            // Preserve payment_date as the date of first payment received
            if (empty($invoice->payment_date)) {
                $invoice->payment_date = date('Y-m-d');
            }
        } else {
            $invoice->payment_status = Invoice::PAYMENT_STATUS_UNPAID;
            $invoice->payment_date   = null;
        }
    }

    /**
     * Return the invoice number prefix string and financial-year label.
     * Prefixes match Yii1: MAE/ (service domestic), MAEINT/ (service international),
     * MAEMC/ (product/sales), PRO/ (proforma), CN/ (credit note).
     * Financial year: April 1 – March 31 (e.g. FY 2026-27 → "26-27").
     */
    private function invoicePrefix(int $role, int $category, int $clientType): string
    {
        // Match Yii1 Utility::get_invoice_number: proforma shares the same
        // numbering pool/prefix as tax invoice — no separate "PRO" prefix.
        // Prefix depends only on invoice_category and clientType.
        if ($category === Invoice::CATEGORY_PRODUCT) {
            return 'MAEMC';
        }
        return $clientType === ClientModel::TYPE_INTERNATIONAL ? 'MAEINT' : 'MAE';
    }

    /**
     * FY start date — April 1 of current FY (matches Yii1 $chk_date).
     */
    private function financialYearStart(): string
    {
        $month = (int)date('n');
        $year  = (int)date('Y');
        $fyStartYear = $month >= 4 ? $year : $year - 1;
        return $fyStartYear . '-04-01 00:00:00';
    }

    private function financialYear(): string
    {
        $month   = (int)date('n');
        $year    = (int)date('Y');
        $fyStart = $month >= 4 ? $year : $year - 1;
        return substr((string)$fyStart, 2) . '-' . substr((string)($fyStart + 1), 2);
    }

    /**
     * INV-001: Generate invoice number inside an active transaction using SELECT FOR UPDATE.
     * Format: PREFIX/FY/NNN  e.g. MAE/26-27/001
     *
     * @return array{0: string, 1: string, 2: int}  [invoice_no, invoice_prefix, invoice_unique_no]
     */
    private function generateInvoiceNumberLocked(int $role, \yii\db\Connection $db, int $category = Invoice::CATEGORY_SERVICE, int $clientType = 1): array
    {
        $prefix   = $this->invoicePrefix($role, $category, $clientType);
        $fy       = $this->financialYear();
        $pattern  = $prefix . '/' . $fy . '/';
        $fyStart  = $this->financialYearStart();

        // Mirror Yii1 Utility::get_invoice_number: partition by invoice_type
        // (intl=3 vs domestic=1,2) and invoice_category, NOT by status or role.
        // Drafts have invoice_unique_no = NULL so they auto-skip; cancelled
        // invoices still consume a sequence number.
        $typeCondition = ($clientType === ClientModel::TYPE_INTERNATIONAL)
            ? 'invoice_type = 3'
            : 'invoice_type IN (1, 2)';

        $maxNo = (int)$db->createCommand(
            "SELECT COALESCE(MAX(invoice_unique_no), 0) FROM invoice
             WHERE invoice_unique_no IS NOT NULL
               AND invoice_unique_no <> ''
               AND $typeCondition
               AND invoice_category = :category
               AND invoice_date >= :fyStart
             FOR UPDATE",
            [':category' => $category, ':fyStart' => $fyStart]
        )->queryScalar();

        $seq = $maxNo + 1;
        return [$pattern . str_pad($seq, 3, '0', STR_PAD_LEFT), $prefix . '/', $seq];
    }

    /**
     * Preview next invoice number without a lock (for display on the create form).
     */
    private function previewInvoiceNumber(int $role, int $category = Invoice::CATEGORY_SERVICE, int $clientType = 1): string
    {
        $prefix   = $this->invoicePrefix($role, $category, $clientType);
        $fy       = $this->financialYear();
        $pattern  = $prefix . '/' . $fy . '/';
        $fyStart  = $this->financialYearStart();

        // Same partition as generateInvoiceNumberLocked — see comment there.
        $typeCondition = ($clientType === ClientModel::TYPE_INTERNATIONAL)
            ? ['invoice_type' => 3]
            : ['invoice_type' => [1, 2]];

        $maxNo = (int)Invoice::find()
            ->andWhere(['not', ['invoice_unique_no' => null]])
            ->andWhere(['<>', 'invoice_unique_no', ''])
            ->andWhere($typeCondition)
            ->andWhere(['invoice_category' => $category])
            ->andWhere(['>=', 'invoice_date', $fyStart])
            ->max('invoice_unique_no');

        return $pattern . str_pad((int)$maxNo + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Autocomplete endpoint for invoice search — matches Yii1 actionAutocompleteinvoice.
     * Returns JSON array used by other modules (e.g. InwardInvoice, Receipt).
     */
    public function actionAutocompleteInvoice(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $term = trim(Yii::$app->request->get('term', ''));
        if ($term === '') {
            return $this->asJson([]);
        }

        $invoices = Invoice::find()
            ->where(['status' => 1, 'invoice_status' => 1])
            ->andWhere(['or',
                ['like', 'invoice_no', $term],
                ['like', 'title', $term],
            ])
            ->orderBy(['id' => SORT_DESC])
            ->limit(20)
            ->all();

        $result = [];
        foreach ($invoices as $inv) {
            $result[] = [
                'id'             => $inv->id,
                'invoice_no'     => $inv->invoice_no,
                'title'          => $inv->title,
                'invoice_date'   => $inv->invoice_date,
                'invoice_amount' => $inv->net_total,
                'label'          => $inv->invoice_no . ' - ' . $inv->title,
                'value'          => $inv->invoice_no,
            ];
        }

        return $this->asJson($result);
    }

    /** Fields the invoice form may set. Numbers, totals, status, role and payments never come from the browser. */
    private const FORM_FIELDS = [
        'client_id', 'title', 'invoice_category', 'project_id', 'phase_value', 'milestone_value', 'currency',
        'currency_exchange_rate', 'transaction_charge_percent', 'bank_id', 'lut_arn', 'terms_and_conditions', 'to_address',
    ];

    private function loadForm(Invoice $invoice, array $post): void
    {
        $data = is_array($post['Invoice'] ?? null) ? $post['Invoice'] : [];
        $data = array_map(fn($v) => is_scalar($v) ? trim((string)$v) : null, array_intersect_key($data, array_flip(self::FORM_FIELDS)));
        $invoice->setAttributes($data, false);
        foreach (['project_id', 'phase_value', 'milestone_value', 'bank_id'] as $optional) {
            if ($invoice->$optional === '') {
                $invoice->$optional = null;
            }
        }
    }

    /**
     * Read the posted line items (Yii1-style particular[] arrays or items[] rows).
     * Rows with quantity and rate are priced qty × rate plus the transaction charge
     * (the form adds the same charge when a line is added); other rows use their amount.
     *
     * @return array{0: array, 1: float, 2: string[]} [valid items, sum of amounts, error messages]
     */
    private function readItems(array $post, float $chargePct = 0.0): array
    {
        $chargePct = max(0.0, min(100.0, $chargePct));
        $rows = [];
        if (!empty($post['particular']) && is_array($post['particular'])) {
            $col = fn($k, $i) => is_array($post[$k] ?? null) && isset($post[$k][$i]) && is_scalar($post[$k][$i]) ? trim((string)$post[$k][$i]) : '';
            foreach (array_keys($post['particular']) as $i) {
                $rows[] = ['particulars' => $col('particular', $i), 'amount' => $col('particularamount', $i), 'hsn_sac' => $col('hsn_sac', $i),
                           'model' => $col('model', $i), 'qty' => $col('qty', $i), 'rate' => $col('rate', $i)];
            }
        } elseif (is_array($post['items'] ?? null)) {
            foreach ($post['items'] as $row) {
                if (is_array($row)) {
                    $rows[] = array_map(fn($v) => is_scalar($v) ? trim((string)$v) : '', $row + ['particulars' => '', 'amount' => '', 'hsn_sac' => '', 'model' => '', 'qty' => '', 'rate' => '']);
                }
            }
        }

        $items = $errors = [];
        $sum = 0.0;
        foreach ($rows as $row) {
            $hasQtyRate = $row['qty'] !== '' && $row['rate'] !== '';
            if ($row['particulars'] === '' && $row['amount'] === '' && !$hasQtyRate) {
                continue; // blank row
            }
            $n = count($items) + 1;
            foreach (['amount', 'qty', 'rate'] as $k) {
                if ($row[$k] !== '' && !is_numeric($row[$k])) {
                    $errors[] = "Line $n: $k must be a number.";
                    continue 2;
                }
            }
            $amount = $hasQtyRate
                ? round((float)$row['qty'] * (float)$row['rate'] * (1 + $chargePct / 100), 2)
                : round((float)$row['amount'], 2);
            if ($row['particulars'] === '') {
                $errors[] = "Line $n: enter a description.";
            } elseif (mb_strlen($row['particulars']) > 5000) {
                $errors[] = "Line $n: the description is too long.";
            }
            if ($hasQtyRate && ((float)$row['qty'] <= 0 || (float)$row['rate'] < 0)) {
                $errors[] = "Line $n: quantity must be more than 0 and the rate cannot be negative.";
            } elseif ($amount <= 0) {
                $errors[] = "Line $n: the amount must be more than 0.";
            } elseif ($amount > 9999999999) {
                $errors[] = "Line $n: the amount is too large.";
            }
            if (mb_strlen($row['hsn_sac']) > 255 || mb_strlen($row['model']) > 255) {
                $errors[] = "Line $n: HSN/SAC or model is too long.";
            }
            $row['amount'] = $amount;
            $items[] = $row;
            $sum += $amount;
        }
        if (!$items && !$errors) {
            $errors[] = 'Add at least one line item with a description and amount.';
        }
        if (count($items) > self::MAX_ITEMS) {
            $errors[] = 'An invoice can have at most ' . self::MAX_ITEMS . ' line items.';
        }
        return [$items, round($sum, 2), $errors];
    }

    /** The form posts the currency row id; store its code and words (Yii1 behaviour). */
    private function resolveCurrency(Invoice $invoice): void
    {
        if ($invoice->currency !== null && ctype_digit((string)$invoice->currency)) {
            $currency = Currency::findOne(['id' => (int)$invoice->currency, 'status' => 1]);
            $invoice->currency = $currency->currency ?? null;
            if ($currency) {
                $invoice->currency_in_words = $currency->in_words;
            }
        }
    }

    /** Client, bank, project and category must point at real, active records. */
    private function checkReferences(Invoice $invoice): array
    {
        $errors = [];
        if (!$invoice->client_id || !ClientModel::find()->where(['id' => (int)$invoice->client_id, 'status' => 1])->exists()) {
            $errors[] = 'Choose an active client.';
        }
        if (!in_array((int)$invoice->invoice_category, [Invoice::CATEGORY_SERVICE, Invoice::CATEGORY_PRODUCT], true)) {
            $errors[] = 'Choose whether this is a service or sales invoice.';
        }
        if ($invoice->bank_id && !BankDetail::find()->where(['id' => (int)$invoice->bank_id, 'status' => 1])->exists()
            && (string)$invoice->bank_id !== (string)$invoice->getOldAttribute('bank_id')) {
            $errors[] = 'That bank account was removed — pick another one.';
        }
        if ($invoice->project_id && !Project::find()->where(['id' => (int)$invoice->project_id, 'status' => 1])->exists()
            && (string)$invoice->project_id !== (string)$invoice->getOldAttribute('project_id')) {
            $errors[] = 'That project was removed — pick another one.';
        }
        if (empty($invoice->currency)) {
            $errors[] = 'Choose a currency.';
        }
        if (trim((string)$invoice->title) === '') {
            $errors[] = 'Enter a title for the invoice.';
        }
        return $errors;
    }

    /** Insert the line rows of an invoice (caller holds the transaction). */
    private function saveItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $detail = new InvoiceDetails([
                'invoice_id'  => $invoice->id,
                'particulars' => $item['particulars'],
                'sac'         => $item['hsn_sac'],
                'amount'      => $item['amount'],
                'status'      => 1,
            ]);
            // Sale-only columns, added after the Yii1 schema.
            if ($detail->hasAttribute('model')) {
                $detail->model = $item['model'] !== '' ? $item['model'] : null;
            }
            if ($detail->hasAttribute('quantity')) {
                $detail->quantity = $item['qty'] !== '' ? (float)$item['qty'] : null;
            }
            if ($detail->hasAttribute('unit_rate')) {
                $detail->unit_rate = $item['rate'] !== '' ? (float)$item['rate'] : null;
            }
            $detail->save(false);
        }
    }

    private function findModel(int $id): Invoice
    {
        // status=1 (active) or status=-1 (draft) — exclude only soft-deleted (status=0)
        $query = Invoice::find()->where(['id' => $id])->andWhere(['!=', 'status', 0]);
        // A client login sees only its own confirmed, active tax invoices (same set as "My Invoices").
        if ((int)(Yii::$app->user->identity->usertype ?? 0) === 3) {
            $clientId = ClientModel::find()->select('id')->where(['user_id' => Yii::$app->user->id, 'status' => 1])->scalar();
            $query->andWhere(['client_id' => $clientId ?: 0, 'status' => 1, 'invoice_status' => 1, 'invoice_role' => Invoice::ROLE_TAX_INVOICE]);
        }
        $model = $query->one();
        if (!$model) {
            throw new NotFoundHttpException('Invoice not found.');
        }
        return $model;
    }

    private function ensureNotCancelled(Invoice $invoice): void
    {
        if ((int)$invoice->invoice_status === 0) {
            throw new \yii\web\ForbiddenHttpException('This invoice has been cancelled and cannot be modified.');
        }
    }
}
