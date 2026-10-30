<?php

namespace backend\controllers;

use backend\models\DeliveryProduct;
use Yii;
use yii\helpers\ArrayHelper;
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
    /** List active products with optional single-product filter. */
    public function actionIndex(): string
    {
        $model = new DeliveryProduct();
        $selectedId = Yii::$app->request->isPost ? (int) Yii::$app->request->post('item_id') : null;

        $products = $model->getAllProducts($selectedId);

        $selectData = ArrayHelper::map(
            DeliveryProduct::find()->where(['status' => 1])->asArray()->all(),
            'id', 'item_name'
        );

        return $this->render('index', [
            'products' => $products,
            'data' => $selectData,
            'selected' => $selectedId,
        ]);
    }

    /** Create a new product. Accepts optional `id` (DC id) to return to DC form after save. */
    public function actionCreate(): string|Response
    {
        $model = new DeliveryProduct();

        if ($model->load(Yii::$app->request->post())) {
            $dcId = (int) Yii::$app->request->post('dc_id', 0);

            $nameExists = DeliveryProduct::find()
                ->where(['status' => 1])
                ->andWhere('LOWER(item_name) = :name', [':name' => mb_strtolower((string)$model->item_name)])
                ->one();

            if ($nameExists) {
                Yii::$app->session->setFlash('error', 'Product Name Already Exists');
                return $this->render('create', ['model' => $model]);
            }

            $model->status = 1;
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Product added successfully');
                return $dcId > 0
                    ? $this->redirect(['/dc/update', 'id' => $dcId])
                    : $this->redirect(['index']);
            }

            Yii::$app->session->setFlash('error', 'Something went wrong while saving the product.');
        }

        return $this->render('create', ['model' => $model]);
    }

    /** Update a product. */
    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Product updated successfully');
            return $this->redirect(['index']);
        }

        return $this->render('update', ['model' => $model]);
    }

    /**
     * Delete a product (soft-delete: status=0, matches project convention — never hard delete rows).
     * Responds with JSON for AJAX callers and falls back to redirect-with-flash for direct GET hits.
     */
    public function actionDelete(): Response|array
    {
        $id = (int) (Yii::$app->request->post('id') ?: Yii::$app->request->get('id'));
        $isAjax = Yii::$app->request->isAjax || Yii::$app->request->isPost;

        $model = $this->findModel($id);
        $model->status = 0;
        $model->save(false);

        if ($isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['success' => true, 'message' => 'Product deleted.'];
        }
        Yii::$app->session->setFlash('success', 'Product deleted.');
        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }

    /** AJAX: JSON-add a product inline (used by DC form). */
    public function actionAjaxCreate(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $req = Yii::$app->request;

        $itemName = trim((string) $req->post('productname', ''));
        if ($itemName === '') {
            return ['success' => false, 'msg' => 'Product name is required.'];
        }

        $exists = DeliveryProduct::find()
            ->where(['status' => 1])
            ->andWhere('LOWER(item_name) = :name', [':name' => mb_strtolower($itemName)])
            ->exists();
        if ($exists) {
            return ['success' => false, 'msg' => 'Product already exists.'];
        }

        $model = new DeliveryProduct([
            'item_name' => $itemName,
            'hsn_code'  => (string) $req->post('hsn', ''),
            'units'     => (string) $req->post('unit', ''),
            'status'    => 1,
        ]);

        if ($model->save()) {
            return ['success' => true, 'msg' => 'Product added successfully', 'id' => $model->id];
        }
        return ['success' => false, 'msg' => 'Failed to save product.'];
    }

    /** AJAX: list active products (for DC form multiselect). */
    public function actionAjaxList(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return [
            'success' => true,
            'data' => DeliveryProduct::find()->where(['status' => 1])->asArray()->all(),
        ];
    }

    /** AJAX: look up a batch of products by id list (for DC form preview). */
    public function actionAjaxGet(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $ids = Yii::$app->request->post('product_id', []);
        if (empty($ids)) {
            return ['success' => false, 'data' => []];
        }
        return [
            'success' => true,
            'data' => DeliveryProduct::find()->where(['status' => 1, 'id' => $ids])->asArray()->all(),
        ];
    }

    private function findModel(int $id): DeliveryProduct
    {
        $m = DeliveryProduct::findOne($id);
        if (!$m) {
            throw new NotFoundHttpException('Product not found.');
        }
        return $m;
    }
}
