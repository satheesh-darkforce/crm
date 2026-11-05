<?php

namespace backend\controllers;

use backend\models\Asset;
use backend\models\AssetBrand;
use backend\models\AssetCategory;
use backend\models\AssetHandover;
use backend\models\AssetLocation;
use backend\models\AssetMaintenance;
use backend\models\AssetSubcategory;
use backend\models\AssetTag;
use backend\models\AssetUpload;
use backend\models\Developer;
use backend\models\Vendor;
use Yii;
use yii\filters\VerbFilter;
use backend\assets\InventoryAsset;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class AssetController extends Controller
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'delete'                  => ['POST'],
                    'delete-upload'           => ['POST'],
                    'delete-maintenance'      => ['POST'],
                    'delete-maintenance-bill' => ['POST'],
                    'delete-handover'         => ['POST'],
                    'save-master'             => ['POST'],
                    'delete-master'           => ['POST'],
                    'add-vendor'              => ['POST'],
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
            'delete-upload' => ['asset/create', 'asset/update'],
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

    // ─── Assets List ──────────────────────────────────────────────────────────

    public function actionIndex(): string
    {
        $query = Asset::find()
            ->with(['brand', 'category', 'subcategory', 'location', 'vendor'])
            ->where(['assets.status' => 1])
            ->orderBy(['id' => SORT_DESC]);

        $req = Yii::$app->request;

        if ($s = $req->get('search'))        { $query->andWhere(['or', ['like','product_name',$s], ['like','barcode',$s], ['like','model',$s]]); }
        if ($v = $req->get('cat_id'))        { $query->andWhere(['asset_cat_id'    => (int)$v]); }
        if ($v = $req->get('subcat_id'))     { $query->andWhere(['asset_subcat_id' => (int)$v]); }
        if ($v = $req->get('brand_id'))      { $query->andWhere(['brand_id'        => (int)$v]); }
        if ($v = $req->get('location_id'))   { $query->andWhere(['asset_loc'       => (int)$v]); }
        if ($v = $req->get('vendor_id'))     { $query->andWhere(['vendor_id'       => (int)$v]); }

        // Warranty / AMC status (handle "0" correctly)
        $warranty = $req->get('warranty');
        if ($warranty !== null && $warranty !== '') { $query->andWhere(['warranty' => (int)$warranty]); }
        $amc = $req->get('amc');
        if ($amc !== null && $amc !== '') { $query->andWhere(['amc' => (int)$amc]); }

        // Date range filters
        if ($v = $req->get('purchase_from'))   { $query->andWhere(['>=', 'date_of_purchase', $v]); }
        if ($v = $req->get('purchase_to'))     { $query->andWhere(['<=', 'date_of_purchase', $v]); }
        if ($v = $req->get('warranty_from'))   { $query->andWhere(['>=', 'warranty_start_date', $v]); }
        if ($v = $req->get('warranty_to'))     { $query->andWhere(['<=', 'warranty_end_date', $v]); }
        if ($v = $req->get('amc_from'))        { $query->andWhere(['>=', 'amc_start_date', $v]); }
        if ($v = $req->get('amc_to'))          { $query->andWhere(['<=', 'amc_end_date', $v]); }

        // Text search filters
        if ($v = $req->get('service_contact')) { $query->andWhere(['like', 'service_contact', $v]); }
        if ($v = $req->get('description'))     { $query->andWhere(['like', 'description', $v]); }

        // Tags filter
        if ($v = $req->get('tags')) {
            $tagIds = is_array($v) ? $v : [$v];
            foreach ($tagIds as $tagId) {
                $query->andWhere(['or',
                    ['tags' => (string)$tagId],
                    ['like', 'tags', $tagId . ','],
                    ['like', 'tags', ',' . $tagId . ','],
                    ['like', 'tags', ',' . $tagId],
                ]);
            }
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 100]]);
        $categories   = AssetCategory::find()->where(['status' => 1])->orderBy('name')->all();
        $brands       = AssetBrand::find()->where(['status' => 1])->orderBy('brand_name')->all();
        $locations    = AssetLocation::find()->where(['status' => 1])->orderBy('loc')->all();
        $vendors      = Vendor::find()->where(['status' => 1])->orderBy('company_name')->all();
        $tags         = AssetTag::find()->where(['status' => 1])->orderBy('name')->all();

        return $this->render('index', compact('dataProvider', 'categories', 'brands', 'locations', 'vendors', 'tags'));
    }

    public function actionCreate(): string|Response
    {
        $model = new Asset();

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->status   = 1;
            $this->prepareAssetAttributes($model);
            if ($model->save()) {
                // Auto-generate barcode — matches Yii1: uniqid('MAE'.$id)
                if (empty($model->barcode)) {
                    $model->barcode = uniqid('MAE' . $model->id);
                    $model->save(false);
                }
                $this->handleAssetUploads($model);
                Yii::$app->session->setFlash('success', 'Asset created successfully.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
            Yii::$app->session->setFlash('error', 'Failed to save asset: ' . implode(', ', $model->getFirstErrors()));
        }

        return $this->render('_form', $this->formData($model));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findAsset($id);

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $this->prepareAssetAttributes($model);
            if ($model->save()) {
                $this->handleAssetUploads($model);
                Yii::$app->session->setFlash('success', 'Asset updated successfully.');
                return $this->redirect(['view', 'id' => $model->id]);
            }
            Yii::$app->session->setFlash('error', 'Failed to update asset: ' . implode(', ', $model->getFirstErrors()));
        }

        return $this->render('_form', $this->formData($model));
    }

    public function actionView(int $id): string
    {
        $model        = $this->findAsset($id);
        $handovers    = AssetHandover::find()->where(['status' => 1])->andWhere(['like', 'asset_id', '"' . $id . '"'])->with('developer')->orderBy(['id' => SORT_DESC])->all();
        $maintenances = AssetMaintenance::find()->where(['asset_id' => $id, 'status' => 1])->with('developer')->orderBy(['id' => SORT_DESC])->all();
        $uploads      = AssetUpload::find()->where(['asset_id' => $id, 'status' => 1])->orderBy(['upload_type' => SORT_ASC])->all();
        return $this->render('view', compact('model', 'handovers', 'maintenances', 'uploads'));
    }

    public function actionDelete(int $id): Response
    {
        $m = $this->findAsset($id);
        if ($m->hasActiveHandover()) {
            Yii::$app->session->setFlash('error', 'Cannot delete: asset is currently assigned to an employee.');
            return $this->redirect(['index']);
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $m->status = 0;
            $m->save(false);
            AssetMaintenance::updateAll(['status' => 0], ['asset_id' => $id]);
            AssetUpload::updateAll(['status' => 0], ['asset_id' => $id]);
            $transaction->commit();
            Yii::$app->session->setFlash('success', 'Asset deleted successfully.');
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("Asset delete failed: {$e->getMessage()}", 'asset');
            Yii::$app->session->setFlash('error', 'Failed to delete asset.');
        }
        return $this->redirect(['index']);
    }

    /** AJAX: get subcategories for a category */
    public function actionGetSubcategory(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $catId = (int)Yii::$app->request->post('cat_id');
        $rows  = AssetSubcategory::find()->where(['category_id' => $catId, 'status' => 1])->orderBy('name')->all();
        return $this->asJson(array_map(fn($s) => ['id' => $s->id, 'name' => $s->name], $rows));
    }

    /** AJAX: delete an asset upload file */
    public function actionDeleteUpload(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = (int)Yii::$app->request->post('id');
        $upload = AssetUpload::findOne(['id' => $id, 'status' => 1]);
        if ($upload) {
            $upload->status = 0;
            $upload->save(false);
            return $this->asJson(['success' => true]);
        }
        return $this->asJson(['success' => false, 'message' => 'Upload not found.']);
    }

    // ─── Maintenance ──────────────────────────────────────────────────────────

    public function actionMaintenance(): string
    {
        $query = AssetMaintenance::find()
            ->with(['asset', 'developer'])
            ->where(['assets_maintenance.status' => 1])
            ->orderBy(['id' => SORT_DESC]);

        if ($v = Yii::$app->request->get('asset_id'))     { $query->andWhere(['asset_id'    => (int)$v]); }
        if ($v = Yii::$app->request->get('payment_type')) { $query->andWhere(['payment_type' => (int)$v]); }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 25]]);
        $assets       = Asset::find()->where(['status' => 1])->orderBy('product_name')->all();

        return $this->render('maintenance', compact('dataProvider', 'assets'));
    }

    public function actionAddMaintenance(): string|Response
    {
        $model      = new AssetMaintenance();
        $assets     = Asset::find()->where(['status' => 1])->orderBy('product_name')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $model->status = 1;
            if ((int)$model->payment_type === 1) {
                $model->payment_mode = null;
                $model->service_cost = null;
                $model->invoice_bill = null;
                $model->gpay_number  = null;
                $model->bank_number  = null;
            }
            // Validate first (it clears errors), then take the bill.
            if ($model->validate() && ((int)$model->payment_type === 1 || $this->attachMaintenanceBill($model)) && $model->save(false)) {
                Yii::$app->session->setFlash('success', 'Maintenance record added successfully.');
                $subKey = Yii::$app->request->post('sub_key', '1');
                if ($subKey === '0') {
                    return $this->redirect(['add-maintenance']);
                }
                return $this->redirect(['maintenance']);
            }
            Yii::$app->session->setFlash('error', 'Please fix the highlighted fields.');
        }

        return $this->render('_maintenance_form', compact('model', 'assets', 'developers'));
    }

    public function actionUpdateMaintenance(int $id): string|Response
    {
        $model      = $this->findMaintenance($id);
        $assets     = Asset::find()->where(['status' => 1])->orderBy('product_name')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $existingBill = $model->invoice_bill;

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            if ((int)$model->payment_type === 1) {
                $model->payment_mode = null;
                $model->service_cost = null;
                $model->gpay_number  = null;
                $model->bank_number  = null;
            }
            $model->invoice_bill = (int)$model->payment_type === 1 ? null : $existingBill;
            // Validate first (it clears errors), then take the bill.
            if ($model->validate() && ((int)$model->payment_type === 1 || $this->attachMaintenanceBill($model)) && $model->save(false)) {
                Yii::$app->session->setFlash('success', 'Maintenance record updated successfully.');
                return $this->redirect(['maintenance']);
            }
            Yii::$app->session->setFlash('error', 'Please fix the highlighted fields.');
        }

        return $this->render('_maintenance_form', compact('model', 'assets', 'developers'));
    }

    public function actionDeleteMaintenance(int $id): Response
    {
        $m = $this->findMaintenance($id);
        $m->status = 0;
        $m->save(false);
        Yii::$app->session->setFlash('success', 'Maintenance record deleted successfully.');
        return $this->redirect(['maintenance']);
    }

    /** AJAX: delete maintenance invoice bill */
    public function actionDeleteMaintenanceBill(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id = (int)Yii::$app->request->post('id');
        $m = AssetMaintenance::findOne(['id' => $id, 'status' => 1]);
        if ($m) {
            $m->invoice_bill = null;
            $m->save(false);
            return $this->asJson(['success' => true]);
        }
        return $this->asJson(['success' => false, 'message' => 'Record not found.']);
    }

    /** View single maintenance record detail */
    public function actionViewMaintenance(int $id): string
    {
        $model = $this->findMaintenance($id);
        return $this->render('view-maintenance', compact('model'));
    }

    // ─── Handover ─────────────────────────────────────────────────────────────

    public function actionHandover(): string
    {
        // One row per developer = the most recent handover. A bare GROUP BY dev_id
        // breaks under only_full_group_by (and is non-deterministic anyway), so
        // pick the latest id per dev_id via a subquery and load those rows.
        $latestIds = (new \yii\db\Query())
            ->select('MAX(id)')
            ->from('assets_handover')
            ->where(['status' => 1])
            ->groupBy('dev_id');

        $query = AssetHandover::find()
            ->with(['developer'])
            ->where(['id' => $latestIds])
            ->orderBy(['id' => SORT_DESC]);

        if ($v = Yii::$app->request->get('developer_id')) { $query->andWhere(['dev_id' => (int)$v]); }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 25]]);
        $developers   = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        return $this->render('handover', compact('dataProvider', 'developers'));
    }

    public function actionAddHandover(): string|Response
    {
        $model      = new AssetHandover();

        // Exclude assets that are currently handed over (active handover with no return date).
        // Matches the Yii1 actionAdd_assets_handover() logic.
        $activeAssetIds = [];
        $activeHandovers = (new \yii\db\Query())
            ->select('asset_id')
            ->from('assets_handover')
            ->where('status = 1 AND out_date IS NULL')
            ->all();
        foreach ($activeHandovers as $row) {
            $ids = !empty($row['asset_id']) ? json_decode($row['asset_id'], true) : [];
            if (is_array($ids)) {
                foreach ($ids as $aid) {
                    $aid = (int)$aid;
                    if ($aid > 0) { $activeAssetIds[$aid] = true; }
                }
            }
        }

        $assetQuery = Asset::find()->where(['status' => 1]);
        if (!empty($activeAssetIds)) {
            $assetQuery->andWhere(['not in', 'id', array_keys($activeAssetIds)]);
        }
        $assets     = $assetQuery->orderBy('product_name')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $locations  = AssetLocation::find()->where(['status' => 1])->orderBy('loc')->all();

        if (Yii::$app->request->isPost) {
            $post      = Yii::$app->request->post('AssetHandover', []);
            $assetIds  = (array)($post['asset_id'] ?? []);
            $assetIds  = array_filter(array_map('intval', $assetIds));

            if (empty($assetIds)) {
                Yii::$app->session->setFlash('error', 'Please select at least one asset.');
                return $this->render('_handover_form', compact('model', 'assets', 'developers', 'locations'));
            }

            if (($problem = $this->handoverAssetProblem($assetIds, null)) !== null) {
                Yii::$app->session->setFlash('error', $problem);
                return $this->render('_handover_form', compact('model', 'assets', 'developers', 'locations'));
            }

            $post['asset_id'] = json_encode(array_values($assetIds));
            $model->load(['AssetHandover' => $post]);
            $model->status = 1;

            if ($model->validate() && $model->save(false)) {
                Yii::$app->session->setFlash('success', 'Asset handover success.');
                $subKey = Yii::$app->request->post('sub_key', '1');
                if ($subKey === '0') {
                    return $this->redirect(['add-handover']);
                }
                return $this->redirect(['handover']);
            }
            if ($model->hasErrors()) {
                Yii::$app->session->setFlash('error', 'Failed to save handover: ' . implode(', ', $model->getFirstErrors()));
            }
        }

        return $this->render('_handover_form', compact('model', 'assets', 'developers', 'locations'));
    }

    public function actionUpdateHandover(int $id): string|Response
    {
        $model      = $this->findHandover($id);
        $assets     = Asset::find()->where(['status' => 1])->orderBy('product_name')->all();
        $developers = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();
        $locations  = AssetLocation::find()->where(['status' => 1])->orderBy('loc')->all();

        if (Yii::$app->request->isPost && $model->load(Yii::$app->request->post())) {
            $held = array_map('intval', (array)(json_decode((string)$model->getOldAttribute('asset_id'), true) ?: []));
            $ids = is_array($model->asset_id) ? $model->asset_id : (json_decode((string)$model->asset_id, true) ?: []);
            $ids = array_values(array_filter(array_map('intval', (array)$ids)));
            $model->asset_id = json_encode($ids);
            $problem = $ids ? $this->handoverAssetProblem($ids, (int)$model->id, $held) : 'Please select at least one asset.';
            if ($problem !== null) {
                $model->addError('asset_id', $problem);
            } elseif (!$model->out_date) {
                $model->addError('out_date', 'Return date is required.');
            } elseif ($model->validate()) {
                if ($model->save(false)) {
                    Yii::$app->session->setFlash('success', 'Handover updated successfully.');
                    return $this->redirect(['handover']);
                }
            }
            if ($model->hasErrors()) {
                Yii::$app->session->setFlash('error', 'Failed to update handover: ' . implode(', ', $model->getFirstErrors()));
            }
        }

        return $this->render('_handover_form', compact('model', 'assets', 'developers', 'locations'));
    }

    public function actionDeleteHandover(int $id): Response
    {
        $m = $this->findHandover($id);
        $m->status = 0;
        $m->save(false);

        $devId = Yii::$app->request->get('dev_id');
        $key   = Yii::$app->request->get('key', 1);

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return $this->asJson(['status' => 1, 'msg' => 'Handover record deleted successfully.']);
        }

        Yii::$app->session->setFlash('success', 'Handover record deleted successfully.');
        if ($devId) {
            return $this->redirect(['handover-history', 'dev_id' => $devId, 'key' => $key]);
        }
        return $this->redirect(['handover']);
    }

    /** Handover history for a specific developer */
    public function actionHandoverHistory(int $dev_id): string
    {
        $developer = Developer::findOne(['id' => $dev_id, 'status' => 1]);
        if (!$developer) throw new NotFoundHttpException('Employee not found.');

        $key = (int) Yii::$app->request->get('key', 1); // 1=Active Assets, 2=Handovered Assets

        $query = AssetHandover::find()
            ->where(['dev_id' => $dev_id, 'assets_handover.status' => 1])
            ->orderBy(['id' => SORT_DESC]);

        if ($key === 1) {
            $query->andWhere(['out_date' => null]);
        } elseif ($key === 2) {
            $query->andWhere(['IS NOT', 'out_date', null]);
        }

        $handovers = $query->all();
        return $this->render('handover-history', compact('developer', 'handovers', 'key'));
    }

    // ─── Barcode ──────────────────────────────────────────────────────────────

    public function actionBarcode(int $id): string
    {
        $model = $this->findAsset($id);
        return $this->render('barcode', compact('model'));
    }

    public function actionPrintMultiBarcode(): string|Response
    {
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['index']);
        }
        $ids = Yii::$app->request->post('id', []);
        if (empty($ids)) {
            Yii::$app->session->setFlash('error', 'No assets selected.');
            return $this->redirect(['index']);
        }
        $ids    = array_map('intval', (array)$ids);
        $models = Asset::find()->where(['id' => $ids, 'status' => 1])->all();
        if (empty($models)) {
            Yii::$app->session->setFlash('error', 'No matching assets found.');
            return $this->redirect(['index']);
        }
        return $this->render('multi-barcode', compact('models'));
    }

    /** AJAX: return asset model/brand JSON for a given asset_id */
    public function actionGetAssetValue(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $assetId = (int)Yii::$app->request->post('asset_id');
        $asset   = Asset::find()->with('brand')->where(['id' => $assetId, 'status' => 1])->one();
        if (!$asset) {
            return $this->asJson(['success' => false, 'message' => 'Asset not found.']);
        }
        return $this->asJson([
            'success' => true,
            'model'   => $asset->model ?? '',
            'brand'   => $asset->brand->brand_name ?? '',
        ]);
    }

    /** AJAX: search assets by barcode/name term */
    public function actionGetAssetList(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $term  = trim(Yii::$app->request->get('term', ''));
        $query = Asset::find()->where(['status' => 1])->orderBy('product_name')->limit(50);
        if ($term !== '') {
            $query->andWhere(['or', ['like', 'product_name', $term], ['like', 'barcode', $term]]);
        }
        $assets = $query->all();
        $data   = array_map(fn($a) => ['id' => $a->id, 'text' => $a->barcode . ' — ' . $a->product_name], $assets);
        return $this->asJson(['assets' => $data]);
    }

    // ─── Categories Master ────────────────────────────────────────────────────

    public function actionCategories(): string
    {
        $tab    = Yii::$app->request->get('tab', 'cat');
        $search = trim(Yii::$app->request->get('search', ''));
        $catFilter = (int)Yii::$app->request->get('cat_id', 0);
        $page   = max(1, (int)Yii::$app->request->get('page', 1));
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        // Build query based on active tab
        switch ($tab) {
            case 'brand':
                $query = AssetBrand::find()->where(['status' => 1]);
                if ($search) $query->andWhere(['like', 'brand_name', $search]);
                $query->orderBy(['id' => SORT_DESC]);
                break;
            case 'subcat':
                $query = AssetSubcategory::find()->alias('t')
                    ->joinWith('category c')
                    ->where(['t.status' => 1]);
                if ($search) $query->andWhere(['like', 't.name', $search]);
                if ($catFilter) $query->andWhere(['t.category_id' => $catFilter]);
                $query->orderBy(['t.id' => SORT_DESC]);
                break;
            case 'loc':
                $query = AssetLocation::find()->where(['status' => 1]);
                if ($search) $query->andWhere(['like', 'loc', $search]);
                $query->orderBy(['id' => SORT_DESC]);
                break;
            case 'tags':
                $query = AssetTag::find()->where(['status' => 1]);
                if ($search) $query->andWhere(['like', 'name', $search]);
                $query->orderBy(['id' => SORT_DESC]);
                break;
            default: // cat
                $tab = 'cat';
                $query = AssetCategory::find()->where(['status' => 1]);
                if ($search) $query->andWhere(['like', 'name', $search]);
                $query->orderBy(['id' => SORT_DESC]);
                break;
        }

        $total  = (int)$query->count();
        $models = $query->offset($offset)->limit($pageSize)->all();
        $pages  = (int)ceil($total / $pageSize);

        // Categories list for subcategory filter
        $allCategories = AssetCategory::find()->where(['status' => 1])->orderBy('name')->all();

        return $this->render('categories', compact(
            'tab', 'search', 'catFilter', 'models', 'total', 'page', 'pages', 'pageSize', 'allCategories'
        ));
    }

    public function actionSaveMaster(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $type = Yii::$app->request->post('type');
        $name = trim(Yii::$app->request->post('name', ''));
        $id   = (int)Yii::$app->request->post('id', 0);

        if (!$name) return $this->asJson(['success' => false, 'message' => 'Name is required.']);

        $now    = date('Y-m-d H:i:s');
        $userId = (int)Yii::$app->user->id;

        // The categories view uses short slugs (cat / subcat / brand / loc / tags),
        // matching actionCategories(). Accept the same names here so saving from
        // the modal actually works; long names kept as fallback for back-compat.
        switch ($type) {
            case 'brand':
                $m = $id ? AssetBrand::findOne(['id' => $id, 'status' => 1]) : new AssetBrand();
                if ($m === null) { break; }
                $m->brand_name = $name; $m->status = 1; $m->modified = $now; break;
            case 'cat':
            case 'category':
                $m = $id ? AssetCategory::findOne(['id' => $id, 'status' => 1]) : new AssetCategory();
                if ($m === null) { break; }
                $m->name = $name; $m->status = 1; $m->modified = $now; $m->modified_by = $userId; break;
            case 'subcat':
            case 'subcategory':
                $catId = (int)Yii::$app->request->post('cat_id', 0);
                if (!$catId || !AssetCategory::find()->where(['id' => $catId, 'status' => 1])->exists()) {
                    return $this->asJson(['success' => false, 'message' => 'Please select a parent category.']);
                }
                $m = $id ? AssetSubcategory::findOne(['id' => $id, 'status' => 1]) : new AssetSubcategory();
                if ($m === null) { break; }
                $m->name = $name;
                $m->category_id = $catId;
                $m->status = 1; $m->modified = $now; $m->modified_by = $userId; break;
            case 'loc':
            case 'location':
                $m = $id ? AssetLocation::findOne(['id' => $id, 'status' => 1]) : new AssetLocation();
                if ($m === null) { break; }
                $m->loc = $name; $m->status = 1; $m->modified = $now; break;
            case 'tags':
            case 'tag':
                $m = $id ? AssetTag::findOne(['id' => $id, 'status' => 1]) : new AssetTag();
                if ($m === null) { break; }
                $m->name = $name; $m->status = 1; $m->modified = $now; $m->modified_by = $userId; break;
            default:
                return $this->asJson(['success' => false, 'message' => 'Unknown type.']);
        }
        if ($m === null) {
            return $this->asJson(['success' => false, 'message' => 'That entry no longer exists. Reload the page.']);
        }

        if (!$m->validate()) {
            $errors = $m->getFirstErrors();
            return $this->asJson(['success' => false, 'message' => reset($errors)]);
        }

        if ($m->save(false)) return $this->asJson(['success' => true, 'id' => $m->id, 'name' => $name]);
        return $this->asJson(['success' => false, 'message' => 'Save failed.']);
    }

    public function actionDeleteMaster(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $type = Yii::$app->request->post('type');
        $id   = (int)Yii::$app->request->post('id');

        $inUse = false;
        // Same short slugs as the view (cat / subcat / brand / loc / tags).
        switch ($type) {
            case 'brand':
                $inUse = Asset::find()->where(['brand_id' => $id, 'status' => 1])->exists();
                if (!$inUse) { $m = AssetBrand::findOne($id); if ($m) { $m->status = 0; $m->save(false); } } break;
            case 'cat':
            case 'category':
                $inUse = Asset::find()->where(['asset_cat_id' => $id, 'status' => 1])->exists();
                if (!$inUse) { $m = AssetCategory::findOne($id); if ($m) { $m->status = 0; $m->save(false); } } break;
            case 'subcat':
            case 'subcategory':
                $inUse = Asset::find()->where(['asset_subcat_id' => $id, 'status' => 1])->exists();
                if (!$inUse) { $m = AssetSubcategory::findOne($id); if ($m) { $m->status = 0; $m->save(false); } } break;
            case 'loc':
            case 'location':
                $inUse = Asset::find()->where(['asset_loc' => $id, 'status' => 1])->exists();
                if (!$inUse) { $m = AssetLocation::findOne($id); if ($m) { $m->status = 0; $m->save(false); } } break;
            case 'tags':
            case 'tag':
                // Tags are stored as comma-separated IDs in assets.tags — check if any asset uses this tag
                $inUse = Asset::find()->where(['status' => 1])
                    ->andWhere(['or',
                        ['tags' => (string)$id],
                        ['like', 'tags', $id . ','],
                        ['like', 'tags', ',' . $id . ','],
                        ['like', 'tags', ',' . $id],
                    ])->exists();
                if (!$inUse) { $m = AssetTag::findOne($id); if ($m) { $m->status = 0; $m->save(false); } } break;
        }

        if ($inUse) return $this->asJson(['success' => false, 'message' => 'Cannot delete — item is in use by assets.']);
        return $this->asJson(['success' => true]);
    }

    /** AJAX: add a vendor inline */
    public function actionAddVendor(): Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $name    = trim(Yii::$app->request->post('name', ''));
        $gst     = trim(Yii::$app->request->post('gst_no', ''));
        $address = trim(Yii::$app->request->post('address', ''));

        if (!$name) return $this->asJson(['success' => false, 'message' => 'Vendor name is required.']);

        // Check duplicate name
        if (Vendor::find()->where(['company_name' => $name, 'status' => 1])->exists()) {
            return $this->asJson(['success' => false, 'message' => 'Vendor with this name already exists.']);
        }
        // Check duplicate GST
        if ($gst && Vendor::find()->where(['gst_no' => $gst, 'status' => 1])->exists()) {
            return $this->asJson(['success' => false, 'message' => 'Vendor with this GST number already exists.']);
        }

        $v = new Vendor();
        $v->company_name = $name;
        $v->gst_no       = $gst ?: null;
        $v->address      = $address ?: null;
        $v->status       = 1;
        $v->created_by   = (int)Yii::$app->user->id;
        $v->created_at   = date('Y-m-d H:i:s');

        if ($v->save(false)) {
            return $this->asJson(['success' => true, 'id' => $v->id, 'name' => $name]);
        }
        return $this->asJson(['success' => false, 'message' => 'Save failed.']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Normalize asset attributes before save — matches Yii1 controller logic */
    private function prepareAssetAttributes(Asset $model): void
    {
        // Ensure warranty/amc are integers (0 or 1)
        $model->warranty = (int)($model->warranty ?? 0);
        $model->amc      = (int)($model->amc ?? 0);

        // Tags: if posted as array (JS fallback), convert to comma-separated string
        if (is_array($model->tags)) {
            $model->tags = implode(',', array_filter($model->tags));
        }
        // Ensure tags is null when empty (not empty string)
        if (empty($model->tags)) {
            $model->tags = null;
        }

        // Nullify empty optional fields (matches Yii1 behavior of setting NULL)
        if (empty($model->bill)) { $model->bill = null; }
        if (empty($model->date_of_expire)) { $model->date_of_expire = null; }
        if (empty($model->service_contact)) { $model->service_contact = null; }
        if (empty($model->description)) { $model->description = null; }
        if (empty($model->model)) { $model->model = null; }
    }

    /**
     * Stores an uploaded maintenance bill (field "invoice_bill_file") on the model.
     * No file: keeps the current bill. Bad file: adds a form error and returns false.
     */
    private function attachMaintenanceBill(AssetMaintenance $model): bool
    {
        $file = UploadedFile::getInstanceByName('invoice_bill_file');
        if (!$file || $file->error === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        $ext = strtolower((string)$file->extension);
        if ($file->error !== UPLOAD_ERR_OK) {
            $model->addError('invoice_bill', 'The bill could not be uploaded. Please try again.');
            return false;
        }
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'], true)) {
            $model->addError('invoice_bill', 'Bill must be a PDF, image or Word file.');
            return false;
        }
        if ($file->size > 10 * 1024 * 1024) {
            $model->addError('invoice_bill', 'Bill must be 10 MB or smaller.');
            return false;
        }
        $dir = Yii::getAlias('@webroot/uploads/assets_maintenance/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = 'assets_maintenance' . uniqid() . '_' . time() . '.' . $ext;
        if (!is_dir($dir) || !is_writable($dir) || !@$file->saveAs($dir . $name)) {
            Yii::error('Maintenance bill save failed: ' . $dir . $name, 'asset');
            $model->addError('invoice_bill', 'The bill could not be saved. Please try again.');
            return false;
        }
        $model->invoice_bill = 'uploads/assets_maintenance/' . $name;
        return true;
    }

    /**
     * Null when every asset can be handed over: it exists, is active, and is not
     * out on another open handover (one without a return date). Otherwise a message.
     */
    private function handoverAssetProblem(array $assetIds, ?int $exceptHandoverId, array $alreadyHeld = []): ?string
    {
        $assetIds = array_values(array_unique(array_map('intval', $assetIds)));
        $names    = Asset::find()->select(['product_name', 'id'])->where(['id' => $assetIds])->andWhere(['or', ['status' => 1], ['id' => $alreadyHeld]])->indexBy('id')->column();
        if (count($names) !== count($assetIds)) {
            return 'One of the selected assets no longer exists. Reload the page and try again.';
        }
        $open = AssetHandover::find()->select(['id', 'asset_id'])->where(['status' => 1, 'out_date' => null]);
        if ($exceptHandoverId) {
            $open->andWhere(['!=', 'id', $exceptHandoverId]);
        }
        foreach ($open->asArray()->all() as $row) {
            $taken = array_map('intval', (array)(json_decode((string)$row['asset_id'], true) ?: []));
            foreach (array_intersect($assetIds, $taken) as $aid) {
                return 'Asset "' . $names[$aid] . '" is already assigned to an employee.';
            }
        }
        return null;
    }

    /** Handle asset file uploads (bill, warranty, amc, product image) */
    private function handleAssetUploads(Asset $model): void
    {
        $uploadMap = [
            'billupload'           => 1,
            'warrantyupload'       => 2,
            'amcupload'            => 3,
            'product_image_upload' => 4,
        ];

        $allowedExtensions = [
            1 => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'],
            2 => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'],
            3 => ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'],
            4 => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        ];
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        $uploadDir = Yii::getAlias('@webroot/uploads/assets_bill/');
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true)) {
            Yii::error("Cannot create upload directory: $uploadDir", 'asset');
            if (UploadedFile::getInstancesByName('billupload') || UploadedFile::getInstancesByName('warrantyupload') || UploadedFile::getInstancesByName('amcupload') || UploadedFile::getInstancesByName('product_image_upload')) {
                Yii::$app->session->setFlash('warning', 'The asset was saved, but its files could not be stored (upload folder unavailable). Please contact the administrator.');
            }
            return;
        }

        $rejected = [];
        foreach ($uploadMap as $inputName => $uploadType) {
            $files = UploadedFile::getInstancesByName($inputName);
            if (empty($files)) continue;
            foreach ($files as $file) {
                $ext = strtolower($file->extension);
                if (!in_array($ext, $allowedExtensions[$uploadType], true)) {
                    $rejected[] = $file->name . ' (file type not allowed)';
                    continue;
                }
                if ($file->size > $maxFileSize) {
                    $rejected[] = $file->name . ' (over 5 MB)';
                    continue;
                }
                $prefix = match($uploadType) {
                    1 => 'assets_bill',
                    2 => 'assets_warranty',
                    3 => 'assets_amc',
                    4 => 'assets_image',
                };
                $filename = $prefix . uniqid() . '_' . $model->id . '.' . $ext;
                try {
                    if (@$file->saveAs($uploadDir . $filename)) {
                        $upload = new AssetUpload();
                        $upload->asset_id    = $model->id;
                        $upload->type        = $ext;
                        $upload->path        = 'uploads/assets_bill/' . $filename;
                        $upload->upload_type = $uploadType;
                        $upload->modified    = date('Y-m-d H:i:s');
                        $upload->status      = 1;
                        $upload->save(false);
                    }
                } catch (\Throwable $e) {
                    Yii::error("Asset upload failed: {$e->getMessage()}", 'asset');
                }
            }
        }
        if ($rejected) {
            Yii::$app->session->setFlash('warning', 'Some files were not attached: ' . implode(', ', $rejected) . '.');
        }
    }

    private function formData(Asset $model): array
    {
        $subcats = $model->asset_cat_id
            ? AssetSubcategory::find()->where(['category_id' => $model->asset_cat_id, 'status' => 1])->orderBy('name')->all()
            : [];

        $uploads = !$model->isNewRecord
            ? AssetUpload::find()->where(['asset_id' => $model->id, 'status' => 1])->orderBy('upload_type')->all()
            : [];

        return [
            'model'      => $model,
            'categories' => AssetCategory::find()->where(['status' => 1])->orderBy('name')->all(),
            'subcats'    => $subcats,
            'brands'     => AssetBrand::find()->where(['status' => 1])->orderBy('brand_name')->all(),
            'locations'  => AssetLocation::find()->where(['status' => 1])->orderBy('loc')->all(),
            'vendors'    => Vendor::find()->where(['status' => 1])->orderBy('company_name')->all(),
            'tags'       => AssetTag::find()->where(['status' => 1])->orderBy('name')->all(),
            'uploads'    => $uploads,
        ];
    }

    private function findAsset(int $id): Asset
    {
        $m = Asset::findOne(['id' => $id, 'status' => 1]);
        if (!$m) throw new NotFoundHttpException('Asset not found.');
        return $m;
    }

    private function findMaintenance(int $id): AssetMaintenance
    {
        $m = AssetMaintenance::findOne(['id' => $id, 'status' => 1]);
        if (!$m) throw new NotFoundHttpException('Maintenance record not found.');
        return $m;
    }

    private function findHandover(int $id): AssetHandover
    {
        $m = AssetHandover::findOne(['id' => $id, 'status' => 1]);
        if (!$m) throw new NotFoundHttpException('Handover record not found.');
        return $m;
    }
}
