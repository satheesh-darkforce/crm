<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\models\DcDetail;
use backend\models\DcDocumentFile;
use backend\models\DcProduct;
use backend\models\DeliveryDetail;
use backend\models\DeliveryProduct;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\IntegrityException;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Delivery Challan controller — ported 1:1 from delivery/controllers/DcController.php.
 *
 * Matches delivery's two-button save/download UX:
 *   - action=preview  → saves as draft (status=3, no dc_no), renders challan-pdf inline in browser.
 *   - action=submit   → saves final (status=1, unique dc_no), redirects back to the form with
 *                        ?download_id=N&download_submit=N so the page auto-redirects to download-dc.
 *
 * Simplified after DB merge: `client_id` references `client.id` directly (no ClientDetails upsert).
 */
class DcController extends Controller
{
    public function actionIndex(): string
    {
        // Listing now includes Cancelled rows (status=2) so users can see what
        // was cancelled. Drafts/previews (3) and deleted (0) stay hidden.
        // Inward DCs (type=2) are shown in their own listing at /dc/inward-index.
        $statusFilter = (string) Yii::$app->request->get('status', 'all');
        $query = DcDetail::find()
            ->alias('dc')
            ->where(['in', 'dc.status', [1, 2]])
            ->andWhere(['or', ['dc.type' => null], ['dc.type' => 1]])
            ->joinWith('client client')
            ->with('delivery')
            ->orderBy([
                new \yii\db\Expression('CAST(dc.dc_no AS UNSIGNED) DESC'),
                'dc.id' => SORT_DESC,
            ]);

        if ($statusFilter === 'active') {
            $query->andWhere(['dc.status' => 1]);
        } elseif ($statusFilter === 'cancelled') {
            $query->andWhere(['dc.status' => 2]);
        }

        if ($dcNo = Yii::$app->request->get('dc_no')) {
            $query->andFilterWhere(['dc.dc_no' => $dcNo]);
        }
        if ($clientName = Yii::$app->request->get('client_name')) {
            $query->andFilterWhere(['like', 'client.client_name', $clientName]);
        }
        if ($companyName = Yii::$app->request->get('company_name')) {
            $query->andFilterWhere(['like', 'client.company_name', $companyName]);
        }
        $dcType = (int) Yii::$app->request->get('dc_type', 0);
        if (in_array($dcType, [1, 2, 3], true)) {
            $query->andWhere(['dc.dc_type' => $dcType]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 15],
        ]);

        [$clientOptions, $companyOptions] = $this->buildClientCompanyOptions();

