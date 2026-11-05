<?php

namespace backend\controllers;

use backend\assets\InvoiceAsset;
use backend\models\BankDetail;
use Yii;
use yii\data\ActiveDataProvider;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class BankDetailController extends Controller
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['set-default' => ['POST'], 'delete' => ['POST']]],
        ]);
    }

    /** Full pages get the Invoices module stylesheet and script. */
    public function render($view, $params = []): string
    {
        InvoiceAsset::register($this->getView());
        return parent::render($view, $params);
    }

    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query'      => BankDetail::find()->where(['status' => 1])->orderBy(['is_default' => SORT_DESC, 'bank_name' => SORT_ASC]),
            'pagination' => ['pageSize' => 20],
        ]);
        return $this->render('index', ['dataProvider' => $dataProvider]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate(): string|Response
    {
        $model = new BankDetail(['status' => 1, 'is_default' => BankDetail::defaultId() ? 0 : 1]);
        return $this->save($model, 'Bank account added.') ?? $this->render('create', compact('model'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);
        return $this->save($model, 'Bank account updated.') ?? $this->render('update', compact('model'));
    }

    public function actionSetDefault(int $id): Response
    {
        $model = $this->findModel($id);
        $this->makeDefault($model);
        Yii::$app->session->setFlash('success', $model->bank_name . ' is now the default account.');
        return $this->redirect(['index']);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $model->updateAttributes(['status' => 0, 'is_default' => 0]);
        Yii::$app->session->setFlash('success', $model->bank_name . ' account removed. Invoices that already print it keep it.');
        return $this->redirect(['index']);
    }

    /** Validate + save from POST; null means "show the form". */
    private function save(BankDetail $model, string $done): ?Response
    {
        $post = Yii::$app->request->post('BankDetail');
        if (!Yii::$app->request->isPost || !is_array($post)) {
            return null;
        }
        // Only the editable fields; status and id never come from the form.
        $model->setAttributes(array_intersect_key($post, array_flip(['acc_name', 'acc_no', 'bank_name', 'branch', 'ifsc', 'swift', 'is_default'])));
        if (!$model->validate()) {
            return null;
        }
        $tx = Yii::$app->db->beginTransaction();
        try {
            $model->save(false);
            if ((int)$model->is_default === 1) {
                $this->makeDefault($model);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        Yii::$app->session->setFlash('success', $done);
        return $this->redirect(['index']);
    }

    /** Exactly one default account. */
    private function makeDefault(BankDetail $model): void
    {
        BankDetail::updateAll(['is_default' => 0], ['and', ['status' => 1], ['<>', 'id', $model->id]]);
        $model->updateAttributes(['is_default' => 1]);
    }

    private function findModel(int $id): BankDetail
    {
        $model = BankDetail::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Bank account not found.');
        }
        return $model;
    }
}
