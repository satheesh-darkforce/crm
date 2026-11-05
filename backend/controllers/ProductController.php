<?php

namespace backend\controllers;

use backend\assets\ProductAsset;
use backend\components\ImageUpload;
use backend\models\Product;
use backend\models\ProductCategory;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Product Management — the sales catalogue of products and services
 * (artificial grass, vertical gardens, plants, installation work …).
 *
 * Items live in the shared item master `delivery_product_details`; categories
 * in `product_category`. Every change is a soft change (status / is_active).
 */
class ProductController extends Controller
{
    const PAGE_SIZE = 20;

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class'   => VerbFilter::class,
                'actions' => [
                    'delete'          => ['POST'],
                    'toggle-active'   => ['POST'],
                    'remove-image'    => ['POST'],
                    'category-save'   => ['POST'],
                    'category-delete' => ['POST'],
                ],
            ],
        ]);
    }

    /** Full pages get the Products module stylesheet and script. */
    public function render($view, $params = []): string
    {
        ProductAsset::register($this->getView());
        return parent::render($view, $params);
    }

    // ─── Catalogue ──────────────────────────────────────────────────────────

    public function actionIndex(): string
    {
        $req    = Yii::$app->request;
        $q      = $this->cleanText($req->get('q'));
        $cat    = (int)$req->get('category_id');
        $type   = (int)$req->get('item_type');
        $active = $req->get('is_active');
        $active = in_array($active, ['0', '1'], true) ? $active : '';

        $query = Product::find()->alias('p')->with('category')->where(['p.status' => 1]);
        if ($q !== '') {
            $query->andWhere(['or',
                ['like', 'p.item_name', $q],
                ['like', 'p.product_code', $q],
                ['like', 'p.hsn_code', $q],
                ['like', 'p.short_description', $q],
            ]);
        }
        if ($cat > 0) {
            $query->andWhere(['p.category_id' => $cat]);
        } elseif ($cat === -1) {
            $query->andWhere(['p.category_id' => null]);
        }
        if (isset(Product::typeOptions()[$type])) {
            $query->andWhere(['p.item_type' => $type]);
        }
        if ($active !== '') {
            $query->andWhere(['p.is_active' => (int)$active]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => self::PAGE_SIZE],
            'sort'       => [
                'defaultOrder' => ['item_name' => SORT_ASC],
                'attributes'   => [
                    'item_name'    => ['asc' => ['p.item_name' => SORT_ASC], 'desc' => ['p.item_name' => SORT_DESC], 'label' => 'Name'],
                    'product_code' => ['asc' => ['p.product_code' => SORT_ASC], 'desc' => ['p.product_code' => SORT_DESC], 'label' => 'Code'],
                    'sale_price'   => ['asc' => ['p.sale_price' => SORT_ASC], 'desc' => ['p.sale_price' => SORT_DESC], 'label' => 'Unit price'],
                    'updated_at'   => ['asc' => ['p.updated_at' => SORT_ASC], 'desc' => ['p.updated_at' => SORT_DESC], 'label' => 'Last updated'],
                ],
            ],
        ]);

        $counts = Product::find()->where(['status' => 1])
            ->select(['total' => 'COUNT(*)', 'active' => 'SUM(is_active = 1)', 'services' => 'SUM(item_type = 2)', 'uncategorised' => 'SUM(category_id IS NULL)'])
            ->asArray()->one();

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'categories'   => ProductCategory::options(),
            'counts'       => array_map('intval', $counts ?: []),
            'filters'      => ['q' => $q, 'category_id' => $cat, 'item_type' => $type, 'is_active' => $active],
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate(): string|Response
    {
        $model = new Product();
        $model->item_type    = Product::TYPE_PRODUCT;
        $model->is_active    = 1;
        $model->gst_rate     = '18';
        $model->product_code = Product::suggestCode();
        if ($cat = (int)Yii::$app->request->get('category_id')) {
            $model->category_id = $cat;
        }
        return $this->saveForm($model, 'create', 'Product added.');
    }

    public function actionUpdate(int $id): string|Response
    {
        return $this->saveForm($this->findModel($id), 'update', 'Product updated.');
    }

    /** Soft delete. Items already on delivery challans stay on those documents. */
    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $model->updateAttributes(['status' => 0, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => (int)Yii::$app->user->id]);
        Yii::$app->session->setFlash('success', '"' . $model->item_name . '" was removed from the catalogue.');
        return $this->redirect(['index']);
    }

    /** Active ⇄ inactive. Inactive items stay in the catalogue but cannot be picked on new documents. */
    public function actionToggleActive(int $id): Response
    {
        $model = $this->findModel($id);
        $model->updateAttributes([
            'is_active'  => (int)$model->is_active ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => (int)Yii::$app->user->id,
        ]);
        Yii::$app->session->setFlash('success', '"' . $model->item_name . '" is now ' . ($model->is_active ? 'active' : 'inactive') . '.');
        return $this->redirect(Yii::$app->request->post('back') === 'index' ? ['index'] : ['view', 'id' => $model->id]);
    }

    public function actionRemoveImage(int $id): Response
    {
        $model = $this->findModel($id);
        $model->updateAttributes(['image' => null, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => (int)Yii::$app->user->id]);
        Yii::$app->session->setFlash('success', 'Image removed.');
        return $this->redirect(['update', 'id' => $model->id]);
    }

    /**
     * JSON picker for quotation / invoice lines: active items matching ?q=.
     * Returns at most 20 rows.
     */
    public function actionSearch(): Response
    {
        $q = $this->cleanText(Yii::$app->request->get('q'));
        $query = Product::find()->with('category')->where(['status' => 1, 'is_active' => 1])->orderBy(['item_name' => SORT_ASC])->limit(20);
        if ($q !== '') {
            $query->andWhere(['or', ['like', 'item_name', $q], ['like', 'product_code', $q]]);
        }
        return $this->asJson(['results' => array_map(fn(Product $p) => $p->toPickerArray(), $query->all())]);
    }

    // ─── Categories ─────────────────────────────────────────────────────────

    public function actionCategories(): string
    {
        $categories = ProductCategory::find()->where(['status' => 1])->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])->all();
        $counts = Product::find()->select(['n' => 'COUNT(*)', 'category_id'])->where(['status' => 1])
            ->andWhere(['not', ['category_id' => null]])->groupBy('category_id')->indexBy('category_id')->column();
        $edit = null;
        if ($id = (int)Yii::$app->request->get('edit')) {
            $edit = $this->findCategory($id);
        }
        return $this->render('categories', [
            'categories' => $categories,
            'counts'     => array_map('intval', $counts),
            'model'      => $edit ?? new ProductCategory(),
        ]);
    }

    public function actionCategorySave(): Response|string
    {
        $id    = (int)Yii::$app->request->post('id');
        $model = $id ? $this->findCategory($id) : new ProductCategory();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Category "' . $model->name . '" ' . ($id ? 'updated.' : 'added.'));
            return $this->redirect(['categories']);
        }
        $categories = ProductCategory::find()->where(['status' => 1])->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])->all();
        $counts = Product::find()->select(['n' => 'COUNT(*)', 'category_id'])->where(['status' => 1])
            ->andWhere(['not', ['category_id' => null]])->groupBy('category_id')->indexBy('category_id')->column();
        return $this->render('categories', ['categories' => $categories, 'counts' => array_map('intval', $counts), 'model' => $model]);
    }

    /** Blocked while live products still use the category. */
    public function actionCategoryDelete(int $id): Response
    {
        $model = $this->findCategory($id);
        $n = $model->getProductCount();
        if ($n > 0) {
            Yii::$app->session->setFlash('error', '"' . $model->name . '" still has ' . $n . ' product' . ($n === 1 ? '' : 's')
                . '. Move them to another category first.');
            return $this->redirect(['categories']);
        }
        $model->updateAttributes(['status' => 0, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => (int)Yii::$app->user->id]);
        Yii::$app->session->setFlash('success', 'Category "' . $model->name . '" removed.');
        return $this->redirect(['categories']);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function saveForm(Product $model, string $view, string $done): string|Response
    {
        $req = Yii::$app->request;
        if ($req->isPost && $model->load($req->post())) {
            $post = $req->post('Product', []);
            $model->featureList = isset($post['featureList']) && is_array($post['featureList']) ? $post['featureList'] : [];
            $model->specList    = isset($post['specList']) && is_array($post['specList']) ? $post['specList'] : [];

            if ($model->validate() && $this->attachImage($model)) {
                $model->save(false);
                Yii::$app->session->setFlash('success', $done);
                if ($req->post('after') === 'new') {
                    return $this->redirect(['create', 'category_id' => $model->category_id]);
                }
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }
        return $this->render($view, ['model' => $model, 'categories' => ProductCategory::options()]);
    }

    /**
     * Stores an uploaded image (field "image_file"). No file keeps the current one;
     * a file that is not a real JPG/PNG/WebP adds a form error.
     */
    private function attachImage(Product $model): bool
    {
        $file = UploadedFile::getInstanceByName('image_file');
        if (!$file || $file->error === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        $ext = strtolower((string)$file->extension);
        if ($file->error !== UPLOAD_ERR_OK || !in_array($ext, ImageUpload::ALLOWED_EXTENSIONS, true)
            || $file->size > ImageUpload::MAX_BYTES || @getimagesize($file->tempName) === false) {
            $model->addError('image', 'Upload a JPG, PNG or WebP image up to 5 MB.');
            return false;
        }
        $saved = null;
        $rejected = ImageUpload::save('image_file', 'products', 'product', function (string $path) use (&$saved) {
            $saved = $path;
        });
        if ($rejected || $saved === null) {
            $model->addError('image', 'The image could not be saved. Please try again.');
            return false;
        }
        $model->image = $saved;
        return true;
    }

    private function cleanText($v): string
    {
        return trim(mb_substr((string)(is_scalar($v) ? $v : ''), 0, 100));
    }

    private function findModel(int $id): Product
    {
        $m = Product::findOne(['id' => $id, 'status' => 1]);
        if (!$m) {
            throw new NotFoundHttpException('Product not found.');
        }
        return $m;
    }

    private function findCategory(int $id): ProductCategory
    {
        $m = ProductCategory::findOne(['id' => $id, 'status' => 1]);
        if (!$m) {
            throw new NotFoundHttpException('Category not found.');
        }
        return $m;
    }
}