        return $this->render('index', [
            'dataProvider'   => $dataProvider,
            'clientOptions'  => $clientOptions,
            'companyOptions' => $companyOptions,
        ]);
    }

    /** Build {client_name=>client_name} and {company_name=>company_name} option maps for Select2 filters. */
    private function buildClientCompanyOptions(): array
    {
        $rows = ClientModel::find()
            ->select(['client_name', 'company_name'])
            ->where(['status' => 1])
            ->orderBy('client_name')
            ->asArray()->all();
        $clients = $companies = [];
        foreach ($rows as $r) {
            $cn = trim((string) ($r['client_name']  ?? ''));
            $co = trim((string) ($r['company_name'] ?? ''));
            if ($cn !== '') { $clients[$cn] = $cn; }
            if ($co !== '') { $companies[$co] = $co; }
        }
        ksort($companies);
        return [$clients, $companies];
    }

    /**
     * Create a DC. Optional `?id=<client_id>` pre-fills from the client record.
     * Two submit actions (matches delivery):
     *   action=preview  → save with status=3, render challan-pdf with preview=1
     *   action=submit   → save with status=1 + dc_no, redirect to create with auto-download
     */
    public function actionCreate()
    {
        $clientId = (int) Yii::$app->request->get('id', 0);
        $client   = $clientId ? ClientModel::find()->where(['id' => $clientId, 'status' => 1])->one() : null;
        if ($clientId && !$client) {
            throw new NotFoundHttpException('Client not found.');
        }

        $dc       = new DcDetail();
        $delivery = new DeliveryDetail();
        $document = new DcDocumentFile();

        if ($client) {
            $dc->client_id            = $client->id;
            $delivery->consern_name   = $client->client_name;
            $delivery->address        = $client->address;
            $delivery->gst_no         = $client->gst_no ?? '';
            $delivery->contact_no     = $client->phone;
            $delivery->alt_contact_no = $client->alt_phone ?? '';
        }

        $dc->dc_no   = $this->getNextAvailableDcNumber();
        $dc->dc_date = date('Y-m-d');

        if (Yii::$app->request->isPost) {
            $action = (string) Yii::$app->request->post('action', 'submit');
            $tx = DcDetail::getDb()->beginTransaction();
            try {
                $dc->load(Yii::$app->request->post());
                $delivery->load(Yii::$app->request->post());
                $document->load(Yii::$app->request->post());

                $dc->dc_created_by    = (string) (Yii::$app->user->identity->username ?? '');
                $dc->dc_created_by_id = (int) (Yii::$app->user->identity->user_id ?? 0);
                $dc->status           = $action === 'submit' ? 1 : 3;
                if ($action !== 'submit') {
                    $dc->dc_no = null;
                }

                if ($action === 'submit') {
                    if (!$this->saveDcWithGeneratedNumber($dc)) {
                        throw new \RuntimeException($this->formatModelErrors($dc));
                    }
                } elseif (!$dc->save()) {
                    throw new \RuntimeException($this->formatModelErrors($dc));
                }

                $this->saveDcProducts($dc->id, Yii::$app->request->post('ProductDetails', []));

                $delivery->dc_id = $dc->id;
                if (!$delivery->save()) {
                    throw new \RuntimeException($this->formatModelErrors($delivery));
                }

                $document->dc_id = $dc->id;
                $this->handleDocumentUploads($document);
                if (!empty($document->quotation_no) && !$document->save()) {
                    throw new \RuntimeException($this->formatModelErrors($document));
                }

                $tx->commit();

                if ($action === 'preview') {
                    // Render the challan HTML inline — user clicks "Download DC" to finalise.
                    return $this->render('challan-pdf', [
                        'dc'      => $this->getDcPreviewData($dc->id),
                        'preview' => 1,
                    ]);
                }

                // Final submit: redirect back with download_id so the page auto-downloads.
                Yii::$app->session->setFlash('success', 'DC submitted successfully');
                return $this->redirect([
                    'create',
                    'id'              => $client ? $client->id : ($dc->client_id ?? null),
                    'download_id'     => $dc->id,
                    'download_submit' => 0,
                ]);
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::$app->session->setFlash('error', $e->getMessage());
                $dc->dc_no = $this->getNextAvailableDcNumber();
            }
        }

        return $this->render('create', [
            'dc' => $dc,
            'delivery' => $delivery,
            'document' => $document,
            'client' => $client,
            'productOptions' => $this->productOptions(),
            'existingProducts' => [],
            'isEdit' => false,
            'displayDcNo' => $dc->dc_no,
        ]);
    }

    /** Update an existing DC. Same two-action behaviour as Create. */
    public function actionUpdate(int $id)
    {
        $dc = DcDetail::find()->where(['id' => $id])->andWhere(['!=', 'status', 0])
                              ->with(['client', 'delivery', 'documents', 'dcProducts.product'])
                              ->one();
        if (!$dc) {
            throw new NotFoundHttpException('DC not found.');
        }

        $client   = $dc->client;
        $delivery = $dc->delivery ?: new DeliveryDetail(['dc_id' => $dc->id]);
        $document = $dc->documents ?: new DcDocumentFile(['dc_id' => $dc->id]);

        if (Yii::$app->request->isPost) {
            $action = (string) Yii::$app->request->post('action', 'submit');
            $tx = DcDetail::getDb()->beginTransaction();
            $existing = [
                'quotation_path' => $document->quotation_path,
                'invoice_path'   => $document->invoice_path,
                'po_path'        => $document->po_path,
            ];
            try {
                $dc->load(Yii::$app->request->post());
                $delivery->load(Yii::$app->request->post());
                $document->load(Yii::$app->request->post());

                foreach ($existing as $k => $v) {
                    if (empty($document->$k)) {
                        $document->$k = $v;
                    }
                }

                if ($action === 'preview' && (int) $dc->status !== 1) {
                    $dc->status = 3;
                } else {
                    $dc->status = 1;
                }
                if (!$dc->save()) {
                    throw new \RuntimeException($this->formatModelErrors($dc));
                }

                // Replace product rows each save (matches delivery).
                DcProduct::deleteAll(['dc_id' => $dc->id]);
                $this->saveDcProducts($dc->id, Yii::$app->request->post('ProductDetails', []));

                $delivery->dc_id = $dc->id;
                if (!$delivery->save()) {
                    throw new \RuntimeException($this->formatModelErrors($delivery));
                }

                $document->dc_id = $dc->id;
                $this->handleDocumentUploads($document);
                if (!empty($document->quotation_no) && !$document->save()) {
                    throw new \RuntimeException($this->formatModelErrors($document));
                }

                $tx->commit();

                if ($action === 'preview') {
                    return $this->render('challan-pdf', [
                        'dc'      => $this->getDcPreviewData($dc->id),
                        'preview' => 1,
                    ]);
                }

                Yii::$app->session->setFlash('success', 'DC submitted successfully');
                return $this->redirect([
                    'create',
                    'id'              => $client->id ?? null,
                    'download_id'     => $dc->id,
                    'download_submit' => empty($dc->dc_no) ? 1 : 0,
                ]);
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }

        return $this->render('update', [
            'dc' => $dc,
            'delivery' => $delivery,
            'document' => $document,
            'client' => $client,
            'productOptions' => $this->productOptions(),
            'existingProducts' => $this->buildExistingProductRows($dc),
            'isEdit' => true,
            'displayDcNo' => $dc->dc_no ?: $this->getNextAvailableDcNumber(),
        ]);
    }

    /** Read-only detail page. */
    public function actionView(int $id): string
    {
        $dc = DcDetail::find()
            ->alias('t')
            ->where(['t.id' => $id])
            ->andWhere(['!=', 't.status', 0])
            ->with(['client', 'delivery', 'documents', 'dcProducts.product'])
            ->asArray()
            ->one();
        if (!$dc) {
            throw new NotFoundHttpException('DC not found.');
        }
        return $this->render('view', ['model' => $dc]);
    }

    /**
     * Generate and download the challan PDF.
     *
     * NO STRICT RETURN TYPE — mpdf's Output() with 'D' destination ends the script
     * with a void return, and a strict `: string` return type turns the download
     * into a corrupt half-written file. This mirrors delivery's signature exactly.
     *
     * GET ?id=<dc_id>&submit=1   — if submit=1 and DC has no dc_no, assign one first.
     */
    public function actionChallanPdf(int $id)
    {
        $submit = (int) Yii::$app->request->get('submit', 0);

        $dc = DcDetail::findOne($id);
        if (!$dc) {
            throw new NotFoundHttpException('DC not found.');
        }

        $dc->status = 1;
        if ($submit === 1 && empty($dc->dc_no)) {
            if (!$this->saveDcWithGeneratedNumber($dc)) {
                Yii::$app->session->setFlash('error', $this->formatModelErrors($dc));
                return $this->redirect(['update', 'id' => $dc->id]);
            }
        } elseif ($dc->save(false) === false) {
            Yii::$app->session->setFlash('error', 'Unable to finalise the DC.');
            return $this->redirect(['update', 'id' => $dc->id]);
        }

        $data = DcDetail::find()
            ->alias('t')
            ->where(['t.id' => $id, 't.status' => 1])
            ->with(['client', 'delivery', 'documents', 'dcProducts.product'])
            ->asArray()
            ->one();

        $html    = $this->renderPartial('challan-pdf', ['dc' => $data, 'preview' => 0]);
        $dcNo    = $dc->dc_no;
        $company = preg_replace('/[^A-Za-z0-9\-]/', '_', $dc->client->company_name ?? 'Client');
        $fileName = "DC_{$dcNo}_{$company}.pdf";

        $tempDir = Yii::getAlias('@runtime/mpdf');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        $mpdf = new Mpdf([
            'mode'    => 'utf-8',
            'tempDir' => $tempDir,
            'format'  => 'A4',
        ]);
        $mpdf->WriteHTML($html);

        // Hand the PDF bytes to Yii's Response so headers don't collide with mpdf's own.
        $pdfContent = $mpdf->Output($fileName, Destination::STRING_RETURN);
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/pdf');
        Yii::$app->response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        return $pdfContent;
    }

    /** Cancel a DC (soft delete with reason). */
    public function actionCancel(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;
            return ['success' => false, 'message' => 'Invalid request method.'];
        }
        $id     = (int) Yii::$app->request->post('id');
        $reason = trim((string) Yii::$app->request->post('reason', ''));
        if ($id <= 0)       return ['success' => false, 'message' => 'Invalid DC selected.'];
        if ($reason === '') return ['success' => false, 'message' => 'Reason cannot be empty.'];

        $dc = DcDetail::findOne($id);
        if (!$dc)                    return ['success' => false, 'message' => 'No record found.'];
        if ((int)$dc->status === 2)  return ['success' => false, 'message' => 'DC is already cancelled.'];

        $dc->status        = 2;
        $dc->cancel_reason = $reason;
        if (!$dc->save(false)) {
            return ['success' => false, 'message' => $this->formatModelErrors($dc)];
        }
        return ['success' => true, 'message' => 'DC cancelled.', 'id' => $id];
    }

    public function actionSaveReturn(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id          = (int) Yii::$app->request->post('id');
        $date        = (string) Yii::$app->request->post('date', '');
        $description = (string) Yii::$app->request->post('description', '');

        $dc = DcDetail::findOne($id);
        if (!$dc) {
            return ['success' => false, 'message' => 'DC not found.'];
        }
        if ($date === '') {
            return ['success' => false, 'message' => 'Return date is required.'];
        }
        if (!empty($dc->dc_date)) {
            $dcDay     = substr((string) $dc->dc_date, 0, 10);
            $returnDay = substr($date, 0, 10);
            if ($returnDay !== '' && $dcDay !== '' && $returnDay < $dcDay) {
                return ['success' => false, 'message' => 'Return date cannot be earlier than the DC date (' . $dcDay . ').'];
            }
        }
        $dc->dc_returned_date      = $date;
        $dc->dc_return_description = $description;
        return $dc->save(false)
            ? ['success' => true]
            : ['success' => false, 'message' => $this->formatModelErrors($dc)];
    }

    public function actionGetNextDcNo(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ['success' => true, 'dc_no' => $this->getNextAvailableDcNumber()];
    }

    /**
     * AJAX: fetch a client's details so the DC form can auto-fill Client Name,
     * Company, GST, Address, and the Delivery & Logistics section when the
     * Client Select2 changes.
     */
    public function actionGetClientInfo(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = (int) (Yii::$app->request->post('id') ?: Yii::$app->request->get('id'));
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid client.'];
        }
        $c = ClientModel::findOne(['id' => $id, 'status' => 1]);
        if (!$c) {
            return ['success' => false, 'message' => 'Client not found.'];
        }
        return [
            'success' => true,
            'client'  => [
                'id'           => (int) $c->id,
                'client_name'  => (string) ($c->client_name  ?? ''),
                'company_name' => (string) ($c->company_name ?? ''),
                'gst_no'       => (string) ($c->gst_no       ?? ''),
                'address'      => (string) ($c->address      ?? ''),
                'phone'        => (string) ($c->phone        ?? ''),
                'alt_phone'    => (string) ($c->alt_phone    ?? ''),
                'email_id'     => (string) ($c->email_id     ?? ''),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────── helpers ──

    private function productOptions(): array
    {
        return ArrayHelper::map(
            DeliveryProduct::find()->where(['status' => 1])->orderBy(['item_name' => SORT_ASC])->asArray()->all(),
            'id', 'item_name'
        );
    }

    private function getDcPreviewData(int $dcId): ?array
    {
        return DcDetail::find()
            ->alias('t')
            ->where(['t.id' => $dcId])
            ->andWhere(['!=', 't.status', 0])
            ->with(['client', 'delivery', 'documents', 'dcProducts.product'])
            ->asArray()
            ->one();
    }

    private function buildExistingProductRows(DcDetail $dc): array
    {
        $rows = [];
        foreach ($dc->dcProducts as $i => $dp) {
            if ((int)$dp->status === -1) continue;
            $rows[] = [
                'index'     => $i,
                'id'        => $dp->product_id,
                'item_name' => $dp->product->item_name ?? '',
                'hsn_code'  => $dp->product->hsn_code  ?? '',
                'units'     => $dp->product->units     ?? '',
                'quantity'  => $dp->quantity,
                'serial_no' => $dp->serial_nos,
            ];
        }
        return $rows;
    }

    private function saveDcProducts(int $dcId, array $rows): void
    {
        foreach ($rows as $row) {
            $pid = (int) ($row['id']       ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            $sn  = trim((string) ($row['serial_no'] ?? ''));
            if ($pid <= 0 || $qty <= 0 || $sn === '') {
                continue;
            }
            $dp = new DcProduct([
                'dc_id'      => $dcId,
                'product_id' => $pid,
                'quantity'   => $qty,
                'serial_nos' => $sn,
                'status'     => 1,
            ]);
            if (!$dp->save()) {
                throw new \RuntimeException($this->formatModelErrors($dp));
            }
        }
    }

    private function handleDocumentUploads(DcDocumentFile $doc): void
    {
        $fields = [
            'quotation_path' => ['DcDocumentFile[quotation_path]', 'quotation_no', 'Quotation'],
            'invoice_path'   => ['DcDocumentFile[invoice_path]',   'invoice_no',   'Invoice'],
            'po_path'        => ['DcDocumentFile[po_path]',        'po_no',        'PO'],
        ];

        // Pass 1: validate every file/number pair before touching disk so a
        // failure in a later pair doesn't leave an earlier file orphaned.
        $pending = [];
        foreach ($fields as $attr => [$inputName, $numAttr, $label]) {
            $f = UploadedFile::getInstanceByName($inputName);
            if (!$f) continue;
            if (empty(trim((string) $doc->$numAttr))) {
                throw new \RuntimeException("$label Number is required when $label File is uploaded");
            }
            $pending[$attr] = $f;
        }

        if (!$pending) return;

        $uploadDir = Yii::getAlias('@webroot/uploads/dc_documents/');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        // Pass 2: persist files to disk.
        foreach ($pending as $attr => $f) {
            $clean = preg_replace('/[^a-zA-Z0-9._-]/', '', $f->baseName);
            $name  = time() . '_' . $clean . '.' . $f->extension;
            $path  = $uploadDir . $name;
            if ($f->saveAs($path)) {
                $doc->$attr = 'uploads/dc_documents/' . $name;
            }
        }
    }

    private function saveDcWithGeneratedNumber(DcDetail $dc, int $maxAttempts = 10): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $dc->dc_no = $this->getNextAvailableDcNumber();
            try {
                if ($dc->save(false)) {
                    return true;
                }
            } catch (IntegrityException $e) {
                continue;
            }
        }
        $dc->addError('dc_no', 'Unable to generate a unique DC number — please retry.');
        return false;
    }

    private function getNextAvailableDcNumber(): int
    {
        $dcNo = (int) DcDetail::find()->max('dc_no');
        do {
            $dcNo++;
        } while (DcDetail::find()->where(['dc_no' => $dcNo])->exists());
        return max($dcNo, 1);
    }

    private function formatModelErrors($model): string
    {
        $out = [];
        foreach ($model->getErrors() as $errs) {
            foreach ($errs as $e) {
                $out[] = $e;
            }
        }
        return $out ? implode(' ', $out) : 'Something went wrong.';
    }

    // ────────────────────────────────────────────────── Inward DC feature ──

    /** Inward DC listing — rows where type=2 (excluded from the normal DC list). */
    public function actionInwardIndex(): string
    {
        $query = DcDetail::find()
            ->alias('dc')
            ->where(['dc.type' => 2])
            ->andWhere(['!=', 'dc.status', 0])
            ->joinWith('client client')
            ->orderBy(['dc.id' => SORT_DESC]);

        if ($no = Yii::$app->request->get('inward_dc_no')) {
            $query->andFilterWhere(['like', 'dc.inward_dc_no', $no]);
        }
        if ($clientName = Yii::$app->request->get('client_name')) {
            $query->andFilterWhere(['like', 'client.client_name', $clientName]);
        }
        if ($companyName = Yii::$app->request->get('company_name')) {
            $query->andFilterWhere(['like', 'client.company_name', $companyName]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 15],
        ]);

        [$clientOptions, $companyOptions] = $this->buildClientCompanyOptions();

        return $this->render('inward-index', [
            'dataProvider'   => $dataProvider,
            'clientOptions'  => $clientOptions,
            'companyOptions' => $companyOptions,
        ]);
    }

    /**
     * Create an Inward DC. Requires ?id=<client_id>.
     *
     * Stores: client_id, inward_dc_no (auto), ewbc_no, dc_date (today),
     * dc_return_date (expected return), type=2, status=1, plus products.
     */
    public function actionCreateInward()
    {
        $clientId = (int) Yii::$app->request->get('id', 0);
        $client   = $clientId ? ClientModel::find()->where(['id' => $clientId, 'status' => 1])->one() : null;
        if ($clientId && !$client) {
            throw new NotFoundHttpException('Client not found.');
        }

        $dc = new DcDetail();
        $dc->type             = 2;
        $dc->dc_type          = 1; // returnable-style, since inward expects return
        $dc->dc_delivery_type = 1;
        $dc->dc_date          = date('Y-m-d');
        if ($client) {
            $dc->client_id = $client->id;
        }

        if (Yii::$app->request->isPost) {
            $tx = DcDetail::getDb()->beginTransaction();
            try {
                $dc->load(Yii::$app->request->post());
                $dc->type             = 2;
                $dc->dc_type          = 1;
                $dc->dc_delivery_type = 1;
                $dc->dc_no            = null;
                $dc->status           = 1;
                $dc->dc_created_by    = (string) (Yii::$app->user->identity->username ?? '');
                $dc->dc_created_by_id = (int) (Yii::$app->user->identity->user_id ?? 0);

                $rows = Yii::$app->request->post('ProductDetails', []);
                $hasRow = false;
                foreach ($rows as $r) {
                    if ((int)($r['id'] ?? 0) > 0 && (int)($r['quantity'] ?? 0) > 0 && trim((string)($r['serial_no'] ?? '')) !== '') {
                        $hasRow = true;
                        break;
                    }
                }
                if (!$hasRow) {
                    throw new \RuntimeException('Please add at least one product row with serial number and quantity.');
                }

                if (!$dc->save()) {
                    throw new \RuntimeException($this->formatModelErrors($dc));
                }

                $this->saveDcProducts($dc->id, $rows);

                $tx->commit();
                Yii::$app->session->setFlash('success', 'Inward DC created successfully.');
                return $this->redirect(['inward-index']);
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }

        return $this->render('create-inward', [
            'dc'              => $dc,
            'client'          => $client,
            'productOptions'  => $this->productOptions(),
            'existingProducts' => [],
        ]);
    }

    /** View an Inward DC (read-only). */
    public function actionInwardView(int $id): string
    {
        $dc = DcDetail::find()
            ->alias('t')
            ->where(['t.id' => $id, 't.type' => 2])
            ->andWhere(['!=', 't.status', 0])
            ->with(['client', 'dcProducts.product'])
            ->asArray()
            ->one();
        if (!$dc) {
            throw new NotFoundHttpException('Inward DC not found.');
        }
        return $this->render('inward-view', ['model' => $dc]);
    }

    /**
     * Add Return form — convert an Inward DC into an outgoing (type=1) DC.
     *
     * Mirrors actionCreate's two-action behaviour:
     *   action=preview → save with status=3 (draft, no dc_no) and render challan-pdf inline.
     *                    User can then click "Save & Download" to finalise via actionChallanPdf?submit=1.
     *   action=submit  → save final (status=1 + generated dc_no) and redirect with download_id
     *                    so the page auto-downloads the challan.
     */
    public function actionAddReturn(int $id)
    {
        $inward = DcDetail::find()
            ->where(['id' => $id, 'type' => 2])
            ->andWhere(['!=', 'status', 0])
            ->with(['client', 'dcProducts.product'])
            ->one();
        if (!$inward) {
            throw new NotFoundHttpException('Inward DC not found.');
        }
        $client = $inward->client;

        // Build the outgoing (type=1) models.
        $dc                   = new DcDetail();
        $dc->scenario         = 'return_outbound';
        $delivery             = new DeliveryDetail();
        $document             = new DcDocumentFile();
        $document->scenario   = 'return';

        $dc->client_id        = $inward->client_id;
        $dc->type             = 1;
        $dc->dc_type          = 3;
        $dc->dc_delivery_type = 1;
        $dc->dc_date          = date('Y-m-d');
        $dc->inward_dc_no     = $inward->inward_dc_no;
        $dc->inward_dc_id     = $inward->id;
        $dc->dc_no            = $this->getNextAvailableDcNumber();

        if ($client) {
            $delivery->consern_name   = $client->client_name;
            $delivery->address        = $client->address;
            $delivery->gst_no         = $client->gst_no ?? '';
            $delivery->contact_no     = $client->phone;
            $delivery->alt_contact_no = $client->alt_phone ?? '';
        }

        // Prefill products from the inward DC.
        $existingProducts = [];
        foreach ($inward->dcProducts as $i => $dp) {
            if ((int)$dp->status === -1) continue;
            $existingProducts[] = [
                'index'     => $i,
                'id'        => $dp->product_id,
                'item_name' => $dp->product->item_name ?? '',
                'hsn_code'  => $dp->product->hsn_code  ?? '',
                'units'     => $dp->product->units     ?? '',
                'quantity'  => $dp->quantity,
                'serial_no' => $dp->serial_nos,
            ];
        }

        if (Yii::$app->request->isPost) {
            $action = (string) Yii::$app->request->post('action', 'submit');
            $tx = DcDetail::getDb()->beginTransaction();
            try {
                $dc->load(Yii::$app->request->post());
                $delivery->load(Yii::$app->request->post());
                $document->load(Yii::$app->request->post());

                // Lock the server-controlled fields regardless of form input.
                $dc->type             = 1;
                $dc->dc_type          = 3;
                $dc->inward_dc_no     = $inward->inward_dc_no;
                $dc->inward_dc_id     = $inward->id;
                $dc->client_id        = $inward->client_id;
                $dc->dc_created_by    = (string) (Yii::$app->user->identity->username ?? '');
                $dc->dc_created_by_id = (int) (Yii::$app->user->identity->user_id ?? 0);
                $dc->status           = $action === 'submit' ? 1 : 3;
                if ($action !== 'submit') {
                    $dc->dc_no = null;
                }

                if ($action === 'submit') {
                    if (!$this->saveDcWithGeneratedNumber($dc)) {
                        throw new \RuntimeException($this->formatModelErrors($dc));
                    }
                } elseif (!$dc->save()) {
                    throw new \RuntimeException($this->formatModelErrors($dc));
                }

                $this->saveDcProducts($dc->id, Yii::$app->request->post('ProductDetails', []));

                $delivery->dc_id = $dc->id;
                if (!$delivery->save()) {
                    throw new \RuntimeException($this->formatModelErrors($delivery));
                }

                $document->dc_id = $dc->id;
                $this->handleDocumentUploads($document);
                $hasAnyDoc = !empty($document->quotation_no) || !empty($document->invoice_no) || !empty($document->po_no);
                if ($hasAnyDoc && !$document->save()) {
                    throw new \RuntimeException($this->formatModelErrors($document));
                }

                $tx->commit();

                if ($action === 'preview') {
                    return $this->render('challan-pdf', [
                        'dc'      => $this->getDcPreviewData($dc->id),
                        'preview' => 1,
                    ]);
                }

                Yii::$app->session->setFlash('success', 'Return DC created successfully.');
                return $this->redirect([
                    'add-return',
                    'id'              => $inward->id,
                    'download_id'     => $dc->id,
                    'download_submit' => 0,
                ]);
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::$app->session->setFlash('error', $e->getMessage());
                $dc->dc_no = $this->getNextAvailableDcNumber();
            }
        }

        return $this->render('add-return', [
            'dc'               => $dc,
            'inward'           => $inward,
            'delivery'         => $delivery,
            'document'         => $document,
            'client'           => $client,
            'productOptions'   => $this->productOptions(),
            'existingProducts' => $existingProducts,
            'displayDcNo'      => $dc->dc_no,
        ]);
    }

    /**
     * Edit an existing return DC (type=1 with inward_dc_no set).
     * Mirrors actionAddReturn but loads an existing row instead of creating a new one.
     */
    public function actionUpdateReturn(int $id)
    {
        $dc = DcDetail::find()
            ->where(['id' => $id, 'type' => 1])
            ->andWhere(['not', ['inward_dc_no' => null]])
            ->andWhere(['!=', 'status', 0])
            ->with(['client', 'delivery', 'documents', 'dcProducts.product'])
            ->one();
        if (!$dc) {
            throw new NotFoundHttpException('Return DC not found.');
        }
        $dc->scenario = 'return_outbound';

        $inwardQuery = DcDetail::find()->where(['type' => 2])->andWhere(['!=', 'status', 0])->with(['client']);
        $inward = $dc->inward_dc_id
            ? (clone $inwardQuery)->andWhere(['id' => $dc->inward_dc_id])->one()
            : (clone $inwardQuery)->andWhere(['inward_dc_no' => $dc->inward_dc_no, 'client_id' => $dc->client_id])->one();
        if (!$inward) {
            throw new NotFoundHttpException('Source inward DC not found.');
        }

        $client   = $inward->client;
        $delivery = $dc->delivery  ?: new DeliveryDetail(['dc_id' => $dc->id]);
        $document = $dc->documents ?: new DcDocumentFile(['dc_id' => $dc->id]);
        $document->scenario = 'return';

        $existingProducts = [];
        foreach ($dc->dcProducts as $i => $dp) {
            if ((int)$dp->status === -1) continue;
            $existingProducts[] = [
                'index'     => $i,
                'id'        => $dp->product_id,
                'item_name' => $dp->product->item_name ?? '',
                'hsn_code'  => $dp->product->hsn_code  ?? '',
                'units'     => $dp->product->units     ?? '',
                'quantity'  => $dp->quantity,
                'serial_no' => $dp->serial_nos,
            ];
        }

        if (Yii::$app->request->isPost) {
            $action = (string) Yii::$app->request->post('action', 'submit');
            $tx = DcDetail::getDb()->beginTransaction();
            $existing = [
                'quotation_path' => $document->quotation_path,
                'invoice_path'   => $document->invoice_path,
                'po_path'        => $document->po_path,
            ];
            try {
                $dc->load(Yii::$app->request->post());
                $delivery->load(Yii::$app->request->post());
                $document->load(Yii::$app->request->post());

                foreach ($existing as $k => $v) {
                    if (empty($document->$k)) {
                        $document->$k = $v;
                    }
                }

                // Lock server-controlled fields.
                $dc->type         = 1;
                $dc->dc_type      = 3;
                $dc->inward_dc_no = $inward->inward_dc_no;
                $dc->inward_dc_id = $inward->id;
                $dc->client_id    = $inward->client_id;

                if ($action === 'preview' && (int) $dc->status !== 1) {
                    $dc->status = 3;
                } else {
                    $dc->status = 1;
                }

                if ($action === 'submit' && empty($dc->dc_no)) {
                    if (!$this->saveDcWithGeneratedNumber($dc)) {
                        throw new \RuntimeException($this->formatModelErrors($dc));
                    }
                } elseif (!$dc->save()) {
                    throw new \RuntimeException($this->formatModelErrors($dc));
                }

                DcProduct::deleteAll(['dc_id' => $dc->id]);
                $this->saveDcProducts($dc->id, Yii::$app->request->post('ProductDetails', []));

                $delivery->dc_id = $dc->id;
                if (!$delivery->save()) {
                    throw new \RuntimeException($this->formatModelErrors($delivery));
                }

                $document->dc_id = $dc->id;
                $this->handleDocumentUploads($document);
                $hasAnyDoc = !empty($document->quotation_no) || !empty($document->invoice_no) || !empty($document->po_no);
                if ($hasAnyDoc && !$document->save()) {
                    throw new \RuntimeException($this->formatModelErrors($document));
                }

                $tx->commit();

                if ($action === 'preview') {
                    return $this->render('challan-pdf', [
                        'dc'      => $this->getDcPreviewData($dc->id),
                        'preview' => 1,
                    ]);
                }

                Yii::$app->session->setFlash('success', 'Return DC saved.');
                return $this->redirect([
                    'update-return',
                    'id'              => $dc->id,
                    'download_id'     => $dc->id,
                    'download_submit' => empty($dc->dc_no) ? 1 : 0,
                ]);
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }

        return $this->render('add-return', [
            'dc'               => $dc,
            'inward'           => $inward,
            'delivery'         => $delivery,
            'document'         => $document,
            'client'           => $client,
            'productOptions'   => $this->productOptions(),
            'existingProducts' => $existingProducts,
            'displayDcNo'      => $dc->dc_no ?: $this->getNextAvailableDcNumber(),
        ]);
    }

}
