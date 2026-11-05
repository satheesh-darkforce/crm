<?php
/** @var \yii\web\View $this */
/** @var \backend\models\LeadMaster $model */

use backend\models\Lead;
use backend\models\LeadMaster;
use yii\bootstrap5\Html;

$rows = [
    'Account Name' => $model->account_name,
    'First Name'   => $model->first_name,
    'Last Name'    => $model->last_name,
    'Designation'  => $model->designation,
    'Email'        => $model->email_id,
    'Phone Number' => $model->phone_number,
    'City'         => $model->city,
    'Created By'   => Lead::userLabel($model->createdByUser),
    'Created At'   => Lead::formatDateTime($model->created_at),
];
$active = (int)$model->status === LeadMaster::STATUS_ACTIVE;
?>
<table class="table ui-detail">
    <?php foreach ($rows as $label => $value): ?>
        <tr><th scope="row"><?= $label ?></th><td><?= Html::encode($value === null || $value === '' ? '-' : $value) ?></td></tr>
    <?php endforeach; ?>
    <tr><th scope="row">Status</th><td><span class="ui-pill tone-<?= $active ? 'success' : 'neutral' ?>"><?= Html::encode(LeadMaster::statusOptions()[(int)$model->status] ?? '-') ?></span></td></tr>
</table>
