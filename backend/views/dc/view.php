<?php

/** @var yii\web\View $this */
/** @var array $model */

// Delegate to the Quotation Dashboard's view-dc partial for visual consistency.
// Editing is exposed only from the preview page, per product rules.
$this->title = 'Delivery Challan #' . ($model['dc_no'] ?? $model['id']);

echo $this->render('@backend/views/quotation-dashboard/view-dc', ['model' => $model]);
