<?php

return [
    'id'        => 'app-api',
    'basePath'  => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases'   => [
        '@api' => dirname(__DIR__),
    ],
    'components' => [
        'user' => [
            'identityClass'   => \common\models\User::class,
            'enableAutoLogin' => false,
            'enableSession'   => false, // stateless API — no sessions
            'loginUrl'        => null,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [[
                'class'        => \yii\log\FileTarget::class,
                'levels'       => ['error', 'warning'],
                'logFile'      => '@api/runtime/logs/app.log',
            ]],
        ],
        'response' => [
            'class'  => \yii\web\Response::class,
            'format' => \yii\web\Response::FORMAT_JSON,
        ],
        'request' => [
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
            'enableCsrfValidation' => false, // API — auth via token, no CSRF needed
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName'  => false,
            'rules' => [
                // ── Auth ────────────────────────────────────────────────────
                'POST api/v1/auth/login'  => 'api/v1/auth/login',
                'POST api/v1/auth/logout' => 'api/v1/auth/logout',

                // ── Profile ──────────────────────────────────────────────────
                'POST api/v1/profile/me'              => 'api/v1/profile/me',
                'POST api/v1/profile/change-password' => 'api/v1/profile/change-password',

                // ── Projects ─────────────────────────────────────────────────
                'POST api/v1/project/list' => 'api/v1/project/list',

                // ── Work Reports ──────────────────────────────────────────────
                'POST api/v1/work-report/add'     => 'api/v1/work-report/add',
                'POST api/v1/work-report/my-list' => 'api/v1/work-report/my-list',
                'POST api/v1/work-report/view'    => 'api/v1/work-report/view',
                'POST api/v1/work-report/update'  => 'api/v1/work-report/update',

                // ── Tasks ─────────────────────────────────────────────────────
                'POST api/v1/task/add'           => 'api/v1/task/add',
                'POST api/v1/task/my-list'       => 'api/v1/task/my-list',
                'POST api/v1/task/accept'        => 'api/v1/task/accept',
                'POST api/v1/task/view'          => 'api/v1/task/view',
                'POST api/v1/task/subtask-list'  => 'api/v1/task/subtask-list',
                'POST api/v1/task/bulk-reassign' => 'api/v1/task/bulk-reassign',

                // ── Developers ────────────────────────────────────────────────
                'POST api/v1/developer/list' => 'api/v1/developer/list',

                // ── Leads / CRM ───────────────────────────────────────────────
                'POST api/v1/lead/add'             => 'api/v1/lead/add',
                'POST api/v1/lead/list'            => 'api/v1/lead/list',
                'POST api/v1/lead/my-list'         => 'api/v1/lead/my-list',
                'POST api/v1/lead/view'            => 'api/v1/lead/view',
                'POST api/v1/lead/change-status'   => 'api/v1/lead/change-status',
                'POST api/v1/lead/add-follower'    => 'api/v1/lead/add-follower',
                'POST api/v1/lead/followers'       => 'api/v1/lead/followers',
                'POST api/v1/lead/follower-detail' => 'api/v1/lead/follower-detail',
                'POST api/v1/lead/followup-status' => 'api/v1/lead/followup-status',
                'POST api/v1/lead/notifications'   => 'api/v1/lead/notifications',
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'api/v1/default/error',
        ],
    ],
    'modules' => [
        'api' => [
            'class'   => \api\Module::class,
            'modules' => [
                'v1' => ['class' => \api\modules\v1\Module::class],
            ],
        ],
    ],
    'params' => require __DIR__ . '/params.php',
];
