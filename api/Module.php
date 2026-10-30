<?php

namespace api;

class Module extends \yii\base\Module
{
    public function init()
    {
        parent::init();
        \Yii::setAlias('@api', __DIR__);
    }
}
