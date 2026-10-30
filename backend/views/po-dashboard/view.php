<?php

/** @var yii\web\View $this */
/** @var backend\models\PoDetail $model */

// Reuse the standalone PO module's full view; the layout is identical.
echo $this->render('@app/views/po/view', ['model' => $model]);
