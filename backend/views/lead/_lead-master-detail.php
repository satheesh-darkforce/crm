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
    'Status'       => LeadMaster::statusOptions()[(int)$model->status] ?? '-',
    'Created By'   => Lead::userLabel($model->createdByUser),
    'Created At'   => $model->created_at ? date('d-m-Y h:i a', strtotime($model->created_at)) : null,
];
?>
<table class="table table-sm table-striped mb-0" style="font-size:.85rem">
    <?php foreach ($rows as $label => $value): ?>
        <tr>
            <th class="text-muted fw-normal" style="width:40%"><?= $label ?></th>
            <td><?= Html::encode($value === null || $value === '' ? '-' : $value) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
