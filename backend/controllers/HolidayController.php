<?php

namespace backend\controllers;

use backend\models\Developer;
use backend\models\Holiday;
use backend\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class HolidayController extends Controller
{
    /** Full pages get the HR module stylesheet and script. */
    public function render($view, $params = []): string
    {
        \backend\assets\HrAsset::register($this->getView());
        return parent::render($view, $params);
    }

    /**
     * Holiday listing with inline create form — matches Yii1 holidayssetup/admin.
     */
    public function actionIndex(): string|Response
    {
        $model       = new Holiday();
        $model->status = 1;

        // Handle inline create form POST
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post('Holiday', []);
            $model->date   = $post['date'] ?? '';
            $model->reason = trim($post['reason'] ?? '');
            $model->status = 1;

            // Employee multi-select: JSON-encode the array of user IDs
            $employeeIds = $post['employee_id'] ?? [];
            $model->employee_id = !empty($employeeIds) ? json_encode($employeeIds) : null;

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Holiday added successfully');
                return $this->redirect(['index']);
            }
        }

        // Listing query
        $query = Holiday::find()
            ->where(['status' => 1])
            ->orderBy(['date' => SORT_DESC]);

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 50],
        ]);

        // Developers for employee multi-select
        $developers = Developer::find()
            ->alias('d')
            ->joinWith('user u')
            ->where(['d.status' => 1, 'u.status' => 1, 'u.usertype' => User::TYPE_DEVELOPER])
            ->orderBy('d.developer_name')
            ->all();

        return $this->render('index', compact('dataProvider', 'model', 'developers'));
    }

    /**
     * View holiday detail — renders in modal (matches Yii1 holidayssetup/view).
     */
    public function actionView(int $id): string
    {
        $model = $this->findModel($id);

        // Resolve employee names from JSON user IDs (employee_id stores user.user_id values)
        $employeeNames = [];
        if (!empty($model->employee_id)) {
            $ids = json_decode($model->employee_id, true);
            if (is_array($ids) && !empty($ids)) {
                // Prefer developer_name (matches the create form); fall back to username
                $names = Developer::find()
                    ->select('developer_name')
                    ->where(['user_id' => $ids])
                    ->indexBy('user_id')
                    ->column();
                if (empty($names)) {
                    $names = User::find()
                        ->select('username')
                        ->where(['user_id' => $ids])
                        ->indexBy('user_id')
                        ->column();
                }
                foreach ($ids as $id) {
                    if (isset($names[$id])) {
                        $employeeNames[] = $names[$id];
                    }
                }
            }
        }

        if (Yii::$app->request->isAjax) {
            return $this->renderPartial('_view', compact('model', 'employeeNames'));
        }
        return $this->render('_view', compact('model', 'employeeNames'));
    }

    public function actionUpdate(int $id): string|Response
    {
        $model = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post('Holiday', []);
            $model->date   = $post['date'] ?? $model->date;
            $model->reason = trim($post['reason'] ?? $model->reason);

            $employeeIds = $post['employee_id'] ?? [];
            $model->employee_id = !empty($employeeIds) ? json_encode($employeeIds) : null;

            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Holiday updated successfully.');
                return $this->redirect(['index']);
            }
        }

        $developers = Developer::find()
            ->alias('d')
            ->joinWith('user u')
            ->where(['d.status' => 1, 'u.status' => 1, 'u.usertype' => User::TYPE_DEVELOPER])
            ->orderBy('d.developer_name')
            ->all();

        return $this->render('update', compact('model', 'developers'));
    }

    public function actionDelete(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException();
        }
        $model = $this->findModel($id);
        $model->status = 0;
        $model->save(false);
        Yii::$app->session->setFlash('success', 'Holiday deleted.');
        return $this->redirect(['index']);
    }

    private function findModel(int $id): Holiday
    {
        $model = Holiday::findOne(['id' => $id, 'status' => 1]);
        if (!$model) {
            throw new NotFoundHttpException('Holiday not found.');
        }
        return $model;
    }
}
