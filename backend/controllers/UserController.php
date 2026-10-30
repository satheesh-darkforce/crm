<?php

namespace backend\controllers;

use backend\models\User;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backend User account management.
 * Allows admins to manage user accounts (reset passwords, toggle status, etc.)
 */
class UserController extends Controller
{
    public function actionIndex(): string
    {
        $query = User::find()->orderBy(['user_id' => SORT_DESC]);

        if ($usertype = Yii::$app->request->get('usertype')) {
            $query->andWhere(['usertype' => (int)$usertype]);
        }
        if ($status = Yii::$app->request->get('status')) {
            $query->andWhere(['status' => (int)$status]);
        }
        if ($username = Yii::$app->request->get('username')) {
            $query->andWhere(['like', 'username', $username]);
        }

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 25],
        ]);

        return $this->render('index', ['dataProvider' => $dataProvider]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    /**
     * Reset a user's password.
     */
    public function actionResetPassword(int $id): string|Response
    {
        $user = $this->findModel($id);

        if (Yii::$app->request->isPost) {
            $newPassword = trim(Yii::$app->request->post('new_password', ''));
            $confirm     = trim(Yii::$app->request->post('confirm_password', ''));

            if ($newPassword === '') {
                Yii::$app->session->setFlash('error', 'New password is required.');
            } elseif ($newPassword !== $confirm) {
                Yii::$app->session->setFlash('error', 'Passwords do not match.');
            } else {
                $user->setPassword($newPassword);
                $user->force_password_change = 1;
                $user->last_password_changed_date = date('Y-m-d H:i:s');
                $user->save(false);
                Yii::$app->session->setFlash('success', 'Password reset successfully.');
                return $this->redirect(['view', 'id' => $id]);
            }
        }

        return $this->render('reset-password', ['model' => $user]);
    }

    /**
     * Toggle user active/inactive status.
     */
    public function actionToggleStatus(int $id): Response
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('POST request required.');
        }
        $user         = $this->findModel($id);
        $user->status = $user->status === User::STATUS_ACTIVE
            ? User::STATUS_INACTIVE
            : User::STATUS_ACTIVE;
        $user->save(false);

        $label = $user->status === User::STATUS_ACTIVE ? 'activated' : 'deactivated';
        Yii::$app->session->setFlash('success', "User {$label}.");
        return $this->redirect(['index']);
    }

    /**
     * Redirect to the settings access-menus page.
     * The sidebar links here for convenience.
     */
    public function actionAccessMenus(): Response
    {
        return $this->redirect(['/settings/access-menus']);
    }

    private function findModel(int $id): User
    {
        $model = User::findOne(['user_id' => $id]);
        if (!$model) {
            throw new NotFoundHttpException('User not found.');
        }
        return $model;
    }
}
