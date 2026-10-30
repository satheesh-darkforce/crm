<?php

namespace backend\controllers;

use backend\models\AccLedger;
use backend\models\AccLedgerCategory;
use backend\models\InwardInvoice;
use backend\models\InwardInvoiceAttachments;
use backend\models\InwardInvoiceDetails;
use backend\models\Vendor;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class InwardInvoiceController extends Controller
{
    /* ── List ─────────────────────────────────────────────── */

    public function actionIndex(): string
    {
        $query = InwardInvoice::find()
            ->with('vendor')
            ->where(['inward_invoice.status' => 1])
            ->orderBy(['inward_invoice.id' => SORT_DESC]);

        if ($vendorId = Yii::$app->request->get('vendor_id')) {
            $query->andWhere(['vendor_id' => (int)$vendorId]);
        }
        if ($from = Yii::$app->request->get('from_date')) {
            $query->andWhere(['>=', 'invoice_date', $from]);
        }
        if ($to = Yii::$app->request->get('to_date')) {
            $query->andWhere(['<=', 'invoice_date', $to]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);
        $vendors = Vendor::find()->where(['status' => 1])->orderBy('company_name')->all();

        return $this->render('index', compact('dataProvider', 'vendors'));
    }

    /* ── View ─────────────────────────────────────────────── */

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);
        return $this->render('view', ['model' => $model]);
    }

    /* ── Create ───────────────────────────────────────────── */

    public function actionCreate(): string|Response
    {
        $vendorId = Yii::$app->request->get('vendor_id');

        // No vendor selected → show vendor-selection page
        if (!$vendorId) {
            $vendors = Vendor::find()->where(['status' => 1])->orderBy('company_name')->all();
            return $this->render('select-vendor', compact('vendors'));
        }

        $vendor = Vendor::findOne(['id' => (int)$vendorId, 'status' => 1]);
        if (!$vendor) {
            Yii::$app->session->setFlash('error', 'Invalid vendor.');
            return $this->redirect(['index']);
        }
        if ($vendor->vendor_type < 1) {
            Yii::$app->session->setFlash('error', 'Vendor Type not yet set. Please update vendor type and try again.');
            return $this->redirect(['/vendor/update', 'id' => $vendor->id]);
        }

        $model = new InwardInvoice();
        $model->status    = 1;
        $model->vendor_id = $vendor->id;

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $model->load($post, 'InwardInvoice');
            $model->vendor_id   = $vendor->id;
            $model->grand_total = floatval($post['grandtotal'] ?? 0);
            $model->sgst_amount = floatval($post['sgstamount'] ?? 0);
            $model->cgst_amount = floatval($post['cgstamount'] ?? 0);
            $model->igst_amount = floatval($post['igstamount'] ?? 0);
            $model->round_off   = floatval($post['roundoff'] ?? 0);
            $model->net_total   = floatval($post['nettotal'] ?? 0);
            $model->invoice_role = 2; // provisional
            $model->created_at   = date('Y-m-d H:i:s');
            $model->created_by   = Yii::$app->user->id;

            $this->loadPaymentFields($model, $post);

            if ($model->save()) {
                if ($this->saveDetails($model, $post) && $this->saveExpenses($model, $post)) {
                    $this->saveAttachments($model);
                    Yii::$app->session->setFlash('success', 'Inward invoice created successfully.');
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }

        return $this->render('create', array_merge(
            compact('model', 'vendor'),
            $this->formDropdowns(),
        ));
    }

    /* ── Update ───────────────────────────────────────────── */

    public function actionUpdate(int $id): string|Response
    {
        $model  = $this->findModel($id);
        $vendor = $model->vendor;

        if (!$vendor || $vendor->vendor_type < 1) {
            Yii::$app->session->setFlash('error', 'Vendor or vendor type is invalid.');
            return $this->redirect(['index']);
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $model->load($post, 'InwardInvoice');
            $model->vendor_id   = $vendor->id;
            $model->grand_total = floatval($post['grandtotal'] ?? 0);
            $model->sgst_amount = floatval($post['sgstamount'] ?? 0);
            $model->cgst_amount = floatval($post['cgstamount'] ?? 0);
            $model->igst_amount = floatval($post['igstamount'] ?? 0);
            $model->round_off   = floatval($post['roundoff'] ?? 0);
            $model->net_total   = floatval($post['nettotal'] ?? 0);

            $this->loadPaymentFields($model, $post);

            if ($model->save()) {
                // Soft-delete old details
                InwardInvoiceDetails::updateAll(['status' => 0], ['inward_invoice_id' => $model->id]);

                if ($this->saveDetails($model, $post) && $this->saveExpenses($model, $post)) {
                    $this->saveAttachments($model);
                    Yii::$app->session->setFlash('success', 'Inward invoice updated successfully.');
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }

        return $this->render('update', array_merge(
            compact('model', 'vendor'),
            $this->formDropdowns(),
        ));
    }

    /* ── Convert Provisional → Actual ─────────────────────── */

    public function actionConvertToInvoice(int $id): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST required']);
        }
        $model = InwardInvoice::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            return $this->asJson(['status' => 0, 'msg' => 'Invoice not found']);
        }
        if ($model->invoice_role != 2) {
            return $this->asJson(['status' => 0, 'msg' => 'Only provisional invoices can be converted']);
        }
        $model->invoice_role = 1;
        if ($model->save(false)) {
            return $this->asJson(['status' => 1, 'msg' => 'Converted to actual invoice']);
        }
        return $this->asJson(['status' => 0, 'msg' => 'Save failed']);
    }

    /* ── Delete (soft) ────────────────────────────────────── */

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST required.');
        }
        $model = $this->findModel($id);
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Invoice deleted.');
        return $this->redirect(['index']);
    }

    /* ── AJAX: Autocomplete ledger categories ─────────────── */

    public function actionAutocompleteLedgerCategory(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $term = trim(Yii::$app->request->get('term', ''));
        $result = [];
        if ($term !== '') {
            $rows = AccLedgerCategory::find()
                ->where(['status' => 1])
                ->andWhere(['not', ['parent_id' => null]])
                ->andWhere(['like', 'title', $term])
                ->orderBy('title')
                ->all();
            foreach ($rows as $r) {
                $result[] = ['label' => $r->title, 'value' => $r->title, 'id' => $r->id];
            }
        }
        return $result;
    }

    /* ── AJAX: Get ledgers for a category ─────────────────── */

    public function actionAutocompleteLedger(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $categoryId = trim(Yii::$app->request->post('categoryId', ''));
        if ($categoryId === '') {
            return ['status' => 0, 'msg' => 'No category'];
        }
        $rows = AccLedger::find()
            ->where(['status' => 1, 'category_id' => (int)$categoryId])
            ->orderBy('title')
            ->all();
        $data = [];
        foreach ($rows as $r) {
            $data[$r->id] = $r->title;
        }
        return ['status' => 1, 'data' => $data];
    }

    /* ── AJAX: Get payment ledgers by payment type ────────── */

    public function actionGetPaymentLedgers(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $categoryId = trim(Yii::$app->request->post('payment_type', ''));
        if ($categoryId === '') {
            return ['status' => 0, 'msg' => 'No type'];
        }
        $rows = AccLedger::find()
            ->where(['status' => 1, 'category_id' => (int)$categoryId])
            ->orderBy('title')
            ->all();
        $data = [];
        foreach ($rows as $r) {
            $data[$r->id] = $r->title;
        }
        return empty($data)
            ? ['status' => 0, 'msg' => 'No ledgers found']
            : ['status' => 1, 'data' => $data];
    }

    /* ── AJAX: Delete attachment ───────────────────────────── */

    public function actionDeleteAttachment(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return ['status' => 0, 'msg' => 'POST required'];
        }
        $att = InwardInvoiceAttachments::findOne(['id' => $id, 'status' => 1]);
        if ($att) {
            $att->status = 0;
            $att->save(false);
            return ['status' => 1, 'msg' => 'Deleted'];
        }
        return ['status' => 0, 'msg' => 'Not found'];
    }

    /* ── Private helpers ──────────────────────────────────── */

    private function findModel(int $id): InwardInvoice
    {
        $model = InwardInvoice::find()
            ->with(['vendor', 'lineItems', 'lineItems.ledger', 'expenses', 'expenses.ledger', 'attachments'])
            ->where(['inward_invoice.id' => $id, 'inward_invoice.status' => 1])
            ->one();
        if (!$model) {
            throw new NotFoundHttpException('Inward invoice not found.');
        }
        return $model;
    }

    private function loadPaymentFields(InwardInvoice $model, array $post): void
    {
        $inv = $post['InwardInvoice'] ?? [];
        $model->payment_status    = ($inv['payment_status'] ?? '') !== '' ? (int)$inv['payment_status'] : null;
        $model->payment_type      = !empty($inv['payment_type']) ? (int)$inv['payment_type'] : null;
        $model->payment_ledger_id = !empty($inv['payment_ledger_id']) ? (int)$inv['payment_ledger_id'] : null;
        $model->payment_amount    = !empty($inv['payment_amount']) ? floatval($inv['payment_amount']) : 0;
        $model->tds_amount        = !empty($inv['tds_amount']) ? floatval($inv['tds_amount']) : 0;
        $model->payment_date      = !empty($inv['payment_date']) ? $inv['payment_date'] : null;
    }

    private function saveDetails(InwardInvoice $model, array $post): bool
    {
        $particulars = $post['particular'] ?? [];
        foreach ($particulars as $i => $particular) {
            if (trim($particular) === '') continue;
            $d = new InwardInvoiceDetails();
            $d->inward_invoice_id = $model->id;
            $d->product       = trim($particular);
            $d->line_item_type = 1;
            $d->hsn           = trim($post['hsn'][$i] ?? '');
            $d->qty           = floatval($post['qty'][$i] ?? 0);
            $d->ledger_id     = !empty($post['ledger_ids'][$i]) ? (int)$post['ledger_ids'][$i] : null;
            $d->price_per_qty = floatval($post['price_per_qty'][$i] ?? 0);
            $d->item_total    = floatval($post['item_total'][$i] ?? 0);
            $d->sgst_percent  = floatval($post['sgst_percent'][$i] ?? 0);
            $d->sgst_amount   = floatval($post['sgst_amount'][$i] ?? 0);
            $d->cgst_percent  = floatval($post['cgst_percent'][$i] ?? 0);
            $d->cgst_amount   = floatval($post['cgst_amount'][$i] ?? 0);
            $d->igst_percent  = floatval($post['igst_percent'][$i] ?? 0);
            $d->igst_amount   = floatval($post['igst_amount'][$i] ?? 0);
            $d->total_amount  = floatval($post['total_amount'][$i] ?? 0);
            $d->status        = 1;
            if (!$d->save()) {
                InwardInvoiceDetails::deleteAll(['inward_invoice_id' => $model->id]);
                $model->delete();
                Yii::$app->session->setFlash('error', 'Failed to save line item: ' . implode(', ', $d->getFirstErrors()));
                return false;
            }
        }
        return true;
    }

    private function saveExpenses(InwardInvoice $model, array $post): bool
    {
        $titles = $post['expense_title'] ?? [];
        if (empty($titles)) return true;
        foreach ($titles as $i => $title) {
            if (trim($title) === '') continue;
            $d = new InwardInvoiceDetails();
            $d->inward_invoice_id = $model->id;
            $d->product        = trim($title);
            $d->line_item_type = 2;
            $d->ledger_id      = !empty($post['expense_ledger_id'][$i]) ? (int)$post['expense_ledger_id'][$i] : null;
            $d->item_total     = floatval($post['expense_amount'][$i] ?? 0);
            $d->total_amount   = $d->item_total;
            $d->qty            = 1;
            $d->status         = 1;
            if (!$d->save()) {
                InwardInvoiceDetails::deleteAll(['inward_invoice_id' => $model->id]);
                $model->delete();
                Yii::$app->session->setFlash('error', 'Failed to save expense: ' . implode(', ', $d->getFirstErrors()));
                return false;
            }
        }
        return true;
    }

    private function saveAttachments(InwardInvoice $model): void
    {
        $files = UploadedFile::getInstancesByName('attachments');
        if (empty($files)) return;

        // Store under the project-root uploads/ directory (root/uploads/inward_invoice_attachments),
        // consistent with every other module's attachments. The root `uploads` is a symlink to
        // backend/web/uploads, so files remain web-accessible at /uploads/... as before.
        $dir = dirname(Yii::getAlias('@backend')) . '/uploads/inward_invoice_attachments';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $i = 1;
        foreach ($files as $file) {
            $name = 'attachment_' . $model->id . '_' . $i . '.' . $file->extension;
            $path = '/uploads/inward_invoice_attachments/' . $name;
            if ($file->saveAs($dir . '/' . $name)) {
                $att = new InwardInvoiceAttachments();
                $att->inward_invoice_id = $model->id;
                $att->path   = $path;
                $att->status = 1;
                $att->save(false);
            }
            $i++;
        }
    }

    private function formDropdowns(): array
    {
        $ledgerCategories = AccLedgerCategory::find()
            ->where(['status' => 1])
            ->andWhere(['not', ['parent_id' => null]])
            ->orderBy('title')
            ->all();
        $paymentCategories = AccLedgerCategory::find()
            ->where(['status' => 1, 'is_payment_ledger' => 1])
            ->orderBy('title')
            ->all();
        return compact('ledgerCategories', 'paymentCategories');
    }
}
