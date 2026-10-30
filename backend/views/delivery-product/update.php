<?php
/** @var yii\web\View $this */
/** @var backend\models\DeliveryProduct $model */
$this->title = 'Update Product — ' . $model->item_name;
echo $this->render('_form', ['model' => $model]);
