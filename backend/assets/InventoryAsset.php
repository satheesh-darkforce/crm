<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Inventory (assets, domains, IoT products) — module styles and script on top
 * of the shared page UI kit. Registered by their controllers for full-page renders.
 */
class InventoryAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/inventory.css',
    ];
    public $js = [
        'js/inventory.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];
}
