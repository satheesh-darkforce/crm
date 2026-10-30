<?php

namespace backend\controllers;

use common\components\UserAccess;
use backend\models\ClientModel;
use backend\models\Developer;
use backend\models\PoDetail;
use backend\models\PoInvoiceDetail;
use backend\models\PoPayment;
use backend\models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Full PO management dashboard — table with status badges, edit-status popup,
 * invoices / payments modals, file uploads, search & filter.
 *
 *  AJAX endpoints
 *    actionChangeStatus($id, $status)
 *    actionAddInvoice($po_id)         → multipart with optional PDF
 *    actionListInvoices($po_id)       → JSON list for the table render
 *    actionDeleteInvoice($id)         → soft delete (status=0)
 *    actionAddPayment($po_id)         → POST
 *    actionListPayments($po_id)       → JSON list
 *    actionDeletePayment($id)         → soft delete
 *    actionDownloadInvoice($id)       → stream inline
 */
class PoDashboardController extends Controller
{
    public function actionIndex(): string
    {
        $rows = PoDetail::find()
            ->alias('po')
            ->select([
                'po.id', 'po.po_number', 'po.date', 'po.particulars', 'po.po_cost',
                'po.po_status', 'po.delivery_date', 'po.po_received_by',
                'po.po_incharge', 'po.client_id', 'po.quote_id', 'po.file',
                'po.created_at',
                'company_name' => 'COALESCE(c1.company_name, c2.company_name)',
                'client_name'  => 'COALESCE(c1.client_name,  c2.client_name)',
                'incharge_name'=> 'd.developer_name',
            ])
            ->leftJoin(['c1' => ClientModel::tableName()], 'c1.id = po.client_id')
            ->leftJoin(['cq' => 'client_quotation'], 'cq.id = po.quote_id')
            ->leftJoin(['c2' => ClientModel::tableName()], 'c2.id = cq.client_id')
            ->leftJoin(['d'  => Developer::tableName()], 'd.user_id = po.po_incharge')
            ->where(['!=', 'po.status', 0])
            ->orderBy(['po.id' => SORT_DESC])
            ->asArray()
            ->all();

        $ids = ArrayHelper::getColumn($rows, 'id');
        $paid     = $this->sumByPo(PoPayment::class, 'amount', $ids);
        $invoices = $this->countByPo(PoInvoiceDetail::class, $ids);

        // Stats
        $total      = count($rows);
        $totalVal   = 0.0;
        $inProgress = 0;
        $totalReceived = 0.0;
        foreach ($rows as $r) {
            $totalVal      += (float) $r['po_cost'];
            $totalReceived += (float) ($paid[$r['id']] ?? 0);
            if ((int) $r['po_status'] === PoDetail::STATUS_IN_PROGRESS) {
                $inProgress++;
            }
        }
        $totalBalance = $totalVal - $totalReceived;

        return $this->render('index', [
            'rows'           => $rows,
            'paidByPo'       => $paid,
            'invoiceCounts'  => $invoices,
            'inchargeOptions'=> $this->getInchargeOptions(),
            'stats'          => [
                'total'         => $total,
                'inProgress'    => $inProgress,
                'totalValue'    => $totalVal,
                'totalReceived' => $totalReceived,
                'totalBalance'  => $totalBalance,
            ],
        ]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findPo($id);
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * XLSX export of the dashboard. Honours the same filters the user has
     * applied on screen (status tab, search text, accountant) so the file
     * matches what they see.
     */
    public function actionExport(): Response
    {
        $request = Yii::$app->request;
        $status  = $request->get('status', '');
        $search  = trim((string) $request->get('search', ''));
        $acc     = trim((string) $request->get('acc', ''));

        $q = PoDetail::find()
            ->alias('po')
            ->select([
                'po.id', 'po.po_number', 'po.date', 'po.particulars', 'po.po_cost',
                'po.po_status', 'po.delivery_date', 'po.po_received_by',
                'company_name' => 'COALESCE(c1.company_name, c2.company_name)',
            ])
            ->leftJoin(['c1' => ClientModel::tableName()], 'c1.id = po.client_id')
            ->leftJoin(['cq' => 'client_quotation'], 'cq.id = po.quote_id')
            ->leftJoin(['c2' => ClientModel::tableName()], 'c2.id = cq.client_id')
            ->where(['!=', 'po.status', 0])
            ->orderBy(['po.id' => SORT_DESC]);

        if ($status !== '' && array_key_exists((int) $status, PoDetail::statusOptions())) {
            $q->andWhere(['po.po_status' => (int) $status]);
        }
        if ($acc !== '') {
            $q->andWhere(['po.po_received_by' => $acc]);
        }
        if ($search !== '') {
            $q->andWhere(['or',
                ['like', 'po.po_number', $search],
                ['like', 'po.particulars', $search],
                ['like', 'c1.company_name', $search],
                ['like', 'c2.company_name', $search],
            ]);
        }
        $rows = $q->asArray()->all();
        $ids  = ArrayHelper::getColumn($rows, 'id');
        $paid     = $this->sumByPo(PoPayment::class, 'amount', $ids);
        $invoices = $this->countByPo(PoInvoiceDetail::class, $ids);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Purchase Orders');

        $headers = ['S.No', 'PO Date', 'PO Number', 'Company', 'Value (₹)',
                    'Particulars', 'Accountant', 'Delivery Date', 'Status',
                    'Invoices', 'Received (₹)', 'Balance (₹)'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $statusOpts = PoDetail::statusOptions();
        $rowIdx = 2;
        foreach ($rows as $i => $r) {
            $id      = (int) $r['id'];
            $value   = (float) $r['po_cost'];
            $rec     = (float) ($paid[$id] ?? 0);
            $balance = $value - $rec;
            $sheet->fromArray([
                $i + 1,
                $r['date'] ? date('d M Y', strtotime((string) $r['date'])) : '',
                $r['po_number'],
                $r['company_name'] ?? '',
                $value,
                $r['particulars'],
                $r['po_received_by'] ?: '',
                $r['delivery_date'] ? date('d M Y', strtotime((string) $r['delivery_date'])) : '',
                $statusOpts[(int) $r['po_status']] ?? '',
                (int) ($invoices[$id] ?? 0),
                $rec,
                $balance,
            ], null, 'A' . $rowIdx);
            $rowIdx++;
        }

        // Number formatting for Rs columns and auto-sized columns.
        if ($rowIdx > 2) {
            $range = 'E2:E' . ($rowIdx - 1);
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('K2:K' . ($rowIdx - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('L2:L' . ($rowIdx - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return Yii::$app->response->sendContentAsFile(
            $content,
            'PO_Dashboard_' . date('Y-m-d') . '.xlsx',
            ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    // ── Delivery date ──────────────────────────────────────────────────

    /**
     * Inline set/update the delivery_date for a PO from the dashboard row.
     * Used by the "Click to add" link in the Delivery column when a PO has
     * no delivery date yet.
     */
    public function actionSetDeliveryDate(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }
        $id   = (int) Yii::$app->request->post('id');
        $date = trim((string) Yii::$app->request->post('delivery_date'));
        if ($date === '') {
            return ['success' => false, 'message' => 'Please pick a delivery date.'];
        }
        $po = $this->findPo($id);
        $po->delivery_date = date('Y-m-d H:i:s', strtotime($date));
        $po->modified_at   = date('Y-m-d H:i:s');
        if (!$po->save(false, ['delivery_date', 'modified_at'])) {
            return ['success' => false, 'message' => 'Could not save delivery date.'];
        }
        return [
            'success'        => true,
            'message'        => 'Delivery date updated.',
            'delivery_date'  => date('d M Y', strtotime((string) $po->delivery_date)),
        ];
    }

    // ── Status ─────────────────────────────────────────────────────────

    public function actionChangeStatus(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }
        $id     = (int) Yii::$app->request->post('id');
        $status = (int) Yii::$app->request->post('status');
        if (!array_key_exists($status, PoDetail::statusOptions())) {
            return ['success' => false, 'message' => 'Invalid status.'];
        }
        $po = $this->findPo($id);
        $po->po_status   = $status;
        $po->modified_at = date('Y-m-d H:i:s');
        if (!$po->save(false, ['po_status', 'modified_at'])) {
            return ['success' => false, 'message' => 'Could not update status.'];
        }
        return [
            'success' => true,
            'message' => 'Status updated.',
            'label'   => $po->getStatusLabel(),
            'status'  => $status,
        ];
    }

    // ── Invoices ───────────────────────────────────────────────────────

    public function actionAddInvoice(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }
        $po = $this->findPo((int) Yii::$app->request->post('po_id'));

        $inv = new PoInvoiceDetail();
        $inv->po_id          = $po->id;
        $inv->invoice_number = trim((string) Yii::$app->request->post('invoice_number'));
        $rawDate             = trim((string) Yii::$app->request->post('invoice_date'));
        if ($rawDate !== '' && strtotime($rawDate) > strtotime(date('Y-m-d'))) {
            return ['success' => false, 'message' => 'Invoice date cannot be in the future.'];
        }
        $inv->invoice_date   = $rawDate ? date('Y-m-d H:i:s', strtotime($rawDate)) : null;
        $inv->created_at     = date('Y-m-d H:i:s');
        $inv->status         = 1;

        $file = UploadedFile::getInstanceByName('invoice_file');
        if ($file) {
            $inv->invoice_file_upload = $file;
            if (!$inv->validate(['invoice_file_upload'])) {
                return ['success' => false, 'message' => $this->firstError($inv)];
            }
            $savedPath = $this->saveInvoiceFile($po, $file);
            if (!$savedPath) {
                return ['success' => false, 'message' => 'Could not save invoice file.'];
            }
            $inv->invoice_file = $savedPath;
        } else {
            $inv->invoice_file = '';
        }

        if (!$inv->validate(['po_id', 'invoice_number', 'invoice_date'])) {
            return ['success' => false, 'message' => $this->firstError($inv)];
        }
        if (!$inv->save(false)) {
            return ['success' => false, 'message' => 'Could not save invoice.'];
        }
        return ['success' => true, 'message' => 'Invoice added.'];
    }

    public function actionListInvoices(int $po_id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $items = PoInvoiceDetail::find()
            ->where(['po_id' => $po_id, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        $list = [];
        foreach ($items as $i) {
            $list[] = [
                'id'             => $i->id,
                'invoice_number' => $i->invoice_number,
                'invoice_date'   => $i->invoice_date ? date('d M Y', strtotime($i->invoice_date)) : '—',
                'has_file'       => !empty($i->invoice_file),
                'view_url'       => $i->invoice_file ? \yii\helpers\Url::to(['download-invoice', 'id' => $i->id]) : null,
            ];
        }
        return ['success' => true, 'items' => $list];
    }

    public function actionDeleteInvoice(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }
        $id  = (int) Yii::$app->request->post('id');
        $inv = PoInvoiceDetail::findOne($id);
        if (!$inv) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }
        $inv->status = 0;
        $inv->save(false, ['status']);
        return ['success' => true, 'message' => 'Invoice removed.'];
    }

    public function actionDownloadInvoice(int $id): Response
    {
        $inv = PoInvoiceDetail::findOne($id);
        if (!$inv || $inv->status !== 1 || empty($inv->invoice_file)) {
            throw new NotFoundHttpException('Invoice file not found.');
        }
        $path = Yii::getAlias('@webroot/') . ltrim($inv->invoice_file, '/');
        if (!is_file($path)) {
            throw new NotFoundHttpException('File missing on disk.');
        }
        return Yii::$app->response->sendFile($path, basename($path), [
            'inline' => true,
            'mimeType' => 'application/pdf',
        ]);
    }

    // ── Payments ───────────────────────────────────────────────────────

    public function actionAddPayment(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }
        $po = $this->findPo((int) Yii::$app->request->post('po_id'));

        $p = new PoPayment();
        $p->po_id        = $po->id;
        $rawDate         = trim((string) Yii::$app->request->post('payment_date'));
        if ($rawDate !== '' && strtotime($rawDate) > strtotime(date('Y-m-d'))) {
            return ['success' => false, 'message' => 'Payment date cannot be in the future.'];
        }
        $p->payment_date = $rawDate ? date('Y-m-d H:i:s', strtotime($rawDate)) : null;
        $p->amount       = trim((string) Yii::$app->request->post('amount'));
        $p->mode         = (int)  Yii::$app->request->post('mode');
        $p->note         = trim((string) Yii::$app->request->post('note', ''));
        $p->created_at   = date('Y-m-d H:i:s');
        $p->status       = 1;

        if (!$p->validate()) {
            return ['success' => false, 'message' => $this->firstError($p)];
        }
        if (!$p->save(false)) {
            return ['success' => false, 'message' => 'Could not save payment.'];
        }
        return ['success' => true, 'message' => 'Payment recorded.'];
    }

    public function actionListPayments(int $po_id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $items = PoPayment::find()
            ->where(['po_id' => $po_id, 'status' => 1])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        $list = [];
        foreach ($items as $p) {
            $list[] = [
                'id'           => $p->id,
                'payment_date' => $p->payment_date ? date('d M Y', strtotime($p->payment_date)) : '—',
                'amount'       => (float) $p->amount,
                'mode'         => $p->mode,
                'mode_label'   => $p->getModeLabel(),
                'note'         => $p->note,
            ];
        }
        return ['success' => true, 'items' => $list, 'modes' => PoPayment::modeOptions()];
    }

    public function actionDeletePayment(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }
        $id = (int) Yii::$app->request->post('id');
        $p  = PoPayment::findOne($id);
        if (!$p) {
            return ['success' => false, 'message' => 'Payment not found.'];
        }
        $p->status = 0;
        $p->save(false, ['status']);
        return ['success' => true, 'message' => 'Payment removed.'];
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function findPo(int $id): PoDetail
    {
        $po = PoDetail::findOne($id);
        if (!$po || (int) $po->status === 0) {
            throw new NotFoundHttpException('Purchase Order not found.');
        }
        return $po;
    }

    private function getInchargeOptions(): array
    {
        return User::find()
            ->alias('u')
            ->select(['d.developer_name', 'u.user_id'])
            ->innerJoin(['d' => Developer::tableName()], 'd.user_id = u.user_id')
            ->where(['u.status' => 1, 'u.usertype' => 2, 'd.status' => 1])
            ->orderBy(['d.developer_name' => SORT_ASC])
            ->indexBy('user_id')
            ->column();
    }

    private function sumByPo(string $modelClass, string $col, array $poIds): array
    {
        if (empty($poIds)) return [];
        $rows = $modelClass::find()
            ->select(["po_id", "SUM($col) AS total"])
            ->where(['po_id' => $poIds, 'status' => 1])
            ->groupBy('po_id')
            ->asArray()
            ->all();
        return ArrayHelper::map($rows, 'po_id', 'total');
    }

    private function countByPo(string $modelClass, array $poIds): array
    {
        if (empty($poIds)) return [];
        $rows = $modelClass::find()
            ->select(['po_id', 'COUNT(*) AS cnt'])
            ->where(['po_id' => $poIds, 'status' => 1])
            ->groupBy('po_id')
            ->asArray()
            ->all();
        return ArrayHelper::map($rows, 'po_id', 'cnt');
    }

    private function firstError($model): string
    {
        $errs = $model->getFirstErrors();
        return $errs ? reset($errs) : 'Validation failed.';
    }

    private function saveInvoiceFile(PoDetail $po, UploadedFile $file): ?string
    {
        if (strtolower((string) $file->extension) !== 'pdf') {
            return null;
        }
        $dir = Yii::getAlias('@webroot/uploads/po_invoices/');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $clean = preg_replace('/[^a-zA-Z0-9._-]/', '', $file->baseName);
        $name  = time() . '_' . $po->id . '_' . $clean . '.' . $file->extension;
        if (!$file->saveAs($dir . $name)) return null;
        return 'uploads/po_invoices/' . $name;
    }
}
