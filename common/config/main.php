<?php

return [
    // Application time zone — drives PHP's default timezone, so every date()/
    // time() and DB timestamp written by the app is in IST. Matches Yii1's
    // config ('timeZone' => 'Asia/Kolkata'). Without this Yii defaults to UTC
    // and stored timestamps (e.g. iot_consumption.created_at) land ~5:30 off.
    // (The formatter's defaultTimeZone below only affects display formatting.)
    'timeZone' => 'Asia/Kolkata',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'authManager' => [
            'class' => \yii\rbac\DbManager::class,
            'itemTable'       => '{{%auth_item}}',
            'itemChildTable'  => '{{%auth_item_child}}',
            'assignmentTable' => '{{%auth_assignment}}',
            'ruleTable'       => '{{%auth_rule}}',
            'cache'           => 'cache',
        ],
        'formatter' => [
            'class'           => \yii\i18n\Formatter::class,
            'dateFormat'       => 'php:d-m-Y',
            'datetimeFormat'   => 'php:d-m-Y H:i:s',
            'timeFormat'       => 'php:H:i:s',
            'defaultTimeZone'  => 'Asia/Kolkata',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
        ],
    ],
];
