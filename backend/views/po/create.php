<?php

/** @var yii\web\View $this */
/** @var backend\models\PoDetail $model */
/** @var array $clientOptions */
/** @var array $companyOptions */
/** @var array $inchargeOptions */

$this->title = 'Add Purchase Order';
$isUpdate = false;
echo $this->render('_form', compact('model', 'clientOptions', 'companyOptions', 'inchargeOptions', 'isUpdate'));
