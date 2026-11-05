<?php

namespace backend\assets;

use yii\web\AssetBundle;

/**
 * HR (employees, attendance, leave, holidays, payslips, allowances, RFID) — module styles and script on top
 * of the shared page UI kit. Registered by their controllers for full-page renders.
 */
class HrAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/hr.css',
    ];
    public $js = [
        'js/hr.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];
}
