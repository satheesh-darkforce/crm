<?php
/** @var yii\web\View $this */
/** @var backend\models\DcDetail $dc */
/** @var backend\models\DeliveryDetail $delivery */
/** @var backend\models\DcDocumentFile $document */
/** @var backend\models\ClientModel|null $client */
/** @var array $productOptions */
/** @var array $existingProducts */
/** @var bool  $isEdit */
$this->title = 'Update DC #' . ($dc->dc_no ?? $dc->id);
echo $this->render('_form', compact('dc', 'delivery', 'document', 'client', 'productOptions', 'existingProducts', 'isEdit'));
