<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\InwardInvoice;
use backend\models\IotBrand;
use backend\models\IotCategory;
use backend\models\IotConsumption;
use backend\models\IotConsumptionProducts;
use backend\models\IotLocation;
use backend\models\InwardInvoiceDetails;
use backend\models\IotNonPurchase;
use backend\models\IotNonPurchaseProducts;
use backend\models\IotIssuedHistory;
use backend\models\IotProduct;
use backend\models\IotReturnProductHistory;
use backend\models\IotSubcategory;
use backend\models\IotTags;
use backend\models\IotUploads;
use yii\web\UploadedFile;
use backend\models\Project;
use Yii;
use yii\filters\VerbFilter;
use backend\assets\InventoryAsset;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class IotProductController extends Controller
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'delete'              => ['POST'],
                    'delete-upload'       => ['POST'],
                    'change-status'       => ['POST'],
                    'save-master'         => ['POST'],
                    'delete-master'       => ['POST'],
                    'delete-non-purchase' => ['POST'],
                    'delete-purchase'     => ['POST'],
                    'convert-to-invoice'  => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * Developers get some helper routes by default; the ones that change data
     * still need the matching primary right.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $needs = [
            'delete-upload' => ['iot-product/create', 'iot-product/update'],
        ][$action->id] ?? null;
        if ($needs !== null && !array_filter($needs, fn($r) => \common\components\UserAccess::checkAccess($r))) {
            throw new \yii\web\ForbiddenHttpException('You are not allowed to perform this action.');
        }
        return true;
    }

    /** Full pages get the Inventory module stylesheet and script. */
    public function render($view, $params = []): string
    {
        InventoryAsset::register($this->getView());
        return parent::render($view, $params);
    }

    // ─── All Products ────────────────────────────────────────────────────────

    public function actionIndex(): string
    {
        $query = IotProduct::find()
            ->with(['brand', 'category', 'subcategory', 'location'])
            ->where(['iot_products.status' => 1])
            ->orderBy(['iot_products.id' => SORT_ASC]);

        if ($s = Yii::$app->request->get('search'))     { $query->andWhere(['or', ['like','product_name',$s], ['like','product_code',$s], ['like','part_number',$s]]); }
        if ($v = Yii::$app->request->get('cat_id'))      { $query->andWhere(['cat_id'    => (int)$v]); }
        if ($v = Yii::$app->request->get('brand_id'))    { $query->andWhere(['brand_id'  => (int)$v]); }
        if ($v = Yii::$app->request->get('subcat_id'))   { $query->andWhere(['subcat_id' => (int)$v]); }
        if ($v = Yii::$app->request->get('location_id')) { $query->andWhere(['location_id' => (int)$v]); }

        $dataProvider  = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 25]]);
        $categories    = IotCategory::find()->where(['status' => 1])->orderBy('name')->all();
        $brands        = IotBrand::find()->where(['status' => 1])->orderBy('brand_name')->all();
        $subcategories = IotSubcategory::find()->where(['status' => 1])->orderBy('name')->all();
        $locations     = IotLocation::find()->where(['status' => 1])->orderBy('location')->all();

        return $this->render('index', compact('dataProvider', 'categories', 'brands', 'subcategories', 'locations'));
    }

    public function actionCreate(): string|Response
    {
        $model = new IotProduct();
        // Initial display-only candidate for the read-only Product Code field.
        $model->product_code = $this->nextIotProductCode();

        $session = Yii::$app->session;

        if ($model->load(Yii::$app->request->post())) {
            // Idempotency guard against duplicate inserts. Each rendered form
            // carries a one-time token; the first POST consumes it, any later
            // POST of the SAME form (double-click, browser retry, an XHR error
            // re-fire, or a stale tab) finds the token already gone and is
            // rejected before any insert. We keep a small set of recent valid
            // tokens so several open tabs each work once. PHP's session file
            // lock serializes concurrent POSTs, so consume-on-use is race-safe.
            $postedToken  = (string) Yii::$app->request->post('form_token', '');
            $validTokens  = (array) $session->get('iot_product_form_tokens', []);
            $tokenPos     = array_search($postedToken, $validTokens, true);
            if ($postedToken === '' || $tokenPos === false) {
                Yii::$app->session->setFlash('warning', 'This product was already submitted — not added again.');
                return $this->redirect(['index']);
            }
            // Consume immediately so a duplicate POST can't pass this check.
            unset($validTokens[$tokenPos]);
            $session->set('iot_product_form_tokens', array_values($validTokens));

            if (is_array($model->tags)) {
                $model->tags = implode(',', array_filter($model->tags));
            }
            $model->created_at = date('Y-m-d H:i:s');
            $model->created_by = Yii::$app->user->id;
            $model->opening_stock_qty = $model->stock_qty;
            $model->status = 1;

            // Always regenerate the product code at save time — never trust the
            // value submitted from the form. The form's pre-filled code can go
            // stale between render and submit (especially on Save & Add New),
            // causing "Product code already exists" errors when another row
            // was inserted in between or when the MAX query missed a non-
            // standard-format code.
            $maxAttempts = 25;
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $model->product_code = $this->nextIotProductCode();
                $model->clearErrors('product_code');
                if ($model->save()) {
                    $this->saveUploads($model->id);
                    Yii::$app->session->setFlash('success', 'Product created successfully.');
                    if (Yii::$app->request->post('add_new')) {
                        return $this->redirect(['create']);
                    }
                    return $this->redirect(['index']);
                }
                // If the only error is the unique product_code, retry with a
                // fresh candidate. Any other validation error breaks out so
                // the user sees it.
                $errors = $model->getErrors();
                if (count($errors) !== 1 || !isset($errors['product_code'])) {
                    break;
                }
                // Reset the auto-incremented id (if any) so the next save() is treated
                // as a fresh insert.
                $model->isNewRecord = true;
                $model->id          = null;
            }
        }

        // Issue a fresh one-time token for this render (covers GET and the
        // re-render after a validation error). Keep only the last few so
        // several open tabs each work, without growing the session unbounded.
        $formToken   = Yii::$app->security->generateRandomString();
        $validTokens = (array) $session->get('iot_product_form_tokens', []);
        $validTokens[] = $formToken;
        $validTokens = array_slice($validTokens, -10);
        $session->set('iot_product_form_tokens', $validTokens);

        return $this->render('_product_form', [
            'model'      => $model,
            'categories' => IotCategory::find()->where(['status' => 1])->orderBy('name')->all(),
            'brands'     => IotBrand::find()->where(['status' => 1])->orderBy('brand_name')->all(),
            'locations'  => IotLocation::find()->where(['status' => 1])->orderBy('location')->all(),
            'allTags'    => IotTags::find()->where(['status' => 1])->orderBy('name')->all(),
            'uploads'    => [],
            'formToken'  => $formToken,
        ]);
    }

    /**
     * Compute the next available IOT product code (format: IOT + 5-digit zero-padded sequence).
     *
     * Uses MAX(numeric portion) over all IOT* codes as a fast starting point,
     * then linearly probes for the first non-existing code. This is resilient
     * to legacy codes that don't follow the IOT##### convention and to gaps
     * in the sequence caused by deleted rows.
     */
    private function nextIotProductCode(): string
    {
        // Raw SQL via PDO to avoid Yii2 query-cache surprises and so the
        // expression isn't quoted as a column name.
        $maxNum = (int) IotProduct::getDb()->createCommand(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(product_code, 4) AS UNSIGNED)), 0)
             FROM iot_products
             WHERE product_code LIKE 'IOT%'"
        )->queryScalar();

        $next = $maxNum + 1;
        // Probe forward up to a sanity ceiling so we never spin forever.
        for ($i = 0; $i < 10000; $i++) {
            $candidate = 'IOT' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
            $exists = IotProduct::find()->where(['product_code' => $candidate])->exists();
            if (!$exists) {
                return $candidate;
            }
            $next++;
        }
        // Fallback (should never hit) — at least return something unique-ish.
        return 'IOT' . str_pad((string)($maxNum + 1), 5, '0', STR_PAD_LEFT);
    }

    public function actionView(int $id): string
    {
        $model = $this->findProduct($id);
        $model->refresh();
        return $this->render('view', ['model' => $model]);
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findProduct($id);
        $oldStockQty = (int)$model->stock_qty;

        if ($model->load(Yii::$app->request->post())) {
            if (is_array($model->tags)) {
                $model->tags = implode(',', array_filter($model->tags));
            }
            $model->updated_at = date('Y-m-d H:i:s');
            $model->updated_by = Yii::$app->user->id;
            if ($model->save()) {
                // Track stock qty changes (like Yii1)
                $newStockQty = (int)$model->stock_qty;
                $diff = $newStockQty - $oldStockQty;
                if ($diff > 0) {
                    // Stock increased → log in return history
                    $history = new IotReturnProductHistory();
                    $history->product_id = $model->id;
                    $history->quantity   = $diff;
                    $history->type       = '2'; // stock increase from update
                    $history->particular = 'Product Update';
                    $history->created_at = date('Y-m-d H:i:s');
                    $history->status     = 1;
                    $history->save(false);
                } elseif ($diff < 0) {
                    // Stock decreased → log in issued history
                    $history = new IotIssuedHistory();
                    $history->product_id = $model->id;
                    $history->quantity   = abs($diff);
                    $history->type       = '2'; // stock decrease from update
                    $history->particular = 'Product Update';
                    $history->created_at = date('Y-m-d H:i:s');
                    $history->status     = 1;
                    $history->save(false);
                }

                $this->saveUploads($model->id);
                Yii::$app->session->setFlash('success', 'Product updated successfully.');
                return $this->redirect(['index']);
            }
        }

        return $this->render('_product_form', [
            'model'      => $model,
            'categories' => IotCategory::find()->where(['status' => 1])->orderBy('name')->all(),
            'brands'     => IotBrand::find()->where(['status' => 1])->orderBy('brand_name')->all(),
            'locations'  => IotLocation::find()->where(['status' => 1])->orderBy('location')->all(),
            'allTags'    => IotTags::find()->where(['status' => 1])->orderBy('name')->all(),
            'uploads'    => IotUploads::find()->where(['product_id' => $model->id, 'status' => 1])->orderBy('id')->all(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) { throw new \yii\web\BadRequestHttpException('POST only.'); }
        $p = $this->findProduct($id);

        // Check for dependencies before deleting (like Yii1)
        $hasPurchased = InwardInvoiceDetails::find()
            ->where(['product' => $p->product_name, 'status' => 1])
            ->exists();
        if ($hasPurchased) {
            Yii::$app->session->setFlash('error', 'Cannot delete: product has purchased invoice records.');
            return $this->redirect(['index']);
        }

        $hasNonPurchased = IotNonPurchaseProducts::find()
            ->where(['product_id' => $p->id, 'status' => 1])
            ->exists();
        if ($hasNonPurchased) {
            Yii::$app->session->setFlash('error', 'Cannot delete: product has non-purchased records.');
            return $this->redirect(['index']);
        }

        $hasIssued = IotConsumptionProducts::find()
            ->where(['product_id' => $p->id, 'status' => 1])
            ->exists();
        if ($hasIssued) {
            Yii::$app->session->setFlash('error', 'Cannot delete: product has issued/consumption records.');
            return $this->redirect(['index']);
        }

        // Soft-delete the product and related history
        $p->status = 0;
        $p->save(false);
        IotReturnProductHistory::updateAll(['status' => 0], ['product_id' => $p->id]);
        IotIssuedHistory::updateAll(['status' => 0], ['product_id' => $p->id]);

        Yii::$app->session->setFlash('success', 'Product deleted.');
        return $this->redirect(['index']);
    }

    /** AJAX: get subcategories for a category */
    public function actionGetSubcategory(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $catId = (int)Yii::$app->request->post('cat_id');
        $rows  = IotSubcategory::find()->where(['category_id' => $catId, 'status' => 1])->orderBy('name')->all();
        return $this->asJson(array_map(fn($s) => ['id' => $s->id, 'name' => $s->name], $rows));
    }

    // ─── Issued Products ─────────────────────────────────────────────────────

    public function actionIssued(): string
    {
        // Mirror Yii1 IotConsumption::search() used by actionIssuedproduct:
        //  - INNER JOIN active product lines + DISTINCT → only consumptions
        //    that still have at least one status=1 product row (hides records
        //    whose lines were all removed).
        //  - consumption_type = 1 → ISSUED only (excludes returns, type 2).
        //  - status = 1 → active consumptions only.
        //  - developer scoping: a developer without change-status access sees
        //    only their own requests.
        $query = IotConsumption::find()
            ->with(['project', 'developer', 'products.product'])
            ->innerJoin('iot_consumption_products icp', 'icp.consumption_id = iot_consumption.id AND icp.status = 1')
            ->where([
                'iot_consumption.status'           => 1,
                'iot_consumption.consumption_type' => 1,
            ])
            ->distinct()
            ->orderBy(['iot_consumption.created_at' => SORT_DESC]);

        // Developer-role scoping (Yii1: developer_id filter unless they can change status).
        /** @var \backend\models\User $user */
        $user = Yii::$app->user->identity;
        if ($user && $user->isDeveloper()
            && !\common\components\UserAccess::checkAccess('iot-product/change-status')) {
            $selfDev = Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
            $query->andWhere(['iot_consumption.developer_id' => $selfDev ? $selfDev->id : 0]);
        }

        if ($v = Yii::$app->request->get('developer_id'))       { $query->andWhere(['iot_consumption.developer_id' => (int)$v]); }
        if ($v = Yii::$app->request->get('project_id'))         { $query->andWhere(['iot_consumption.project_id'   => (int)$v]); }
        if (($v = Yii::$app->request->get('consumption_status')) !== null && $v !== '') {
            $query->andWhere(['iot_consumption.consumption_status' => (int)$v]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 25]]);
        $developers   = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $projects     = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('issued', compact('dataProvider', 'developers', 'projects'));
    }

    /**
     * Create an issue-request. Mirrors Yii1 actionCreaterequest, including
     * "Copy as New Request" behaviour when ?copy=<old_id> is supplied.
     */
    public function actionCreateIssue(?int $copy = null): string|Response
    {
        $model    = new IotConsumption();
        $products = IotProduct::find()->where(['status' => 1])->orderBy('product_name')->all();
        $existing = [];

        // Copy mode: pre-fill project/developer/products from an existing request
        if ($copy !== null && $copy > 0) {
            $src = IotConsumption::findOne(['id' => $copy, 'status' => 1]);
            $restrict = $this->selfDeveloperIfRestricted();
            if ($src && $restrict !== false && (int)$src->developer_id !== (int)($restrict->id ?? 0)) {
                $src = null;
            }
            if ($src) {
                $model->project_id   = $src->project_id;
                $model->developer_id = $src->developer_id;
                $existing = IotConsumptionProducts::find()
                    ->where(['status' => 1, 'consumption_id' => $src->id])
                    ->with('product')
                    ->all();
            }
        }

        // Default the picker to today on a fresh load (Yii1 parity).
        if ($model->requested_date === null || $model->requested_date === '') {
            $model->requested_date = date('Y-m-d');
        }

        /** @var \backend\models\User $user */
        $user      = Yii::$app->user->identity;
        $isDevRole = $user && $user->isDeveloper();
        $selfDev   = $isDevRole ? Developer::findOne(['user_id' => $user->user_id, 'status' => 1]) : null;

        if ($model->load(Yii::$app->request->post())) {
            // Yii1 parity: developer-role users may only request for themselves.
            if ($isDevRole && $selfDev) {
                $model->developer_id = $selfDev->id;
            }

            // requested_date is normalized to Y-m-d in the model's beforeValidate()
            // and persisted as a datetime in beforeSave() — Yii1 column parity.
            $model->created_at        = date('Y-m-d H:i:s');
            $model->consumption_type  = 1;
            $model->consumption_status = 1; // Requested (matches Yii1)
            $model->status            = 1;

            // Server-side stock guard — defence-in-depth for the form's JS check.
            [$lines, $stockErrors] = $this->readIssueLines();
            if ($stockErrors) {
                Yii::$app->session->setFlash('error', implode(' ', $stockErrors));
            } elseif ($model->validate()) {
                $tx = Yii::$app->db->beginTransaction();
                try {
                    $model->save(false);
                    $this->saveIssueLines($model->id, $lines);
                    $tx->commit();
                    Yii::$app->session->setFlash('success', 'Product request created successfully.');
                    // Yii1 parity: "Save & Add New" loops back to the create page.
                    if (Yii::$app->request->post('save_action') === 'save_new') {
                        return $this->redirect(['create-issue']);
                    }
                    return $this->redirect(['issued']);
                } catch (\Throwable $e) {
                    $tx->rollBack();
                    Yii::error('Issue request create failed: ' . $e->getMessage(), __METHOD__);
                    Yii::$app->session->setFlash('error', 'Could not save the request. Please try again.');
                }
            }
        }

        $developers = $isDevRole && $selfDev
            ? [$selfDev]
            : Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('_issue_form', compact('model', 'products', 'developers', 'projects', 'existing'));
    }

    public function actionUpdateIssue(int $id): string|Response
    {
        $model = $this->findConsumption($id);
        if ((int)$model->consumption_status !== IotConsumption::STATUS_REQUESTED) {
            Yii::$app->session->setFlash('error', 'This request has already been issued and can no longer be edited.');
            return $this->redirect(['view-issue', 'id' => $model->id]);
        }
        $products = IotProduct::find()->where(['status' => 1])->orderBy('product_name')->all();
        $existing = IotConsumptionProducts::find()->where(['consumption_id' => $id, 'status' => 1])->with('product')->all();
        $selfDev  = $this->selfDeveloperIfRestricted();

        if ($model->load(Yii::$app->request->post())) {
            if ($selfDev) {
                $model->developer_id = $selfDev->id;
            }
            [$lines, $errors] = $this->readIssueLines();
            if ($errors) {
                Yii::$app->session->setFlash('error', implode(' ', $errors));
            } elseif ($model->validate()) {
                $tx = Yii::$app->db->beginTransaction();
                try {
                    $model->save(false);
                    IotConsumptionProducts::updateAll(['status' => 0], ['consumption_id' => $model->id, 'status' => 1]);
                    $this->saveIssueLines($model->id, $lines);
                    $tx->commit();
                    Yii::$app->session->setFlash('success', 'Issue request updated.');
                    return $this->redirect(['issued']);
                } catch (\Throwable $e) {
                    $tx->rollBack();
                    Yii::error('Issue request update failed: ' . $e->getMessage(), __METHOD__);
                    Yii::$app->session->setFlash('error', 'Could not save the request. Please try again.');
                }
            }
        }

        $developers = $selfDev ? [$selfDev] : Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('_issue_form', compact('model', 'products', 'developers', 'projects', 'existing'));
    }

    public function actionViewIssue(int $id): string
    {
        $model = $this->findConsumption($id);
        $items = IotConsumptionProducts::find()->where(['consumption_id' => $id, 'status' => 1])->with('product')->all();
        return $this->render('_issue_view', compact('model', 'items'));
    }

    /**
     * AJAX: mark a Requested issue-request as Issued (the only allowed change).
     * Deducts stock and logs an IotIssuedHistory row per product, all in one
     * transaction with the request row locked, so a double click cannot issue twice.
     */
    public function actionChangeStatus(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $id     = (int)Yii::$app->request->post('id');
        $status = (int)Yii::$app->request->post('status');
        $this->findConsumption($id);

        if ($status !== IotConsumption::STATUS_ISSUED) {
            return $this->asJson(['status' => 0, 'msg' => 'A request can only be marked as Issued.']);
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $model = IotConsumption::findBySql('SELECT * FROM iot_consumption WHERE id = :id AND status = 1 FOR UPDATE', [':id' => $id])->one();
            if (!$model || (int)$model->consumption_status !== IotConsumption::STATUS_REQUESTED) {
                $tx->rollBack();
                return $this->asJson(['status' => 0, 'msg' => 'This request has already been issued.']);
            }
            $items = IotConsumptionProducts::find()->where(['consumption_id' => $id, 'status' => 1])->with('product')->all();
            if (empty($items)) {
                $tx->rollBack();
                return $this->asJson(['status' => 0, 'msg' => 'Please add minimum one product.']);
            }

            $errors = [];
            foreach ($items as $item) {
                $qty = (int)$item->quantity;
                // Conditional decrement: never takes stock below zero, even under concurrency.
                $done = $qty > 0 && IotProduct::updateAllCounters(['stock_qty' => -$qty],
                    ['and', ['id' => (int)$item->product_id, 'status' => 1], ['>=', 'stock_qty', $qty]]);
                if (!$done) {
                    $p = $item->product;
                    $errors[] = 'Product ' . ($p->product_name ?? ('#' . $item->product_id)) . ' available quantity is ' . (int)($p->stock_qty ?? 0);
                    continue;
                }
                $hist             = new IotIssuedHistory();
                $hist->product_id = (int)$item->product_id;
                $hist->quantity   = $qty;
                $hist->type       = '1';
                $hist->particular = 'Issued';
                $hist->created_at = date('Y-m-d H:i:s');
                $hist->status     = 1;
                $hist->save(false);
            }
            if ($errors) {
                $tx->rollBack();
                return $this->asJson(['status' => 0, 'msg' => implode("\n", $errors)]);
            }

            $model->consumption_status = IotConsumption::STATUS_ISSUED;
            $model->approved_at        = date('Y-m-d H:i:s');
            $model->approved_by        = Yii::$app->user->id;
            $model->save(false);
            $tx->commit();
            return $this->asJson(['status' => 1, 'msg' => 'Product issued successfully']);
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error('Issue failed: ' . $e->getMessage(), __METHOD__);
            return $this->asJson(['status' => 0, 'msg' => 'Could not issue the products. Please try again.']);
        }
    }

    /**
     * Return-product flow for an Issued request: per line, raises returned_quantity
     * (capped at what is still out), credits stock and logs IotReturnProductHistory.
     * Runs in one transaction with the request locked.
     */
    public function actionCreateReturn(int $id): string|Response
    {
        $model = $this->findConsumption($id);
        if ((int)$model->consumption_status !== IotConsumption::STATUS_ISSUED) {
            Yii::$app->session->setFlash('error', 'Only Issued requests can be returned.');
            return $this->redirect(['issued']);
        }

        if (Yii::$app->request->isPost) {
            $productIds = (array)Yii::$app->request->post('product_id', []);
            $quantities = (array)Yii::$app->request->post('quantity', []);
            $returned   = 0;
            $tx = Yii::$app->db->beginTransaction();
            try {
                Yii::$app->db->createCommand('SELECT id FROM iot_consumption WHERE id = :id FOR UPDATE', [':id' => $model->id])->execute();
                foreach ($productIds as $idx => $pid) {
                    $pid    = (int)$pid;
                    $retQty = (int)($quantities[$idx] ?? 0);
                    if ($pid <= 0 || $retQty <= 0) continue;

                    $line = IotConsumptionProducts::findOne(['status' => 1, 'consumption_id' => $model->id, 'product_id' => $pid]);
                    if (!$line) continue;
                    $alreadyReturned = (int)($line->returned_quantity ?? 0);
                    $retQty = min($retQty, max(0, (int)$line->quantity - $alreadyReturned));
                    if ($retQty <= 0) continue;

                    $line->returned_quantity = $alreadyReturned + $retQty;
                    $line->save(false);
                    IotProduct::updateAllCounters(['stock_qty' => $retQty], ['id' => $pid]);

                    $hist             = new IotReturnProductHistory();
                    $hist->product_id = $pid;
                    $hist->quantity   = $retQty;
                    $hist->type       = '1';
                    $hist->particular = 'Return';
                    $hist->created_at = date('Y-m-d H:i:s');
                    $hist->status     = 1;
                    $hist->save(false);
                    $returned++;
                }
                $tx->commit();
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::error('Return failed: ' . $e->getMessage(), __METHOD__);
                Yii::$app->session->setFlash('error', 'Could not save the return. Please try again.');
                return $this->redirect(['create-return', 'id' => $model->id]);
            }

            if ($returned === 0) {
                Yii::$app->session->setFlash('warning', 'Nothing was returned: enter a quantity for at least one product that is still out.');
                return $this->redirect(['create-return', 'id' => $model->id]);
            }
            Yii::$app->session->setFlash('success', 'Return products updated successfully.');
            return $this->redirect(['issued']);
        }

        $items = IotConsumptionProducts::find()
            ->where(['status' => 1, 'consumption_id' => $model->id])
            ->with('product')
            ->all();
        $projects   = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('_return_form', compact('model', 'items', 'projects', 'developers'));
    }

    /** AJAX: check available quantity for a product */
    public function actionGetAvailableQuantity(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $pid = (int)Yii::$app->request->post('product_id');
        $qty = (int)Yii::$app->request->post('quantity');
        $p   = IotProduct::findOne(['id' => $pid, 'status' => 1]);
        if (!$p)                        return $this->asJson(['status' => 0, 'msg' => 'Invalid product.']);
        if ($p->stock_qty >= $qty)      return $this->asJson(['status' => 1, 'msg' => 'Available']);
        return $this->asJson(['status' => 0, 'msg' => "Only {$p->stock_qty} available.", 'qty' => $p->stock_qty]);
    }

    // ─── Non-Purchased Products ───────────────────────────────────────────────

    public function actionNonPurchased(): string
    {
        $query = IotNonPurchase::find()
            ->with(['project', 'items.product'])
            ->where(['iot_non_purchase.status' => 1])
            ->orderBy(['id' => SORT_DESC]);

        if ($v = Yii::$app->request->get('project_id')) { $query->andWhere(['project_id' => (int)$v]); }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 25]]);
        $projects     = Project::find()->where(['status' => 1])->orderBy('title')->all();

        return $this->render('non-purchased', compact('dataProvider', 'projects'));
    }

    public function actionCreateNonPurchase(): string|Response
    {
        $model    = new IotNonPurchase();
        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $products = IotProduct::find()->where(['status' => 1])->orderBy('product_name')->all();

        if ($model->load(Yii::$app->request->post())) {
            $model->created_at = date('Y-m-d H:i:s');
            $model->created_by = Yii::$app->user->id;
            $model->status     = 1;

            if ($model->save()) {
                $productIds = Yii::$app->request->post('product_id', []);
                $qtys       = Yii::$app->request->post('quantity', []);
                foreach ($productIds as $k => $pid) {
                    if (!$pid) continue;
                    $qty  = (int)($qtys[$k] ?? 1);
                    $item = new IotNonPurchaseProducts();
                    $item->non_purchase_id = $model->id;
                    $item->product_id      = (int)$pid;
                    $item->quantity        = $qty;
                    $item->created_at      = date('Y-m-d H:i:s');
                    $item->status          = 1;
                    if ($item->save(false)) {
                        // Update stock and create history — matches Yii1
                        $product = IotProduct::findOne((int)$pid);
                        if ($product) {
                            $product->stock_qty = (int)$product->stock_qty + $qty;
                            if ($product->save(false)) {
                                $history = new IotReturnProductHistory();
                                $history->product_id = (int)$pid;
                                $history->quantity   = $qty;
                                $history->type       = 5;
                                $history->particular = 'Non Purchase';
                                $history->created_at = date('Y-m-d H:i:s');
                                $history->status     = 1;
                                $history->save(false);
                            }
                        }
                    }
                }
                Yii::$app->session->setFlash('success', 'Non-purchase entry created.');
                return $this->redirect(['non-purchased']);
            }
        }

        return $this->render('_non_purchase_form', compact('model', 'projects', 'products'));
    }

    public function actionUpdateNonPurchase(int $id): string|Response
    {
        $model    = $this->findNonPurchase($id);
        $projects = Project::find()->where(['status' => 1])->orderBy('title')->all();
        $products = IotProduct::find()->where(['status' => 1])->orderBy('product_name')->all();
        $existing = IotNonPurchaseProducts::find()->where(['non_purchase_id' => $id, 'status' => 1])->all();

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                // Build old quantities map — matches Yii1 diff-based stock adjustment
                $oldProducts = [];
                foreach ($existing as $e) {
                    $oldProducts[(int)$e->product_id] = (int)$e->quantity;
                }

                $productIds  = Yii::$app->request->post('product_id', []);
                $qtys        = Yii::$app->request->post('quantity', []);
                $newProducts = [];
                foreach ($productIds as $k => $pid) {
                    if ($pid) {
                        $newProducts[(int)$pid] = (int)($qtys[$k] ?? 1);
                    }
                }

                // Products removed from the form — reverse their stock
                $deletedProducts = array_diff_key($oldProducts, $newProducts);

                IotNonPurchaseProducts::deleteAll(['non_purchase_id' => $model->id]);

                foreach ($productIds as $k => $pid) {
                    if (!$pid) continue;
                    $newQty = (int)($qtys[$k] ?? 1);
                    $oldQty = $oldProducts[(int)$pid] ?? 0;

                    $item = new IotNonPurchaseProducts();
                    $item->non_purchase_id = $model->id;
                    $item->product_id      = (int)$pid;
                    $item->quantity        = $newQty;
                    $item->created_at      = date('Y-m-d H:i:s');
                    $item->status          = 1;
                    $item->save(false);

                    $product = IotProduct::findOne((int)$pid);
                    if ($product) {
                        if ($newQty > $oldQty) {
                            // Quantity increased — add to stock
                            $addQty = $newQty - $oldQty;
                            $product->stock_qty = (int)$product->stock_qty + $addQty;
                            if ($product->save(false)) {
                                $history = new IotReturnProductHistory();
                                $history->product_id = (int)$pid;
                                $history->quantity   = $addQty;
                                $history->type       = 3;
                                $history->particular = 'Non Purchase Update';
                                $history->created_at = date('Y-m-d H:i:s');
                                $history->status     = 1;
                                $history->save(false);
                            }
                        } elseif ($newQty < $oldQty) {
                            // Quantity decreased — remove from stock
                            $lessQty = $oldQty - $newQty;
                            if ((int)$product->stock_qty >= $lessQty) {
                                $product->stock_qty = (int)$product->stock_qty - $lessQty;
                                if ($product->save(false)) {
                                    $history = new IotIssuedHistory();
                                    $history->product_id = (int)$pid;
                                    $history->quantity   = $lessQty;
                                    $history->type       = 5;
                                    $history->particular = 'Non Purchase Update';
                                    $history->created_at = date('Y-m-d H:i:s');
                                    $history->status     = 1;
                                    $history->save(false);
                                }
                            }
                        }
                    }
                }

                // Reverse stock for deleted products
                foreach ($deletedProducts as $delPid => $delQty) {
                    $product = IotProduct::findOne($delPid);
                    if ($product && (int)$product->stock_qty >= $delQty) {
                        $product->stock_qty = (int)$product->stock_qty - $delQty;
                        if ($product->save(false)) {
                            $history = new IotIssuedHistory();
                            $history->product_id = $delPid;
                            $history->quantity   = $delQty;
                            $history->type       = 5;
                            $history->particular = 'Non Purchase Update';
                            $history->created_at = date('Y-m-d H:i:s');
                            $history->status     = 1;
                            $history->save(false);
                        }
                    }
                }

                Yii::$app->session->setFlash('success', 'Non-purchase entry updated.');
                return $this->redirect(['non-purchased']);
            }
        }

        return $this->render('_non_purchase_form', compact('model', 'projects', 'products', 'existing'));
    }

    public function actionViewNonPurchase(int $id): string
    {
        $model = $this->findNonPurchase($id);
        $items = IotNonPurchaseProducts::find()->where(['non_purchase_id' => $id, 'status' => 1])->with('product')->all();
        return $this->render('_non_purchase_view', compact('model', 'items'));
    }

    /**
     * AJAX: Soft-delete a non-purchase record and reverse stock. Mirrors Yii1 actionNonpurchasedelete.
     */
    public function actionDeleteNonPurchase(): \yii\web\Response
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST required.']);
        }

        $id = (int)Yii::$app->request->post('id', 0);
        $model = IotNonPurchase::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            return $this->asJson(['status' => 0, 'msg' => 'Invalid Data']);
        }

        $details = IotNonPurchaseProducts::find()->where(['non_purchase_id' => $id, 'status' => 1])->all();
        $errorMsg = '';

        // Check stock availability before deleting
        foreach ($details as $d) {
            $product = IotProduct::findOne($d->product_id);
            $stockQty = (int)($product->stock_qty ?? 0);
            $invoiceQty = (int)($d->quantity ?? 0);
            if ($invoiceQty > $stockQty) {
                $productName = $product->product_name ?? '';
                $errorMsg .= "Product {$productName} has only {$stockQty} quantity, cannot delete.\n";
                break;
            }
        }

        if ($errorMsg !== '') {
            return $this->asJson(['status' => 0, 'msg' => $errorMsg]);
        }

        // Reverse stock and log history
        foreach ($details as $d) {
            $product = IotProduct::findOne($d->product_id);
            if ($product) {
                $product->stock_qty = (int)$product->stock_qty - (int)$d->quantity;
                $product->save(false);

                $history = new IotIssuedHistory();
                $history->product_id  = $d->product_id;
                $history->quantity    = $d->quantity;
                $history->type        = 2;
                $history->particular  = 'Delete Non Purchase';
                $history->created_at  = date('Y-m-d H:i:s');
                $history->status      = 1;
                $history->save(false);
            }
            $d->status = 0;
            $d->save(false);
        }

        $model->status = 0;
        $model->save(false);

        return $this->asJson(['status' => 1, 'msg' => 'Record deleted successfully']);
    }

    // ─── Purchased Products (Inward Invoices) ────────────────────────────────

    public function actionPurchased(): string
    {
        $query = InwardInvoice::find()
            ->with('vendor')
            ->where(['inward_invoice.status' => 1, 'invoice_type' => 2])
            ->orderBy(['id' => SORT_DESC]);

        if ($s = Yii::$app->request->get('search')) { $query->andWhere(['or', ['like','invoice_number',$s], ['like','title',$s]]); }
        if ($v = Yii::$app->request->get('invoice_role')) { $query->andWhere(['invoice_role' => (int)$v]); }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 25]]);
        return $this->render('purchased', compact('dataProvider'));
    }

    /**
     * Vendor picker before creating a Purchase Invoice.
     * Mirrors Yii1 "Add Purchased Products" flow (vendors/admin?iotpurchase=1).
     */
    public function actionPickVendor(): string
    {
        $query = \backend\models\Vendor::find()
            ->where(['vendors.status' => 1])
            ->orderBy('company_name');

        if ($s = Yii::$app->request->get('search')) {
            $query->andWhere(['or',
                ['like', 'company_name', $s],
                ['like', 'gst_no',       $s],
            ]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);
        return $this->render('pick_vendor', compact('dataProvider'));
    }

    public function actionCreatePurchase(): string|Response
    {
        $model = new InwardInvoice();
        $model->status      = 1;
        $model->invoice_type = 2; // IOT purchase

        // Pre-select vendor when arriving from the vendor-picker
        if (!Yii::$app->request->isPost && ($vid = (int)Yii::$app->request->get('vendor_id', 0)) > 0) {
            $model->vendor_id = $vid;
        }

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $model->load($post, 'InwardInvoice');
            $model->invoice_type = 2;
            $model->created_by  = Yii::$app->user->id;
            $model->created_at  = date('Y-m-d H:i:s');
            $model->status      = 1;

            // Use submitted totals (matches Yii1 — totals are editable input fields)
            $model->grand_total  = (float)($post['grandtotal'] ?? 0);
            $model->sgst_amount  = (float)($post['sgstamount'] ?? 0);
            $model->cgst_amount  = (float)($post['cgstamount'] ?? 0);
            $model->igst_amount  = (float)($post['igstamount'] ?? 0);
            $model->round_off    = (float)($post['round_off'] ?? 0);
            $model->net_total    = (float)($post['nettotal'] ?? 0);
            $model->invoice_role = 2; // Proforma by default (matches Yii1)

            // Payment fields
            $model->payment_status  = ($post['InwardInvoice']['payment_status'] ?? null) !== '' ? (int)($post['InwardInvoice']['payment_status'] ?? null) : null;
            $model->payment_remarks = $post['InwardInvoice']['payment_remarks'] ?? null;
            if ($model->payment_status == 1) {
                $model->payment_amount     = (float)($post['InwardInvoice']['payment_amount'] ?? 0);
                $model->tds_amount         = (float)($post['InwardInvoice']['tds_amount'] ?? 0);
                $model->payment_type       = $post['InwardInvoice']['payment_type'] ?? null;
                $model->payment_ledger_id  = $post['InwardInvoice']['payment_ledger_id'] ?? null;
            }

            if ($model->save()) {
                // Read Yii1-style flat arrays
                $productIds   = $post['product_id'] ?? [];
                $productTexts = $post['product_text'] ?? [];
                $hsnArr       = $post['hsn'] ?? [];
                $qtyArr       = $post['qty'] ?? [];
                $ppqArr       = $post['price_per_qty'] ?? [];
                $itArr        = $post['item_total'] ?? [];
                $spArr        = $post['sgst_percent'] ?? [];
                $saArr        = $post['sgst_amount'] ?? [];
                $cpArr        = $post['cgst_percent'] ?? [];
                $caArr        = $post['cgst_amount'] ?? [];
                $ipArr        = $post['igst_percent'] ?? [];
                $iaArr        = $post['igst_amount'] ?? [];
                $taArr        = $post['total_amount'] ?? [];
                $ledgerIds    = $post['ledger_ids'] ?? [];

                foreach ($productIds as $i => $pid) {
                    $productName = trim($productTexts[$i] ?? '');
                    if ($productName === '' && empty($pid)) continue;

                    $d = new InwardInvoiceDetails();
                    $d->inward_invoice_id = $model->id;
                    $d->line_item_type = 1;
                    $d->product_id    = !empty($pid) ? (int)$pid : null;
                    $d->product       = $productName;
                    $d->hsn           = trim($hsnArr[$i] ?? '');
                    $d->qty           = (float)($qtyArr[$i] ?? 1);
                    $d->price_per_qty = (float)($ppqArr[$i] ?? 0);
                    $d->item_total    = (float)($itArr[$i] ?? $d->qty * $d->price_per_qty);
                    $d->sgst_percent  = (float)($spArr[$i] ?? 0);
                    $d->sgst_amount   = (float)($saArr[$i] ?? 0);
                    $d->cgst_percent  = (float)($cpArr[$i] ?? 0);
                    $d->cgst_amount   = (float)($caArr[$i] ?? 0);
                    $d->igst_percent  = (float)($ipArr[$i] ?? 0);
                    $d->igst_amount   = (float)($iaArr[$i] ?? 0);
                    $d->total_amount  = (float)($taArr[$i] ?? 0);
                    $d->ledger_id     = !empty($ledgerIds[$i]) ? (int)$ledgerIds[$i] : null;
                    $d->status        = 1;
                    $d->save(false);
                }
                Yii::$app->session->setFlash('success', 'Successfully created');
                return $this->redirect(['purchased']);
            }
        }

        $vendors = \backend\models\Vendor::find()->where(['status' => 1])->orderBy('company_name')->all();
        return $this->render('_purchase_form', compact('model', 'vendors'));
    }

    public function actionUpdatePurchase(int $id): string|Response
    {
        $model = $this->findInwardInvoice($id);

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $model->load($post, 'InwardInvoice');

            // Use submitted totals (matches Yii1)
            $model->grand_total  = (float)($post['grandtotal'] ?? 0);
            $model->sgst_amount  = (float)($post['sgstamount'] ?? 0);
            $model->cgst_amount  = (float)($post['cgstamount'] ?? 0);
            $model->igst_amount  = (float)($post['igstamount'] ?? 0);
            $model->round_off    = (float)($post['round_off'] ?? 0);
            $model->net_total    = (float)($post['nettotal'] ?? 0);

            // Payment fields
            $model->payment_status  = ($post['InwardInvoice']['payment_status'] ?? null) !== '' ? (int)($post['InwardInvoice']['payment_status'] ?? null) : null;
            $model->payment_remarks = $post['InwardInvoice']['payment_remarks'] ?? null;
            if ($model->payment_status == 1) {
                $model->payment_amount    = (float)($post['InwardInvoice']['payment_amount'] ?? 0);
                $model->tds_amount        = (float)($post['InwardInvoice']['tds_amount'] ?? 0);
                $model->payment_type      = $post['InwardInvoice']['payment_type'] ?? null;
                $model->payment_ledger_id = $post['InwardInvoice']['payment_ledger_id'] ?? null;
            }

            if ($model->save()) {
                InwardInvoiceDetails::updateAll(['status' => 0], ['inward_invoice_id' => $model->id]);

                // Read Yii1-style flat arrays
                $productIds   = $post['product_id'] ?? [];
                $productTexts = $post['product_text'] ?? [];
                $hsnArr       = $post['hsn'] ?? [];
                $qtyArr       = $post['qty'] ?? [];
                $ppqArr       = $post['price_per_qty'] ?? [];
                $itArr        = $post['item_total'] ?? [];
                $spArr        = $post['sgst_percent'] ?? [];
                $saArr        = $post['sgst_amount'] ?? [];
                $cpArr        = $post['cgst_percent'] ?? [];
                $caArr        = $post['cgst_amount'] ?? [];
                $ipArr        = $post['igst_percent'] ?? [];
                $iaArr        = $post['igst_amount'] ?? [];
                $taArr        = $post['total_amount'] ?? [];
                $ledgerIds    = $post['ledger_ids'] ?? [];

                foreach ($productIds as $i => $pid) {
                    $productName = trim($productTexts[$i] ?? '');
                    if ($productName === '' && empty($pid)) continue;

                    $d = new InwardInvoiceDetails();
                    $d->inward_invoice_id = $model->id;
                    $d->line_item_type = 1;
                    $d->product_id    = !empty($pid) ? (int)$pid : null;
                    $d->product       = $productName;
                    $d->hsn           = trim($hsnArr[$i] ?? '');
                    $d->qty           = (float)($qtyArr[$i] ?? 1);
                    $d->price_per_qty = (float)($ppqArr[$i] ?? 0);
                    $d->item_total    = (float)($itArr[$i] ?? $d->qty * $d->price_per_qty);
                    $d->sgst_percent  = (float)($spArr[$i] ?? 0);
                    $d->sgst_amount   = (float)($saArr[$i] ?? 0);
                    $d->cgst_percent  = (float)($cpArr[$i] ?? 0);
                    $d->cgst_amount   = (float)($caArr[$i] ?? 0);
                    $d->igst_percent  = (float)($ipArr[$i] ?? 0);
                    $d->igst_amount   = (float)($iaArr[$i] ?? 0);
                    $d->total_amount  = (float)($taArr[$i] ?? 0);
                    $d->ledger_id     = !empty($ledgerIds[$i]) ? (int)$ledgerIds[$i] : null;
                    $d->status        = 1;
                    $d->save(false);
                }
                Yii::$app->session->setFlash('success', 'Purchase invoice updated.');
                return $this->redirect(['view-purchase', 'id' => $model->id]);
            }
        }

        $vendors = \backend\models\Vendor::find()->where(['status' => 1])->orderBy('company_name')->all();
        return $this->render('_purchase_form', compact('model', 'vendors'));
    }

    public function actionViewPurchase(int $id): string
    {
        $model = $this->findInwardInvoice($id);
        return $this->render('_purchase_view', ['model' => $model]);
    }

    /**
     * AJAX: soft-delete a Proforma purchase invoice, reverse its stock impact,
     * and log an IotIssuedHistory row per product. Mirrors Yii1 actionPurchasedelete.
     * Only Proforma (invoice_role=2) invoices can be deleted.
     *
     * POST: id
     */
    public function actionDeletePurchase(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST required.']);
        }

        $id = (int)Yii::$app->request->post('id');
        $inv = InwardInvoice::findOne(['id' => $id, 'status' => 1, 'invoice_type' => 2]);
        if (!$inv) return $this->asJson(['status' => 0, 'msg' => 'Invalid Data']);
        if ((int)$inv->invoice_role !== 2) {
            return $this->asJson(['status' => 0, 'msg' => 'Only Proforma invoices can be deleted.']);
        }

        $details = InwardInvoiceDetails::find()
            ->where(['status' => 1, 'inward_invoice_id' => $id])
            ->andWhere(['not', ['product_id' => null]])
            ->all();

        $vendorName = $inv->vendor->company_name ?? '';

        // Stock-sufficiency check first
        $errors = [];
        foreach ($details as $d) {
            $prod = IotProduct::findOne(['id' => (int)$d->product_id]);
            if (!$prod) continue;
            if ((int)$d->qty > (int)$prod->stock_qty) {
                $errors[] = 'product ' . $prod->product_name . '  have only ' . (int)$prod->stock_qty . ' quantity cannot delete invoice';
                break; // matches Yii1 (first mismatch aborts)
            }
        }
        if ($errors) {
            return $this->asJson(['status' => 0, 'msg' => implode("\n", $errors)]);
        }

        $viewUrl = \yii\helpers\Url::to(['view-purchase', 'id' => $inv->id], true);

        foreach ($details as $d) {
            $prod = IotProduct::findOne(['id' => (int)$d->product_id]);
            if ($prod) {
                $qty = (int)$d->qty;
                $prod->stock_qty = (int)$prod->stock_qty - $qty;
                $prod->save(false);

                $hist                = new IotIssuedHistory();
                $hist->product_id    = (int)$d->product_id;
                $hist->quantity      = $qty;
                $hist->type          = '3';
                $hist->particular    = 'purchase delete (<a href="' . $viewUrl . '" target="_blank">' .
                                        ($inv->invoice_number ?? '') . '</a>)  (' . $vendorName . ')';
                $hist->price_per_qty = (float)($d->price_per_qty ?? 0);
                $hist->created_at    = date('Y-m-d H:i:s');
                $hist->status        = 1;
                $hist->save(false);
            }
            $d->status = 0;
            $d->save(false);
        }

        $inv->status = 0;
        $inv->save(false);

        return $this->asJson(['status' => 1, 'msg' => 'invoice deleted successfully']);
    }

    /**
     * AJAX: convert a Proforma (invoice_role=2) inward invoice to an Actual
     * Invoice (invoice_role=1). Adds stock to each regular-item line and logs
     * an IotReturnProductHistory row (type=6, "purchase"). Mirrors Yii1
     * actionConverttoactualinvoice.
     *
     * POST: id
     */
    public function actionConvertToInvoice(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST required.']);
        }

        $id  = (int)Yii::$app->request->post('id');
        $inv = InwardInvoice::findOne(['id' => $id, 'status' => 1]);
        if (!$inv || (int)$inv->invoice_role !== 2) {
            return $this->asJson(['status' => 0, 'msg' => 'Failed']);
        }

        $inv->invoice_role = 1;
        if (!$inv->save()) {
            return $this->asJson([
                'status' => 0,
                'msg'    => 'Failed: ' . implode('; ', array_map(
                    fn($msgs) => is_array($msgs) ? implode(', ', $msgs) : (string)$msgs,
                    $inv->getErrors()
                )),
            ]);
        }

        $vendorName = $inv->vendor->company_name ?? '';
        $viewUrl    = \yii\helpers\Url::to(['view-purchase', 'id' => $inv->id], true);

        $details = InwardInvoiceDetails::find()
            ->where(['inward_invoice_id' => $inv->id, 'status' => 1, 'line_item_type' => 1])
            ->andWhere(['>', 'product_id', 0])
            ->all();

        foreach ($details as $d) {
            $prod = IotProduct::findOne(['id' => (int)$d->product_id]);
            if (!$prod) continue;
            $qty = (int)$d->qty;
            $prod->stock_qty = (int)$prod->stock_qty + $qty;
            if ($prod->save(false)) {
                $hist                = new IotReturnProductHistory();
                $hist->product_id    = (int)$d->product_id;
                $hist->quantity      = $qty;
                $hist->type          = '6';
                $hist->particular    = 'purchase (<a href="' . $viewUrl . '" target="_blank">' .
                                        ($inv->invoice_number ?? '') . '</a>)  (' . $vendorName . ')';
                $hist->price_per_qty = (float)($d->price_per_qty ?? 0);
                $hist->created_at    = date('Y-m-d H:i:s');
                $hist->status        = 1;
                $hist->save(false);
            }
        }

        return $this->asJson(['status' => 1, 'msg' => 'Success']);
    }

    // ─── IOT Masters ─────────────────────────────────────────────────────────

    // ─── IoT Masters (Manage Dropdowns) ─────────────────────────────────────
    // Mirrors Yii1 IotproductsController::actionManagedropdown etc.
    // Table codes: cat, subcat, brand, loc, tags

    /**
     * Active master tab + paginated, searchable grid for that tab.
     * Query params: table (cat|subcat|brand|loc|tags), search, category_id (for subcat), page.
     */
    public function actionMasters(string $table = 'cat'): string
    {
        $table = in_array($table, ['cat', 'subcat', 'brand', 'loc', 'tags'], true) ? $table : 'cat';

        $req    = Yii::$app->request;
        $search = trim((string)$req->get('search', ''));
        $catId  = (int)$req->get('category_id', 0);

        $query = $this->mastersQuery($table, $search, $catId);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 10, 'pageParam' => 'page'],
            'sort'       => ['defaultOrder' => ['id' => SORT_ASC]],
        ]);

        $categories = IotCategory::find()->where(['status' => 1])->orderBy('name')->all();

        return $this->render('masters', [
            'table'        => $table,
            'dataProvider' => $dataProvider,
            'search'       => $search,
            'categoryId'   => $catId,
            'categories'   => $categories,
        ]);
    }

    /**
     * Base ActiveQuery for the masters grid on a given tab.
     */
    private function mastersQuery(string $table, string $search, int $catId): \yii\db\ActiveQuery
    {
        switch ($table) {
            case 'cat':
                $q = IotCategory::find()->where(['status' => 1]);
                if ($search !== '') $q->andWhere(['like', 'name', $search]);
                return $q;
            case 'subcat':
                $q = IotSubcategory::find()->with('category')->where(['iot_subcategory.status' => 1]);
                if ($search !== '') $q->andWhere(['like', 'iot_subcategory.name', $search]);
                if ($catId > 0)     $q->andWhere(['iot_subcategory.category_id' => $catId]);
                return $q;
            case 'brand':
                $q = IotBrand::find()->where(['status' => 1]);
                if ($search !== '') $q->andWhere(['like', 'brand_name', $search]);
                return $q;
            case 'loc':
                $q = IotLocation::find()->where(['status' => 1]);
                if ($search !== '') $q->andWhere(['like', 'location', $search]);
                return $q;
            case 'tags':
            default:
                $q = IotTags::find()->where(['status' => 1]);
                if ($search !== '') $q->andWhere(['like', 'name', $search]);
                return $q;
        }
    }

    /**
     * AJAX: Fetch a single master record for editing.
     * Returns {success, data:{id,name,category_id?}}.
     */
    public function actionGetMaster(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id    = (int)Yii::$app->request->get('id', 0);
        $table = (string)Yii::$app->request->get('table', '');
        $m     = $this->findMaster($table, $id);
        if (!$m) return $this->asJson(['success' => false, 'message' => 'Record not found.']);

        switch ($table) {
            case 'cat':    $data = ['id' => $m->id, 'name' => $m->name]; break;
            case 'subcat': $data = ['id' => $m->id, 'name' => $m->name, 'category_id' => $m->category_id]; break;
            case 'brand':  $data = ['id' => $m->id, 'name' => $m->brand_name]; break;
            case 'loc':    $data = ['id' => $m->id, 'name' => $m->location]; break;
            case 'tags':   $data = ['id' => $m->id, 'name' => $m->name]; break;
            default:       return $this->asJson(['success' => false, 'message' => 'Unknown table.']);
        }
        return $this->asJson(['success' => true, 'data' => $data]);
    }

    /**
     * AJAX: create or update a master record. Mirrors Yii1 actionAdddropdownvalue.
     * POST: table, id (optional for update), name, category_id (for subcat).
     */
    public function actionSaveMaster(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['success' => false, 'message' => 'POST required.']);
        }

        $table = (string)Yii::$app->request->post('table', '');
        $id    = (int)Yii::$app->request->post('id', 0);
        $name  = trim((string)Yii::$app->request->post('name', ''));
        $catId = (int)Yii::$app->request->post('category_id', 0);

        if ($name === '') {
            return $this->asJson(['success' => false, 'message' => 'Name is required.']);
        }
        if ($table === 'subcat' && $catId <= 0) {
            return $this->asJson(['success' => false, 'message' => 'Please select a category.']);
        }

        $now   = date('Y-m-d H:i:s');
        $uid   = Yii::$app->user->id;
        $isNew = ($id <= 0);
        $m     = $isNew ? $this->newMaster($table) : $this->findMaster($table, $id);
        if (!$m) return $this->asJson(['success' => false, 'message' => 'Record not found or unknown table.']);

        switch ($table) {
            case 'cat':    $m->name        = $name; break;
            case 'subcat': $m->name        = $name; $m->category_id = $catId; break;
            case 'brand':  $m->brand_name  = $name; break;
            case 'loc':    $m->location    = $name; break;
            case 'tags':   $m->name        = $name; break;
            default:       return $this->asJson(['success' => false, 'message' => 'Unknown table.']);
        }

        if ($isNew) {
            if ($m->hasAttribute('created_at')) $m->created_at = $now;
            if ($m->hasAttribute('created_by')) $m->created_by = $uid;
            if ($m->hasAttribute('status'))     $m->status     = 1;
        }
        if ($m->hasAttribute('updated_at')) $m->updated_at = $now;
        if ($m->hasAttribute('updated_by')) $m->updated_by = $uid;

        if (!$m->save()) {
            return $this->asJson([
                'success' => false,
                'message' => implode('<br>', $m->getFirstErrors() ?: ['Save failed.']),
            ]);
        }
        return $this->asJson([
            'success' => true,
            'message' => $isNew ? 'Record created successfully.' : 'Record updated successfully.',
            'id'      => $m->id,
        ]);
    }

    /**
     * AJAX: soft-delete a master record after checking it is not used by any
     * active IoT product. Mirrors Yii1 actionRemovedropdownvalue.
     * POST: id, table.
     */
    public function actionDeleteMaster(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id    = (int)Yii::$app->request->post('id', 0);
        $table = (string)Yii::$app->request->post('table', '');

        $m = $this->findMaster($table, $id);
        if (!$m) return $this->asJson(['status' => 0, 'msg' => 'Record not found.']);

        // In-use check against active products
        $usedCol = [
            'cat'    => 'cat_id',
            'subcat' => 'subcat_id',
            'brand'  => 'brand_id',
            'loc'    => 'location_id',
            'tags'   => null,   // tags are a CSV column, checked separately
        ][$table] ?? null;

        $label = [
            'cat' => 'Category', 'subcat' => 'Sub-Category', 'brand' => 'Brand',
            'loc' => 'Location', 'tags' => 'Tag',
        ][$table] ?? 'Record';

        if ($usedCol !== null) {
            $inUse = IotProduct::find()->where(['status' => 1, $usedCol => $id])->exists();
        } else {
            // tags column is comma-separated ids
            $inUse = IotProduct::find()
                ->where(['status' => 1])
                ->andWhere(['or',
                    ['tags' => (string)$id],
                    ['like', 'tags', ',' . $id . ','],
                    ['like', 'tags', $id . ',', false],
                    ['like', 'tags', ',' . $id, false],
                ])
                ->exists();
        }

        if ($inUse) {
            return $this->asJson([
                'status' => 0,
                'msg'    => $label . ' delete failed — it is in use by an active product.',
            ]);
        }

        $m->status = 0;
        if ($m->hasAttribute('updated_at')) $m->updated_at = date('Y-m-d H:i:s');
        if ($m->hasAttribute('updated_by')) $m->updated_by = Yii::$app->user->id;
        $m->save(false);

        return $this->asJson(['status' => 1, 'msg' => $label . ' successfully deleted.']);
    }

    /**
     * Factory for new master rows.
     *
     * @return \yii\db\ActiveRecord|null
     */
    private function newMaster(string $table)
    {
        return match ($table) {
            'cat'    => new IotCategory(),
            'subcat' => new IotSubcategory(),
            'brand'  => new IotBrand(),
            'loc'    => new IotLocation(),
            'tags'   => new IotTags(),
            default  => null,
        };
    }

    /**
     * Find an active master row by id.
     *
     * @return \yii\db\ActiveRecord|null
     */
    private function findMaster(string $table, int $id)
    {
        if ($id <= 0) return null;
        return match ($table) {
            'cat'    => IotCategory::findOne(['id' => $id, 'status' => 1]),
            'subcat' => IotSubcategory::findOne(['id' => $id, 'status' => 1]),
            'brand'  => IotBrand::findOne(['id' => $id, 'status' => 1]),
            'loc'    => IotLocation::findOne(['id' => $id, 'status' => 1]),
            'tags'   => IotTags::findOne(['id' => $id, 'status' => 1]),
            default  => null,
        };
    }

    /** AJAX: soft-delete an upload file */
    /**
     * AJAX: Check if stock is sufficient for multiple products.
     * POST: product_id[] (array of IotProduct IDs), qty[] (array of requested quantities),
     *       purchase_id (invoice ID), key_value (1=non-purchase, 0=purchase), remove_key (1=remove check)
     * Returns JSON: { status: 1|0, msg: string }
     */
    public function actionCheckQuantity(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['status' => 0, 'msg' => 'POST required']);
        }

        $productIds = (array)Yii::$app->request->post('product_id', []);
        $quantities = (array)Yii::$app->request->post('qty', []);
        $purchaseId = (int)Yii::$app->request->post('purchase_id', 0);
        $removeKey  = (int)Yii::$app->request->post('remove_key', 0);

        if (empty($productIds)) {
            return $this->asJson(['status' => 1]);
        }

        $errors = [];
        foreach ($productIds as $key => $productId) {
            $product = IotProduct::findOne(['id' => (int)$productId, 'status' => 1]);
            if (!$product) continue;

            $currentStock = (int)($product->stock_qty ?? 0);
            $requested    = (int)($quantities[$key] ?? 0);

            if ($removeKey === 1) {
                // Removing product: ensure stock is sufficient to restore
                if ($currentStock < 0) {
                    $errors[] = "Product \"{$product->product_name}\" has only {$currentStock} in stock.";
                }
            } else {
                // Updating quantity: check if increased demand can be fulfilled
                if ($requested > $currentStock) {
                    $errors[] = "Product \"{$product->product_name}\" has only {$currentStock} available (requested: {$requested}).";
                }
            }
        }

        if (!empty($errors)) {
            return $this->asJson(['status' => 0, 'msg' => implode("\n", $errors)]);
        }
        return $this->asJson(['status' => 1]);
    }

    // ─── Product History & Barcode ───────────────────────────────────────────

    /**
     * Renders the IoT product stock-movement history (Opening → Issues → Returns
     * with running balance) plus min/max/avg/last purchase quantity stats.
     * Mirrors Yii1 IotproductsController::actionProductoverview.
     *
     * @throws NotFoundHttpException
     */
    public function actionProductHistory(int $id): string
    {
        $model = IotProduct::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Product not found.');
        }

        $history = [];

        // Opening-stock row
        if ((int)$model->opening_stock_qty > 0) {
            $history[] = [
                'date'          => $model->created_at ?: null,
                'particular'    => 'Opening',
                'in'            => (int)$model->opening_stock_qty,
                'out'           => null,
                'price_per_qty' => null,
            ];
        }

        // Issues (OUT)
        $issues = IotIssuedHistory::find()
            ->where(['status' => 1, 'product_id' => $id])
            ->all();
        foreach ($issues as $row) {
            $history[] = [
                'date'          => $row->created_at ?: null,
                'particular'    => $row->particular ?: '-',
                'in'            => null,
                'out'           => (int)$row->quantity,
                'price_per_qty' => $row->price_per_qty ?: null,
            ];
        }

        // Returns / purchases (IN)
        $returns = IotReturnProductHistory::find()
            ->where(['status' => 1, 'product_id' => $id])
            ->all();
        foreach ($returns as $row) {
            $history[] = [
                'date'          => $row->created_at ?: null,
                'particular'    => $row->particular ?: '-',
                'in'            => (int)$row->quantity,
                'out'           => null,
                'price_per_qty' => $row->price_per_qty ?: null,
            ];
        }

        // Sort ascending by date to compute running balance correctly
        usort($history, function ($a, $b) {
            return strtotime((string)$a['date']) <=> strtotime((string)$b['date']);
        });
        $balance = 0;
        foreach ($history as $k => $row) {
            if ($row['in']  !== null) $balance += (int)$row['in'];
            if ($row['out'] !== null) $balance -= (int)$row['out'];
            $history[$k]['balance'] = $balance;
        }
        // Show most recent first
        $history = array_reverse($history);

        // Min / Max / Avg purchase qty from inward invoice details (last 5 rows)
        $purchaseStats = (new \yii\db\Query())
            ->select(['MAX(qty) AS max_qty', 'MIN(qty) AS min_qty', 'AVG(qty) AS avg_qty'])
            ->from('inward_invoice_details')
            ->where(['status' => 1, 'product_id' => $id])
            ->one();

        $lastPurchase = InwardInvoiceDetails::find()
            ->where(['status' => 1, 'product_id' => $id])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $this->renderPartial('product-history', [
            'model'          => $model,
            'history'        => $history,
            'purchaseStats'  => $purchaseStats ?: [],
            'lastPurchase'   => $lastPurchase,
        ]);
    }

    /**
     * Renders the barcode card for a single IoT product.
     * Mirrors Yii1 IotproductsController::actionBarcode.
     * The barcode image itself is generated client-side by JsBarcode (CODE128).
     *
     * @throws NotFoundHttpException
     */
    public function actionBarcode(int $id): string
    {
        $model = IotProduct::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Product not found.');
        }
        return $this->renderPartial('barcode', [
            'model'    => $model,
            'location' => $model->location->location ?? '',
        ]);
    }

    /**
     * Renders the multi-barcode card for a set of selected IoT products.
     * Mirrors Yii1 IotproductsController::actionPrintmultibarcode.
     */
    public function actionPrintMultiBarcode(): string
    {
        $ids = Yii::$app->request->post('id', []);
        if (!\is_array($ids)) {
            $ids = [$ids];
        }
        $ids = \array_values(\array_filter(\array_map('intval', $ids)));

        if (empty($ids)) {
            return '<div class="alert alert-warning mb-0">No products selected.</div>';
        }

        $models = IotProduct::find()
            ->with(['location'])
            ->where(['id' => $ids, 'status' => 1])
            ->all();

        if (empty($models)) {
            return '<div class="alert alert-warning mb-0">No products found for the selected rows.</div>';
        }

        return $this->renderPartial('multi-barcode', ['models' => $models]);
    }

    public function actionDeleteUpload(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!Yii::$app->request->isPost) {
            return $this->asJson(['success' => false]);
        }
        $id = (int)Yii::$app->request->post('id');
        $upload = IotUploads::findOne(['id' => $id, 'status' => 1]);
        if (!$upload) return $this->asJson(['success' => false]);
        $upload->status = 0;
        $upload->save(false);
        return $this->asJson(['success' => true]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function saveUploads(int $productId): void
    {
        $files = UploadedFile::getInstancesByName('data_sheet');
        if (empty($files)) return;

        // Server-side allowlist (matches the client `accept` attribute).
        // Defence-in-depth — the form-level JS already screens, but a crafted
        // POST could send anything. Reject .php / .exe / .sh etc. here.
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'];
        $maxBytes    = 5 * 1024 * 1024; // 5 MB per file

        // Mirror Yii1 convention: ensure both /uploads and /uploads/iot exist
        // and are world-writable so Apache (www-data) can write into them.
        $webroot     = Yii::getAlias('@webroot');
        $uploadsRoot = $webroot . '/uploads';
        $uploadDir   = $uploadsRoot . '/iot/';

        $oldUmask = umask(0);
        if (!is_dir($uploadsRoot))        { @mkdir($uploadsRoot, 0777, true); }
        if (!is_dir($uploadDir))          { @mkdir($uploadDir,   0777, true); }
        umask($oldUmask);

        $skipped = [];
        foreach ($files as $file) {
            $ext = strtolower((string)$file->extension);
            if (!in_array($ext, $allowedExts, true)) {
                $skipped[] = $file->name . ' (type not allowed)';
                continue;
            }
            if ($file->size > $maxBytes) {
                $skipped[] = $file->name . ' (over 5 MB)';
                continue;
            }
            $filename = 'data_sheet_' . $productId . '_' . time() . '_' . uniqid() . '.' . $ext;
            $path     = '/uploads/iot/' . $filename;
            $fullPath = $uploadDir . $filename;
            if (@$file->saveAs($fullPath)) {
                @chmod($fullPath, 0664);
                $upload              = new IotUploads();
                $upload->product_id  = $productId;
                $upload->type        = $ext;
                $upload->path        = $path;
                $upload->upload_type = 5;
                $upload->created_at  = date('Y-m-d H:i:s');
                $upload->created_by  = Yii::$app->user->id;
                $upload->status      = 1;
                $upload->save(false);
            }
        }

        if (!empty($skipped)) {
            Yii::$app->session->setFlash('warning',
                'Some attachments were skipped: ' . implode(', ', $skipped));
        }
    }

    private function findProduct(int $id): IotProduct
    {
        $m = IotProduct::findOne(['id' => $id, 'status' => 1]);
        if (!$m) throw new NotFoundHttpException('Product not found.');
        return $m;
    }

    /**
     * Developers without the status-change right only reach their own requests
     * (the same rule the Issued list applies).
     */
    private function findConsumption(int $id): IotConsumption
    {
        $m = IotConsumption::findOne(['id' => $id, 'status' => 1]);
        $selfDev = $this->selfDeveloperIfRestricted();
        if (!$m || ($selfDev !== false && (int)$m->developer_id !== (int)($selfDev->id ?? 0))) {
            throw new NotFoundHttpException('Issue request not found.');
        }
        return $m;
    }

    /**
     * For a developer who cannot change issue status: their Developer row
     * (null when none is linked). For everyone else: false (no restriction).
     */
    private function selfDeveloperIfRestricted(): Developer|false|null
    {
        /** @var \backend\models\User|null $user */
        $user = Yii::$app->user->identity;
        if (!$user || !$user->isDeveloper() || \common\components\UserAccess::checkAccess('iot-product/change-status')) {
            return false;
        }
        return Developer::findOne(['user_id' => $user->user_id, 'status' => 1]);
    }

    /**
     * Reads the posted product rows of an issue request.
     * @return array{0: array<int,int>, 1: string[]} [product_id => qty], errors
     */
    private function readIssueLines(): array
    {
        $productIds = (array)Yii::$app->request->post('product_id', []);
        $quantities = (array)Yii::$app->request->post('quantity', []);
        $lines  = [];
        $errors = [];
        foreach ($productIds as $k => $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;
            if (isset($lines[$pid])) { $errors[] = 'Duplicate product on multiple rows.'; break; }
            $qty = (int)($quantities[$k] ?? 0);
            if ($qty < 1) { $errors[] = 'Quantity must be at least 1 for every row.'; break; }
            $product = IotProduct::findOne(['id' => $pid, 'status' => 1]);
            if (!$product) { $errors[] = 'One of the selected products no longer exists.'; break; }
            if ($qty > (int)$product->stock_qty) {
                $errors[] = "\"{$product->product_name}\" has only {$product->stock_qty} in stock (requested {$qty}).";
            }
            $lines[$pid] = $qty;
        }
        if (!$lines && !$errors) {
            $errors[] = 'Please add at least one product.';
        }
        return [$lines, $errors];
    }

    /** @param array<int,int> $lines product_id => qty */
    private function saveIssueLines(int $consumptionId, array $lines): void
    {
        foreach ($lines as $pid => $qty) {
            $item = new IotConsumptionProducts();
            $item->consumption_id = $consumptionId;
            $item->product_id     = $pid;
            $item->quantity       = $qty;
            $item->status         = 1;
            $item->save(false);
        }
    }

    private function findNonPurchase(int $id): IotNonPurchase
    {
        $m = IotNonPurchase::findOne(['id' => $id, 'status' => 1]);
        if (!$m) throw new NotFoundHttpException('Record not found.');
        return $m;
    }

    private function findInwardInvoice(int $id): InwardInvoice
    {
        $m = InwardInvoice::findOne(['id' => $id, 'status' => 1]);
        if (!$m) throw new NotFoundHttpException('Invoice not found.');
        return $m;
    }
}
