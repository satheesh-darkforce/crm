<?php

namespace backend\controllers;

use common\components\UserAccess;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller as BaseController;

/**
 * Backend base controller.
 *
 * All authenticated users can access the backend.
 * Per-route access is controlled by UserAccess::checkAccess() which checks
 * the user's role and permission assignments (mirroring the Yii1 architecture).
 *
 * Admin/Sub-admin get full access.
 * Developer/Client access is governed by default permissions + DB-stored access_permissions.
 */
abstract class Controller extends BaseController
{
    /**
     * Returns the real web-accessible base URL, auto-detected from the request.
     * Unlike Yii::getAlias('@web'), this ignores the configured baseUrl and always
     * returns the actual path the browser uses (e.g. /projects/.../backend/web).
     */
    public static function getWebBaseUrl(): string
    {
        return rtrim(dirname(Yii::$app->request->scriptUrl), '/');
    }

    /**
     * Build a web-accessible URL for an uploaded file path.
     * Handles both '/uploads/...' and 'uploads/...' formats.
     */
    public static function uploadUrl(string $path): string
    {
        return self::getWebBaseUrl() . '/' . ltrim($path, '/');
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function ($rule, $action) {
                            $route = $action->controller->id . '/' . $action->id;
                            return UserAccess::checkAccess($route);
                        },
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    if (Yii::$app->user->isGuest) {
                        return Yii::$app->response->redirect(['/site/login']);
                    }
                    throw new \yii\web\ForbiddenHttpException('You do not have permission to access this page.');
                },
            ],
        ];
    }
}
