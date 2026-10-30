<?php

namespace api\modules\v1\controllers;

use common\models\Developer;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\rest\Controller;
use yii\web\Response;

/**
 * Base controller for all v1 API endpoints.
 * Enforces Bearer token authentication and JSON responses.
 *
 * Migrated from: WebserviceForm::checkToken() pattern in Yii1
 */
class BaseController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // Force JSON output for all actions
        $behaviors['contentNegotiator'] = [
            'class'   => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        // Bearer token auth — replaces Yii1 WebserviceForm::checkToken()
        $behaviors['authenticator'] = [
            'class'  => HttpBearerAuth::class,
            'except' => $this->guestActions(),
        ];

        return $behaviors;
    }

    /**
     * Actions that do NOT require authentication (login, etc.)
     * Override in child controllers to add more open actions.
     */
    protected function guestActions(): array
    {
        return [];
    }

    /**
     * Standard success response — mirrors Yii1 {"status":1, "msg":"..."} format.
     */
    protected function success(mixed $data = [], string $msg = 'Success'): array
    {
        return ['status' => 1, 'msg' => $msg, 'data' => $data];
    }

    /**
     * Standard error response — mirrors Yii1 {"status":0, "msg":"..."} format.
     */
    protected function error(string $msg = 'An error occurred', int $status = 0): array
    {
        return ['status' => $status, 'msg' => $msg];
    }

    /**
     * Returns the Developer record for the currently authenticated user.
     * Most API actions are performed by developers — null if user is not a developer.
     */
    protected function getLoginDeveloper(): ?Developer
    {
        return Developer::findOne(['user_id' => Yii::$app->user->id, 'status' => 1]);
    }

    /**
     * Calculates pagination offsets from Yii1-style page number (1-indexed).
     * Returns ['totalPages', 'offset', 'limit'] for query building.
     */
    protected function paginate(int $count, int $page, int $perPage = 10): array
    {
        $totalPages = (int)ceil($count / $perPage);
        $offset     = ($page - 1) * $perPage;
        return [
            'totalPages'  => max(1, $totalPages),
            'currentPage' => $page,
            'offset'      => $offset,
            'limit'       => $perPage,
        ];
    }

    /**
     * Converts "HH:MM" string from mobile app to seconds (Yii1 storage format).
     */
    protected function hhmmToSeconds(string $hhmm): int
    {
        $parts = explode(':', $hhmm);
        return ((int)($parts[0] ?? 0) * 3600) + ((int)($parts[1] ?? 0) * 60);
    }

    /**
     * Converts seconds to "HH:MM" display string (backward compatible with Yii1).
     */
    protected function secondsToHhmm(int $seconds): string
    {
        return sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
