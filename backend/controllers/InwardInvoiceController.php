<?php

namespace backend\controllers;

use backend\assets\InvoiceAsset;
use backend\components\SafeUpload;
use backend\models\AccLedger;
use backend\models\AccLedgerCategory;
use backend\models\InwardInvoice;
use backend\models\InwardInvoiceAttachments;
use backend\models\InwardInvoiceDetails;
use backend\models\Vendor;
use common\components\UserAccess;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class InwardInvoiceController extends Controller
{
    const MAX_LINES = 200;

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'delete' => ['POST'], 'convert-to-invoice' => ['POST'], 'delete-attachment' => ['POST'],
                'autocomplete-ledger' => ['POST'], 'get-payment-ledgers' => ['POST'],
            ]],
        ]);
    }

    /** Full pages get the Invoices module stylesheet and script. */
    public function render($view, $params = []): string
    {
        InvoiceAsset::register($this->getView());
        return parent::render($view, $params);
    }

    /* ── List ─────────────────────────────────────────────── */

    public function actionIndex(): string
    {
        $f = $this->filters();
        $query = InwardInvoice::find()->alias('ii')->with('vendor')->where(['ii.status' => 1]);

        if ($f['vendor_id'] !== '')      { $query->andWhere(['ii.vendor_id' => (int)$f['vendor_id']]); }
        if ($f['from_date'] !== '')      { $query->andWhere(['>=', 'ii.invoice_date', $f['from_date']]); }
        if ($f['to_date'] !== '')        { $query->andWhere(['<=', 'ii.invoice_date', $f['to_date']]); }
        if ($f['q'] !== '')              { $query->andWhere(['or', ['like', 'ii.invoice_number', $f['q']], ['like', 'ii.title', $f['q']]]); }
        if ($f['payment_status'] === 'none') {
            $query->andWhere(['ii.payment_status' => null]);
        } elseif ($f['payment_status'] !== '') {
            $query->andWhere(['ii.payment_status' => (int)$f['payment_status']]);
        }
        if ($f['invoice_role'] !== '')   { $query->andWhere(['ii.invoice_role' => (int)$f['invoice_role']]); }

        $netTotal = (float)(clone $query)->sum('ii.net_total');
        $dataProvider = new ActiveDataProvider([
            'query'      => $query->orderBy(['ii.invoice_date' => SORT_DESC, 'ii.id' => SORT_DESC]),
            'pagination' => ['pageSize' => 25],
        ]);
        $vendors = Vendor::find()->where(['status' => 1])->orderBy('company_name')->all();

        return $this->render('index', compact('dataProvider', 'vendors', 'f', 'netTotal'));
    }

    /* ── View ─────────────────────────────────────────────── */

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    /* ── Create ───────────────────────────────────────────── */

    public function actionCreate(): string|Response
    {
        $vendorId = Yii::$app->request->get('vendor_id');
        if ($vendorId === null || $vendorId === '') {
            $vendors = Vendor::find()->where(['status' => 1])->orderBy('company_name')->all();
            return $this->render('select-vendor', compact('vendors'));
        }

        $vendor = ctype_digit((string)$vendorId) ? Vendor::findOne(['id' => (int)$vendorId, 'status' => 1]) : null;
        if (!$vendor) {
            Yii::$app->session->setFlash('error', 'That vendor was not found. Pick one from the list.');
            return $this->redirect(['create']);
        }
        if ($redirect = $this->requireVendorType($vendor)) {
            return $redirect;
        }

        $model = new InwardInvoice([
            'status' => 1, 'vendor_id' => $vendor->id, 'invoice_role' => InwardInvoice::ROLE_PROVISIONAL,
            'invoice_date' => date('Y-m-d'), 'round_off' => 0,
        ]);
        [$lines, $expenses] = [[], []];

        if (Yii::$app->request->isPost) {
            $model->created_at = date('Y-m-d H:i:s');
            $model->created_by = Yii::$app->user->id;
            [$ok, $lines, $expenses] = $this->saveInvoice($model, $vendor);
            if ($ok) {
                Yii::$app->session->setFlash('success', 'Inward invoice ' . $model->invoice_number . ' saved.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', array_merge(compact('model', 'vendor', 'lines', 'expenses'), $this->formData()));
    }

    /* ── Update ───────────────────────────────────────────── */

    public function actionUpdate(int $id): string|Response
    {
        $model  = $this->findModel($id);
        $vendor = $model->vendor;
        if (!$vendor) {
            Yii::$app->session->setFlash('error', 'The vendor of this invoice was removed, so it cannot be edited.');
            return $this->redirect(['view', 'id' => $model->id]);
        }
        if ($redirect = $this->requireVendorType($vendor)) {
            return $redirect;
        }
        $lines    = $model->lineItems;
        $expenses = $model->expenses;

        if (Yii::$app->request->isPost) {
            [$ok, $lines, $expenses] = $this->saveInvoice($model, $vendor);
            if ($ok) {
                Yii::$app->session->setFlash('success', 'Inward invoice ' . $model->invoice_number . ' updated.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', array_merge(compact('model', 'vendor', 'lines', 'expenses'), $this->formData()));
    }

    /* ── Convert Provisional → Actual ─────────────────────── */

    public function actionConvertToInvoice(int $id): Response
    {
        $model = InwardInvoice::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            return $this->asJson(['success' => false, 'message' => 'This invoice no longer exists.']);
        }
        if ((int)$model->invoice_role !== InwardInvoice::ROLE_PROVISIONAL) {
            return $this->asJson(['success' => false, 'message' => 'It is already an actual invoice.']);
        }
        $model->updateAttributes(['invoice_role' => InwardInvoice::ROLE_ACTUAL]);
        return $this->asJson(['success' => true, 'message' => 'Marked as an actual invoice.']);
    }

    /* ── Delete (soft) ────────────────────────────────────── */

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $model->updateAttributes(['status' => 0]);
        Yii::$app->session->setFlash('success', 'Inward invoice ' . $model->invoice_number . ' removed.');
        return $this->redirect(['index']);
    }

    /**
     * The ledger and attachment endpoints are in every developer's default routes, so they
     * also need the right to create or edit inward invoices.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $utility = ['autocomplete-ledger-category', 'autocomplete-ledger', 'get-payment-ledgers', 'delete-attachment'];
        if (in_array($action->id, $utility, true)
            && !UserAccess::checkAccess('inward-invoice/create') && !UserAccess::checkAccess('inward-invoice/update')) {
            throw new ForbiddenHttpException('You do not have permission to change inward invoices.');
        }
        return true;
    }

    /* ── AJAX: ledgers (kept for existing permission routes) ─ */

    public function actionAutocompleteLedgerCategory(): Response
    {
        $term = trim((string)Yii::$app->request->get('term', ''));
        if ($term === '') {
            return $this->asJson([]);
        }
        $rows = AccLedgerCategory::find()->where(['status' => 1])->andWhere(['not', ['parent_id' => null]])
            ->andWhere(['like', 'title', $term])->orderBy('title')->limit(50)->all();
        return $this->asJson(array_map(fn($r) => ['label' => $r->title, 'value' => $r->title, 'id' => $r->id], $rows));
    }

    public function actionAutocompleteLedger(): Response
    {
        return $this->asJson($this->ledgersOf(Yii::$app->request->post('categoryId')));
    }

    public function actionGetPaymentLedgers(): Response
    {
        return $this->asJson($this->ledgersOf(Yii::$app->request->post('payment_type')));
    }

    /* ── AJAX: Delete attachment ───────────────────────────── */

    public function actionDeleteAttachment(int $id): Response
    {
        $att = InwardInvoiceAttachments::find()->alias('a')
            ->innerJoin(InwardInvoice::tableName() . ' ii', 'ii.id = a.inward_invoice_id AND ii.status = 1')
            ->where(['a.id' => $id, 'a.status' => 1])->one();
        if (!$att) {
            return $this->asJson(['success' => false, 'message' => 'That attachment was already removed.']);
        }
        $att->updateAttributes(['status' => 0]);
        return $this->asJson(['success' => true, 'message' => 'Attachment removed.']);
    }

    /* ── Private helpers ──────────────────────────────────── */

    /**
     * Read the posted invoice, lines and expenses, validate everything, then save in one transaction.
     *
     * @return array{0: bool, 1: InwardInvoiceDetails[], 2: InwardInvoiceDetails[]}
     */
    private function saveInvoice(InwardInvoice $model, Vendor $vendor): array
    {
        $req  = Yii::$app->request;
        $post = $req->post('InwardInvoice');
        $post = is_array($post) ? array_map(fn($v) => is_scalar($v) ? (string)$v : null, $post) : [];

        // Only form fields; totals, vendor, role and status never come from the browser.
        $model->setAttributes(array_intersect_key($post, array_flip([
            'invoice_number', 'invoice_date', 'title', 'round_off', 'payment_status', 'payment_type',
            'payment_ledger_id', 'payment_amount', 'tds_amount', 'payment_date', 'payment_remarks',
        ])), false);
        foreach (['round_off', 'payment_amount', 'tds_amount'] as $num) {
            if ($model->$num === '' || $model->$num === null) {
                $model->$num = 0;
            }
        }
        foreach (['payment_status', 'payment_type', 'payment_ledger_id', 'payment_date'] as $opt) {
            if ($model->$opt === '') {
                $model->$opt = null;
            }
        }
        $model->vendor_id = $vendor->id;

        [$lines, $expenses, $lineErrors] = $this->readLines($vendor, (array)$req->post('lines', []), (array)$req->post('expenses', []));
        $model->applyLines($lines, $expenses);

        $valid = $model->validate();
        if ($lineErrors) {
            // Totals are derived from the lines; their errors would only repeat the line messages.
            foreach (['grand_total', 'sgst_amount', 'cgst_amount', 'igst_amount', 'net_total'] as $derived) {
                $model->clearErrors($derived);
            }
        }
        foreach ($lineErrors as $msg) {
            $model->addError('lines', $msg);
            $valid = false;
        }
        if (!$valid) {
            return [false, $lines, $expenses];
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $model->save(false);
            InwardInvoiceDetails::updateAll(['status' => 0], ['inward_invoice_id' => $model->id, 'status' => 1]);
            foreach (array_merge($lines, $expenses) as $d) {
                $d->inward_invoice_id = $model->id;
                $d->status = 1;
                $d->save(false);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error($e, __METHOD__);
            $model->addError('lines', 'The invoice could not be saved. Nothing was changed — please try again.');
            return [false, $lines, $expenses];
        }

        // Files after the commit: a rejected file never loses the invoice.
        SafeUpload::flashRejected(SafeUpload::save('attachments', 'inward_invoice_attachments', 'inward_' . $model->id,
            function (string $path) use ($model) {
                (new InwardInvoiceAttachments(['inward_invoice_id' => $model->id, 'path' => $path, 'status' => 1]))->save(false);
            }));

        return [true, $lines, $expenses];
    }

    /**
     * Build line / expense rows from the posted arrays (blank rows are skipped).
     *
     * @return array{0: InwardInvoiceDetails[], 1: InwardInvoiceDetails[], 2: string[]}
     */
    private function readLines(Vendor $vendor, array $postLines, array $postExpenses): array
    {
        $lines = $expenses = $errors = [];
        $ledgerIds = [];
        $str = fn($row, $k) => is_array($row) && isset($row[$k]) && is_scalar($row[$k]) ? trim((string)$row[$k]) : '';
        $num = fn($row, $k) => is_numeric($str($row, $k)) ? (float)$str($row, $k) : null;

        // Which taxes apply depends on where the vendor is.
        $allowed = match ((int)$vendor->vendor_type) {
            Vendor::TYPE_INTRA_STATE => ['sgst' => true, 'cgst' => true, 'igst' => false],
            Vendor::TYPE_INTER_STATE => ['sgst' => false, 'cgst' => false, 'igst' => true],
            default                  => ['sgst' => false, 'cgst' => false, 'igst' => false],
        };

        foreach (array_slice($postLines, 0, self::MAX_LINES) as $row) {
            $product = $str($row, 'product');
            if ($product === '' && $str($row, 'qty') === '' && $str($row, 'price_per_qty') === '') {
                continue;
            }
            $n = count($lines) + 1;
            $d = new InwardInvoiceDetails(['line_item_type' => 1, 'product' => $product, 'hsn' => $str($row, 'hsn')]);
            $d->qty           = $num($row, 'qty');
            $d->price_per_qty = $num($row, 'price_per_qty');
            $d->ledger_id     = ctype_digit($str($row, 'ledger_id')) ? (int)$str($row, 'ledger_id') : null;
            foreach (['sgst', 'cgst', 'igst'] as $tax) {
                $pct = $num($row, $tax . '_percent') ?? 0.0;
                if ($pct < 0 || $pct > 100) {
                    $errors[] = "Line $n: " . strtoupper($tax) . ' must be between 0 and 100%.';
                    $pct = 0.0;
                } elseif ($pct > 0 && !$allowed[$tax]) {
                    $errors[] = "Line $n: " . strtoupper($tax) . ' does not apply to ' . strtolower(Vendor::typeLabels()[(int)$vendor->vendor_type] ?? 'this') . ' vendors.';
                    $pct = 0.0;
                }
                $d->{$tax . '_percent'} = $pct;
            }
            if ($product === '') {
                $errors[] = "Line $n: enter what was bought.";
            } elseif (mb_strlen($product) > 5000) {
                $errors[] = "Line $n: the description is too long.";
            }
            if ($d->qty === null || $d->qty <= 0 || $d->qty > 1000000) {
                $errors[] = "Line $n: quantity must be more than 0.";
            }
            if ($d->price_per_qty === null || $d->price_per_qty <= 0) {
                $errors[] = "Line $n: price must be more than 0.";
            }
            if (mb_strlen((string)$d->hsn) > 500) {
                $errors[] = "Line $n: HSN code is too long.";
            }
            if ($d->ledger_id) {
                $ledgerIds[$d->ledger_id] = "Line $n";
            }
            $lines[] = $d;
        }

        foreach (array_slice($postExpenses, 0, self::MAX_LINES) as $row) {
            $title  = $str($row, 'product');
            $amount = $num($row, 'amount');
            if ($title === '' && $str($row, 'amount') === '') {
                continue;
            }
            $n = count($expenses) + 1;
            $e = new InwardInvoiceDetails(['line_item_type' => 2, 'product' => $title, 'qty' => 1, 'item_total' => $amount ?? 0]);
            $e->ledger_id = ctype_digit($str($row, 'ledger_id')) ? (int)$str($row, 'ledger_id') : null;
            if ($title === '') {
                $errors[] = "Extra expense $n: enter a description.";
            }
            if ($amount === null || $amount <= 0) {
                $errors[] = "Extra expense $n: amount must be more than 0.";
            }
            if ($e->ledger_id) {
                $ledgerIds[$e->ledger_id] = "Extra expense $n";
            }
            $expenses[] = $e;
        }

        if (!$lines) {
            $errors[] = 'Add at least one line item.';
        }
        if ($ledgerIds) {
            $found = AccLedger::find()->select('id')->where(['id' => array_keys($ledgerIds), 'status' => 1])->column();
            foreach (array_diff(array_keys($ledgerIds), array_map('intval', $found)) as $bad) {
                $errors[] = $ledgerIds[$bad] . ': that ledger no longer exists — pick another.';
            }
        }
        if (count($postLines) > self::MAX_LINES) {
            $errors[] = 'An invoice can have at most ' . self::MAX_LINES . ' lines.';
        }
        return [$lines, $expenses, $errors];
    }

    private function requireVendorType(Vendor $vendor): ?Response
    {
        if (in_array((int)$vendor->vendor_type, [Vendor::TYPE_INTER_STATE, Vendor::TYPE_INTRA_STATE, Vendor::TYPE_INTERNATIONAL], true)) {
            return null;
        }
        Yii::$app->session->setFlash('warning', 'Set the vendor type of ' . $vendor->company_name . ' first — it decides which GST applies.');
        return $this->redirect(['/vendor/update', 'id' => $vendor->id]);
    }

    private function ledgersOf($categoryId): array
    {
        if (!is_scalar($categoryId) || !ctype_digit((string)$categoryId)) {
            return ['success' => false, 'message' => 'Choose a category first.', 'data' => []];
        }
        $data = AccLedger::find()->select(['title', 'id'])->where(['status' => 1, 'category_id' => (int)$categoryId])
            ->orderBy('title')->indexBy('id')->column();
        return ['success' => (bool)$data, 'message' => $data ? '' : 'No ledgers in this category.', 'data' => $data];
    }

    /** Cleaned list filters (unreadable values are dropped). */
    private function filters(): array
    {
        $req = Yii::$app->request;
        $get = fn($k) => is_scalar($req->get($k)) ? trim((string)$req->get($k)) : '';
        $date = function ($k) use ($get) {
            $v = $get($k);
            $d = \DateTime::createFromFormat('!Y-m-d', $v);
            return $d && $d->format('Y-m-d') === $v ? $v : '';
        };
        $f = [
            'vendor_id'      => ctype_digit($get('vendor_id')) ? $get('vendor_id') : '',
            'from_date'      => $date('from_date'),
            'to_date'        => $date('to_date'),
            'q'              => mb_substr($get('q'), 0, 100),
            'payment_status' => in_array($get('payment_status'), ['0', '1', 'none'], true) ? $get('payment_status') : '',
            'invoice_role'   => in_array($get('invoice_role'), ['1', '2'], true) ? $get('invoice_role') : '',
        ];
        if ($f['from_date'] !== '' && $f['to_date'] !== '' && $f['to_date'] < $f['from_date']) {
            [$f['from_date'], $f['to_date']] = [$f['to_date'], $f['from_date']];
        }
        return $f;
    }

    /** Ledgers grouped by category for the line pickers, and payment types with their ledgers. */
    private function formData(): array
    {
        $cats = AccLedgerCategory::find()->select(['title', 'id'])->where(['status' => 1])->andWhere(['not', ['parent_id' => null]])
            ->orderBy('title')->indexBy('id')->column();
        $ledgerGroups = [];
        foreach (AccLedger::find()->select(['id', 'title', 'category_id'])->where(['status' => 1, 'category_id' => array_keys($cats)])
                     ->orderBy('title')->asArray()->all() as $l) {
            $ledgerGroups[$cats[$l['category_id']]][$l['id']] = $l['title'];
        }
        ksort($ledgerGroups);

        $paymentTypes = AccLedgerCategory::find()->select(['title', 'id'])->where(['status' => 1, 'is_payment_ledger' => 1])
            ->orderBy('title')->indexBy('id')->column();
        $paymentLedgers = [];
        foreach (AccLedger::find()->select(['id', 'title', 'category_id'])->where(['status' => 1, 'category_id' => array_keys($paymentTypes)])
                     ->orderBy('title')->asArray()->all() as $l) {
            $paymentLedgers[$l['category_id']][$l['id']] = $l['title'];
        }
        return compact('ledgerGroups', 'paymentTypes', 'paymentLedgers');
    }

    private function findModel(int $id): InwardInvoice
    {
        $model = InwardInvoice::find()
            ->with(['vendor', 'lineItems', 'lineItems.ledger', 'expenses', 'expenses.ledger', 'attachments', 'paymentLedger', 'paymentCategory'])
            ->where(['inward_invoice.id' => $id, 'inward_invoice.status' => 1])
            ->one();
        if (!$model) {
            throw new NotFoundHttpException('Inward invoice not found.');
        }
        return $model;
    }
}
