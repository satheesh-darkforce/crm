<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Shared page UI kit for redesigned modules (css/page-ui.css, js/page-ui.js).
 * Module bundles (e.g. LeadAsset, ClientAsset) depend on it; pages opt in by
 * wrapping their content in `.ui-page`.
 */
class PageUiAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/page-ui.css',
    ];
    public $js = [
        'js/page-ui.js',
    ];
    public $depends = [
        AppAsset::class,
    ];
}
