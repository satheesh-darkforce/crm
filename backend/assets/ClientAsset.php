<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * Clients module script, on top of the shared page UI kit. Registered by
 * ClientController and by client/_form (which Leads → Add To Client reuses).
 */
class ClientAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $js = [
        'js/clients.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];
}
