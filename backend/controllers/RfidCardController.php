<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\RfidCard;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class RfidCardController extends Controller
{
    /** Full pages get the HR module stylesheet and script. */
    public function render($view, $params = []): string
    {
        \backend\assets\HrAsset::register($this->getView());
        return parent::render($view, $params);
    }

    public function actionIndex(): string
    {
        $query = RfidCard::find()->with('developer')->where(['>=', 'rfid_cards.status', 0])->orderBy('id DESC');

        if ($search = Yii::$app->request->get('search')) {
            $query->andWhere(['like', 'code', $search]);
        }
        $statusFilter = Yii::$app->request->get('status');
        if ($statusFilter !== null && $statusFilter !== '') {
            $query->andWhere(['rfid_cards.status' => (int)$statusFilter]);
        }
        if ($devId = Yii::$app->request->get('developer_id')) {
            $query->andWhere(['rfid_cards.developer_id' => (int)$devId]);
        }

        $dataProvider = new ActiveDataProvider(['query' => $query, 'pagination' => ['pageSize' => 20]]);
        $developers   = Developer::find()->where(['status' => 1])->orderBy('developer_name')->all();

        // Map: developer_id => card_id for every active assignment. The assign
        // modal uses this to filter out developers already assigned to a
        // different card (while still allowing the card's own current dev).
        $devToCard = [];
        foreach (RfidCard::find()->where(['status' => 1])->andWhere(['IS NOT', 'developer_id', null])->all() as $c) {
            $devToCard[(int)$c->developer_id] = (int)$c->id;
        }

        return $this->render('index', compact('dataProvider', 'developers', 'devToCard'));
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate(): string|Response
    {
        $model = new RfidCard();
        $model->status     = 1;
        $model->created_at = date('Y-m-d H:i:s');
        $model->created_by = Yii::$app->user->id;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'RFID card added.');
            return $this->redirect(['index']);
        }

        return $this->render('_form', compact('model'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);
        $model->updated_at = date('Y-m-d H:i:s');
        $model->updated_by = Yii::$app->user->id;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'RFID card updated.');
            return $this->redirect(['index']);
        }

        return $this->render('_form', compact('model'));
    }

    /**
     * Soft-delete an RFID card with a mandatory reason (captured in a modal).
     * Yii1 parity: rfid_cards table has no `delete_reason` column, so the
     * reason is appended to the `code` value (mirrors Yii1
     * RfidcardsController::actionDelete which appends " - deleted @<date>").
     * Sets status = -1. Returns JSON for AJAX callers.
     */
    public function actionDelete(int $id): array|Response
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $reason = trim((string) Yii::$app->request->post('reason', ''));
        if ($reason === '') {
            return ['success' => false, 'message' => 'Please provide a reason for deleting this card.'];
        }

        $model = $this->findModel($id);

        if ($model->developer_id) {
            return ['success' => false, 'message' => 'Cannot delete an assigned card. Unassign it first.'];
        }

        $deletedSuffix = ' - deleted @' . date('Y-m-d H:i:s') . ' [' . $reason . ']';
        $model->code       = mb_substr($model->code . $deletedSuffix, 0, 100);
        $model->status     = -1;
        $model->updated_at = date('Y-m-d H:i:s');
        $model->updated_by = Yii::$app->user->id;
        $model->save(false);

        return ['success' => true, 'message' => 'RFID card deleted.'];
    }

    public function actionToggleStatus(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $model = RfidCard::findOne($id);
        if (!$model || $model->status < 0) {
            throw new NotFoundHttpException('RFID card not found.');
        }

        $model->status     = $model->status == 1 ? 0 : 1;
        $model->updated_at = date('Y-m-d H:i:s');
        $model->updated_by = Yii::$app->user->id;
        $model->save(false);

        Yii::$app->session->setFlash('success', 'Card status changed to ' . ($model->status ? 'Active' : 'Inactive') . '.');
        return $this->redirect(['index']);
    }

    public function actionAssign(int $id): string|Response
    {
        $model = $this->findModel($id);

        // Get developers not already assigned to another card (excluding current card's assignment)
        $assignedDevIds = RfidCard::find()
            ->select('developer_id')
            ->where(['status' => 1])
            ->andWhere(['IS NOT', 'developer_id', null])
            ->andWhere(['!=', 'id', $id])
            ->column();

        $devQuery = Developer::find()->where(['status' => 1])->orderBy('developer_name');
        if (!empty($assignedDevIds)) {
            $devQuery->andWhere(['NOT IN', 'id', $assignedDevIds]);
        }
        $developers = $devQuery->all();

        // Also include the currently assigned developer if any (so it shows as selected)
        if ($model->developer_id && $model->developer) {
            $found = false;
            foreach ($developers as $d) {
                if ($d->id == $model->developer_id) { $found = true; break; }
            }
            if (!$found) {
                array_unshift($developers, $model->developer);
            }
        }

        if (Yii::$app->request->isPost) {
            $developerId = Yii::$app->request->post('developer_id');
            $model->developer_id  = $developerId ?: null;
            $model->assigned_from = $developerId ? date('Y-m-d H:i:s') : null;
            $model->updated_at    = date('Y-m-d H:i:s');
            $model->updated_by    = Yii::$app->user->id;
            $model->save(false);
            Yii::$app->session->setFlash('success', $developerId ? 'Card assigned.' : 'Card unassigned.');
            return $this->redirect(['index']);
        }

        return $this->render('assign', compact('model', 'developers'));
    }

    private function findModel(int $id): RfidCard
    {
        $model = RfidCard::find()->where(['id' => $id])->andWhere(['>=', 'status', 0])->one();
        if (!$model) {
            throw new NotFoundHttpException('RFID card not found.');
        }
        return $model;
    }
}
