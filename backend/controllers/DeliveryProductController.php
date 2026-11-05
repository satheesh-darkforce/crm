<?php

namespace backend\controllers;

use backend\assets\DeliveryAsset;
use backend\models\DeliveryProduct;
use common\components\UserAccess;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Delivery product catalogue (ported from delivery's ProductController).
 *
 * Controller id: `delivery-product`.
 * Table: `delivery_product_details` (renamed from delivery.product_details).
 */
class DeliveryProductController extends Controller
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'delete' => ['POST'], 'ajax-create' => ['POST'], 'ajax-get' => ['POST'],
            ]],
        ]);
    }

    /**
     * The ajax-* helpers are in every developer's default routes (the DC forms use them),
     * so they also need the right to manage products or delivery challans.
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $any = fn(array $routes) => (bool)array_filter($routes, fn($r) => UserAccess::checkAccess($r));
        if ($action->id === 'ajax-create' && !$any(['delivery-product/create', 'dc/create', 'dc/update', 'dc/create-inward', 'dc/add-return'])) {
            throw new ForbiddenHttpException('You do not have permission to add products.');
        }
        if (in_array($action->id, ['ajax-list', 'ajax-get'], true) && !$any(['delivery-product/index', 'dc/create', 'dc/update', 'dc/create-inward', 'dc/add-return'])) {
            throw new ForbiddenHttpException('You do not have permission to view products.');
        }
        return true;
    }

    /** Full pages get the Delivery / PO / Quotation module assets. */
    public function render($view, $params = []): string
    {
        DeliveryAsset::register($this->getView());
        return parent::render($view, $params);
    }

    public function actionIndex(): string
    {
        $q = trim(mb_substr((string)(is_scalar(Yii::$app->request->get('q')) ? Yii::$app->request->get('q') : ''), 0, 100));
        $query = DeliveryProduct::find()->where(['status' => 1]);
        if ($q !== '') {
            $query->andWhere(['or', ['like', 'item_name', $q], ['like', 'hsn_code', $q]]);
        }
        $dataProvider = new ActiveDataProvider([
            'query' => $query, 'pagination' => ['pageSize' => 25], 'sort' => ['defaultOrder' => ['item_name' => SORT_ASC]],
        ]);
        return $this->render('index', compact('dataProvider', 'q'));
    }

    /** Create a product. An optional `dc_id` returns to that DC's form after saving. */
    public function actionCreate(): string|Response
    {
        $model = new DeliveryProduct(['status' => 1, 'units' => 'Nos']);
        if ($this->saveForm($model)) {
            Yii::$app->session->setFlash('success', 'Product "' . $model->item_name . '" added.');
            $dcId = (int)Yii::$app->request->post('dc_id', 0);
            return $dcId > 0 ? $this->redirect(['/dc/update', 'id' => $dcId]) : $this->redirect(['index']);
        }
        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);
        if ($this->saveForm($model)) {
            Yii::$app->session->setFlash('success', 'Product "' . $model->item_name . '" updated.');
            return $this->redirect(['index']);
        }
        return $this->render('update', ['model' => $model]);
    }

    /** Soft delete (status=0). JSON for AJAX callers, redirect with a flash otherwise. */
    public function actionDelete(): Response
    {
        $id    = (int)(Yii::$app->request->post('id') ?: Yii::$app->request->get('id'));
        $model = $this->findModel($id);
        $model->updateAttributes(['status' => 0]);
        $msg = 'Product "' . $model->item_name . '" removed. Challans that already list it keep it.';
        if (Yii::$app->request->isAjax) {
            return $this->asJson(['success' => true, 'message' => $msg]);
        }
        Yii::$app->session->setFlash('success', $msg);
        return $this->redirect(['index']);
    }

    /** AJAX: add a product inline from the DC form. */
    public function actionAjaxCreate(): Response
    {
        $req   = Yii::$app->request;
        $model = new DeliveryProduct([
            'item_name' => trim((string)$req->post('productname', '')),
            'hsn_code'  => trim((string)$req->post('hsn', '')),
            'units'     => trim((string)$req->post('unit', '')) ?: 'Nos',
            'status'    => 1,
        ]);
        if (!$model->save()) {
            $msg = implode(' ', $model->getErrorSummary(false));
            return $this->asJson(['success' => false, 'msg' => $msg, 'message' => $msg]);
        }
        return $this->asJson(['success' => true, 'msg' => 'Product added.', 'message' => 'Product added.', 'id' => $model->id]);
    }

    /** AJAX: list active products (DC form multiselect). */
    public function actionAjaxList(): Response
    {
        return $this->asJson(['success' => true, 'data' => DeliveryProduct::find()->where(['status' => 1])->orderBy('item_name')->asArray()->all()]);
    }

    /** AJAX: a batch of products by id (DC form preview). */
    public function actionAjaxGet(): Response
    {
        $ids = array_filter(array_map('intval', (array)Yii::$app->request->post('product_id', [])));
        if (!$ids) {
            return $this->asJson(['success' => false, 'data' => []]);
        }
        return $this->asJson(['success' => true, 'data' => DeliveryProduct::find()->where(['status' => 1, 'id' => $ids])->asArray()->all()]);
    }

    /** Only the form fields; status never comes from the browser. */
    private function saveForm(DeliveryProduct $model): bool
    {
        $post = Yii::$app->request->post('DeliveryProduct');
        if (!Yii::$app->request->isPost || !is_array($post)) {
            return false;
        }
        $model->setAttributes(array_intersect_key($post, array_flip(['item_name', 'hsn_code', 'units', 'serial_no'])));
        return $model->save();
    }

    private function findModel(int $id): DeliveryProduct
    {
        $m = DeliveryProduct::findOne(['id' => $id, 'status' => 1]);
        if (!$m) {
            throw new NotFoundHttpException('Product not found.');
        }
        return $m;
    }
}
