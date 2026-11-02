<?php
/** @var \yii\web\View $this */
/** @var \backend\models\Lead $lead */
/** @var \backend\models\LeadMaster $prospect */
/** @var array $users */

use yii\bootstrap5\Html;

$this->title = 'Convert to Lead';
?>
<div class="lead-convert">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0"><?= Html::encode($this->title) ?></h5>
        <?= Html::a('<i class="fas fa-arrow-left me-1"></i>Back', ['lead-master'], ['class' => 'btn btn-outline-secondary btn-sm', 'encode' => false]) ?>
    </div>
    <div class="alert alert-info py-2 small">
        Converting prospect <strong><?= Html::encode($prospect->getFullName()) ?></strong>
        (<?= Html::encode($prospect->account_name) ?>). Once the lead is saved the prospect is removed from Lead Master.
    </div>
    <?= $this->render('_form', ['lead' => $lead, 'users' => $users, 'cancelUrl' => ['lead-master']]) ?>
</div>
