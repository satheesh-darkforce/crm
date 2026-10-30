<?php
/** @var \backend\models\Holiday $model */
/** @var string[] $employeeNames */

use yii\bootstrap5\Html;
?>
<table class="table table-striped table-sm mb-0">
    <tbody>
        <tr>
            <th class="w-25">Date</th>
            <td><?= Html::encode($model->date) ?></td>
        </tr>
        <tr>
            <th>Reason</th>
            <td><?= Html::encode($model->reason) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?= $model->status == 1 ? 'Active' : 'Inactive' ?></td>
        </tr>
        <tr>
            <th>Employees</th>
            <td><?= !empty($employeeNames) ? Html::encode(implode(', ', $employeeNames)) : '--' ?></td>
        </tr>
    </tbody>
</table>
