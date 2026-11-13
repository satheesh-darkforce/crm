<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'backend\controllers',
    'bootstrap' => ['log'],
    'modules' => [],
    'components' => [
        // ?v=<mtime> on every asset URL so browsers pick up CSS / JS changes straight away.
        'assetManager' => ['appendTimestamp' => true],
        'request' => [
            'csrfParam' => '_csrf-backend',
            'baseUrl'   => '/erp/maestro_erp_yii2',
        ],
        'user' => [
            'identityClass'   => 'backend\models\User',
            // enableAutoLogin is disabled on purpose: a persistent remember-me
            // cookie would call loginByCookie() during renewAuthStatus() right
            // after authTimeout logs the user out, silently reviving the
            // session every request. Idle-timeout and "remember me for N days"
            // are mutually exclusive — keep this false to make authTimeout
            // actually enforceable.
            'enableAutoLogin' => false,
            // Auto-logout after 1 hour of inactivity. Every request refreshes
            // the __expire timestamp; the next request beyond the window is
            // forced to /site/login.
            'authTimeout'     => 3600,
            'identityCookie'  => ['name' => '_identity-backend', 'httpOnly' => true],
        ],
        'session' => [
            // this is the name of the session cookie used for login on the backend
            'name'         => 'advanced-backend',
            // Keep the PHP session itself alive at least as long as authTimeout
            // so GC doesn't reap it before Yii gets to enforce the timeout.
            'timeout'      => 3600,
            // lifetime MUST be 0 (a browser-session cookie). A fixed lifetime
            // (e.g. 3600) makes the cookie expire an ABSOLUTE 1 hour after
            // login — PHP never refreshes it on activity — so an actively
            // working user is logged out ~1h after login regardless of what
            // they're doing. With lifetime=0 the cookie persists for the
            // browsing session and `user.authTimeout` (refreshed every request)
            // is the ONLY timeout: logout strictly after 1h of inactivity,
            // never while active.
            'cookieParams' => ['lifetime' => 0, 'httponly' => true],
            // Override default /var/lib/php/sessions, which is swept every 30
            // minutes by the system cron `/etc/cron.d/php` using PHP's
            // session.gc_maxlifetime (1440s / 24 min on Debian/Ubuntu) — that
            // would delete the session file long before Yii's 1-hour timeout
            // fires, logging out actively-working users. Storing sessions in
            // a project-owned directory avoids the system sweeper entirely.
            'savePath'     => '@app/runtime/sessions',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            // Custom handler shows a standalone "DB connection lost" page
            // instead of the raw exception page when MySQL is unreachable.
            'class'       => \backend\components\ErrorHandler::class,
            'errorAction' => 'site/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'baseUrl'         => '/erp/maestro_erp_yii2',
            'rules'           => [],
        ],
    ],
    'params' => $params,
    'container' => [
        'definitions' => [
            // Use Bootstrap5 pagination everywhere GridView renders a pager
            \yii\widgets\LinkPager::class => \yii\bootstrap5\LinkPager::class,
        ],
    ],
];
