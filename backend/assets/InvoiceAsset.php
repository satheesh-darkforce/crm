<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Invoices, Inward invoices and Bank details — module styles and script on top
 * of the shared page UI kit. Registered by their controllers for full-page renders.
 */
class InvoiceAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/invoices.css',
    ];
    public $js = [
        'js/invoices.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];
}
