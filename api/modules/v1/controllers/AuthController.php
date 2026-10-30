<?php

namespace api\modules\v1\controllers;

use common\models\LoginForm;
use Yii;

/**
 * Handles API authentication: login and logout.
 *
 * Migrated from: WebserviceController::actionLogin() and actionLogout()
 * Original token model: CrmToken
 *
 * POST /api/v1/auth/login   — returns token
 * POST /api/v1/auth/logout  — invalidates token
 */
class AuthController extends BaseController
{
    protected function guestActions(): array
    {
        return ['login'];
    }

    /**
     * POST /api/v1/auth/login
     *
     * Input (JSON body or form-data):
     *   username    string  required
     *   password    string  required
     *   devicetype  string  optional (ios|android|web)
     *   deviceid    string  optional
     *   pushid      string  optional
     *
     * Response:
     *   {"status":1, "msg":"Login successful", "data":{"token":"...", "user_id":1, "user_type":2}}
     */
    public function actionLogin()
    {
        $model = new LoginForm();
        $model->load(Yii::$app->request->post(), '');

        if ($model->login()) {
            $user = Yii::$app->user->identity;
            return $this->success([
                'token'     => $user->generateApiToken(),
                'user_id'   => $user->id,
                'user_type' => $user->user_type,
            ], 'Login successful');
        }

        return $this->error(implode(', ', $model->getFirstErrors()) ?: 'Invalid credentials');
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Revokes the current Bearer token.
     */
    public function actionLogout()
    {
        $user = Yii::$app->user->identity;

        if ($user) {
            $user->revokeApiToken();
        }

        Yii::$app->user->logout(false);

        return $this->success([], 'Logged out successfully');
    }
}
