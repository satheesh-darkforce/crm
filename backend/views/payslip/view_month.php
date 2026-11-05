<?php
/**
 * All employees' payslips for a given month
 *
 * @var \yii\web\View            $this
 * @var string                   $month     YYYY-MM-01
 * @var \backend\models\Payslip[] $payslips
 * @var bool                     $isAdmin
 */

use yii\bootstrap5\Html;
use yii\helpers\Url;

$monthStr = date('F Y', strtotime($month));

$this->title = 'Payslips — ' . $monthStr;
?>
<style>
.ps-month-table th {
    font-size:.78rem; text-transform:uppercase; letter-spacing:.04em;
    color:#0060b0; font-weight:700; background:#f4f6f9;
    padding:10px 14px; border-bottom:1px solid #dee2e6; white-space:nowrap;
}
.ps-month-table td { padding:9px 14px; font-size:.875rem; vertical-align:middle; border-bottom:1px solid #f0f0f0; }
.ps-month-table tbody tr:hover { background:#f8fbff; }
.ps-action { display:flex; gap:8px; align-items:center; }
.ps-action a { color:#444; font-size:1.05rem; text-decoration:none; }
.ps-action a:hover { color:#0d6efd; }
.ps-action a.del-btn:hover { color:#dc3545; }
</style>

<div class="ui-page payslip-view-month">

    <div class="ui-head">
        <div>
            <h1><strong>Payslips</strong> — <?= Html::encode($monthStr) ?></h1>
            <div class="ui-sub"><?= count($payslips) ?> employee<?= count($payslips) != 1 ? 's' : '' ?></div>
        </div>
        <div class="ui-actions">
            <?= Html::a('<i class="fas fa-arrow-left"></i> Back', ['index'], ['class' => 'btn btn-secondary btn-sm', 'encode' => false]) ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table ps-month-table mb-0">
                <thead>
                    <tr>
                        <th style="width:45px">#</th>
                        <th>Employee Name</th>
                        <th>Emp Code</th>
                        <th>Designation</th>
                        <th class="text-end">Gross Salary</th>
                        <th class="text-end">Net Pay</th>
                        <th style="width:110px">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($payslips)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No payslips found for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($payslips as $i => $ps): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= Html::encode($ps->name ?: ($ps->developer->developer_name ?? '—')) ?></td>
                        <td class="text-muted small"><?= Html::encode($ps->emp_id ?: '—') ?></td>
                        <td class="text-muted small"><?= Html::encode($ps->designation ?: '—') ?></td>
                        <td class="text-end small">&#8377;<?= number_format((float)$ps->gross_salary) ?></td>
                        <td class="text-end small fw-semibold text-success">&#8377;<?= number_format((float)$ps->actual_salary) ?></td>
                        <td>
                            <div class="ps-action">
                                <?= Html::a('<i class="bi bi-eye"></i>', ['view', 'id' => $ps->id], ['title' => 'View', 'encode' => false]) ?>
                                <?= Html::a('<i class="bi bi-printer"></i>', ['print', 'id' => $ps->id], ['title' => 'Print', 'target' => '_blank', 'encode' => false]) ?>
                                <?= Html::a('<i class="bi bi-download"></i>', ['download-pdf', 'id' => $ps->id], ['title' => 'Download PDF', 'encode' => false]) ?>
                                <?php if ($isAdmin): ?>
                                <?= Html::a('<i class="bi bi-trash3"></i>', ['delete', 'id' => $ps->id], [
                                    'class' => 'del-btn', 'title' => 'Delete', 'encode' => false,
                                    'data-method' => 'post', 'data-confirm' => 'Delete this payslip?',
                                ]) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
