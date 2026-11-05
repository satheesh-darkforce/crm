<?php

namespace backend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;
use yii\web\View;

/**
 * Projects + AMC module script, on top of the shared page UI kit.
 * Registered by ProjectController and AmcController for full-page renders.
 */
class ProjectAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/projects.css',
    ];
    public $js = [
        'js/projects.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];

    public static function register($view)
    {
        $bundle = parent::register($view);
        /** @var View $view */
        $view->registerJsVar('projectUrls', [
            'changeStatus' => Url::to(['/project/change-status']),
            'setLiveDate'  => Url::to(['/project/set-live-date']),
            'phaseStatus'  => Url::to(['/project/change-phase-status']),
            'deletePhase'  => Url::to(['/project/delete-phase']),
            'amcStart'     => Url::to(['/amc/start']),
        ]);
        return $bundle;
    }
}
