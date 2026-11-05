<?php

namespace backend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;
use yii\web\View;

/**
 * Tasks + Work Reports module, on top of the shared page UI kit.
 * Registered by TaskController and WorkReportController for full-page renders.
 */
class TaskAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/tasks.css',
    ];
    public $js = [
        'js/tasks.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];

    public static function register($view)
    {
        $bundle = parent::register($view);
        /** @var View $view */
        $view->registerJsVar('taskUrls', [
            'start'    => Url::to(['/task/task-start']),
            'stop'     => Url::to(['/task/task-stop']),
            'complete' => Url::to(['/task/complete-task']),
            'logs'     => Url::to(['/task/task-logs']),
        ]);
        return $bundle;
    }
}
