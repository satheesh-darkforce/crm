<?php

namespace backend\assets;

use yii\helpers\Url;
use yii\web\AssetBundle;
use yii\web\View;

/**
 * Leads module styles and scripts (lead + lead-followers views), on top of
 * the shared page UI kit. Registered by LeadController and
 * LeadFollowersController for full-page renders.
 */
class LeadAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/leads.css',
    ];
    public $js = [
        'js/leads.js',
    ];
    public $depends = [
        PageUiAsset::class,
    ];

    /** Register the bundle plus the AJAX endpoints leads.js calls. */
    public static function registerFor(View $view): void
    {
        static::register($view);
        $view->registerJsVar('leadUrls', [
            'status'   => Url::to(['/lead/change-status']),
            'active'   => Url::to(['/lead/change-lead-status']),
            'followup' => Url::to(['/lead/change-followup-status']),
            'deleteAt' => Url::to(['/lead/delete-attachment']),
            'create'   => Url::to(['/lead-followers/create']),
            'dup'      => Url::to(['/lead/check-duplicate']),
            'estimate' => Url::to(['/lead/estimate']),
        ]);
    }
}
