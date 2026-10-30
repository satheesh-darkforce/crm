<?php

namespace backend\controllers;

use backend\models\ClientModel;
use backend\models\Domain;
use backend\models\DomainFollowUp;
use backend\models\DomainRenewalHistory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Transaction;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class DomainController extends Controller
{
    public function actionIndex(): string
    {
        $query = Domain::find()
            ->joinWith('client')
            ->where(['domains.status' => 1])
            ->orderBy(['expiry_date' => SORT_ASC]);

        // Filter tabs
        $tab = Yii::$app->request->get('tab');
        if ($tab === 'expiring') {
            $query->andWhere(['between', 'expiry_date', date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))]);
        } elseif ($tab === 'expired') {
            $query->andWhere(['<', 'expiry_date', date('Y-m-d')]);
        }

        // Column filters
        if ($clientId = Yii::$app->request->get('client_id')) {
            $query->andWhere(['domains.client_id' => (int)$clientId]);
        }
        if ($domainName = Yii::$app->request->get('domain_name')) {
            $query->andWhere(['like', 'domain_name', $domainName]);
        }
        if ($serviceProvider = Yii::$app->request->get('service_provider')) {
            $query->andWhere(['like', 'service_provider', $serviceProvider]);
        }
        if ($hostingServer = Yii::$app->request->get('hosting_server')) {
            $query->andWhere(['like', 'hosting_server', $hostingServer]);
        }
        if ($expiryFrom = Yii::$app->request->get('expiry_from')) {
            $query->andWhere(['>=', 'expiry_date', $expiryFrom]);
        }
        if ($expiryTo = Yii::$app->request->get('expiry_to')) {
            $query->andWhere(['<=', 'expiry_date', $expiryTo]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 50]]);

        // Stats
        $total      = Domain::find()->where(['status' => 1])->count();
        $expiring30 = Domain::find()->where(['status' => 1])->andWhere(['between', 'expiry_date', date('Y-m-d'), date('Y-m-d', strtotime('+30 days'))])->count();
        $expired    = Domain::find()->where(['status' => 1])->andWhere(['<', 'expiry_date', date('Y-m-d')])->count();

        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();

        return $this->render('index', compact('dataProvider', 'total', 'expiring30', 'expired', 'tab', 'clients'));
    }

    public function actionCreate(): string|Response
    {
        $model = new Domain();
        $model->domain_status = 1; // default: Live

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->created_at = date('Y-m-d H:i:s');
            $model->created_by = Yii::$app->user->id;
            $model->status     = 1;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Domain added successfully.');
                return $this->redirect(['index']);
            }
        }

        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        return $this->render('_form', compact('model', 'clients'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findDomain($id);
        $oldValues = $model->getAttributes();

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->updated_at = date('Y-m-d H:i:s');
            $model->updated_by = Yii::$app->user->id;

            if ($model->save()) {
                // Track changes in update_history
                $newValues = $model->getAttributes();
                $changes = array_diff_assoc($newValues, $oldValues);
                if ($changes) {
                    $changeLog = date('Y-m-d H:i:s') . ' | Updated by: ' . Yii::$app->user->identity->username . "\n";
                    foreach ($changes as $field => $newValue) {
                        $oldValue = $oldValues[$field] ?? 'N/A';
                        // ✅ Use ASCII-safe arrow instead of UTF-8 arrow (→)
                        $changeLog .= "  $field: '" . $oldValue . "' -> '" . $newValue . "'\n";
                    }
                    // ✅ Truncate history if too long to prevent database issues
                    $historyPrefix = $changeLog;
                    $historyExisting = $model->update_history ?? '';
                    $maxHistoryLength = 65000; // Leave room for other data
                    $model->update_history = substr($historyPrefix . $historyExisting, 0, $maxHistoryLength);
                    $model->save(false);
                }

                Yii::$app->session->setFlash('success', 'Domain updated successfully.');
                return $this->redirect(['index']);
            }
        }

        $clients = ClientModel::find()->where(['status' => 1])->orderBy('client_name')->all();
        return $this->render('_form', compact('model', 'clients'));
    }

    public function actionView(int $id): string
    {
        $model     = $this->findDomain($id);
        $history   = DomainRenewalHistory::find()
            ->where(['domain_id' => $id, 'status' => 1])
            ->with(['createdBy', 'updatedBy'])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        $followups = DomainFollowUp::find()
            ->where(['domain_id' => $id])
            ->with(['createdBy', 'domain'])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        $followupModel = new DomainFollowUp(['domain_id' => $id]);
        return $this->render('view', compact('model', 'history', 'followups', 'followupModel'));
    }

    /** Add a follow-up log entry for a domain (POST, AJAX-friendly) */
    public function actionAddFollowup(int $id): Response
    {
        $domain = $this->findDomain($id);
        $model  = new DomainFollowUp();
        $model->load(Yii::$app->request->post());
        $model->domain_id  = $domain->id;
        $model->created_at = date('Y-m-d H:i:s');
        $model->created_by = Yii::$app->user->id;

        if ($model->save()) {
            // Mirror Yii1 logic: update domain status based on follow-up status
            if ($model->followup_status == 1) {
                $domain->domain_status = 3; // "Need Follow"
            } elseif ($model->followup_status == 2) {
                $domain->domain_status = 1; // "Live"
            }
            $domain->save(false);
            Yii::$app->session->setFlash('success', 'Follow-up logged.');
        } else {
            Yii::$app->session->setFlash('error', 'Could not save follow-up: ' . implode(', ', \yii\helpers\ArrayHelper::getColumn($model->errors, 0)));
        }

        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST request required.');
        }
        $m = $this->findDomain($id);
        $m->status = 0;
        $m->save(false);
        Yii::$app->session->setFlash('success', 'Domain deleted.');
        return $this->redirect(['index']);
    }

    public function actionRenewal(?int $id = null): string|Response
    {
        if ($id === null) {
            return $this->redirect(['index']);
        }
        $domain = $this->findDomain($id);
        $model  = new DomainRenewalHistory();

        if ($model->load(Yii::$app->request->post())) {
            $model->domain_id      = $domain->id;
            $model->created_at     = date('Y-m-d H:i:s');
            $model->created_by     = Yii::$app->user->id;
            $model->update_history = '';

            if ($model->validate()) {
                // Handle file upload (validated before saveAs, matching Yii1 security checks)
                $file = UploadedFile::getInstanceByName('img');
                if ($file) {
                    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
                    $ext = strtolower($file->extension);
                    $maxSize = 2 * 1024 * 1024; // 2MB (matching Yii1 client-side limit)

                    if (!in_array($ext, $allowedExtensions, true)) {
                        Yii::$app->session->setFlash('error', 'File type not allowed. Allowed: ' . implode(', ', $allowedExtensions));
                        return $this->render('_renewal_form', compact('domain', 'model'));
                    }
                    if ($file->size > $maxSize) {
                        Yii::$app->session->setFlash('error', 'File too large. Maximum size is 2MB.');
                        return $this->render('_renewal_form', compact('domain', 'model'));
                    }

                    $uploadDir = Yii::getAlias('@webroot/uploads/domainrenewal');
                    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                        Yii::$app->session->setFlash('error', 'Upload directory is not writable. Please contact the administrator.');
                        return $this->render('_renewal_form', compact('domain', 'model'));
                    }
                    if (!is_writable($uploadDir)) {
                        Yii::$app->session->setFlash('error', 'Upload directory is not writable. Please contact the administrator.');
                        return $this->render('_renewal_form', compact('domain', 'model'));
                    }
                    $filename = time() . '_' . md5($file->baseName . uniqid()) . '.' . $ext;
                    if ($file->saveAs($uploadDir . '/' . $filename)) {
                        $model->invoice_attachment = '/uploads/domainrenewal/' . $filename;
                    }
                }

                if ($model->save(false)) {
                    // Push new expiry onto the domain (matches Yii1)
                    $domain->expiry_date = $model->expiry_date;
                    $domain->save(false);
                    Yii::$app->session->setFlash('success', 'Renewal recorded.');
                    return $this->redirect(['index']);
                }
            }
        }

        return $this->render('_renewal_form', compact('domain', 'model'));
    }

    public function actionRenewalHistory(int $id): string
    {
        $domain  = $this->findDomain($id);
        $history = DomainRenewalHistory::find()
            ->where(['domain_id' => $id, 'status' => 1])
            ->with(['createdBy', 'updatedBy'])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        return $this->render('_renewal_history', compact('domain', 'history'));
    }

    /** Delete/soft-delete a renewal history record */
    public function actionDeleteRenewalHistory(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST request required.');
        }
        $renewal = DomainRenewalHistory::findOne(['id' => $id, 'status' => 1]);
        if (!$renewal) {
            throw new NotFoundHttpException('Renewal record not found.');
        }

        $domainId = $renewal->domain_id;
        $renewal->status = 0; // Soft delete
        $renewal->save(false);

        Yii::$app->session->setFlash('success', 'Renewal record deleted.');
        return $this->redirect(['view', 'id' => $domainId]);
    }

    /** AJAX: change domain status inline */
    public function actionChangeStatus(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id     = (int)Yii::$app->request->post('id');
        $status = (int)Yii::$app->request->post('status');
        $m = Domain::findOne(['id' => $id, 'status' => 1]);
        if (!$m) return $this->asJson(['success' => false]);
        $m->domain_status = $status;
        $m->save(false);
        return $this->asJson(['success' => true]);
    }

    /** AJAX: get client details for popup */
    public function actionClientDetails(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $clientId = (int)Yii::$app->request->get('client_id');
        $client = ClientModel::findOne(['id' => $clientId, 'status' => 1]);

        if (!$client) {
            return $this->asJson(['success' => false, 'message' => 'Client not found.']);
        }

        return $this->asJson([
            'success' => true,
            'data' => [
                'name' => $client->client_name ?? '—',
                'contact_person' => $client->contact_person ?? '—',
                'phone' => $client->phone ?? '—',
                'email' => $client->email_id ?? '—',
                'gst' => $client->gst_no ?? '—',
                'address' => $client->address ?? '—',
            ]
        ]);
    }

    /** AJAX: get renewal details for popup */
    public function actionRenewalDetails(int $id): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $renewal = DomainRenewalHistory::findOne(['id' => $id, 'status' => 1]);

        if (!$renewal) {
            return $this->asJson(['success' => false, 'message' => 'Renewal record not found.']);
        }

        return $this->asJson([
            'success' => true,
            'data' => [
                'domain_name' => $renewal->domain->domain_name ?? '—',
                'amount_received' => $renewal->amount_received ? number_format($renewal->amount_received, 2) : '—',
                'amount_received_date' => $renewal->amount_received_date ? date('d M Y', strtotime($renewal->amount_received_date)) : '—',
                'expiry_date' => $renewal->expiry_date ? date('d M Y', strtotime($renewal->expiry_date)) : '—',
                'notes' => $renewal->notes ?? '—',
                'created_by' => $renewal->createdBy->username ?? '—',
                'created_at' => $renewal->created_at ? date('d M Y H:i', strtotime($renewal->created_at)) : '—',
                'has_attachment' => !empty($renewal->invoice_attachment),
                'attachment_url' => $renewal->invoice_attachment ? '/erp/maestro_erp_yii2/' . $renewal->invoice_attachment : null,
            ]
        ]);
    }

    /**
     * Export domains to Excel.
     */
    public function actionExportDomains(): Response
    {
        $query = Domain::find()->with('client')->where(['status' => 1]);

        if ($clientId = Yii::$app->request->get('client_id')) {
            $query->andWhere(['client_id' => (int)$clientId]);
        }
        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['like', 'domain_name', $search]);
        }
        $query->orderBy(['id' => SORT_DESC]);

        $domains = $query->all();

        $statusLabels = [1 => 'Live', 2 => 'Expired', 3 => 'Need Follow', 4 => 'Pending Payment', 5 => 'Pending Renewal'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $headers     = ['#', 'Domain Name', 'Client', 'Service Provider', 'Price', 'Purchase Date', 'Expiry Date', 'Username', 'Hosting Server', 'Status'];
        $sheet->fromArray($headers, null, 'A1');

        foreach ($sheet->getColumnIterator('A', 'J') as $col) {
            $sheet->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
        }

        $row = 2;
        foreach ($domains as $i => $d) {
            $sheet->fromArray([
                $i + 1,
                $d->domain_name,
                $d->client->client_name ?? '',
                $d->service_provider ?? '',
                $d->price ?? '',
                $d->date_of_purchase ?? '',
                $d->expiry_date ?? '',
                $d->domain_username ?? '',
                $d->hosting_server ?? '',
                $statusLabels[$d->domain_status] ?? $d->domain_status,
            ], null, 'A' . $row++);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return Yii::$app->response->sendContentAsFile(
            $content,
            'Domains_' . date('Y-m-d') . '.xlsx',
            ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Bulk import domains from Excel/CSV file
     */
    public function actionImportDomains(): string|Response
    {
        if (Yii::$app->request->isPost) {
            $file = UploadedFile::getInstanceByName('import_file');
            if (!$file) {
                Yii::$app->session->setFlash('error', 'Please select a file to import.');
                return $this->refresh();
            }

            try {
                $spreadsheet = IOFactory::load($file->tempName);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();

                if (empty($rows)) {
                    Yii::$app->session->setFlash('error', 'File is empty.');
                    return $this->refresh();
                }

                $headers = array_shift($rows); // Remove header row
                $imported = 0;
                $errors = [];

                // Start transaction for batch insert
                $transaction = Yii::$app->db->beginTransaction();

                try {
                    foreach ($rows as $rowNum => $row) {
                        if (empty(array_filter($row))) continue; // Skip empty rows

                        $domain = new Domain();
                        $domain->client_id = (int)($row[0] ?? 0);
                        $domain->domain_name = trim($row[1] ?? '');
                        $domain->price = (float)($row[2] ?? 0);
                        $domain->date_of_purchase = trim($row[3] ?? '');
                        $domain->service_provider = trim($row[4] ?? '');
                        $domain->domain_username = trim($row[5] ?? '');
                        $domain->domain_password = trim($row[6] ?? '');
                        $domain->hosting_server = trim($row[7] ?? '');
                        $domain->description = trim($row[8] ?? '');
                        $domain->expiry_date = trim($row[9] ?? '');
                        $domain->domain_status = 1; // Live by default
                        $domain->created_at = date('Y-m-d H:i:s');
                        $domain->created_by = Yii::$app->user->id;
                        $domain->status = 1;

                        if ($domain->save()) {
                            $imported++;
                        } else {
                            $errors[] = "Row " . ($rowNum + 2) . ": " . implode('; ', array_values($domain->getFirstErrors()));
                        }
                    }

                    if (count($errors) === 0) {
                        $transaction->commit();
                        Yii::$app->session->setFlash('success', "Successfully imported {$imported} domains.");
                        return $this->redirect(['index']);
                    } else {
                        $transaction->rollBack();
                        $errorMsg = implode("\n", array_slice($errors, 0, 10));
                        if (count($errors) > 10) {
                            $errorMsg .= "\n... and " . (count($errors) - 10) . " more errors";
                        }
                        Yii::$app->session->setFlash('error', "Import failed with " . count($errors) . " errors:\n" . $errorMsg);
                    }
                } catch (\Exception $e) {
                    $transaction->rollBack();
                    Yii::$app->session->setFlash('error', 'Import error: ' . $e->getMessage());
                }
            } catch (\Exception $e) {
                Yii::$app->session->setFlash('error', 'File read error: ' . $e->getMessage());
            }

            return $this->refresh();
        }

        return $this->render('_import_form');
    }

    private function findDomain(int $id): Domain
    {
        $m = Domain::findOne(['id' => $id, 'status' => 1]);
        if (!$m) throw new NotFoundHttpException('Domain not found.');
        return $m;
    }

}
