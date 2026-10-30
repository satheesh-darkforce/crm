<?php
use yii\bootstrap5\Html;
$this->title = 'Edit Lead: ' . $lead->subject;
?>
<div class="lead-update">
    <h4 class="mb-3"><?= Html::encode($this->title) ?></h4>
    <?= $this->render('_form', compact('lead', 'users')) ?>
</div>
