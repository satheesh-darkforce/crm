<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Products (catalogue and categories) — module styles and script on top
 * of the shared page UI kit. Registered by their controllers for full-page renders.
 */
class ProductAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/products.css',
    ];
    public $js = [
        'js/products.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];
}
