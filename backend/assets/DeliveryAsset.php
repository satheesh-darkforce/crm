<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Quotations, delivery challans, purchase orders and delivery products — module styles and script on top
 * of the shared page UI kit. Registered by their controllers for full-page renders.
 */
class DeliveryAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/delivery.css',
    ];
    public $js = [
        'js/delivery.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];
}
