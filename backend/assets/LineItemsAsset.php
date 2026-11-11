<?php

namespace backend\assets;

use kartik\select2\Select2Asset;
use kartik\select2\ThemeKrajeeBs5Asset;
use yii\web\AssetBundle;

/**
 * Line-item editor shared by quotations and invoices (views/_ui/_line_items.php):
 * product picker, quantity in the product's unit, discount, GST per line and live totals.
 */
class LineItemsAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/line-items.css',
    ];
    public $js = [
        'js/line-items.js',
    ];
    public $depends = [
        PageUiAsset::class,
        Select2Asset::class,
        ThemeKrajeeBs5Asset::class,
    ];
}
