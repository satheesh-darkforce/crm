<?php

namespace backend\components;

use Yii;
use yii\web\Response;

/**
 * Backend error handler.
 *
 * When the database is unreachable, the normal error flow cannot help: in
 * debug mode Yii shows the raw exception page, and in production the
 * site/error action renders inside layouts that query the DB again. Instead,
 * connection failures render a standalone "connection lost" page (HTTP 503)
 * that has no DB, layout or asset-bundle dependencies. All other exceptions
 * fall through to the default handling.
 */
class ErrorHandler extends \yii\web\ErrorHandler
{
    /** MySQL client/server error codes that mean "cannot reach / log in to the DB". */
    private const CONNECTION_ERROR_CODES = [1044, 1045, 1049, 2002, 2003, 2005, 2006, 2013];

    /** @var string view file rendered for connection failures */
    public $dbErrorView = '@backend/views/site/db-error.php';

    protected function renderException($exception)
    {
        if (!$this->isDbConnectionError($exception)) {
            parent::renderException($exception);
            return;
        }

        $response = Yii::$app->has('response') ? Yii::$app->getResponse() : new Response();
        $response->setStatusCode(503);
        $response->headers->set('Retry-After', '30');

        $request = Yii::$app->has('request') ? Yii::$app->getRequest() : null;
        if ($request instanceof \yii\web\Request && $request->getIsAjax()) {
            $response->format = Response::FORMAT_JSON;
            $response->data   = [
                'success' => false,
                'error'   => 'db_connection_lost',
                'message' => 'Database connection lost. Please try again shortly.',
            ];
        } else {
            $response->format = Response::FORMAT_HTML;
            $response->data   = $this->renderFile($this->dbErrorView, ['exception' => $exception]);
        }

        $response->send();
    }

    /**
     * True when the exception (or any exception it wraps) is a failure to
     * open the DB connection, as opposed to a bad query on a live connection.
     */
    protected function isDbConnectionError($exception): bool
    {
        for ($e = $exception; $e !== null; $e = $e->getPrevious()) {
            if (!$e instanceof \yii\db\Exception && !$e instanceof \PDOException) {
                continue;
            }

            if (preg_match('/SQLSTATE\[[^\]]+\]\s*\[(\d+)\]/', $e->getMessage(), $m)
                && in_array((int) $m[1], self::CONNECTION_ERROR_CODES, true)) {
                return true;
            }

            $info = $e instanceof \PDOException ? $e->errorInfo : ($e->errorInfo ?? null);
            if (is_array($info) && isset($info[1]) && in_array((int) $info[1], self::CONNECTION_ERROR_CODES, true)) {
                return true;
            }

            // A DB exception while the connection never opened is a connection failure.
            if (Yii::$app && Yii::$app->has('db', true) && Yii::$app->db->pdo === null) {
                return true;
            }
        }

        return false;
    }
}
